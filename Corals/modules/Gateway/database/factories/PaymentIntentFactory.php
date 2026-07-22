<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    public function definition(): array
    {
        return [
            'issuer_id' => Issuer::factory(),
            'merchant_id' => Merchant::factory(),
            'invoice_id' => $this->faker->unique()->numerify('INV-########'),
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
            'mapping_strategy' => 'stored',
            'state' => 'CREATED',
            'expires_at' => now()->addDay(),
        ];
    }
}
