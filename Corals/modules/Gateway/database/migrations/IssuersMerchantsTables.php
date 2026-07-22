<?php

namespace Corals\Modules\Gateway\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IssuersMerchantsTables extends Migration
{
    public function up()
    {
        Schema::create('issuers', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->nullable()->unique();
            $table->string('name');
            $table->string('settlement_clabe', 18)->nullable();
            $table->string('status')->default('active');
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('finality_policy')->default('on_confirm');
            $table->timestamps();
        });

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->char('mid', 9)->unique();
            $table->foreignId('issuer_id')->constrained('issuers')->cascadeOnDelete();
            $table->string('signing_key_current_kid')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('merchant_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('kid')->unique();
            $table->string('alg');
            $table->string('secret_ref');
            $table->string('state')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retire_after')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('merchant_keys');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('issuers');
    }
}
