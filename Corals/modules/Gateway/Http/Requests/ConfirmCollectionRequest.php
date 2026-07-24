<?php

namespace Corals\Modules\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ConfirmCollectionRequest extends FormRequest
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
            'amount_paid' => ['required', 'integer', 'min:0'],
            'is_partial' => ['required', 'boolean'],
            'network_txn_id' => ['required', 'string'],
            'idempotency_key' => ['required', 'string'],
            'store_id' => ['required', 'string'],
            'terminal_id' => ['required', 'string'],
            'collected_at' => ['required', 'integer'],
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->header('Idempotency-Key')) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['The Idempotency-Key header is required.'],
            ]);
        }
    }
}
