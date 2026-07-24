<?php

namespace Corals\Modules\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $fillable = [
        'issuer_id',
        'period',
        'gross_centavos',
        'commission_centavos',
        'fee_centavos',
        'net_centavos',
        'spei_ref',
        'status',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'reconciled_at' => 'datetime',
        ];
    }

    public function issuer()
    {
        return $this->belongsTo(Issuer::class);
    }
}
