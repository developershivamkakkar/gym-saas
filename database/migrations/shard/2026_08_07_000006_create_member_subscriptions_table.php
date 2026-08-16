<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('member_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('auto_renew')->default(false);
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('member_subscriptions');
    }
};
