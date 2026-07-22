<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\References\QrPayload;
use Corals\Modules\Gateway\Core\References\QrVerifier;
use Corals\Modules\Gateway\Core\References\ReferenceGenerator;
use Corals\Modules\Gateway\Models\MerchantKey;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use InvalidArgumentException;

class QrSigningTest extends GatewayTestCase
{
    private function issueSignedReference(array $amountPolicy)
    {
        $intent = PaymentIntent::factory()->create(['amount_policy' => $amountPolicy]);
        $reference = (new ReferenceGenerator())->generate($intent);
        $key = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);

        return QrPayload::issue($reference, $key);
    }

    public function test_valid_signed_qr_verifies_against_matching_db_amount(): void
    {
        $reference = $this->issueSignedReference(['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false]);

        $verified = (new QrVerifier())->verify($reference->qr_payload, 10000);

        $this->assertSame($reference->id, $verified->id);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $reference = $this->issueSignedReference(['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false]);
        $tampered = str_replace('"amount":10000', '"amount":1', $reference->qr_payload);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid QR signature.');

        (new QrVerifier())->verify($tampered, 1);
    }

    public function test_expired_payload_is_rejected(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
            'expires_at' => now()->subMinute(),
        ]);
        $reference = (new ReferenceGenerator())->generate($intent);
        $key = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);
        $reference = QrPayload::issue($reference, $key);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('QR payload has expired.');

        (new QrVerifier())->verify($reference->qr_payload, 10000);
    }

    public function test_amount_disagreeing_with_current_db_is_rejected_even_with_a_valid_signature(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $reference = (new ReferenceGenerator())->generate($intent);
        $key = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);
        $reference = QrPayload::issue($reference, $key);

        // Drift: the intent's amount changes in the DB after the QR was
        // signed. The QR itself is untouched (still validly signed).
        $intent->update(['amount_policy' => ['type' => 'fixed', 'amount' => 20000, 'allow_partial' => false]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attempted amount does not match the intent amount on record.');

        // Attempt to collect the QR's original (now stale) amount.
        (new QrVerifier())->verify($reference->fresh()->qr_payload, 10000);
    }

    public function test_amount_matching_current_db_is_accepted_even_though_payload_amt_is_stale(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $reference = (new ReferenceGenerator())->generate($intent);
        $key = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);
        $reference = QrPayload::issue($reference, $key);

        $intent->update(['amount_policy' => ['type' => 'fixed', 'amount' => 20000, 'allow_partial' => false]]);

        $verified = (new QrVerifier())->verify($reference->fresh()->qr_payload, 20000);

        $this->assertSame($reference->id, $verified->id);
    }

    public function test_variable_amount_outside_db_range_is_rejected(): void
    {
        $reference = $this->issueSignedReference([
            'type' => 'variable', 'min' => 5000, 'max' => 15000, 'allow_partial' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attempted amount is outside the intent amount range on record.');

        (new QrVerifier())->verify($reference->qr_payload, 20000);
    }

    public function test_variable_amount_within_db_range_is_accepted(): void
    {
        $reference = $this->issueSignedReference([
            'type' => 'variable', 'min' => 5000, 'max' => 15000, 'allow_partial' => true,
        ]);

        $verified = (new QrVerifier())->verify($reference->qr_payload, 12000);

        $this->assertSame($reference->id, $verified->id);
    }

    public function test_unknown_signing_key_is_rejected(): void
    {
        $reference = $this->issueSignedReference(['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false]);
        $tampered = str_replace($reference->kid, 'not-a-real-kid', $reference->qr_payload);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown or inactive signing key.');

        (new QrVerifier())->verify($tampered, 10000);
    }
}
