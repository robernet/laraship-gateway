<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\PaymentReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_intent_id',
        'reference_token',
        'human_reference',
        'barcode_payload',
        'qr_payload',
        'kid',
        'nonce',
        'expires_at',
        'status',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PaymentReferenceFactory
    {
        return PaymentReferenceFactory::new();
    }

    public function paymentIntent()
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
