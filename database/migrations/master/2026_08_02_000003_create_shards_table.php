<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('shards', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. fitcore_shard_01
            $table->string('db_host')->default('127.0.0.1');
            $table->string('db_port')->default('3306');
            $table->string('db_name');
            $table->string('db_user');
            $table->text('db_password'); // Encrypted
            $table->integer('max_tenants')->default(20);
            $table->integer('current_tenants')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_accepting_tenants')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('shards');
    }
};
