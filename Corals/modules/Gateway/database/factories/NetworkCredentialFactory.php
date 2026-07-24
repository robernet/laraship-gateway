<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\NetworkCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkCredentialFactory extends Factory
{
    protected $model = NetworkCredential::class;

    public function definition(): array
    {
        return [
            'network_id' => 'mock-realtime-'.$this->faker->unique()->numerify('###'),
            'name' => $this->faker->company(),
            'status' => 'active',
        ];
    }
}
