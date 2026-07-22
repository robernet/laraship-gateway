<?php

namespace Corals\Modules\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Merchant ownership (mid -> issuer) is enforced in
        // Core\Intents\CreatePaymentIntent, not here — it needs a DB lookup
        // that belongs with the rest of the business logic.
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'string', 'max:255'],
            'mid' => ['required', 'string', 'regex:/^[0-9]{9}$/'],
            'mode' => ['required', 'in:one_time,reusable'],
            'amount_policy' => ['required', 'array'],
            'amount_policy.type' => ['required', 'in:fixed,variable'],
            'amount_policy.amount' => ['required_if:amount_policy.type,fixed', 'integer', 'min:0'],
            'amount_policy.min' => ['required_if:amount_policy.type,variable', 'integer', 'min:0'],
            'amount_policy.max' => ['required_if:amount_policy.type,variable', 'integer', 'gte:amount_policy.min'],
            'amount_policy.allow_partial' => ['sometimes', 'boolean'],
            'mapping_strategy' => ['sometimes', 'in:deterministic,stored'],
            'expires_at' => ['sometimes', 'date'],
            'max_payments' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'overpay_policy' => ['sometimes', 'in:reject,accept,accept_and_flag'],
            'underpay_policy' => ['sometimes', 'in:reject,accept,accept_and_flag'],
        ];
    }
}
