<?php

namespace Corals\Modules\Gateway\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Corals\Modules\Gateway\Models\Issuer;
use Illuminate\Http\Request;

/**
 * Issuer-managed webhook endpoint config (GW-306): the URL GW-304's signed
 * webhook deliveries are sent to, and the shared secret used to sign them.
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
}
