<?php

namespace Corals\Modules\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCollectionRequest extends FormRequest
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
            'mid' => ['required', 'string', 'regex:/^[0-9]{9}$/'],
            'ref' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'amount_attempt' => ['required', 'integer', 'min:0'],
            'store_id' => ['required', 'string'],
            'terminal_id' => ['required', 'string'],
            'request_id' => ['required', 'string'],
        ];
    }
}
