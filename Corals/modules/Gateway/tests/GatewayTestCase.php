<?php

namespace Corals\Modules\Gateway\tests;

use Corals\Modules\Gateway\database\migrations\AuditLogTables;
use Corals\Modules\Gateway\database\migrations\IdempotencyKeysTable;
use Corals\Modules\Gateway\database\migrations\IssuerPortalLoginColumns;
use Corals\Modules\Gateway\database\migrations\IssuerReferenceSecretColumn;
use Corals\Modules\Gateway\database\migrations\IssuersMerchantsTables;
use Corals\Modules\Gateway\database\migrations\LedgerEntriesTables;
use Corals\Modules\Gateway\database\migrations\NetworkAdaptersTable;
use Corals\Modules\Gateway\database\migrations\NetworkCredentialsTable;
use Corals\Modules\Gateway\database\migrations\OutboxEventsTable;
use Corals\Modules\Gateway\database\migrations\PaymentIntentSandboxColumn;
use Corals\Modules\Gateway\database\migrations\PaymentIntentsTables;
use Corals\Modules\Gateway\database\migrations\PosWalletsTables;
use Corals\Modules\Gateway\database\migrations\ReconciliationExceptionsTables;
use Corals\Modules\Gateway\database\migrations\SettlementsTable;
use Corals\Modules\Gateway\database\migrations\TerminalCredentialColumn;
use Corals\Modules\Gateway\database\migrations\TransactionsTable;
use Corals\Modules\Gateway\database\migrations\WalletTopUpsPosWalletNullableColumn;
use Corals\Modules\Gateway\database\migrations\WebhookDeliveriesTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Base test case for Gateway feature/unit tests. Gateway's tables are created
 * via the module-install lifecycle (InstallModuleServiceProvider::createSchema),
 * not Laravel's native migrate — so RefreshDatabase's migrate:fresh doesn't know
 * about them and would drop them without recreating them. Ensure schema exists
 * here (idempotent, run outside any transaction so Postgres DDL isn't rolled
 * back), then wrap each test in its own transaction for isolation.
 */
abstract class GatewayTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('issuers')) {
            (new IssuersMerchantsTables)->up();
        }

        if (! Schema::hasColumn('issuers', 'reference_secret')) {
            (new IssuerReferenceSecretColumn)->up();
        }

        if (! Schema::hasColumn('issuers', 'password')) {
            (new IssuerPortalLoginColumns)->up();
        }

        if (! Schema::hasTable('pos_wallets')) {
            (new PosWalletsTables)->up();
        }

        $posWalletIdNullable = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns WHERE table_name = 'wallet_top_ups' AND column_name = 'pos_wallet_id'"
        );

        if ($posWalletIdNullable && $posWalletIdNullable->is_nullable === 'NO') {
            (new WalletTopUpsPosWalletNullableColumn)->up();
        }

        if (! Schema::hasTable('ledger_entries')) {
            (new LedgerEntriesTables)->up();
        }

        if (! Schema::hasTable('audit_log')) {
            (new AuditLogTables)->up();
        }

        if (! Schema::hasTable('reconciliation_exceptions')) {
            (new ReconciliationExceptionsTables)->up();
        }

        if (! Schema::hasTable('payment_intents')) {
            (new PaymentIntentsTables)->up();
        }

        if (! Schema::hasColumn('payment_intents', 'sandbox')) {
            (new PaymentIntentSandboxColumn)->up();
        }

        if (! Schema::hasTable('idempotency_keys')) {
            (new IdempotencyKeysTable)->up();
        }

        if (! Schema::hasTable('outbox_events')) {
            (new OutboxEventsTable)->up();
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            (new WebhookDeliveriesTable)->up();
        }

        if (! Schema::hasTable('network_credentials')) {
            (new NetworkCredentialsTable)->up();
        }

        if (! Schema::hasColumn('network_credentials', 'terminal_id')) {
            (new TerminalCredentialColumn)->up();
        }

        if (! Schema::hasTable('network_adapters')) {
            (new NetworkAdaptersTable)->up();
        }

        if (! Schema::hasTable('transactions')) {
            (new TransactionsTable)->up();
        }

        if (! Schema::hasTable('settlements')) {
            (new SettlementsTable)->up();
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }
}
