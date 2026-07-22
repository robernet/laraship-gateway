<?php

namespace Tests\Feature\Portal;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\tests\GatewayTestCase;

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
}
