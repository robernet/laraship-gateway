<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\References\BarcodePayload;
use Corals\Modules\Gateway\Core\References\Mod97Check;
use Corals\Modules\Gateway\Core\References\ReferenceGenerator;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class ReferenceGenerationTest extends GatewayTestCase
{
    public function test_stored_strategy_generates_a_valid_human_reference(): void
    {
        $intent = PaymentIntent::factory()->create(['mapping_strategy' => 'stored']);

        $reference = (new ReferenceGenerator())->generate($intent);

        $this->assertSame(10, strlen($reference->reference_token));
        $this->assertSame(12, strlen($reference->human_reference));
        $this->assertStringNotContainsString($intent->merchant->mid, $reference->human_reference);
        $this->assertTrue(Mod97Check::verify(
            $reference->reference_token,
            substr($reference->human_reference, 10)
        ));
        $this->assertSame(
            ['mid' => $intent->merchant->mid, 'token' => $reference->reference_token],
            BarcodePayload::decode($reference->barcode_payload)
        );
    }

    public function test_deterministic_strategy_generates_a_valid_human_reference(): void
    {
        $intent = PaymentIntent::factory()->create(['mapping_strategy' => 'deterministic']);

        $reference = (new ReferenceGenerator())->generate($intent);

        $this->assertSame(10, strlen($reference->reference_token));
        $this->assertTrue(Mod97Check::verify(
            $reference->reference_token,
            substr($reference->human_reference, 10)
        ));
    }

    public function test_deterministic_strategy_is_regenerable_from_the_same_inputs(): void
    {
        $intent = PaymentIntent::factory()->create(['mapping_strategy' => 'deterministic']);

        $first = (new ReferenceGenerator())->generate($intent);
        $token = $first->reference_token;
        $first->delete();

        $second = (new ReferenceGenerator())->generate($intent->fresh());

        $this->assertSame($token, $second->reference_token);
    }

    public function test_deterministic_tokens_are_not_sequential_across_sequential_invoices(): void
    {
        $issuer = PaymentIntent::factory()->create(['mapping_strategy' => 'deterministic'])->issuer;

        $tokens = [];

        for ($i = 1; $i <= 5; $i++) {
            $intent = PaymentIntent::factory()->create([
                'issuer_id' => $issuer->id,
                'invoice_id' => "INV-SEQ-{$i}",
                'mapping_strategy' => 'deterministic',
            ]);

            $tokens[] = (int) (new ReferenceGenerator())->generate($intent)->reference_token;
        }

        sort($tokens);
        $gaps = [];

        for ($i = 1; $i < count($tokens); $i++) {
            $gaps[] = $tokens[$i] - $tokens[$i - 1];
        }

        $this->assertNotEquals(array_fill(0, count($gaps), $gaps[0] ?? 0), $gaps);
    }

    public function test_stored_tokens_do_not_collide(): void
    {
        $intentA = PaymentIntent::factory()->create(['mapping_strategy' => 'stored']);
        $intentB = PaymentIntent::factory()->create(['mapping_strategy' => 'stored']);

        $refA = (new ReferenceGenerator())->generate($intentA);
        $refB = (new ReferenceGenerator())->generate($intentB);

        $this->assertNotSame($refA->reference_token, $refB->reference_token);
        $this->assertSame(2, PaymentReference::count());
    }
}
