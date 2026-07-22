<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Core\Issuers\IssuerAbility;
use Corals\Modules\Gateway\Models\Issuer;
use Illuminate\Console\Command;

/**
 * GW-305: issues a scoped, short-TTL Sanctum API token for an Issuer. Stopgap
 * until the issuer portal (GW-306) can self-serve this.
 */
class IssueIssuerToken extends Command
{
    protected $signature = 'gateway:issuer:issue-token
        {issuer : Issuer id or public_id}
        {--ability=* : Abilities to grant (defaults to all issuer abilities)}
        {--ttl= : Token lifetime in minutes (defaults to config(gateway.issuer_token_ttl_minutes))}';

    protected $description = 'Issue a scoped, short-TTL Sanctum API token for an issuer';

    public function handle(): int
    {
        $issuerArg = $this->argument('issuer');

        $issuer = is_numeric($issuerArg)
            ? Issuer::find($issuerArg)
            : Issuer::where('public_id', $issuerArg)->first();

        if (! $issuer) {
            $this->error('Issuer not found.');

            return self::FAILURE;
        }

        $abilities = $this->option('ability') ?: IssuerAbility::values();
        $ttlMinutes = (int) ($this->option('ttl') ?: config('gateway.issuer_token_ttl_minutes'));

        $token = $issuer->createToken('gateway-api', $abilities, now()->addMinutes($ttlMinutes));

        $this->info('Token issued (shown once):');
        $this->line($token->plainTextToken);
        $this->line('Abilities: '.implode(', ', $abilities));
        $this->line("Expires at: {$token->accessToken->expires_at}");

        return self::SUCCESS;
    }
}
