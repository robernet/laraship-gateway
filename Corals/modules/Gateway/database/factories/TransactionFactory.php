<?php

namespace Corals\Modules\Gateway\database\factories;

use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'payment_reference_id' => PaymentReference::factory(),
            'pos_wallet_id' => PosWallet::factory(),
            'network_id' => 'mock-realtime',
            'amount_centavos' => 10000,
            'state' => 'AUTHORIZED',
        ];
    }
}
