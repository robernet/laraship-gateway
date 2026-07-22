<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Merchants\KeyRotator;
use Corals\Modules\Gateway\Core\References\QrPayload;
use Corals\Modules\Gateway\Core\References\QrVerifier;
use Corals\Modules\Gateway\Core\References\ReferenceGenerator;
use Corals\Modules\Gateway\Models\MerchantKey;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use InvalidArgumentException;

class KeyRotationTest extends GatewayTestCase
{
    public function test_rotate_activates_new_key_and_retires_the_old_one(): void
    {
        $intent = PaymentIntent::factory()->create();
        $oldKey = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);

        $newKey = (new KeyRotator())->rotate(
            $intent->merchant,
            'HS256',
            'kms://new-secret',
            now()->addDays(7)
        );

        $this->assertSame('active', $newKey->fresh()->state);
        $this->assertSame('retiring', $oldKey->fresh()->state);
        $this->assertSame($newKey->kid, $intent->merchant->fresh()->signing_key_current_kid);
    }

    public function test_instrument_signed_with_retiring_key_still_verifies_before_retire_after(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $oldKey = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);
        $reference = QrPayload::issue((new ReferenceGenerator())->generate($intent), $oldKey);

        (new KeyRotator())->rotate($intent->merchant, 'HS256', 'kms://new-secret', now()->addDays(7));

        $verified = (new QrVerifier())->verify($reference->qr_payload, 10000);

        $this->assertSame($reference->id, $verified->id);
    }

    public function test_instrument_signed_with_retiring_key_is_rejected_after_retire_after(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $oldKey = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);
        $reference = QrPayload::issue((new ReferenceGenerator())->generate($intent), $oldKey);

        (new KeyRotator())->rotate($intent->merchant, 'HS256', 'kms://new-secret', now()->subMinute());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Signing key has been retired.');

        (new QrVerifier())->verify($reference->qr_payload, 10000);
    }

    public function test_instrument_signed_with_new_key_verifies_immediately_after_rotation(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);

        $newKey = (new KeyRotator())->rotate($intent->merchant, 'HS256', 'kms://new-secret', now()->addDays(7));
        $reference = QrPayload::issue((new ReferenceGenerator())->generate($intent), $newKey);

        $verified = (new QrVerifier())->verify($reference->qr_payload, 10000);

        $this->assertSame($reference->id, $verified->id);
    }

    public function test_revoked_key_never_verifies(): void
    {
        $intent = PaymentIntent::factory()->create([
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ]);
        $key = MerchantKey::factory()->create(['merchant_id' => $intent->merchant_id]);
        $reference = QrPayload::issue((new ReferenceGenerator())->generate($intent), $key);

        $key->update(['state' => 'revoked']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown or inactive signing key.');

        (new QrVerifier())->verify($reference->qr_payload, 10000);
    }
}
