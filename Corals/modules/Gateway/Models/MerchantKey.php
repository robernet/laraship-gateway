<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\MerchantKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'kid',
        'alg',
        'secret_ref',
        'state',
        'activated_at',
        'retire_after',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'retire_after' => 'datetime',
        ];
    }

    protected static function newFactory(): MerchantKeyFactory
    {
        return MerchantKeyFactory::new();
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
