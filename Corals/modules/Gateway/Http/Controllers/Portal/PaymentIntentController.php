<?php

namespace Corals\Modules\Gateway\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Corals\Modules\Gateway\Core\Intents\CreatePaymentIntent;
use Corals\Modules\Gateway\Http\Requests\CreatePaymentIntentRequest;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Transformers\PaymentIntentTransformer;
use Illuminate\Http\Request;

/**
 * Issuer portal (GW-306): create intents and track invoices without touching
 * the Issuer API directly. Session-authenticated (`issuer` guard), so every
 * query is scoped to `auth('issuer')->user()` — same ownership rule as the
 * API's GW-302 endpoints, just via session instead of a bearer token.
 *
 * ponytail: no Idempotency-Key handling here — that's for API clients
 * retrying safely, not a browser form post. Double-submit is a disabled-
 * button concern client-side; revisit if that stops being enough.
 */
class PaymentIntentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:issuer');
    }

    public function index()
    {
        return view('Gateway::portal.dashboard');
    }

    public function list(Request $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $intents = PaymentIntent::where('issuer_id', $issuer->id)
            ->with('paymentReferences', 'merchant')
            ->latest()
            ->paginate(20);

        $intents->getCollection()->transform(fn (PaymentIntent $intent) => (new PaymentIntentTransformer())->transform($intent));

        return response()->json($intents);
    }

    public function store(CreatePaymentIntentRequest $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $intent = (new CreatePaymentIntent())->handle($issuer, $request->validated());

        return response()->json((new PaymentIntentTransformer())->transform($intent), 201);
    }

    public function invoiceStatus(Request $request, string $invoiceId)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $intent = PaymentIntent::where('invoice_id', $invoiceId)
            ->where('issuer_id', $issuer->id)
            ->with('paymentReferences')
            ->first();

        if (! $intent) {
            abort(404);
        }

        return response()->json((new PaymentIntentTransformer())->transform($intent));
    }
}
