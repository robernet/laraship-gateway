<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GW-501: an inbound SPEI notification that can't be matched to a pos_wallet
 * still creates a wallet_top_ups(status=pending) row (docs/settlement-
 * reconciliation.md "Top-up ingestion & matching") — pos_wallet_id must be
 * nullable to represent "not yet attributed" rather than skipping the row.
 */
class WalletTopUpsPosWalletNullableColumn extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE wallet_top_ups ALTER COLUMN pos_wallet_id DROP NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE wallet_top_ups ALTER COLUMN pos_wallet_id SET NOT NULL');
    }
}
