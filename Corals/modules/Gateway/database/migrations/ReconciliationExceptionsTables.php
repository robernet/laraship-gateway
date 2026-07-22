<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconciliationExceptionsTables extends Migration
{
    public function up()
    {
        Schema::create('reconciliation_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->json('refs')->nullable();
            $table->string('state')->default('open');
            $table->string('assignee')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE reconciliation_exceptions ADD CONSTRAINT reconciliation_exceptions_type_check CHECK (type IN ('unmatched_confirm','amount_mismatch','duplicate','orphan_topup','negative_drift'))");
        DB::statement("ALTER TABLE reconciliation_exceptions ADD CONSTRAINT reconciliation_exceptions_state_check CHECK (state IN ('open','investigating','resolved'))");
    }

    public function down()
    {
        Schema::dropIfExists('reconciliation_exceptions');
    }
}
