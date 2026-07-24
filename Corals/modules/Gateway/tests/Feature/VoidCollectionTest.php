<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Corals\Modules\Gateway\Core\Audit\AuditLogger;
use Corals\Modules\Gateway\Core\Collections\VoidCollection;
use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Models\AuditLog;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use RuntimeException;

class VoidCollectionTest extends GatewayTestCase
{
    private function confirmedTransaction(int $amountCentavos = 10000, ?Carbon $confirmedAt = null): array
    {
        $issuer = Issuer::factory()->create();
        $wallet = PosWallet::factory()->create(['balance_centavos' => $amountCentavos]);

        $transaction = Transaction::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => $amountCentavos,
            'state' => 'CONFIRMED',
            'confirmed_at' => $confirmedAt ?? now(),
        ]);

        (new ConfirmedCollectionPosting())->apply(
            transactionId: $transaction->id,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: $amountCentavos,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        return [$transaction, $wallet];
    }

    public function test_a_below_threshold_void_is_finalized_by_a_single_actor(): void
    {
        config(['gateway.void_dual_control_threshold_centavos' => 500000]);
        [$transaction, $wallet] = $this->confirmedTransaction(10000);

        $voidRequest = (new VoidCollection())->request($transaction->id, 'agent-1', 'customer refund');

        $this->assertSame('voided', $voidRequest->status);
        $this->assertSame('agent-1', $voidRequest->approved_by);
        $this->assertNotNull($voidRequest->voided_posting_id);
        $this->assertSame('VOIDED', $transaction->fresh()->state);
        $this->assertSame(10000, $wallet->fresh()->balance_centavos);
    }

    public function test_an_at_or_above_threshold_void_stays_pending_and_moves_no_money(): void
    {
        config(['gateway.void_dual_control_threshold_centavos' => 5000]);
        [$transaction, $wallet] = $this->confirmedTransaction(10000);

        $voidRequest = (new VoidCollection())->request($transaction->id, 'agent-1', 'large refund');

        $this->assertSame('pending', $voidRequest->status);
        $this->assertNull($voidRequest->voided_posting_id);
        $this->assertSame('CONFIRMED', $transaction->fresh()->state);
        $this->assertSame(0, $wallet->fresh()->balance_centavos);
    }

    public function test_the_requester_cannot_approve_their_own_above_threshold_void(): void
    {
        config(['gateway.void_dual_control_threshold_centavos' => 5000]);
        [$transaction] = $this->confirmedTransaction(10000);

        $voidRequest = (new VoidCollection())->request($transaction->id, 'agent-1', 'large refund');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('second, distinct approver');

        (new VoidCollection())->approve($voidRequest->id, 'agent-1');
    }

    public function test_a_distinct_approver_completes_an_above_threshold_void_with_a_balanced_posting(): void
    {
        config(['gateway.void_dual_control_threshold_centavos' => 5000]);
        [$transaction, $wallet] = $this->confirmedTransaction(10000);

        $voidRequest = (new VoidCollection())->request($transaction->id, 'agent-1', 'large refund');

        $approved = (new VoidCollection())->approve($voidRequest->id, 'agent-2');

        $this->assertSame('voided', $approved->status);
        $this->assertSame('agent-2', $approved->approved_by);
        $this->assertSame('VOIDED', $transaction->fresh()->state);
        $this->assertSame(10000, $wallet->fresh()->balance_centavos);

        $legs = LedgerEntry::whereIn('posting_id', [$voidRequest->posting_id, $approved->voided_posting_id])->get();
        $debits = (int) $legs->where('direction', 'debit')->sum('amount_centavos');
        $credits = (int) $legs->where('direction', 'credit')->sum('amount_centavos');
        $this->assertSame($debits, $credits);
    }

    public function test_a_void_outside_its_window_is_rejected(): void
    {
        config(['gateway.void_window_seconds' => 60]);
        [$transaction] = $this->confirmedTransaction(10000, now()->subMinutes(10));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside its void window');

        (new VoidCollection())->request($transaction->id, 'agent-1');
    }

    public function test_a_non_confirmed_transaction_cannot_be_voided_here(): void
    {
        [$transaction] = $this->confirmedTransaction(10000);
        $transaction->update(['state' => 'FINALIZED']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not voidable');

        (new VoidCollection())->request($transaction->id, 'agent-1');
    }

    public function test_void_request_and_completion_are_both_audited_and_the_chain_stays_intact(): void
    {
        config(['gateway.void_dual_control_threshold_centavos' => 500000]);
        [$transaction] = $this->confirmedTransaction(10000);

        (new VoidCollection())->request($transaction->id, 'agent-1');

        $this->assertNull((new AuditLogger())->firstBrokenRowId());

        $actions = AuditLog::pluck('action')->all();
        $this->assertContains('void.requested', $actions);
        $this->assertContains('void.completed', $actions);
    }
}
