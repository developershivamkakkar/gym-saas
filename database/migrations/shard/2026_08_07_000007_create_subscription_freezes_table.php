<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('subscription_freezes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('subscription_id')->index();
            $table->date('freeze_start_date');
            $table->date('requested_end_date');
            $table->date('actual_unfreeze_date')->nullable();
            $table->integer('requested_days');
            $table->integer('actual_days_frozen')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('subscription_freezes');
    }
};
