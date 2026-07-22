<?php

namespace Corals\Modules\Gateway\Transformers;

use Corals\Foundation\Transformers\APIBaseTransformer;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;

class PaymentIntentTransformer extends APIBaseTransformer
{
    public function transform(PaymentIntent $intent): array
    {
        return [
            'public_id' => $intent->public_id,
            'invoice_id' => $intent->invoice_id,
            'mode' => $intent->mode,
            'amount_policy' => $intent->amount_policy,
            'state' => $intent->state,
            'expires_at' => $intent->expires_at?->toIso8601String(),
            'max_payments' => $intent->max_payments,
            'references' => $intent->paymentReferences->map(fn (PaymentReference $reference) => [
                'human_reference' => $reference->human_reference,
                'barcode_payload' => $reference->barcode_payload,
                'qr_payload' => $reference->qr_payload,
                'kid' => $reference->kid,
                'expires_at' => $reference->expires_at?->toIso8601String(),
                'status' => $reference->status,
            ])->all(),
        ];
    }
}
