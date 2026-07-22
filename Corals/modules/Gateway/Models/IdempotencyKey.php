<?php

namespace Corals\Modules\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'scope',
        'key',
        'request_hash',
        'response_status',
        'response_snapshot',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
