<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payouts OUT to issuers (docs/data-model.md "settlements",
 * docs/settlement-reconciliation.md "Settlement OUT to issuers"). Draws down
 * issuer_payable; gross/commission/fee are the reporting breakdown of the
 * net amount actually paid out.
 */
class SettlementsTable extends Migration
{
    public function up()
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuer_id')->constrained('issuers')->cascadeOnDelete();
            $table->string('period');
            $table->bigInteger('gross_centavos');
            $table->bigInteger('commission_centavos');
            $table->bigInteger('fee_centavos');
            $table->bigInteger('net_centavos');
            $table->string('spei_ref')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE settlements ADD CONSTRAINT settlements_status_check CHECK (status IN ('pending','completed'))");
        DB::statement('ALTER TABLE settlements ADD CONSTRAINT settlements_net_centavos_check CHECK (net_centavos > 0)');
    }

    public function down()
    {
        Schema::dropIfExists('settlements');
    }
}
