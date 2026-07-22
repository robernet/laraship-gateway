<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Core\Ledger\Postings\TopupAppliedPosting;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Illuminate\Support\Facades\DB;

class DailyCloseIntegrityCheckTest extends GatewayTestCase
{
    /**
     * Funds the wallet through an actual topup_applied posting (not a raw
     * factory balance override) so every centavo of its balance is
     * explained by a ledger entry — genuinely clean data, not just an
     * unledgered starting balance.
     */
    private function fundWallet(PosWallet $wallet, int $amountCentavos): void
    {
        $topUp = WalletTopUp::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => $amountCentavos,
            'status' => 'pending',
        ]);

        (new TopupAppliedPosting())->apply($topUp);
    }

    public function test_passes_on_seeded_clean_data(): void
    {
        $wallet = PosWallet::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->fundWallet($wallet, 10000);

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 4000,
            commissionCentavos: 100,
            feeCentavos: 50
        );

        $this->artisan('gateway:daily-close')->assertExitCode(0);

        $this->assertSame(0, ReconciliationException::count());
    }

    public function test_fails_loudly_on_injected_drift(): void
    {
        $wallet = PosWallet::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->fundWallet($wallet, 10000);

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 4000,
            commissionCentavos: 100,
            feeCentavos: 50
        );

        // Injected drift: mutate the wallet balance directly, bypassing the
        // ledger entirely, so it no longer agrees with its net ledger
        // position.
        DB::table('pos_wallets')->where('id', $wallet->id)->update(['balance_centavos' => 999999]);

        $this->artisan('gateway:daily-close')->assertExitCode(1);

        $exception = ReconciliationException::sole();

        $this->assertSame('negative_drift', $exception->type);
        $this->assertSame($wallet->id, $exception->refs['pos_wallet_id']);
        $this->assertSame(6000, $exception->refs['ledger_position']);
        $this->assertSame(999999, $exception->refs['wallet_balance']);
    }
}
