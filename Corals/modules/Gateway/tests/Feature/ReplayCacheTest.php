<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\References\QrPayload;
use Corals\Modules\Gateway\Core\References\QrVerifier;
use Corals\Modules\Gateway\Core\References\ReferenceGenerator;
use Corals\Modules\Gateway\Models\MerchantKey;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use InvalidArgumentException;

class ReplayCacheTest extends GatewayTestCase
{
    public function test_second_sighting_of_the_same_nonce_is_rejected(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $key = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);
        $reference = QrPayload::issue((new ReferenceGenerator())->generate($intent), $key);

        $verifier = new QrVerifier();

        $first = $verifier->verify($reference->qr_payload, 10000);
        $this->assertSame($reference->id, $first->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('QR payload nonce has already been used.');

        $verifier->verify($reference->qr_payload, 10000);
    }

    public function test_two_distinct_references_do_not_collide_on_replay(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $key = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);

        $referenceA = QrPayload::issue((new ReferenceGenerator())->generate($intent), $key);
        $referenceB = QrPayload::issue((new ReferenceGenerator())->generate($intent), $key);

        $verifier = new QrVerifier();

        $this->assertSame($referenceA->id, $verifier->verify($referenceA->qr_payload, 10000)->id);
        $this->assertSame($referenceB->id, $verifier->verify($referenceB->qr_payload, 10000)->id);
    }
}
