<?php

namespace Corals\Modules\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationException extends Model
{
    protected $casts = [
        'refs' => 'array',
    ];

    protected $fillable = [
        'type',
        'refs',
        'state',
        'assignee',
        'resolution',
    ];
}
