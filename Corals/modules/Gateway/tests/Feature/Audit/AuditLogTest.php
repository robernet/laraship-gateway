<?php

namespace Tests\Feature\Audit;

use Corals\Modules\Gateway\Core\Audit\AuditLogger;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Illuminate\Support\Facades\DB;

class AuditLogTest extends GatewayTestCase
{
    public function test_first_row_has_no_prev_hash(): void
    {
        $logger = new AuditLogger();

        $row = $logger->record('ops:1', 'wallet.void', 'pos_wallet', '1', ['amount_centavos' => 10000]);

        $this->assertNull($row->prev_hash);
        $this->assertNotEmpty($row->row_hash);
    }

    public function test_chain_is_valid_when_untampered(): void
    {
        $logger = new AuditLogger();

        $logger->record('ops:1', 'wallet.void', 'pos_wallet', '1', ['amount_centavos' => 10000]);
        $logger->record('ops:2', 'settlement.payout', 'issuer', '2', ['amount_centavos' => 50000]);
        $logger->record('ops:1', 'exception.resolve', 'reconciliation_exceptions', '3', ['type' => 'orphan_topup']);

        $this->assertNull($logger->firstBrokenRowId());
    }

    public function test_mutating_a_historical_row_breaks_the_chain(): void
    {
        $logger = new AuditLogger();

        $logger->record('ops:1', 'wallet.void', 'pos_wallet', '1', ['amount_centavos' => 10000]);
        $second = $logger->record('ops:2', 'settlement.payout', 'issuer', '2', ['amount_centavos' => 50000]);
        $logger->record('ops:1', 'exception.resolve', 'reconciliation_exceptions', '3', ['type' => 'orphan_topup']);

        // Bypasses the AuditLog model's append-only guard on purpose — this
        // simulates an attacker mutating the row directly at the SQL layer,
        // which is exactly the tampering the hash chain must catch.
        DB::table('audit_log')
            ->where('id', $second->id)
            ->update(['payload' => json_encode(['amount_centavos' => 999999999])]);

        $this->assertSame($second->id, $logger->firstBrokenRowId());
    }
}
