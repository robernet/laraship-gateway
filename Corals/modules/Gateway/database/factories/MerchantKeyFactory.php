<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\MerchantKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantKeyFactory extends Factory
{
    protected $model = MerchantKey::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'kid' => $this->faker->unique()->uuid(),
            'alg' => 'HS256',
            'secret_ref' => 'kms://'.$this->faker->uuid(),
            'state' => 'active',
            'activated_at' => now(),
            'retire_after' => null,
        ];
    }
}
