<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Illuminate\Database\QueryException;

class PaymentIntentsSchemaTest extends GatewayTestCase
{
    public function test_migrations_run_and_rows_can_be_created(): void
    {
        $intent = PaymentIntent::factory()->create();
        $reference = PaymentReference::factory()->create(['payment_intent_id' => $intent->id]);

        $this->assertNotNull($intent->public_id);
        $this->assertSame($intent->id, $reference->paymentIntent->id);
        $this->assertSame(['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false], $intent->amount_policy);
    }

    public function test_duplicate_invoice_id_per_issuer_is_rejected(): void
    {
        $intent = PaymentIntent::factory()->create();

        $this->expectException(QueryException::class);

        PaymentIntent::factory()->create([
            'issuer_id' => $intent->issuer_id,
            'invoice_id' => $intent->invoice_id,
        ]);
    }

    public function test_one_time_ref_single_success_constraint(): void
    {
        $intent = PaymentIntent::factory()->create(['mode' => 'one_time']);

        $first = PaymentReference::factory()->create(['payment_intent_id' => $intent->id]);
        $second = PaymentReference::factory()->create(['payment_intent_id' => $intent->id]);

        $first->update(['status' => 'consumed', 'consumed_at' => now()]);

        $this->expectException(QueryException::class);

        $second->update(['status' => 'consumed', 'consumed_at' => now()]);
    }
}
