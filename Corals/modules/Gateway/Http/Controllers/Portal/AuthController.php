<?php

namespace Corals\Modules\Gateway\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Session login for the issuer portal (GW-306), a separate persona from the
 * Sanctum-authenticated Issuer API (GW-301/305) — see the `issuer` guard in
 * config/auth.php. Issuers are provisioned by Corals staff and given a
 * password via `gateway:issuer:set-password`; there is no self-registration.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('Gateway::portal.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! auth('issuer')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('gateway.portal.dashboard');
    }

    public function logout(Request $request)
    {
        auth('issuer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('gateway.portal.login');
    }
}
