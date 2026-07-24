<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\NetworkAdapter;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkAdapterFactory extends Factory
{
    protected $model = NetworkAdapter::class;

    public function definition(): array
    {
        return [
            'network_id' => 'mock-realtime',
            'archetype' => 'realtime',
            'config' => [],
            'contract_version' => 1,
            'enabled' => true,
        ];
    }
}
