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
 * Sanctum-authenticatable principal for the POS/network API (GW-401), one
 * per network_id. Stopgap until GW-407 replaces it with real per-terminal
 * mTLS/short-TTL JWT credentials — the validate/confirm core logic doesn't
 * change when that swap happens, only how the caller authenticates.
 */
class NetworkCredential extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory;

    protected $fillable = [
        'public_id',
        'network_id',
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
