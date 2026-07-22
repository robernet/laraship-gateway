<?php

namespace Corals\Modules\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'issuer_id',
        'event',
        'payload',
        'signature',
        'status',
        'attempts',
        'last_error',
        'next_retry_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function issuer()
    {
        return $this->belongsTo(Issuer::class);
    }
}
