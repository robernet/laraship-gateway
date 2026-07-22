<?php

namespace Tests\Unit;

use Corals\Modules\Gateway\Core\References\BarcodePayload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BarcodePayloadTest extends TestCase
{
    public function test_encode_produces_21_numeric_digits(): void
    {
        $payload = BarcodePayload::encode('000123456', '0123456789');

        $this->assertSame(21, strlen($payload));
        $this->assertTrue(ctype_digit($payload));
        $this->assertStringStartsWith('0001234560123456789', $payload);
    }

    public function test_payload_round_trips(): void
    {
        $payload = BarcodePayload::encode('999999999', '9999999999');

        $this->assertSame(
            ['mid' => '999999999', 'token' => '9999999999'],
            BarcodePayload::decode($payload)
        );
    }

    public function test_tampered_checksum_is_rejected(): void
    {
        $payload = BarcodePayload::encode('000123456', '0123456789');
        $tampered = substr($payload, 0, -1).((int) $payload[-1] + 1) % 10;

        $this->expectException(InvalidArgumentException::class);

        BarcodePayload::decode($tampered);
    }

    public function test_tampered_body_is_rejected(): void
    {
        $payload = BarcodePayload::encode('000123456', '0123456789');
        $tampered = '1'.substr($payload, 1);

        $this->expectException(InvalidArgumentException::class);

        BarcodePayload::decode($tampered);
    }

    public function test_wrong_length_mid_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BarcodePayload::encode('12345', '0123456789');
    }
}
