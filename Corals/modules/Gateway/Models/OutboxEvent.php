<?php

namespace Corals\Modules\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = [
        'event',
        'payload',
        'status',
        'attempts',
        'last_error',
        'dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'dispatched_at' => 'datetime',
        ];
    }
}
