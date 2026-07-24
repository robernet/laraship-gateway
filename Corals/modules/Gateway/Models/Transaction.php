<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\TransactionFactory;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $casts = [
        'is_partial' => 'boolean',
        'collected_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected $fillable = [
        'public_id',
        'payment_reference_id',
        'pos_wallet_id',
        'network_id',
        'network_txn_id',
        'idempotency_key',
        'amount_centavos',
        'state',
        'is_partial',
        'collected_at',
        'confirmed_at',
        'finality',
    ];

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    public function paymentReference()
    {
        return $this->belongsTo(PaymentReference::class);
    }

    public function posWallet()
    {
        return $this->belongsTo(PosWallet::class);
    }

    protected static function booted()
    {
        static::created(function (self $transaction) {
            $transaction->update(['public_id' => (new Hashids(config('app.key')))->encode($transaction->id)]);
        });
    }
}
