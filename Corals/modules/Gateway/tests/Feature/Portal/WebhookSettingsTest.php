<?php

namespace Tests\Feature\Portal;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\WebhookDelivery;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Illuminate\Support\Facades\Http;

class WebhookSettingsTest extends GatewayTestCase
{
    public function test_issuer_can_update_the_webhook_url(): void
    {
        $issuer = Issuer::factory()->create(['webhook_url' => 'https://old.example.com/hook']);

        $response = $this->actingAs($issuer, 'issuer')->put('/portal/webhook', [
            'webhook_url' => 'https://new.example.com/hook',
        ]);

        $response->assertRedirect(route('gateway.portal.webhook.edit'));
        $this->assertSame('https://new.example.com/hook', $issuer->fresh()->webhook_url);
    }

    public function test_issuer_can_regenerate_the_webhook_secret(): void
    {
        $issuer = Issuer::factory()->create();
        $originalSecret = $issuer->webhook_secret;

        $this->actingAs($issuer, 'issuer')->put('/portal/webhook', [
            'webhook_url' => $issuer->webhook_url,
            'regenerate_secret' => '1',
        ]);

        $this->assertNotSame($originalSecret, $issuer->fresh()->webhook_secret);
    }

    public function test_webhook_settings_require_authentication(): void
    {
        $this->get('/portal/webhook')->assertRedirect(route('gateway.portal.login'));
    }

    public function test_sending_a_test_webhook_delivers_a_signed_payment_confirmed_event(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $issuer = Issuer::factory()->create();

        $response = $this->actingAs($issuer, 'issuer')->post('/portal/webhook/test');

        $response->assertRedirect(route('gateway.portal.webhook.edit'));
        $response->assertSessionHas('testResult.status', 'delivered');

        Http::assertSent(function ($request) use ($issuer) {
            return $request->url() === $issuer->webhook_url
                && $request['event'] === 'payment.confirmed'
                && $request['issuer_id'] === $issuer->id
                && ! empty($request['signature']);
        });

        $this->assertSame(1, WebhookDelivery::where('issuer_id', $issuer->id)->count());
    }

    public function test_sending_a_test_webhook_without_a_configured_url_is_rejected(): void
    {
        $issuer = Issuer::factory()->create(['webhook_url' => null, 'webhook_secret' => null]);

        $response = $this->actingAs($issuer, 'issuer')->post('/portal/webhook/test');

        $response->assertRedirect(route('gateway.portal.webhook.edit'));
        $response->assertSessionHas('error');
        $this->assertSame(0, WebhookDelivery::count());
    }
}
