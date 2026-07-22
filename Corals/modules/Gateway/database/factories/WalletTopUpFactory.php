<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTopUpFactory extends Factory
{
    protected $model = WalletTopUp::class;

    public function definition(): array
    {
        return [
            'pos_wallet_id' => PosWallet::factory(),
            'amount_centavos' => $this->faker->numberBetween(10000, 5000000),
            'spei_ref' => $this->faker->unique()->numerify('SPEI##########'),
            'clabe_origin' => $this->faker->numerify(str_repeat('#', 18)),
            'status' => 'pending',
            'applied_at' => null,
        ];
    }
}
