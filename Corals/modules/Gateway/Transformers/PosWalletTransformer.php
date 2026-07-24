<?php

namespace Corals\Modules\Gateway\Transformers;

use Corals\Foundation\Transformers\BaseTransformer;
use Corals\Modules\Gateway\Models\PosWallet;

class PosWalletTransformer extends BaseTransformer
{
    public function transform(PosWallet $wallet)
    {
        return parent::transformResponse([
            'id' => $wallet->id,
            'public_id' => $wallet->public_id,
            'network_id' => $wallet->network_id,
            'external_store_id' => $wallet->external_store_id,
            'balance_centavos' => $wallet->balance_centavos,
            'reserved_centavos' => $wallet->reserved_centavos,
            'currency' => $wallet->currency,
            'status' => $wallet->status,
            'created_at' => format_date_time($wallet->created_at),
        ]);
    }
}
