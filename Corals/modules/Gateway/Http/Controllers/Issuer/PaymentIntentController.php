<?php

namespace Corals\Modules\Gateway\Http\Controllers\Issuer;

use Corals\Foundation\Http\Controllers\APIBaseController;
use Corals\Modules\Gateway\Core\Intents\CreatePaymentIntent;
use Corals\Modules\Gateway\Core\Intents\IdempotentRequest;
use Corals\Modules\Gateway\Http\Requests\CreatePaymentIntentRequest;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Transformers\PaymentIntentTransformer;
use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /v1/payment-intents + status. See Corals/modules/Gateway/CLAUDE.md for
 * invariants and contracts/openapi.yaml for the response shape — this
 * endpoint returns the raw resource / {message, errors} error shape per the
 * contract, not the app-wide apiResponse() envelope.
 */
class PaymentIntentController extends APIBaseController
{
    public function store(CreatePaymentIntentRequest $request)
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['The Idempotency-Key header is required.'],
            ]);
        }

        /** @var Issuer $issuer */
        $issuer = $request->user();
        $data = $request->validated();

        $result = (new IdempotentRequest())->handle(
            scope: "gateway:create_payment_intent:{$issuer->id}",
            key: $idempotencyKey,
            requestPayload: $data,
            ttlSeconds: 86400,
            operation: function () use ($issuer, $data) {
                $intent = (new CreatePaymentIntent())->handle($issuer, $data);

                return [201, (new PaymentIntentTransformer())->transform($intent)];
            }
        );

        return response()->json($result['body'], $result['status']);
    }

    public function show(Request $request, string $id)
    {
        $decoded = (new Hashids(config('app.key')))->decode($id);

        if (empty($decoded)) {
            abort(404);
        }

        $intent = $this->findForIssuer($request, ['id' => $decoded[0]]);

        return response()->json((new PaymentIntentTransformer())->transform($intent));
    }

    public function statusByInvoice(Request $request, string $invoiceId)
    {
        $intent = $this->findForIssuer($request, ['invoice_id' => $invoiceId]);

        return response()->json((new PaymentIntentTransformer())->transform($intent));
    }

    /**
     * Scoped to the authenticated issuer so a valid lookup on someone else's
     * intent 404s exactly like a nonexistent one — no 403, no enumeration
     * signal either way.
     */
    private function findForIssuer(Request $request, array $conditions): PaymentIntent
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $intent = PaymentIntent::where($conditions)->where('issuer_id', $issuer->id)->first();

        if (! $intent) {
            abort(404);
        }

        return $intent->load('paymentReferences');
    }
}
