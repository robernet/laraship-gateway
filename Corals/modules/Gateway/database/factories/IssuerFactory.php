<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\Issuer;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssuerFactory extends Factory
{
    protected $model = Issuer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'settlement_clabe' => $this->faker->numerify(str_repeat('#', 18)),
            'status' => 'active',
            'webhook_url' => $this->faker->url(),
            'webhook_secret' => bin2hex(random_bytes(16)),
            'finality_policy' => 'on_confirm',
        ];
    }
}
