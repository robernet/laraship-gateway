<?php

namespace Tests\Feature\Portal;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class SimulatorPortalTest extends GatewayTestCase
{
    public function test_fixed_policy_can_be_validated_then_confirmed_end_to_end(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);

        $this->actingAs($issuer, 'issuer')->post('/portal/simulator/intents', [
            'invoice_id' => 'SIM-FIXED-1',
            'mid' => $merchant->mid,
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 5000],
        ]);

        $intent = PaymentIntent::where('invoice_id', 'SIM-FIXED-1')->firstOrFail();

        $validate = $this->actingAs($issuer, 'issuer')->post('/portal/simulator/validate', [
            'intent_id' => $intent->public_id,
            'amount_attempt' => 5000,
        ]);
        $validate->assertSessionHas('simResult.response.ok', true);

        $confirm = $this->actingAs($issuer, 'issuer')->post('/portal/simulator/confirm', [
            'intent_id' => $intent->public_id,
            'amount_paid' => 5000,
        ]);
        $confirm->assertSessionHas('simResult.response.ok', true);
    }

    public function test_variable_policy_allows_a_partial_confirm(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);

        $this->actingAs($issuer, 'issuer')->post('/portal/simulator/intents', [
            'invoice_id' => 'SIM-VAR-1',
            'mid' => $merchant->mid,
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'variable', 'min' => 1000, 'max' => 10000, 'allow_partial' => 1],
        ]);

        $intent = PaymentIntent::where('invoice_id', 'SIM-VAR-1')->firstOrFail();

        $confirm = $this->actingAs($issuer, 'issuer')->post('/portal/simulator/confirm', [
            'intent_id' => $intent->public_id,
            'amount_paid' => 3000,
            'is_partial' => 1,
        ]);

        $confirm->assertSessionHas('simResult.response.ok', true);
    }

    public function test_cannot_simulate_against_another_issuers_intent(): void
    {
        $owner = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $owner->id]);

        $this->actingAs($owner, 'issuer')->post('/portal/simulator/intents', [
            'invoice_id' => 'SIM-OTHER-1',
            'mid' => $merchant->mid,
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 5000],
        ]);
        $intent = PaymentIntent::where('invoice_id', 'SIM-OTHER-1')->firstOrFail();

        $other = Issuer::factory()->create();

        $response = $this->actingAs($other, 'issuer')->post('/portal/simulator/validate', [
            'intent_id' => $intent->public_id,
            'amount_attempt' => 5000,
        ]);

        $response->assertSessionHas('simError');
        $response->assertSessionMissing('simResult');
    }

    public function test_simulator_requires_authentication(): void
    {
        $this->get('/portal/simulator')->assertRedirect(route('gateway.portal.login'));
    }
}
