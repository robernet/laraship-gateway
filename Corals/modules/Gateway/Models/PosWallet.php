<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\PosWalletFactory;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'network_id',
        'external_store_id',
        'balance_centavos',
        'reserved_centavos',
        'currency',
        'status',
    ];

    protected static function newFactory(): PosWalletFactory
    {
        return PosWalletFactory::new();
    }

    public function topUps()
    {
        return $this->hasMany(WalletTopUp::class);
    }

    protected static function booted()
    {
        static::created(function (self $wallet) {
            $wallet->update(['public_id' => (new Hashids(config('app.key')))->encode($wallet->id)]);
        });
    }
}
