<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\IssuerFactory;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issuer extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'name',
        'settlement_clabe',
        'status',
        'webhook_url',
        'webhook_secret',
        'finality_policy',
    ];

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
        static::created(function (self $issuer) {
            $issuer->update(['public_id' => (new Hashids(config('app.key')))->encode($issuer->id)]);
        });
    }
}
