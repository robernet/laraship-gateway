<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OutboxEventsTable extends Migration
{
    public function up()
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_status_check CHECK (status IN ('pending','dispatched','failed'))");
    }

    public function down()
    {
        Schema::dropIfExists('outbox_events');
    }
}
