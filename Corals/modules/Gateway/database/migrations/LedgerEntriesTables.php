<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LedgerEntriesTables extends Migration
{
    public function up()
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('posting_id')->index();
            $table->string('account_type');
            $table->string('account_ref');
            $table->string('direction');
            $table->bigInteger('amount_centavos');
            // transactions/settlements tables don't exist yet (later tickets) — plain
            // nullable refs for now, FK constraints added once those tables land.
            $table->foreignId('transaction_id')->nullable();
            $table->foreignId('top_up_id')->nullable()->constrained('wallet_top_ups')->nullOnDelete();
            $table->foreignId('settlement_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_direction_check CHECK (direction IN ('debit','credit'))");
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_account_type_check CHECK (account_type IN ('pos_wallet','issuer_payable','network_commission','gateway_fee','suspense'))");
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_centavos_check CHECK (amount_centavos > 0)');
    }

    public function down()
    {
        Schema::dropIfExists('ledger_entries');
    }
}
