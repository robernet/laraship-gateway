<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IdempotencyKeysTable extends Migration
{
    public function up()
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->string('key');
            $table->string('request_hash');
            $table->unsignedSmallInteger('response_status');
            $table->json('response_snapshot');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('idempotency_keys');
    }
}
