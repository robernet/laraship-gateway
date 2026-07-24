<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Settlement\NetworkReconciliation;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class NetworkReconciliationTest extends GatewayTestCase
{
    public function test_matching_amount_within_tolerance_produces_no_exception(): void
    {
        Transaction::factory()->create([
            'network_id' => 'mock-realtime',
            'network_txn_id' => 'NTX-001',
            'amount_centavos' => 10000,
            'state' => 'CONFIRMED',
        ]);

        $result = (new NetworkReconciliation())->reconcile('mock-realtime', [
            ['network_txn_id' => 'NTX-001', 'amount_paid' => 10000],
        ]);

        $this->assertSame(1, $result['matched_count']);
        $this->assertSame(0, $result['exception_count']);
        $this->assertSame(0, ReconciliationException::count());
    }

    public function test_amount_outside_tolerance_opens_amount_mismatch_exception(): void
    {
        config(['gateway.reconciliation_tolerance_centavos' => 5]);

        Transaction::factory()->create([
            'network_id' => 'mock-realtime',
            'network_txn_id' => 'NTX-002',
            'amount_centavos' => 10000,
            'state' => 'CONFIRMED',
        ]);

        $result = (new NetworkReconciliation())->reconcile('mock-realtime', [
            ['network_txn_id' => 'NTX-002', 'amount_paid' => 10050],
        ]);

        $this->assertSame(0, $result['matched_count']);
        $this->assertSame(1, $result['exception_count']);

        $exception = ReconciliationException::first();
        $this->assertSame('amount_mismatch', $exception->type);
        $this->assertSame('NTX-002', $exception->refs['network_txn_id']);
    }

    public function test_amount_within_configured_tolerance_still_matches(): void
    {
        config(['gateway.reconciliation_tolerance_centavos' => 100]);

        Transaction::factory()->create([
            'network_id' => 'mock-realtime',
            'network_txn_id' => 'NTX-003',
            'amount_centavos' => 10000,
            'state' => 'CONFIRMED',
        ]);

        $result = (new NetworkReconciliation())->reconcile('mock-realtime', [
            ['network_txn_id' => 'NTX-003', 'amount_paid' => 10050],
        ]);

        $this->assertSame(1, $result['matched_count']);
        $this->assertSame(0, $result['exception_count']);
    }

    public function test_remittance_row_with_no_booked_confirm_opens_unmatched_confirm_exception(): void
    {
        $result = (new NetworkReconciliation())->reconcile('mock-realtime', [
            ['network_txn_id' => 'NTX-404', 'amount_paid' => 10000],
        ]);

        $this->assertSame(0, $result['matched_count']);
        $this->assertSame(1, $result['exception_count']);

        $exception = ReconciliationException::first();
        $this->assertSame('unmatched_confirm', $exception->type);
    }

    public function test_transaction_not_yet_confirmed_is_not_a_booked_confirm(): void
    {
        Transaction::factory()->create([
            'network_id' => 'mock-realtime',
            'network_txn_id' => 'NTX-005',
            'amount_centavos' => 10000,
            'state' => 'AUTHORIZED',
        ]);

        $result = (new NetworkReconciliation())->reconcile('mock-realtime', [
            ['network_txn_id' => 'NTX-005', 'amount_paid' => 10000],
        ]);

        $this->assertSame(0, $result['matched_count']);
        $this->assertSame('unmatched_confirm', ReconciliationException::first()->type);
    }

    public function test_duplicate_network_txn_id_within_the_same_report_opens_duplicate_exception(): void
    {
        Transaction::factory()->create([
            'network_id' => 'mock-realtime',
            'network_txn_id' => 'NTX-006',
            'amount_centavos' => 10000,
            'state' => 'CONFIRMED',
        ]);

        $result = (new NetworkReconciliation())->reconcile('mock-realtime', [
            ['network_txn_id' => 'NTX-006', 'amount_paid' => 10000],
            ['network_txn_id' => 'NTX-006', 'amount_paid' => 10000],
        ]);

        $this->assertSame(1, $result['matched_count']);
        $this->assertSame(1, $result['exception_count']);
        $this->assertSame('duplicate', ReconciliationException::first()->type);
    }

    public function test_a_batch_never_aborts_on_a_single_row_exception(): void
    {
        Transaction::factory()->create([
            'network_id' => 'mock-sftp',
            'network_txn_id' => 'NTX-OK',
            'amount_centavos' => 10000,
            'state' => 'CONFIRMED',
        ]);

        $result = (new NetworkReconciliation())->reconcile('mock-sftp', [
            ['network_txn_id' => 'NTX-OK', 'amount_paid' => 10000],
            ['network_txn_id' => 'NTX-MISSING', 'amount_paid' => 5000],
        ]);

        $this->assertSame(1, $result['matched_count']);
        $this->assertSame(1, $result['exception_count']);
    }
}
