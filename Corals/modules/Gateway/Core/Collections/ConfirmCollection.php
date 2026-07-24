<?php

namespace Corals\Modules\Gateway\Core\Collections;

use Carbon\Carbon;
use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AUTHORIZED -> CONFIRMED (docs/state-machines.md "Transaction"). Idempotent
 * on network_txn_id — a replay returns the stored result, never a second
 * posting. Real-time networks confirm an existing AUTHORIZED reservation
 * from GW-401's validate; offline/batch-style callers with no prior
 * reservation get one created here (docs/state-machines.md "finality note").
 *
 * One-time refs: concurrency-safe via a conditional claim on the reference
 * row itself (UPDATE ... WHERE status = 'active', 0 rows = already
 * confirmed by a racing attempt) — the same "conditional UPDATE, not
 * check-then-act" discipline as the wallet debit guard. Reusable refs allow
 * many CONFIRMED transactions; duplicates are blocked solely by
 * network_txn_id uniqueness (docs/data-model.md).
 */
class ConfirmCollection
{
    public function handle(array $data): array
    {
        $existing = Transaction::where('network_txn_id', $data['network_txn_id'])->first();

        if ($existing) {
            return $this->resultFor($existing);
        }

        $merchant = Merchant::where('mid', $data['mid'])->where('status', 'active')->first();

        if (! $merchant) {
            return $this->error('mid_not_found');
        }

        $reference = PaymentReference::where('reference_token', $data['ref'])->first();

        if (! $reference || $reference->paymentIntent->merchant_id !== $merchant->id) {
            return $this->error('reference_not_found');
        }

        $intent = $reference->paymentIntent;

        $wallet = PosWallet::where('network_id', $data['network_id'])
            ->where('external_store_id', $data['store_id'])
            ->first();

        if (! $wallet) {
            return $this->error('wallet_not_found');
        }

        if ($data['is_partial'] && ! ($intent->amount_policy['type'] === 'variable' && ($intent->amount_policy['allow_partial'] ?? false))) {
            return $this->error('partial_not_allowed');
        }

        [$policyOk, $amountToPost, $flagged] = $this->resolveAmount($intent, $data['amount_paid']);

        if (! $policyOk) {
            return $this->error('amount_mismatch');
        }

        try {
            return DB::transaction(function () use ($reference, $intent, $wallet, $data, $amountToPost, $flagged) {
                if ($intent->mode === 'one_time') {
                    $claimed = DB::table('payment_references')
                        ->where('id', $reference->id)
                        ->where('status', 'active')
                        ->update(['status' => 'consumed', 'consumed_at' => now()]);

                    if ($claimed === 0) {
                        throw new RuntimeException('reference_already_consumed');
                    }
                }

                $transaction = Transaction::where('payment_reference_id', $reference->id)
                    ->where('state', 'AUTHORIZED')
                    ->lockForUpdate()
                    ->first();

                if ($transaction) {
                    DB::table('pos_wallets')
                        ->where('id', $transaction->pos_wallet_id)
                        ->update(['reserved_centavos' => DB::raw('reserved_centavos - '.(int) $transaction->amount_centavos)]);
                } else {
                    $transaction = Transaction::create([
                        'payment_reference_id' => $reference->id,
                        'pos_wallet_id' => $wallet->id,
                        'network_id' => $data['network_id'],
                        'amount_centavos' => $amountToPost,
                        'state' => 'AUTHORIZED',
                    ]);
                }

                $transaction->update([
                    'network_txn_id' => $data['network_txn_id'],
                    'idempotency_key' => $data['idempotency_key'],
                    'amount_centavos' => $amountToPost,
                    'is_partial' => $data['is_partial'],
                    'collected_at' => Carbon::createFromTimestamp($data['collected_at']),
                    'finality' => $data['finality'] ?? 'on_confirm',
                ]);

                $isFirstConfirm = $intent->state === 'ACTIVE';
                $event = $isFirstConfirm ? 'payment.confirmed' : 'payment.credited';

                [$commission, $fee] = $this->pricing($amountToPost);

                (new ConfirmedCollectionPosting())->apply(
                    transactionId: $transaction->id,
                    posWalletId: $wallet->id,
                    issuerId: $intent->issuer_id,
                    amountCentavos: $amountToPost,
                    commissionCentavos: $commission,
                    feeCentavos: $fee,
                    event: $event
                );

                $transaction->update(['state' => 'CONFIRMED', 'confirmed_at' => now()]);

                if ($isFirstConfirm) {
                    $intent->update(['state' => 'PAID_PENDING_SETTLEMENT']);
                }

                if ($flagged) {
                    ReconciliationException::create([
                        'type' => 'amount_mismatch',
                        'refs' => ['transaction_id' => $transaction->id, 'payment_reference_id' => $reference->id],
                    ]);
                }

                return $this->resultFor($transaction->fresh());
            });
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage() === 'reference_already_consumed' ? 'reference_already_consumed' : 'insufficient_funds');
        }
    }

    private function resolveAmount(PaymentIntent $intent, int $amountPaid): array
    {
        $policy = $intent->amount_policy;
        $inRange = $policy['type'] === 'fixed'
            ? $amountPaid === $policy['amount']
            : ($amountPaid >= $policy['min'] && $amountPaid <= $policy['max']);

        if ($inRange) {
            return [true, $amountPaid, false];
        }

        $ceiling = $policy['type'] === 'fixed' ? $policy['amount'] : $policy['max'];
        $policyName = $amountPaid > $ceiling ? $intent->overpay_policy : $intent->underpay_policy;

        return match ($policyName) {
            'accept' => [true, $amountPaid, false],
            'accept_and_flag' => [true, $amountPaid, true],
            default => [false, null, false],
        };
    }

    private function pricing(int $amountCentavos): array
    {
        $commission = intdiv($amountCentavos * (int) config('gateway.commission_bps'), 10000);
        $fee = min((int) config('gateway.fixed_fee_centavos'), max(0, $amountCentavos - $commission));

        return [$commission, $fee];
    }

    private function resultFor(Transaction $transaction): array
    {
        return [
            'ok' => true,
            'transaction_public_id' => $transaction->public_id,
            'auth_code' => strtoupper(substr(md5($transaction->id.'|'.$transaction->network_txn_id), 0, 6)),
            'receipt' => [
                'folio' => $transaction->public_id,
                'amount' => $transaction->amount_centavos,
                'ts' => ($transaction->confirmed_at ?? $transaction->updated_at)->getTimestamp(),
            ],
            'error' => null,
        ];
    }

    private function error(string $reason): array
    {
        return [
            'ok' => false,
            'transaction_public_id' => null,
            'auth_code' => null,
            'receipt' => null,
            'error' => $reason,
        ];
    }
}
