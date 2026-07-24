<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per collection attempt that reaches AUTHORIZED (docs/data-model.md
 * "transactions"). A declined /cash/validate never persists a row — see
 * docs/state-machines.md ("no txn row persists as VOIDED; declines are
 * logged, not transactions").
 */
class TransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->nullable()->unique();
            $table->foreignId('payment_reference_id')->constrained('payment_references')->cascadeOnDelete();
            $table->foreignId('pos_wallet_id')->constrained('pos_wallets')->cascadeOnDelete();
            $table->string('network_id');
            $table->string('network_txn_id')->nullable()->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->bigInteger('amount_centavos');
            $table->string('state')->default('AUTHORIZED');
            $table->boolean('is_partial')->default(false);
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('finality')->default('on_confirm');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_state_check CHECK (state IN ('INITIATED','AUTHORIZED','CONFIRMED','FINALIZED','VOIDED'))");
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_centavos_check CHECK (amount_centavos >= 0)');
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
