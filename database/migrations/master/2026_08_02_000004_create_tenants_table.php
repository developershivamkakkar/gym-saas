<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // e.g. gold-gym
            $table->string('custom_domain')->nullable()->unique();
            $table->foreignId('partner_id')->constrained('partners')->onDelete('cascade');
            $table->foreignId('shard_id')->constrained('shards')->onDelete('restrict');
            $table->enum('plan_tier', ['starter', 'pro', 'enterprise'])->default('starter');
            $table->enum('status', ['active', 'trial', 'suspended', 'cancelled'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('tenants');
    }
};
