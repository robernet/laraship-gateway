<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Models\NetworkCredential;
use Illuminate\Console\Command;

/**
 * GW-401: issues a scoped, short-TTL Sanctum API token for a network/POS
 * credential. Stopgap until GW-407 replaces it with real per-terminal
 * mTLS/short-TTL JWT auth. Creates the credential on first use — there is no
 * separate provisioning step yet, one credential per network_id.
 */
class IssueNetworkToken extends Command
{
    protected $signature = 'gateway:network:issue-token
        {network_id : Network identifier, e.g. mock-realtime}
        {--ability=* : Abilities to grant (defaults to all network abilities)}
        {--ttl= : Token lifetime in minutes (defaults to config(gateway.network_token_ttl_minutes))}';

    protected $description = 'Issue a scoped, short-TTL Sanctum API token for a network/POS credential';

    public function handle(): int
    {
        $credential = NetworkCredential::firstOrCreate(
            ['network_id' => $this->argument('network_id')],
            ['status' => 'active']
        );

        if ($credential->status !== 'active') {
            $this->error('Network credential is not active.');

            return self::FAILURE;
        }

        $abilities = $this->option('ability') ?: NetworkAbility::values();
        $ttlMinutes = (int) ($this->option('ttl') ?: config('gateway.network_token_ttl_minutes'));

        $token = $credential->createToken('gateway-pos', $abilities, now()->addMinutes($ttlMinutes));

        $this->info('Token issued (shown once):');
        $this->line($token->plainTextToken);
        $this->line('Abilities: '.implode(', ', $abilities));
        $this->line("Expires at: {$token->accessToken->expires_at}");

        return self::SUCCESS;
    }
}
