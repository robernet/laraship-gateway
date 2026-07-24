<?php

namespace Corals\Modules\Gateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * GW-407: `auth:sanctum` only checks token expiry, not the owning
 * credential's status, so a revoked terminal's still-unexpired token would
 * otherwise keep working — this closes that gap. It also ties a
 * terminal-scoped NetworkCredential to the network_id/terminal_id it
 * claims in the request body, so one terminal's token can't be used to
 * submit on behalf of another. Network-wide credentials (terminal_id
 * null — e.g. the batch-confirm/SFTP poller) skip the claim check.
 */
class EnsureTerminalCredentialActive
{
    public function handle(Request $request, Closure $next)
    {
        $credential = $request->user();

        if (! $credential || $credential->status !== 'active') {
            abort(401, 'Credential is not active.');
        }

        if ($credential->terminal_id !== null && (
            $request->input('network_id') !== $credential->network_id
            || $request->input('terminal_id') !== $credential->terminal_id
        )) {
            abort(403, 'Credential does not match the requested terminal.');
        }

        return $next($request);
    }
}
