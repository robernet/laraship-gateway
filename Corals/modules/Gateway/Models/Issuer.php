<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\IssuerFactory;
use Hashids\Hashids;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Issuer doubles as both the Sanctum-authenticatable principal for the
 * Issuer API (GW-301) and the session-authenticatable principal for the
 * issuer portal (GW-306), via the `issuer` guard/provider in config/auth.php.
 */
class Issuer extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory;

    protected $fillable = [
        'public_id',
        'name',
        'settlement_clabe',
        'status',
        'webhook_url',
        'webhook_secret',
        'finality_policy',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    protected static function newFactory(): IssuerFactory
    {
        return IssuerFactory::new();
    }

    public function merchants()
    {
        return $this->hasMany(Merchant::class);
    }

    protected static function booted()
    {
        static::creating(function (self $issuer) {
            $issuer->reference_secret ??= bin2hex(random_bytes(32));
        });

        static::created(function (self $issuer) {
            $issuer->update(['public_id' => (new Hashids(config('app.key')))->encode($issuer->id)]);
        });
    }
}
