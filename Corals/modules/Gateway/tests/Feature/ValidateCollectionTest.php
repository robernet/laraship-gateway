<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\NetworkCredential;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Laravel\Sanctum\Sanctum;

class ValidateCollectionTest extends GatewayTestCase
{
    private function setUpFixture(array $intentOverrides = [], array $referenceOverrides = [], array $walletOverrides = []): array
    {
        $merchant = Merchant::factory()->create();
        $intent = PaymentIntent::factory()->create(array_merge([
            'merchant_id' => $merchant->id,
            'state' => 'ACTIVE',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ], $intentOverrides));
        $reference = PaymentReference::factory()->create(array_merge([
            'payment_intent_id' => $intent->id,
            'status' => 'active',
            'expires_at' => now()->addHour(),
        ], $referenceOverrides));
        $wallet = PosWallet::factory()->create(array_merge([
            'network_id' => 'mock-realtime',
            'external_store_id' => 'S-001',
            'balance_centavos' => 50000,
            'reserved_centavos' => 0,
        ], $walletOverrides));

        return [$merchant, $intent, $reference, $wallet];
    }

    private function payload(Merchant $merchant, PaymentReference $reference, array $overrides = []): array
    {
        return array_merge([
            'contract_v' => 1,
            'network_id' => 'mock-realtime',
            'mid' => $merchant->mid,
            'ref' => $reference->reference_token,
            'amount_attempt' => 10000,
            'store_id' => 'S-001',
            'terminal_id' => 'T-01',
            'request_id' => 'REQ-'.uniqid(),
        ], $overrides);
    }

    private function actingAsNetwork(): NetworkCredential
    {
        $credential = NetworkCredential::factory()->create();
        Sanctum::actingAs($credential, [NetworkAbility::ValidateCollection->value]);

        return $credential;
    }

    public function test_happy_path_reserves_the_wallet_and_authorizes_a_transaction(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture();
        $this->actingAsNetwork();

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference));

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('intent_state', 'ACTIVE');
        $this->assertNotEmpty($response->json('reservation_id'));

        $this->assertSame(10000, $wallet->fresh()->reserved_centavos);
        $this->assertSame(1, Transaction::where('state', 'AUTHORIZED')->count());
    }

    public function test_insufficient_funds_declines_without_reserving(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture([], [], ['balance_centavos' => 5000]);
        $this->actingAsNetwork();

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference));

        $response->assertStatus(200);
        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('decline_reason', 'insufficient_funds');
        $this->assertSame(0, $wallet->fresh()->reserved_centavos);
        $this->assertSame(0, Transaction::count());
    }

    public function test_unknown_mid_is_declined_as_tampered(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();
        $this->actingAsNetwork();

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference, ['mid' => '999999999']));

        $response->assertStatus(200);
        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('decline_reason', 'tampered');
    }

    public function test_reference_belonging_to_another_merchant_is_declined_as_tampered(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();
        $otherMerchant = Merchant::factory()->create();
        $this->actingAsNetwork();

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($otherMerchant, $reference));

        $response->assertStatus(200);
        $response->assertJsonPath('decline_reason', 'tampered');
    }

    public function test_already_consumed_reference_is_declined_as_replayed(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture([], ['status' => 'consumed']);
        $this->actingAsNetwork();

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference));

        $response->assertJsonPath('decline_reason', 'replayed');
    }

    public function test_expired_reference_is_declined_as_expired(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture([], ['expires_at' => now()->subMinute()]);
        $this->actingAsNetwork();

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference));

        $response->assertJsonPath('decline_reason', 'expired');
    }

    public function test_amount_not_matching_policy_is_declined_as_policy_mismatch(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();
        $this->actingAsNetwork();

        $response = $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference, ['amount_attempt' => 9999]));

        $response->assertJsonPath('decline_reason', 'policy_mismatch');
    }

    public function test_a_replayed_request_id_is_declined(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture([], [], ['balance_centavos' => 100000]);
        $this->actingAsNetwork();
        $payload = $this->payload($merchant, $reference, ['request_id' => 'REQ-fixed-'.uniqid()]);

        $first = $this->postJson('/api/v1/cash/validate', $payload);
        $first->assertJsonPath('ok', true);

        $second = $this->postJson('/api/v1/cash/validate', $payload);
        $second->assertJsonPath('decline_reason', 'replayed');
    }

    public function test_missing_ability_is_rejected(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();
        $credential = NetworkCredential::factory()->create();
        Sanctum::actingAs($credential, []);

        $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference))->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();

        $this->postJson('/api/v1/cash/validate', $this->payload($merchant, $reference))->assertStatus(401);
    }
}
