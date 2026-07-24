<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GW-407: network_credentials moves from one shared row per network_id to
 * one row per (network_id, terminal_id) — terminal_id null keeps the old
 * network-wide credential (e.g. the batch-confirm/SFTP poller principal).
 */
class TerminalCredentialColumn extends Migration
{
    public function up()
    {
        Schema::table('network_credentials', function (Blueprint $table) {
            $table->dropUnique('network_credentials_network_id_unique');
            $table->string('terminal_id')->nullable()->after('network_id');
            $table->unique(['network_id', 'terminal_id']);
        });
    }

    public function down()
    {
        Schema::table('network_credentials', function (Blueprint $table) {
            $table->dropUnique(['network_id', 'terminal_id']);
            $table->dropColumn('terminal_id');
            $table->unique('network_id');
        });
    }
}
