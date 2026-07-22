<?php

namespace Tests\Feature\Portal;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class PaymentIntentPortalTest extends GatewayTestCase
{
    public function test_authenticated_issuer_can_create_a_payment_intent(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);

        $response = $this->actingAs($issuer, 'issuer')->postJson('/portal/payment-intents', [
            'invoice_id' => 'INV-PORTAL-1',
            'mid' => $merchant->mid,
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 5000, 'allow_partial' => false],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('invoice_id', 'INV-PORTAL-1');
        $this->assertSame(1, PaymentIntent::count());
    }

    public function test_list_is_scoped_to_the_authenticated_issuer(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Issuer::factory()->create();
        PaymentIntent::factory()->create(['issuer_id' => $issuer->id]);
        PaymentIntent::factory()->create(['issuer_id' => $merchant->id]);

        $response = $this->actingAs($issuer, 'issuer')->getJson('/portal/payment-intents');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_invoice_status_is_scoped_to_the_authenticated_issuer(): void
    {
        $owner = Issuer::factory()->create();
        $intent = PaymentIntent::factory()->create(['issuer_id' => $owner->id, 'invoice_id' => 'SHARED-1']);

        $other = Issuer::factory()->create();

        $this->actingAs($owner, 'issuer')
            ->getJson('/portal/invoices/SHARED-1/status')
            ->assertStatus(200)
            ->assertJsonPath('invoice_id', 'SHARED-1');

        $this->actingAs($other, 'issuer')
            ->getJson('/portal/invoices/SHARED-1/status')
            ->assertStatus(404);
    }

    public function test_dashboard_and_list_require_authentication(): void
    {
        $this->get('/portal')->assertRedirect(route('gateway.portal.login'));
        $this->getJson('/portal/payment-intents')->assertStatus(401);
    }
}
