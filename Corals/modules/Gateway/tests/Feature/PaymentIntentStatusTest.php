<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Intents\CreatePaymentIntent;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Hashids\Hashids;
use Laravel\Sanctum\Sanctum;

class PaymentIntentStatusTest extends GatewayTestCase
{
    private function createIntent(Issuer $issuer, string $invoiceId = 'INV-1')
    {
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);

        return (new CreatePaymentIntent())->handle($issuer, [
            'mid' => $merchant->mid,
            'invoice_id' => $invoiceId,
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
    }

    public function test_show_returns_the_intent_by_hashid_with_no_raw_pk(): void
    {
        $issuer = Issuer::factory()->create();
        $intent = $this->createIntent($issuer);
        Sanctum::actingAs($issuer, ['*']);

        $response = $this->getJson("/api/v1/payment-intents/{$intent->public_id}");

        $response->assertStatus(200);
        $response->assertJsonPath('invoice_id', 'INV-1');
        $response->assertJsonPath('public_id', $intent->public_id);
        $response->assertJsonMissingPath('id');

        $decoded = (new Hashids(config('app.key')))->decode($intent->public_id);
        $this->assertSame($intent->id, $decoded[0]);
    }

    public function test_show_returns_404_for_an_unknown_hashid(): void
    {
        $issuer = Issuer::factory()->create();
        Sanctum::actingAs($issuer, ['*']);

        $bogus = (new Hashids(config('app.key')))->encode(999999999);

        $this->getJson("/api/v1/payment-intents/{$bogus}")->assertStatus(404);
    }

    public function test_show_returns_404_for_a_malformed_id(): void
    {
        $issuer = Issuer::factory()->create();
        Sanctum::actingAs($issuer, ['*']);

        $this->getJson('/api/v1/payment-intents/not-a-hashid')->assertStatus(404);
    }

    public function test_show_returns_404_not_403_for_another_issuers_intent(): void
    {
        $owner = Issuer::factory()->create();
        $intent = $this->createIntent($owner);

        $other = Issuer::factory()->create();
        Sanctum::actingAs($other, ['*']);

        $this->getJson("/api/v1/payment-intents/{$intent->public_id}")->assertStatus(404);
    }

    public function test_show_requires_authentication(): void
    {
        $issuer = Issuer::factory()->create();
        $intent = $this->createIntent($issuer);

        $this->getJson("/api/v1/payment-intents/{$intent->public_id}")->assertStatus(401);
    }

    public function test_invoice_status_returns_the_intent_by_invoice_id(): void
    {
        $issuer = Issuer::factory()->create();
        $intent = $this->createIntent($issuer, 'INV-42');
        Sanctum::actingAs($issuer, ['*']);

        $response = $this->getJson('/api/v1/invoices/INV-42/status');

        $response->assertStatus(200);
        $response->assertJsonPath('public_id', $intent->public_id);
        $response->assertJsonMissingPath('id');
    }

    public function test_invoice_status_returns_404_for_unknown_invoice(): void
    {
        $issuer = Issuer::factory()->create();
        Sanctum::actingAs($issuer, ['*']);

        $this->getJson('/api/v1/invoices/does-not-exist/status')->assertStatus(404);
    }

    public function test_invoice_status_does_not_leak_another_issuers_invoice(): void
    {
        $owner = Issuer::factory()->create();
        $this->createIntent($owner, 'SHARED-INV');

        $other = Issuer::factory()->create();
        Sanctum::actingAs($other, ['*']);

        $this->getJson('/api/v1/invoices/SHARED-INV/status')->assertStatus(404);
    }
}
