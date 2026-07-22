<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IssuerReferenceSecretColumn extends Migration
{
    public function up()
    {
        Schema::table('issuers', function (Blueprint $table) {
            $table->string('reference_secret')->nullable()->after('webhook_secret');
        });
    }

    public function down()
    {
        Schema::table('issuers', function (Blueprint $table) {
            $table->dropColumn('reference_secret');
        });
    }
}
