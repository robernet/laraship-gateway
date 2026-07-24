<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Models\NetworkCredential;
use Illuminate\Console\Command;

/**
 * GW-407: revokes a single POS terminal's credential. Deleting its tokens
 * is belt-and-suspenders — EnsureTerminalCredentialActive already rejects
 * the credential on its very next request purely from `status`, before any
 * unexpired token would otherwise have lapsed.
 */
class RevokeTerminalToken extends Command
{
    protected $signature = 'gateway:terminal:revoke
        {network_id : Network identifier, e.g. mock-realtime}
        {terminal_id : Terminal identifier, e.g. T-01}';

    protected $description = 'Revoke a single POS terminal credential';

    public function handle(): int
    {
        $credential = NetworkCredential::where('network_id', $this->argument('network_id'))
            ->where('terminal_id', $this->argument('terminal_id'))
            ->first();

        if (! $credential) {
            $this->error('Terminal credential not found.');

            return self::FAILURE;
        }

        $credential->update(['status' => 'revoked']);
        $credential->tokens()->delete();

        $this->info('Terminal credential revoked.');

        return self::SUCCESS;
    }
}
