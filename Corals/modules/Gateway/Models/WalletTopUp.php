<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\WalletTopUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTopUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_wallet_id',
        'amount_centavos',
        'spei_ref',
        'clabe_origin',
        'status',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    protected static function newFactory(): WalletTopUpFactory
    {
        return WalletTopUpFactory::new();
    }

    public function posWallet()
    {
        return $this->belongsTo(PosWallet::class);
    }
}
