<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Intents\CreatePaymentIntent;
use Corals\Modules\Gateway\Core\Issuers\IssuerAbility;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Laravel\Sanctum\Sanctum;

class IssuerAuthScopesTest extends GatewayTestCase
{
    public function test_a_read_only_token_cannot_create_payment_intents(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);
        Sanctum::actingAs($issuer, [IssuerAbility::ReadPaymentIntents->value]);

        $response = $this->postJson('/api/v1/payment-intents', [
            'invoice_id' => 'INV-1',
            'mid' => $merchant->mid,
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ], ['Idempotency-Key' => 'key-scope-1']);

        $response->assertStatus(403);
    }

    public function test_a_write_only_token_cannot_read_payment_intent_status(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);
        $intent = (new CreatePaymentIntent())->handle($issuer, [
            'mid' => $merchant->mid,
            'invoice_id' => 'INV-2',
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);

        Sanctum::actingAs($issuer, [IssuerAbility::CreatePaymentIntents->value]);

        $this->getJson("/api/v1/payment-intents/{$intent->public_id}")->assertStatus(403);
        $this->getJson('/api/v1/invoices/INV-2/status')->assertStatus(403);
    }

    public function test_issue_token_command_grants_short_ttl_scoped_credentials(): void
    {
        $issuer = Issuer::factory()->create();

        $this->artisan('gateway:issuer:issue-token', [
            'issuer' => $issuer->public_id,
            '--ability' => [IssuerAbility::ReadPaymentIntents->value],
            '--ttl' => 30,
        ])->assertSuccessful();

        $token = $issuer->tokens()->sole();

        $this->assertSame([IssuerAbility::ReadPaymentIntents->value], $token->abilities);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addMinutes(30)->addSecond()));
    }

    public function test_issue_token_command_defaults_to_all_abilities_and_configured_ttl(): void
    {
        $issuer = Issuer::factory()->create();

        $this->artisan('gateway:issuer:issue-token', [
            'issuer' => (string) $issuer->id,
        ])->assertSuccessful();

        $token = $issuer->tokens()->sole();

        $this->assertSame(IssuerAbility::values(), $token->abilities);
        $this->assertTrue(
            $token->expires_at->lessThanOrEqualTo(now()->addMinutes(config('gateway.issuer_token_ttl_minutes'))->addSecond())
        );
    }
}
