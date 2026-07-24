<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PaymentIntentSandboxColumn extends Migration
{
    public function up()
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->boolean('sandbox')->default(false)->after('state');
        });
    }

    public function down()
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn('sandbox');
        });
    }
}
