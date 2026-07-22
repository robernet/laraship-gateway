<?php

namespace Corals\Modules\Gateway\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected $casts = [
        'payload' => 'array',
    ];

    protected $fillable = [
        'prev_hash',
        'row_hash',
        'actor',
        'action',
        'subject_type',
        'subject_id',
        'payload',
        'created_at',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new LogicException('audit_log is append-only; existing rows cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('audit_log is append-only; existing rows cannot be deleted.');
        });
    }
}
