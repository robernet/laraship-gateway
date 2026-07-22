<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentReferenceFactory extends Factory
{
    protected $model = PaymentReference::class;

    public function definition(): array
    {
        $token = $this->faker->unique()->numerify(str_repeat('#', 10));

        return [
            'payment_intent_id' => PaymentIntent::factory(),
            'reference_token' => $token,
            'human_reference' => $token.'00',
            'barcode_payload' => null,
            'qr_payload' => null,
            'kid' => null,
            'nonce' => null,
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ];
    }
}
