<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        return [
            'mid' => $this->faker->unique()->numerify(str_repeat('#', 9)),
            'issuer_id' => Issuer::factory(),
            'status' => 'active',
        ];
    }
}
