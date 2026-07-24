<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\NetworkCredential;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Laravel\Sanctum\Sanctum;

/**
 * GW-407: EnsureTerminalCredentialActive, exercised through the real
 * cash/validate route (auth:sanctum -> abilities -> our middleware).
 */
class TerminalAuthTest extends GatewayTestCase
{
    private function setUpFixture(): array
    {
        $merchant = Merchant::factory()->create();
        $intent = PaymentIntent::factory()->create([
            'merchant_id' => $merchant->id,
            'state' => 'ACTIVE',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $reference = PaymentReference::factory()->create([
            'payment_intent_id' => $intent->id,
            'status' => 'active',
            'expires_at' => now()->addHour(),
        ]);
        PosWallet::factory()->create([
            'network_id' => 'mock-realtime',
            'external_store_id' => 'S-001',
            'balance_centavos' => 50000,
            'reserved_centavos' => 0,
        ]);

        return [$merchant, $reference];
    }

    private function payload(Merchant $merchant, PaymentReference $reference): array
    {
        return [
            'contract_v' => 1,
            'network_id' => 'mock-realtime',
            'mid' => $merchant->mid,
            'ref' => $reference->reference_token,
            'amount_attempt' => 10000,
            'store_id' => 'S-001',
            'terminal_id' => 'T-01',
            'request_id' => 'REQ-'.uniqid(),
        ];
    }

    public function test_terminal_scoped_credential_matching_the_payload_succeeds(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $credential = NetworkCredential::factory()->create(['network_id' => 'mock-realtime', 'terminal_id' => 'T-01']);
        Sanctum::actingAs($credential, [NetworkAbility::ValidateCollection->value]);

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference));

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
    }

    public function test_revoked_terminal_is_rejected_immediately(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $credential = NetworkCredential::factory()->create([
            'network_id' => 'mock-realtime',
            'terminal_id' => 'T-01',
            'status' => 'revoked',
        ]);
        Sanctum::actingAs($credential, [NetworkAbility::ValidateCollection->value]);

        $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference))->assertStatus(401);
    }

    public function test_terminal_credential_cannot_claim_a_different_terminal_id(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $credential = NetworkCredential::factory()->create(['network_id' => 'mock-realtime', 'terminal_id' => 'T-02']);
        Sanctum::actingAs($credential, [NetworkAbility::ValidateCollection->value]);

        $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference))->assertStatus(403);
    }

    public function test_terminal_credential_cannot_claim_a_different_network_id(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $credential = NetworkCredential::factory()->create(['network_id' => 'mock-realtime-other', 'terminal_id' => 'T-01']);
        Sanctum::actingAs($credential, [NetworkAbility::ValidateCollection->value]);

        $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference))->assertStatus(403);
    }
}
