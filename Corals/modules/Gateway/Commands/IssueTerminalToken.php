<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Models\NetworkCredential;
use Illuminate\Console\Command;

/**
 * GW-407: issues a scoped, short-TTL Sanctum API token for a single POS
 * terminal — one credential per (network_id, terminal_id). Revoking it
 * (`gateway:terminal:revoke`) blocks that terminal alone; the network and
 * its other terminals keep working. Creates the credential on first use.
 */
class IssueTerminalToken extends Command
{
    protected $signature = 'gateway:terminal:issue-token
        {network_id : Network identifier, e.g. mock-realtime}
        {terminal_id : Terminal identifier, e.g. T-01}
        {--ability=* : Abilities to grant (defaults to all network abilities)}
        {--ttl= : Token lifetime in minutes (defaults to config(gateway.network_token_ttl_minutes))}';

    protected $description = 'Issue a scoped, short-TTL Sanctum API token for a single POS terminal';

    public function handle(): int
    {
        $credential = NetworkCredential::firstOrCreate(
            ['network_id' => $this->argument('network_id'), 'terminal_id' => $this->argument('terminal_id')],
            ['status' => 'active']
        );

        if ($credential->status !== 'active') {
            $this->error('Terminal credential is not active.');

            return self::FAILURE;
        }

        $abilities = $this->option('ability') ?: NetworkAbility::values();
        $ttlMinutes = (int) ($this->option('ttl') ?: config('gateway.network_token_ttl_minutes'));

        $token = $credential->createToken('gateway-terminal', $abilities, now()->addMinutes($ttlMinutes));

        $this->info('Token issued (shown once):');
        $this->line($token->plainTextToken);
        $this->line('Abilities: '.implode(', ', $abilities));
        $this->line("Expires at: {$token->accessToken->expires_at}");

        return self::SUCCESS;
    }
}
