<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosWalletsTables extends Migration
{
    public function up()
    {
        Schema::create('pos_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->nullable()->unique();
            $table->string('network_id');
            $table->string('external_store_id');
            $table->bigInteger('balance_centavos')->default(0);
            $table->bigInteger('reserved_centavos')->default(0);
            $table->bigInteger('available_centavos')->storedAs('balance_centavos - reserved_centavos');
            $table->string('currency', 3)->default('MXN');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE pos_wallets ADD CONSTRAINT pos_wallets_balance_centavos_check CHECK (balance_centavos >= 0)');
        DB::statement('ALTER TABLE pos_wallets ADD CONSTRAINT pos_wallets_reserved_centavos_check CHECK (reserved_centavos >= 0)');

        Schema::create('wallet_top_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_wallet_id')->constrained('pos_wallets')->cascadeOnDelete();
            $table->bigInteger('amount_centavos');
            $table->string('spei_ref')->nullable();
            $table->string('clabe_origin')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallet_top_ups');
        Schema::dropIfExists('pos_wallets');
    }
}
