<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\PaymentIntentFactory;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    use HasFactory;

    protected $casts = [
        'amount_policy' => 'array',
    ];

    protected $fillable = [
        'public_id',
        'issuer_id',
        'merchant_id',
        'invoice_id',
        'mode',
        'amount_policy',
        'mapping_strategy',
        'state',
        'expires_at',
        'max_payments',
        'overpay_policy',
        'underpay_policy',
    ];

    protected static function newFactory(): PaymentIntentFactory
    {
        return PaymentIntentFactory::new();
    }

    public function issuer()
    {
        return $this->belongsTo(Issuer::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function paymentReferences()
    {
        return $this->hasMany(PaymentReference::class);
    }

    protected static function booted()
    {
        static::created(function (self $intent) {
            $intent->update(['public_id' => (new Hashids(config('app.key')))->encode($intent->id)]);
        });
    }
}
