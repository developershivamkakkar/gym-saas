<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('partners', function (Blueprint $table) {
            $table->id();
            $table->enum('partner_type', ['company', 'individual'])->default('company');
            $table->string('company_name')->nullable();
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->integer('gym_quota')->default(10);
            $table->integer('gyms_created')->default(0);
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active');
            $table->text('notes')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('partners');
    }
};
