<?php

namespace Corals\Modules\Gateway\Core\Collections;

use Corals\Modules\Gateway\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * GW-401 "reservation released on timeout": an AUTHORIZED transaction that
 * never reaches CONFIRMED within the reservation window gives its
 * pos_wallet reservation back. Never persisted as VOIDED — like a decline,
 * it's simply as if the reservation never happened (docs/state-machines.md).
 */
class ReleaseExpiredReservations
{
    public function handle(): int
    {
        $cutoff = now()->subSeconds((int) config('gateway.reservation_ttl_seconds'));

        $expired = Transaction::where('state', 'AUTHORIZED')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($expired as $transaction) {
            DB::transaction(function () use ($transaction) {
                DB::table('pos_wallets')
                    ->where('id', $transaction->pos_wallet_id)
                    ->update(['reserved_centavos' => DB::raw('reserved_centavos - '.(int) $transaction->amount_centavos)]);

                $transaction->delete();
            });
        }

        return $expired->count();
    }
}
