<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class ReleaseExpiredReservationsTest extends GatewayTestCase
{
    public function test_a_stale_authorized_transaction_releases_its_reservation(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 50000, 'reserved_centavos' => 10000]);
        $transaction = Transaction::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => 10000,
            'state' => 'AUTHORIZED',
        ]);
        // created_at isn't mass-assignable; backdate it directly so the
        // sweep sees a stale reservation.
        $transaction->forceFill(['created_at' => now()->subSeconds(config('gateway.reservation_ttl_seconds') + 1)])->save();

        $this->artisan('gateway:release-expired-reservations')->assertSuccessful();

        $this->assertSame(0, $wallet->fresh()->reserved_centavos);
        $this->assertNull(Transaction::find($transaction->id));
    }

    public function test_a_fresh_authorized_transaction_is_left_alone(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 50000, 'reserved_centavos' => 10000]);
        $transaction = Transaction::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => 10000,
            'state' => 'AUTHORIZED',
        ]);

        $this->artisan('gateway:release-expired-reservations')->assertSuccessful();

        $this->assertSame(10000, $wallet->fresh()->reserved_centavos);
        $this->assertNotNull(Transaction::find($transaction->id));
    }
}
