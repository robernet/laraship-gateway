<?php

namespace Tests\Feature\Portal;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class ApiKeyPortalTest extends GatewayTestCase
{
    public function test_issuer_can_issue_a_new_token(): void
    {
        $issuer = Issuer::factory()->create();

        $response = $this->actingAs($issuer, 'issuer')->post('/portal/api-keys', [
            'ttl_minutes' => 60,
        ]);

        $response->assertRedirect(route('gateway.portal.api-keys.index'));
        $response->assertSessionHas('plainTextToken');
        $this->assertSame(1, $issuer->tokens()->count());
    }

    public function test_issuer_can_revoke_their_own_token(): void
    {
        $issuer = Issuer::factory()->create();
        $token = $issuer->createToken('test-token');

        $response = $this->actingAs($issuer, 'issuer')
            ->delete("/portal/api-keys/{$token->accessToken->id}");

        $response->assertRedirect(route('gateway.portal.api-keys.index'));
        $this->assertSame(0, $issuer->tokens()->count());
    }

    public function test_issuer_cannot_revoke_another_issuers_token(): void
    {
        $owner = Issuer::factory()->create();
        $token = $owner->createToken('test-token');

        $other = Issuer::factory()->create();

        $this->actingAs($other, 'issuer')->delete("/portal/api-keys/{$token->accessToken->id}");

        $this->assertSame(1, $owner->tokens()->count());
    }

    public function test_api_keys_page_requires_authentication(): void
    {
        $this->get('/portal/api-keys')->assertRedirect(route('gateway.portal.login'));
    }
}
