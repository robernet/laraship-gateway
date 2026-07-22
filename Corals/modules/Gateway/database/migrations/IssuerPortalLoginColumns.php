<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IssuerPortalLoginColumns extends Migration
{
    public function up()
    {
        Schema::table('issuers', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('reference_secret');
            $table->string('password')->nullable()->after('email');
        });
    }

    public function down()
    {
        Schema::table('issuers', function (Blueprint $table) {
            $table->dropColumn(['email', 'password']);
        });
    }
}
