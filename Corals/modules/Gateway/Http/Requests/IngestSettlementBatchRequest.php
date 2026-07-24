<?php

namespace Corals\Modules\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestSettlementBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_v' => ['required', 'integer', 'in:1'],
            'network_id' => ['required', 'string'],
            'batch_id' => ['required', 'string'],
            'rows' => ['required', 'array'],
            'rows.*.network_txn_id' => ['required', 'string'],
            'rows.*.mid' => ['required', 'string', 'regex:/^[0-9]{9}$/'],
            'rows.*.ref' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'rows.*.amount_paid' => ['required', 'integer', 'min:0'],
            'rows.*.collected_at' => ['required', 'integer'],
        ];
    }
}
