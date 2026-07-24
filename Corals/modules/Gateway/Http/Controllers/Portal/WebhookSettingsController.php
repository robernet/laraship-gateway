<?php

namespace Corals\Modules\Gateway\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Corals\Modules\Gateway\Core\Outbox\OutboxDispatcher;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\OutboxEvent;
use Corals\Modules\Gateway\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Issuer-managed webhook endpoint config (GW-306): the URL GW-304's signed
 * webhook deliveries are sent to, and the shared secret used to sign them.
 * GW-307 adds test() — a webhook tester so a third-party developer can
 * verify their receiver without a real cash collection.
 */
class WebhookSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:issuer');
    }

    public function edit(Request $request)
    {
        return view('Gateway::portal.webhook', ['issuer' => $request->user()]);
    }

    public function update(Request $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $data = $request->validate([
            'webhook_url' => ['required', 'url'],
            'regenerate_secret' => ['sometimes', 'boolean'],
        ]);

        $issuer->update([
            'webhook_url' => $data['webhook_url'],
            'webhook_secret' => ($data['regenerate_secret'] ?? false)
                ? bin2hex(random_bytes(16))
                : $issuer->webhook_secret,
        ]);

        return redirect()->route('gateway.portal.webhook.edit')->with('status', 'Webhook settings updated.');
    }

    /**
     * Enqueues a synthetic `payment.confirmed` event through the real
     * outbox/webhook pipeline (same signing + delivery code path production
     * events use) and reports the delivery result inline.
     */
    public function test(Request $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        if (! $issuer->webhook_url || ! $issuer->webhook_secret) {
            return redirect()->route('gateway.portal.webhook.edit')
                ->with('error', 'Save a webhook URL before sending a test event.');
        }

        OutboxEvent::create([
            'event' => 'payment.confirmed',
            'payload' => [
                'transaction_id' => 0,
                'posting_id' => (string) Str::uuid(),
                'pos_wallet_id' => 0,
                'issuer_id' => $issuer->id,
                'amount_centavos' => 12345,
                'test' => true,
            ],
        ]);

        app(OutboxDispatcher::class)->dispatchPending();

        $delivery = WebhookDelivery::where('issuer_id', $issuer->id)->latest()->first();

        return redirect()->route('gateway.portal.webhook.edit')->with('testResult', [
            'status' => $delivery?->status,
            'last_error' => $delivery?->last_error,
        ]);
    }
}
