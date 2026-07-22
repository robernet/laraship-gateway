<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\MerchantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'mid',
        'issuer_id',
        'signing_key_current_kid',
        'status',
    ];

    protected static function newFactory(): MerchantFactory
    {
        return MerchantFactory::new();
    }

    public function issuer()
    {
        return $this->belongsTo(Issuer::class);
    }

    public function merchantKeys()
    {
        return $this->hasMany(MerchantKey::class);
    }
}
