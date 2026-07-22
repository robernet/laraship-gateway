<?php

namespace Corals\Modules\Gateway\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Corals\Modules\Gateway\Core\Issuers\IssuerAbility;
use Corals\Modules\Gateway\Models\Issuer;
use Illuminate\Http\Request;

/**
 * Self-serve Sanctum API tokens for the issuer portal (GW-306) — replaces
 * the gateway:issuer:issue-token artisan stopgap (GW-305) for day-to-day use;
 * the command still exists for out-of-band/support cases.
 */
class ApiKeyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:issuer');
    }

    public function index(Request $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        return view('Gateway::portal.api-keys', [
            'tokens' => $issuer->tokens()->latest()->get(),
            'abilities' => IssuerAbility::values(),
        ]);
    }

    public function store(Request $request)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $data = $request->validate([
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'in:'.implode(',', IssuerAbility::values())],
            'ttl_minutes' => ['sometimes', 'integer', 'min:1'],
        ]);

        $abilities = $data['abilities'] ?? IssuerAbility::values();
        $ttlMinutes = $data['ttl_minutes'] ?? config('gateway.issuer_token_ttl_minutes');

        $token = $issuer->createToken('gateway-portal', $abilities, now()->addMinutes($ttlMinutes));

        return redirect()->route('gateway.portal.api-keys.index')
            ->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId)
    {
        /** @var Issuer $issuer */
        $issuer = $request->user();

        $issuer->tokens()->where('id', $tokenId)->delete();

        return redirect()->route('gateway.portal.api-keys.index');
    }
}
