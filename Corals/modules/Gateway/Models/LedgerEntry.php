<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class LedgerEntry extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'posting_id',
        'account_type',
        'account_ref',
        'direction',
        'amount_centavos',
        'transaction_id',
        'top_up_id',
        'settlement_id',
    ];

    protected static function newFactory(): LedgerEntryFactory
    {
        return LedgerEntryFactory::new();
    }

    public function topUp()
    {
        return $this->belongsTo(WalletTopUp::class, 'top_up_id');
    }

    protected static function booted()
    {
        static::updating(function () {
            throw new LogicException('ledger_entries is append-only; existing rows cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('ledger_entries is append-only; existing rows cannot be deleted.');
        });
    }
}
