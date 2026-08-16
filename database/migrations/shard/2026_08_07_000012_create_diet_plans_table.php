<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('diet_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('member_id')->nullable()->index();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('target_calories')->nullable();
            $table->integer('protein_grams')->nullable();
            $table->integer('carbs_grams')->nullable();
            $table->integer('fat_grams')->nullable();
            $table->json('meals')->nullable();
            $table->boolean('is_template')->default(false)->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('diet_plans');
    }
};
