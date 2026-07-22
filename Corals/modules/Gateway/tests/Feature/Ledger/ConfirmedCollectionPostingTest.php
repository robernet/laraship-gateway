<?php

namespace Tests\Feature\Ledger;

use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\OutboxEvent;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use RuntimeException;

class ConfirmedCollectionPostingTest extends GatewayTestCase
{
    public function test_posting_is_balanced(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();

        $postingId = (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $legs = LedgerEntry::where('posting_id', $postingId)->get();

        $this->assertSame(10000, (int) $legs->where('direction', 'debit')->sum('amount_centavos'));
        $this->assertSame(10000, (int) $legs->where('direction', 'credit')->sum('amount_centavos'));
    }

    public function test_wallet_balance_stays_non_negative_and_correct(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $wallet->refresh();

        $this->assertSame(0, $wallet->balance_centavos);
        $this->assertGreaterThanOrEqual(0, $wallet->balance_centavos);
    }

    public function test_debit_exceeding_balance_writes_nothing(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 5000]);
        $issuer = Issuer::factory()->create();

        try {
            (new ConfirmedCollectionPosting())->apply(
                transactionId: 1,
                posWalletId: $wallet->id,
                issuerId: $issuer->id,
                amountCentavos: 10000,
                commissionCentavos: 150,
                feeCentavos: 50
            );
            $this->fail('Expected insufficient-funds decline was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('insufficient funds', $e->getMessage());
        }

        $wallet->refresh();

        $this->assertSame(5000, $wallet->balance_centavos);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_writes_a_payment_confirmed_outbox_event_atomically_with_the_posting(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();

        $postingId = (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $this->assertSame(1, OutboxEvent::count());

        $event = OutboxEvent::first();
        $this->assertSame('payment.confirmed', $event->event);
        $this->assertSame('pending', $event->status);
        $this->assertSame($postingId, $event->payload['posting_id']);
        $this->assertSame(1, $event->payload['transaction_id']);
        $this->assertSame(10000, $event->payload['amount_centavos']);
    }

    public function test_debit_exceeding_balance_writes_no_outbox_event(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 5000]);
        $issuer = Issuer::factory()->create();

        try {
            (new ConfirmedCollectionPosting())->apply(
                transactionId: 1,
                posWalletId: $wallet->id,
                issuerId: $issuer->id,
                amountCentavos: 10000,
                commissionCentavos: 150,
                feeCentavos: 50
            );
        } catch (RuntimeException $e) {
            // expected decline, asserted elsewhere
        }

        $this->assertSame(0, OutboxEvent::count());
    }

    public function test_replaying_the_same_transaction_does_not_double_post(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();

        $posting = new ConfirmedCollectionPosting();

        $first = $posting->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $second = $posting->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $this->assertSame($first, $second);
        $this->assertSame(
            1,
            LedgerEntry::where('transaction_id', 1)->where('account_type', 'pos_wallet')->count()
        );
        $this->assertSame(1, OutboxEvent::count());

        $wallet->refresh();
        $this->assertSame(0, $wallet->balance_centavos);
    }

    public function test_wallet_balance_matches_net_ledger_position(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 4000,
            commissionCentavos: 100,
            feeCentavos: 50
        );

        $wallet->refresh();

        $netLedgerPosition = LedgerEntry::where('account_type', 'pos_wallet')
            ->where('account_ref', (string) $wallet->id)
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'credit' ? $entry->amount_centavos : -$entry->amount_centavos);

        $this->assertSame($wallet->balance_centavos - 10000, (int) $netLedgerPosition);
    }

    /**
     * Simulates two racing debits against the same wallet contending for a
     * balance that can only cover one of them. Proves the conditional
     * UPDATE (`balance_centavos >= amount`) — not an app-level check-then-act
     * — is what decides the winner: exactly one succeeds, the other declines
     * cleanly, and the wallet never goes negative or double-books.
     */
    public function test_concurrent_debits_exceeding_balance_only_one_succeeds(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();

        $posting = new ConfirmedCollectionPosting();
        $succeeded = 0;
        $declined = 0;

        foreach ([1, 2] as $transactionId) {
            try {
                $posting->apply(
                    transactionId: $transactionId,
                    posWalletId: $wallet->id,
                    issuerId: $issuer->id,
                    amountCentavos: 6000,
                    commissionCentavos: 100,
                    feeCentavos: 50
                );
                $succeeded++;
            } catch (RuntimeException $e) {
                $declined++;
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(1, $declined);

        $wallet->refresh();

        $this->assertSame(4000, $wallet->balance_centavos);
        $this->assertGreaterThanOrEqual(0, $wallet->balance_centavos);
        $this->assertSame(1, LedgerEntry::where('account_type', 'pos_wallet')->count());
    }
}
