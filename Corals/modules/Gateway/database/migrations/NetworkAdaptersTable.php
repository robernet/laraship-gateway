<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NetworkAdaptersTable extends Migration
{
    public function up()
    {
        Schema::create('network_adapters', function (Blueprint $table) {
            $table->id();
            $table->string('network_id')->unique();
            $table->string('archetype');
            $table->json('config')->nullable();
            $table->unsignedInteger('contract_version')->default(1);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE network_adapters ADD CONSTRAINT network_adapters_archetype_check CHECK (archetype IN ('realtime','webhook','sftp'))");
    }

    public function down()
    {
        Schema::dropIfExists('network_adapters');
    }
}
