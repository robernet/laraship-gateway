<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NetworkCredentialsTable extends Migration
{
    public function up()
    {
        Schema::create('network_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->nullable()->unique();
            $table->string('network_id')->unique();
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE network_credentials ADD CONSTRAINT network_credentials_status_check CHECK (status IN ('active','revoked'))");
    }

    public function down()
    {
        Schema::dropIfExists('network_credentials');
    }
}
