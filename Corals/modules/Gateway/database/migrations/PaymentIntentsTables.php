<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentIntentsTables extends Migration
{
    public function up()
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->nullable()->unique();
            $table->foreignId('issuer_id')->constrained('issuers')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('invoice_id');
            $table->string('mode');
            $table->json('amount_policy');
            $table->string('mapping_strategy');
            $table->string('state')->default('CREATED');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_payments')->nullable();
            $table->string('overpay_policy')->nullable();
            $table->string('underpay_policy')->nullable();
            $table->timestamps();

            // ponytail: the doc's "unless reusable multi-invoice is explicitly
            // configured" exception isn't built yet — add a config-driven
            // relaxation of this constraint when that need is real.
            $table->unique(['issuer_id', 'invoice_id']);
        });

        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_mode_check CHECK (mode IN ('one_time','reusable'))");
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_mapping_strategy_check CHECK (mapping_strategy IN ('deterministic','stored'))");
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_state_check CHECK (state IN ('CREATED','ACTIVE','PAID_PENDING_SETTLEMENT','SETTLED','EXPIRED','CANCELED'))");

        Schema::create('payment_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained('payment_intents')->cascadeOnDelete();
            $table->string('reference_token')->unique();
            $table->string('human_reference');
            $table->string('barcode_payload')->nullable();
            $table->text('qr_payload')->nullable();
            $table->string('kid')->nullable();
            $table->string('nonce')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE payment_references ADD CONSTRAINT payment_references_status_check CHECK (status IN ('active','consumed','expired','revoked'))");

        // One-time-ref single success: at most one reference per intent may
        // ever be marked consumed. Full "exactly one CONFIRMED transaction"
        // enforcement lands with the transactions table (Phase 4); this is
        // the schema-level guard available now.
        DB::statement('CREATE UNIQUE INDEX payment_references_one_success ON payment_references (payment_intent_id) WHERE status = \'consumed\'');
    }

    public function down()
    {
        Schema::dropIfExists('payment_references');
        Schema::dropIfExists('payment_intents');
    }
}
