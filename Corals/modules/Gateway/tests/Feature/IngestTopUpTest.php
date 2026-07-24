<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Wallets\IngestTopUp;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class IngestTopUpTest extends GatewayTestCase
{
    public function test_matched_reference_creates_pending_top_up_and_applies_it(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 0]);

        $result = (new IngestTopUp)->handle([
            'spei_ref' => $wallet->public_id,
            'clabe_origin' => '646180157000000004',
            'amount_centavos' => 15000,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['matched']);
        $this->assertNotNull($result['posting_id']);

        $topUp = WalletTopUp::find($result['top_up_id']);
        $this->assertSame($wallet->id, $topUp->pos_wallet_id);
        $this->assertSame('applied', $topUp->status);

        $wallet->refresh();
        $this->assertSame(15000, $wallet->balance_centavos);
        $this->assertSame(0, ReconciliationException::count());
    }

    public function test_unmatched_deposit_opens_orphan_topup_and_never_auto_credits(): void
    {
        $result = (new IngestTopUp)->handle([
            'spei_ref' => 'not-a-known-wallet',
            'clabe_origin' => '646180157000000099',
            'amount_centavos' => 20000,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['matched']);
        $this->assertNull($result['posting_id']);

        $topUp = WalletTopUp::find($result['top_up_id']);
        $this->assertNull($topUp->pos_wallet_id);
        $this->assertSame('pending', $topUp->status);

        $this->assertSame(1, ReconciliationException::where('type', 'orphan_topup')->count());
        $exception = ReconciliationException::where('type', 'orphan_topup')->first();
        $this->assertSame($topUp->id, $exception->refs['top_up_id']);

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_matches_by_clabe_origin_when_reference_is_unknown_but_clabe_has_funded_before(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 0]);
        $clabe = '646180157000000004';

        (new IngestTopUp)->handle([
            'spei_ref' => $wallet->public_id,
            'clabe_origin' => $clabe,
            'amount_centavos' => 10000,
        ]);

        $result = (new IngestTopUp)->handle([
            'spei_ref' => null,
            'clabe_origin' => $clabe,
            'amount_centavos' => 5000,
        ]);

        $this->assertTrue($result['matched']);

        $topUp = WalletTopUp::find($result['top_up_id']);
        $this->assertSame($wallet->id, $topUp->pos_wallet_id);
        $this->assertSame('applied', $topUp->status);

        $wallet->refresh();
        $this->assertSame(15000, $wallet->balance_centavos);
    }

    public function test_unknown_reference_and_never_before_seen_clabe_is_orphaned(): void
    {
        $result = (new IngestTopUp)->handle([
            'spei_ref' => null,
            'clabe_origin' => '646180157000000123',
            'amount_centavos' => 3000,
        ]);

        $this->assertFalse($result['matched']);
        $this->assertSame(1, ReconciliationException::where('type', 'orphan_topup')->count());
    }
}
