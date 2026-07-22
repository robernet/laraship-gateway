<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'posting_id' => (string) Str::uuid(),
            'account_type' => 'pos_wallet',
            'account_ref' => (string) $this->faker->numberBetween(1, 1000),
            'direction' => 'debit',
            'amount_centavos' => $this->faker->numberBetween(100, 500000),
        ];
    }
}
