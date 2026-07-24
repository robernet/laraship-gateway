<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Exceptions\ReconciliationExceptionWorkflow;
use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use RuntimeException;

class ReconciliationExceptionWorkflowTest extends GatewayTestCase
{
    public function test_assign_sets_the_assignee_without_changing_state(): void
    {
        $exception = ReconciliationException::create(['type' => 'unmatched_confirm', 'refs' => []]);

        $result = (new ReconciliationExceptionWorkflow())->assign($exception->id, 'ops-alice');

        $this->assertSame('ops-alice', $result->assignee);
        $this->assertSame('open', $result->fresh()->state);
    }

    public function test_investigate_moves_an_open_case_to_investigating(): void
    {
        $exception = ReconciliationException::create(['type' => 'unmatched_confirm', 'refs' => []]);

        $result = (new ReconciliationExceptionWorkflow())->investigate($exception->id);

        $this->assertSame('investigating', $result->state);
    }

    public function test_investigate_rejects_a_case_that_is_not_open(): void
    {
        $exception = ReconciliationException::create([
            'type' => 'unmatched_confirm', 'refs' => [], 'state' => 'resolved',
        ]);

        $this->expectException(RuntimeException::class);

        (new ReconciliationExceptionWorkflow())->investigate($exception->id);
    }

    public function test_resolve_without_a_posting_to_reverse_just_closes_the_case(): void
    {
        $exception = ReconciliationException::create(['type' => 'orphan_topup', 'refs' => []]);

        $result = (new ReconciliationExceptionWorkflow())->resolve($exception->id, 'False positive, no money involved.');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['corrective_posting_id']);

        $exception->refresh();
        $this->assertSame('resolved', $exception->state);
        $this->assertSame('False positive, no money involved.', $exception->resolution);
    }

    public function test_resolve_with_a_posting_to_reverse_books_a_balanced_corrective_posting(): void
    {
        $issuer = Issuer::factory()->create();
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);

        $postingId = (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $exception = ReconciliationException::create([
            'type' => 'duplicate',
            'refs' => ['network_txn_id' => 'NTX-DUP'],
        ]);

        $result = (new ReconciliationExceptionWorkflow())->resolve(
            $exception->id,
            'Duplicate remittance row, reversing the erroneous posting.',
            $postingId
        );

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['corrective_posting_id']);
        $this->assertNotSame($postingId, $result['corrective_posting_id']);

        // The wallet debit from the original posting is fully credited back.
        $this->assertSame(10000, $wallet->fresh()->balance_centavos);

        // The whole ledger — both postings together — still balances.
        $legs = LedgerEntry::whereIn('posting_id', [$postingId, $result['corrective_posting_id']])->get();
        $debits = (int) $legs->where('direction', 'debit')->sum('amount_centavos');
        $credits = (int) $legs->where('direction', 'credit')->sum('amount_centavos');
        $this->assertSame($debits, $credits);

        $exception->refresh();
        $this->assertSame('resolved', $exception->state);
        $this->assertSame($result['corrective_posting_id'], $exception->refs['corrective_posting_id']);
    }

    public function test_resolving_an_already_resolved_case_throws_and_writes_nothing(): void
    {
        $exception = ReconciliationException::create([
            'type' => 'unmatched_confirm', 'refs' => [], 'state' => 'resolved', 'resolution' => 'done',
        ]);

        $this->expectException(RuntimeException::class);

        (new ReconciliationExceptionWorkflow())->resolve($exception->id, 'trying again');
    }
}
