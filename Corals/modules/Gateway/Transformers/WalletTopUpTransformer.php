<?php

namespace Corals\Modules\Gateway\Transformers;

use Corals\Foundation\Transformers\BaseTransformer;
use Corals\Modules\Gateway\Models\WalletTopUp;

class WalletTopUpTransformer extends BaseTransformer
{
    public function transform(WalletTopUp $topUp)
    {
        return parent::transformResponse([
            'id' => $topUp->id,
            'pos_wallet_id' => $topUp->pos_wallet_id,
            'wallet_public_id' => $topUp->posWallet?->public_id,
            'amount_centavos' => $topUp->amount_centavos,
            'spei_ref' => $topUp->spei_ref,
            'clabe_origin' => $topUp->clabe_origin,
            'status' => $topUp->status,
            'applied_at' => $topUp->applied_at ? format_date_time($topUp->applied_at) : '-',
            'created_at' => format_date_time($topUp->created_at),
        ]);
    }
}
