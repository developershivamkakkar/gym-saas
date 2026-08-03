<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type'); // Developer, Partner, Staff
            $table->unsignedBigInteger('actor_id');
            $table->string('action'); // e.g. partner.created, tenant.suspended
            $table->nullableMorphs('target');
            $table->json('payload')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('audit_logs');
    }
};
