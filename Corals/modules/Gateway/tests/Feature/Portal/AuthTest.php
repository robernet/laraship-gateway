<?php

namespace Tests\Feature\Portal;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class AuthTest extends GatewayTestCase
{
    public function test_issuer_can_log_in_with_valid_credentials(): void
    {
        $issuer = Issuer::factory()->withPassword('correct-password')->create();

        $response = $this->post('/portal/auth/login', [
            'email' => $issuer->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('gateway.portal.dashboard'));
        $this->assertAuthenticatedAs($issuer, 'issuer');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $issuer = Issuer::factory()->withPassword('correct-password')->create();

        $response = $this->post('/portal/auth/login', [
            'email' => $issuer->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('issuer');
    }

    public function test_authenticated_issuer_can_log_out(): void
    {
        $issuer = Issuer::factory()->withPassword('correct-password')->create();

        $response = $this->actingAs($issuer, 'issuer')->post('/portal/logout');

        $response->assertRedirect(route('gateway.portal.login'));
        $this->assertGuest('issuer');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/portal')->assertRedirect(route('gateway.portal.login'));
    }
}
