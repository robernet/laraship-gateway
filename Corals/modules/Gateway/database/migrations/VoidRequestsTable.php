<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GW-505 (docs/state-machines.md "CONFIRMED → VOIDED (void window only)",
 * docs/security-antifraud.md "Void collusion"). One row per void attempt on
 * a CONFIRMED transaction. Below the dual-control threshold a request is
 * immediately finalized by its own requester; at/above threshold it sits
 * `pending` until a second, distinct actor approves it — see
 * Core\Collections\VoidCollection.
 */
class VoidRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('void_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->string('posting_id');
            $table->bigInteger('amount_centavos');
            $table->string('requested_by');
            $table->string('approved_by')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->string('voided_posting_id')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE void_requests ADD CONSTRAINT void_requests_status_check CHECK (status IN ('pending','voided'))");
        DB::statement('ALTER TABLE void_requests ADD CONSTRAINT void_requests_amount_centavos_check CHECK (amount_centavos > 0)');
    }

    public function down()
    {
        Schema::dropIfExists('void_requests');
    }
}
