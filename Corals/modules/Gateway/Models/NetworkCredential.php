<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\NetworkCredentialFactory;
use Hashids\Hashids;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Sanctum-authenticatable principal for the POS/network API (GW-401,
 * GW-407). `terminal_id` null = network-wide credential (e.g. the
 * batch-confirm/SFTP poller); set = a single POS terminal, individually
 * revocable via `status` without affecting siblings on the same network.
 * `Http\Middleware\EnsureTerminalCredentialActive` enforces both the
 * active status and that terminal-scoped credentials match what the
 * request claims.
 */
class NetworkCredential extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory;

    protected $fillable = [
        'public_id',
        'network_id',
        'terminal_id',
        'name',
        'status',
    ];

    protected static function newFactory(): NetworkCredentialFactory
    {
        return NetworkCredentialFactory::new();
    }

    protected static function booted()
    {
        static::created(function (self $credential) {
            $credential->update(['public_id' => (new Hashids(config('app.key')))->encode($credential->id)]);
        });
    }
}
