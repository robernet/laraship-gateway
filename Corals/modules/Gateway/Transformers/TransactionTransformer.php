<?php

namespace Corals\Modules\Gateway\Transformers;

use Corals\Foundation\Transformers\BaseTransformer;
use Corals\Modules\Gateway\Models\Transaction;

class TransactionTransformer extends BaseTransformer
{
    public function transform(Transaction $transaction)
    {
        return parent::transformResponse([
            'id' => $transaction->id,
            'public_id' => $transaction->public_id,
            'network_id' => $transaction->network_id,
            'network_txn_id' => $transaction->network_txn_id,
            'pos_wallet_id' => $transaction->pos_wallet_id,
            'amount_centavos' => $transaction->amount_centavos,
            'state' => $transaction->state,
            'is_partial' => $transaction->is_partial ? 'yes' : 'no',
            'collected_at' => $transaction->collected_at ? format_date_time($transaction->collected_at) : '-',
            'confirmed_at' => $transaction->confirmed_at ? format_date_time($transaction->confirmed_at) : '-',
        ]);
    }
}
