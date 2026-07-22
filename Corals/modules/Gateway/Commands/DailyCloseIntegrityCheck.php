<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled daily-close integrity job. See docs/settlement-reconciliation.md
 * ("Double-entry integrity checks"): asserts global sum(debits)==sum(credits)
 * and, per wallet, that balance_centavos agrees with its net ledger
 * position. Any mismatch opens a `negative_drift` reconciliation exception
 * and the command exits non-zero — it never silently advances a period with
 * drift.
 */
class DailyCloseIntegrityCheck extends Command
{
    protected $signature = 'gateway:daily-close';

    protected $description = 'Asserts global ledger balance and per-wallet ledger-vs-balance agreement; opens negative_drift exceptions on mismatch.';

    public function handle(): int
    {
        $clean = true;

        $totalDebits = (int) DB::table('ledger_entries')->where('direction', 'debit')->sum('amount_centavos');
        $totalCredits = (int) DB::table('ledger_entries')->where('direction', 'credit')->sum('amount_centavos');

        if ($totalDebits !== $totalCredits) {
            $this->openException([
                'scope' => 'global',
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
            ]);
            $this->error("Global ledger imbalance: debits={$totalDebits} credits={$totalCredits}");
            $clean = false;
        }

        $netPositions = DB::table('ledger_entries')
            ->where('account_type', 'pos_wallet')
            ->selectRaw("account_ref, SUM(CASE WHEN direction = 'credit' THEN amount_centavos ELSE -amount_centavos END) as net_position")
            ->groupBy('account_ref')
            ->pluck('net_position', 'account_ref');

        foreach (PosWallet::all() as $wallet) {
            $netLedgerPosition = (int) ($netPositions[(string) $wallet->id] ?? 0);

            if ($netLedgerPosition !== $wallet->balance_centavos) {
                $this->openException([
                    'scope' => 'pos_wallet',
                    'pos_wallet_id' => $wallet->id,
                    'ledger_position' => $netLedgerPosition,
                    'wallet_balance' => $wallet->balance_centavos,
                ]);
                $this->error("pos_wallet {$wallet->id} drift: ledger={$netLedgerPosition} balance={$wallet->balance_centavos}");
                $clean = false;
            }
        }

        if (! $clean) {
            return self::FAILURE;
        }

        $this->info('Daily-close integrity check passed.');

        return self::SUCCESS;
    }

    private function openException(array $refs): void
    {
        ReconciliationException::create([
            'type' => 'negative_drift',
            'refs' => $refs,
            'state' => 'open',
        ]);
    }
}
