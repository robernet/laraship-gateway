<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Models\Issuer;
use Illuminate\Console\Command;

/**
 * Sets (or resets) an Issuer's portal login password (GW-306). Issuers are
 * provisioned by Corals staff, not self-registered, so this is the
 * out-of-band step that lets an issuer sign into the portal for the first
 * time — same pattern as IssueIssuerToken (GW-305) for API tokens.
 */
class SetIssuerPassword extends Command
{
    protected $signature = 'gateway:issuer:set-password
        {issuer : Issuer id, public_id, or login email}
        {password : The new plaintext password}';

    protected $description = "Set an issuer's portal login password";

    public function handle(): int
    {
        $issuerArg = $this->argument('issuer');

        $issuer = match (true) {
            is_numeric($issuerArg) => Issuer::find($issuerArg),
            default => Issuer::where('public_id', $issuerArg)->orWhere('email', $issuerArg)->first(),
        };

        if (! $issuer) {
            $this->error('Issuer not found.');

            return self::FAILURE;
        }

        $issuer->update(['password' => $this->argument('password')]);

        $this->info("Password set for issuer [{$issuer->name}].");

        return self::SUCCESS;
    }
}
