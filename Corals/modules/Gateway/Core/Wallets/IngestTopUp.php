<?php

namespace Corals\Modules\Gateway\Core\Wallets;

use Corals\Modules\Gateway\Core\Ledger\Postings\TopupAppliedPosting;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Illuminate\Support\Facades\DB;

/**
 * GW-501 (docs/settlement-reconciliation.md "Top-up ingestion & matching").
 * Every inbound SPEI notification becomes a wallet_top_ups(pending) row,
 * matched or not — an unmatched deposit is still money the gateway received
 * and must be visible, never silently dropped.
 *
 * Matching, in order:
 *  1. spei_ref === pos_wallet.public_id — the POS is expected to put its
 *     wallet's public id in the SPEI reference/concepto field
 *     ("a reference tied to pos_wallet").
 *  2. clabe_origin matches a CLABE that has previously funded a wallet
 *     (an applied top-up) — a repeat depositor from the same bank account.
 *
 * No match on either → reconciliation_exceptions(type=orphan_topup) and the
 * top-up is left pending with no pos_wallet_id: never auto-credit.
 */
class IngestTopUp
{
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $wallet = $this->match($data);

            $topUp = WalletTopUp::create([
                'pos_wallet_id' => $wallet?->id,
                'amount_centavos' => $data['amount_centavos'],
                'spei_ref' => $data['spei_ref'] ?? null,
                'clabe_origin' => $data['clabe_origin'] ?? null,
                'status' => 'pending',
            ]);

            if (! $wallet) {
                ReconciliationException::create([
                    'type' => 'orphan_topup',
                    'refs' => [
                        'top_up_id' => $topUp->id,
                        'spei_ref' => $data['spei_ref'] ?? null,
                        'clabe_origin' => $data['clabe_origin'] ?? null,
                    ],
                ]);

                return ['ok' => true, 'matched' => false, 'top_up_id' => $topUp->id, 'posting_id' => null];
            }

            $postingId = (new TopupAppliedPosting)->apply($topUp);

            return ['ok' => true, 'matched' => true, 'top_up_id' => $topUp->id, 'posting_id' => $postingId];
        });
    }

    private function match(array $data): ?PosWallet
    {
        if (! empty($data['spei_ref'])) {
            $wallet = PosWallet::where('public_id', $data['spei_ref'])->first();

            if ($wallet) {
                return $wallet;
            }
        }

        if (! empty($data['clabe_origin'])) {
            $priorWalletId = WalletTopUp::where('clabe_origin', $data['clabe_origin'])
                ->where('status', 'applied')
                ->value('pos_wallet_id');

            if ($priorWalletId) {
                return PosWallet::find($priorWalletId);
            }
        }

        return null;
    }
}
