<?php

namespace Corals\Modules\Gateway\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Corals\Modules\Gateway\Core\Collections\ConfirmCollection;
use Corals\Modules\Gateway\Core\Collections\ValidateCollection;
use Corals\Modules\Gateway\Core\Intents\CreatePaymentIntent;
use Corals\Modules\Gateway\Http\Requests\CreatePaymentIntentRequest;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Reference POS simulator (GW-408): a session-authenticated page that walks
 * an issuer through validate -> confirm for the mock-realtime network
 * (GW-404's network_id) so a full collection (fixed, variable, partial) can
 * be demoed without a real POS terminal or network credential.
 *
 * ponytail: calls Core\Collections\{Validate,Confirm}Collection directly —
 * same as Http\Controllers\Pos\CashController — never
 * Adapters\MockRealtime\MockRealtimeAdapter (Http -> Adapters is forbidden
 * by the depfile.yaml seam ruleset; only an Adapter itself may sit in front
 * of Core). This is a dev/demo harness, not a network integration, so it
 * never touches the /v1/cash HTTP endpoints or their Sanctum auth either.
 */
class SimulatorController extends Controller
{
    private const NETWORK_ID = 'mock-realtime';

    private const STORE_ID = 'SIM-01';

    private const TERMINAL_ID = 'SIM-TERM-1';

    public function __construct()
    {
        $this->middleware('auth:issuer');
    }

    public function index(Request $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $intents = PaymentIntent::where('issuer_id', $issuer->id)
            ->whereIn('state', ['ACTIVE', 'PAID_PENDING_SETTLEMENT'])
            ->with('paymentReferences', 'merchant')
            ->latest()
            ->get();

        $wallet = $this->ensureFundedWallet();

        return view('Gateway::portal.simulator', [
            'intents' => $intents,
            'wallet' => $wallet,
        ]);
    }

    public function createIntent(CreatePaymentIntentRequest $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        (new CreatePaymentIntent())->handle($issuer, $request->validated());

        return redirect()->route('gateway.portal.simulator.index');
    }

    public function validateCollection(Request $request)
    {
        $data = $request->validate([
            'intent_id' => ['required', 'string'],
            'amount_attempt' => ['required', 'integer', 'min:0'],
        ]);

        [$intent, $reference, $error] = $this->resolveIntentAndReference($request, $data['intent_id']);

        if ($error) {
            return redirect()->route('gateway.portal.simulator.index')->with('simError', $error);
        }

        $wallet = $this->ensureFundedWallet();

        $payload = [
            'contract_v' => 1,
            'network_id' => self::NETWORK_ID,
            'mid' => $intent->merchant->mid,
            'ref' => $reference->reference_token,
            'amount_attempt' => $data['amount_attempt'],
            'store_id' => $wallet->external_store_id,
            'terminal_id' => self::TERMINAL_ID,
            'request_id' => (string) Str::uuid(),
        ];

        $result = (new ValidateCollection())->handle($payload);

        return redirect()->route('gateway.portal.simulator.index')
            ->with('simResult', ['step' => 'validate', 'request' => $payload, 'response' => $result]);
    }

    public function confirmCollection(Request $request)
    {
        $data = $request->validate([
            'intent_id' => ['required', 'string'],
            'amount_paid' => ['required', 'integer', 'min:0'],
            'is_partial' => ['sometimes', 'boolean'],
        ]);

        [$intent, $reference, $error] = $this->resolveIntentAndReference($request, $data['intent_id']);

        if ($error) {
            return redirect()->route('gateway.portal.simulator.index')->with('simError', $error);
        }

        $wallet = $this->ensureFundedWallet();

        $payload = [
            'contract_v' => 1,
            'network_id' => self::NETWORK_ID,
            'mid' => $intent->merchant->mid,
            'ref' => $reference->reference_token,
            'amount_paid' => $data['amount_paid'],
            'is_partial' => $data['is_partial'] ?? false,
            'network_txn_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'store_id' => $wallet->external_store_id,
            'terminal_id' => self::TERMINAL_ID,
            'collected_at' => now()->timestamp,
        ];

        $result = (new ConfirmCollection())->handle($payload);

        return redirect()->route('gateway.portal.simulator.index')
            ->with('simResult', ['step' => 'confirm', 'request' => $payload, 'response' => $result]);
    }

    /**
     * @return array{0: ?PaymentIntent, 1: ?PaymentReference, 2: ?string}
     */
    private function resolveIntentAndReference(Request $request, string $publicId): array
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $intent = PaymentIntent::where('public_id', $publicId)
            ->where('issuer_id', $issuer->id)
            ->with('paymentReferences', 'merchant')
            ->first();

        if (! $intent) {
            return [null, null, 'Payment intent not found.'];
        }

        $reference = $intent->paymentReferences->firstWhere('status', 'active');

        if (! $reference) {
            return [null, null, 'This intent has no active reference left to collect against.'];
        }

        return [$intent, $reference, null];
    }

    /**
     * A dedicated demo wallet, topped up directly (no top-up-matching
     * pipeline exists yet — that's Phase 5) so the simulator never fails on
     * insufficient_funds. Shared across issuers: it's sandbox infrastructure,
     * not merchant money.
     */
    private function ensureFundedWallet(): PosWallet
    {
        $wallet = PosWallet::firstOrCreate(
            ['network_id' => self::NETWORK_ID, 'external_store_id' => self::STORE_ID],
            ['balance_centavos' => 0, 'reserved_centavos' => 0, 'currency' => 'MXN', 'status' => 'active']
        );

        if ($wallet->balance_centavos - $wallet->reserved_centavos < 1_000_000) {
            $wallet->update(['balance_centavos' => $wallet->balance_centavos + 10_000_000]);
        }

        return $wallet;
    }
}
