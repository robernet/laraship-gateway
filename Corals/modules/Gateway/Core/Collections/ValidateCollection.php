<?php

namespace Corals\Modules\Gateway\Core\Collections;

use Corals\Modules\Gateway\Core\References\ReplayCache;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * INITIATED -> AUTHORIZED (docs/state-machines.md "Transaction"). Verifies
 * the instrument, resolves MID -> merchant -> intent, checks
 * pos_wallet.available >= amount_attempt, and reserves. A decline never
 * persists a row — only a successful validate creates the AUTHORIZED
 * transaction that GW-402's confirm later completes.
 *
 * `ref` here is the plain 10-digit reference_token, already checksum- and
 * MID-verified upstream (barcode Mod97 / QR HMAC, GW-203/204) before this
 * call — this service re-derives everything from the DB row, never trusts
 * the payload's own amount, same discipline as QrVerifier.
 */
class ValidateCollection
{
    public function __construct(private readonly ReplayCache $replayCache = new ReplayCache()) {}

    public function handle(array $data): array
    {
        $merchant = Merchant::where('mid', $data['mid'])->where('status', 'active')->first();

        if (! $merchant) {
            return $this->decline('tampered');
        }

        $reference = PaymentReference::where('reference_token', $data['ref'])->first();

        if (! $reference) {
            return $this->decline('tampered');
        }

        $intent = $reference->paymentIntent;

        if ($intent->merchant_id !== $merchant->id) {
            return $this->decline('tampered');
        }

        if ($reference->status === 'revoked') {
            return $this->decline('tampered');
        }

        if ($reference->status === 'consumed') {
            return $this->decline('replayed');
        }

        if ($reference->status === 'expired' || ($reference->expires_at && $reference->expires_at->isPast())) {
            return $this->decline('expired');
        }

        if (! in_array($intent->state, ['ACTIVE', 'PAID_PENDING_SETTLEMENT'], true)) {
            return $this->decline('expired');
        }

        // TTL mirrors the QR nonce cache: never outlive the instrument's own
        // validity window (docs/security-antifraud.md "TTL >= instrument validity").
        $ttlSeconds = $reference->expires_at
            ? max(1, $reference->expires_at->getTimestamp() - now()->getTimestamp())
            : config('gateway.reservation_ttl_seconds');

        if (! $this->replayCache->markIfNew($data['request_id'], $ttlSeconds)) {
            return $this->decline('replayed');
        }

        if (! $this->amountMatchesPolicy($intent->amount_policy, $data['amount_attempt'])) {
            return $this->decline('policy_mismatch');
        }

        $wallet = PosWallet::where('network_id', $data['network_id'])
            ->where('external_store_id', $data['store_id'])
            ->first();

        if (! $wallet) {
            return $this->decline('insufficient_funds');
        }

        try {
            $transaction = $this->reserve($wallet, $reference, $data);
        } catch (RuntimeException) {
            return $this->decline('insufficient_funds');
        }

        return [
            'ok' => true,
            'intent_state' => $intent->state,
            'amount_policy' => $intent->amount_policy,
            'reservation_id' => $transaction->public_id,
            'decline_reason' => null,
        ];
    }

    private function reserve(PosWallet $wallet, PaymentReference $reference, array $data): Transaction
    {
        return DB::transaction(function () use ($wallet, $reference, $data) {
            $reserved = DB::table('pos_wallets')
                ->where('id', $wallet->id)
                ->whereRaw('balance_centavos - reserved_centavos >= ?', [$data['amount_attempt']])
                ->update(['reserved_centavos' => DB::raw('reserved_centavos + '.(int) $data['amount_attempt'])]);

            if ($reserved === 0) {
                throw new RuntimeException("pos_wallet {$wallet->id} has insufficient available balance for {$data['amount_attempt']}.");
            }

            return Transaction::create([
                'payment_reference_id' => $reference->id,
                'pos_wallet_id' => $wallet->id,
                'network_id' => $data['network_id'],
                'amount_centavos' => $data['amount_attempt'],
                'state' => 'AUTHORIZED',
            ]);
        });
    }

    private function amountMatchesPolicy(array $policy, int $attemptedAmountCentavos): bool
    {
        if ($policy['type'] === 'fixed') {
            return $attemptedAmountCentavos === $policy['amount'];
        }

        return $attemptedAmountCentavos >= $policy['min'] && $attemptedAmountCentavos <= $policy['max'];
    }

    private function decline(string $reason): array
    {
        return [
            'ok' => false,
            'intent_state' => null,
            'amount_policy' => null,
            'reservation_id' => null,
            'decline_reason' => $reason,
        ];
    }
}
