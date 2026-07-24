<?php

namespace Tests\Feature\Portal;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class DocsTest extends GatewayTestCase
{
    public function test_authenticated_issuer_can_view_the_docs_page(): void
    {
        $issuer = Issuer::factory()->create();

        $response = $this->actingAs($issuer, 'issuer')->get('/portal/docs');

        $response->assertStatus(200);
    }

    public function test_authenticated_issuer_can_fetch_the_openapi_spec(): void
    {
        $issuer = Issuer::factory()->create();

        $response = $this->actingAs($issuer, 'issuer')->get('/portal/docs/openapi.yaml');

        $response->assertStatus(200);
        $response->assertSee('PaymentIntent', false);
    }

    public function test_docs_require_authentication(): void
    {
        $this->get('/portal/docs')->assertRedirect(route('gateway.portal.login'));
    }
}
