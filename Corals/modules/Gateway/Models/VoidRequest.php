<?php

namespace Corals\Modules\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

class VoidRequest extends Model
{
    protected $fillable = [
        'transaction_id',
        'posting_id',
        'amount_centavos',
        'requested_by',
        'approved_by',
        'status',
        'reason',
        'voided_posting_id',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
