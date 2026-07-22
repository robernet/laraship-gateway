<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\PosWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosWalletFactory extends Factory
{
    protected $model = PosWallet::class;

    public function definition(): array
    {
        return [
            'network_id' => 'mock-realtime',
            'external_store_id' => 'S-'.$this->faker->unique()->numerify('###'),
            'balance_centavos' => 0,
            'reserved_centavos' => 0,
            'currency' => 'MXN',
            'status' => 'active',
        ];
    }
}
