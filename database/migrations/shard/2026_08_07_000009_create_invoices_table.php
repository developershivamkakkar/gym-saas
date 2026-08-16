<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('member_id')->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->string('invoice_number');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->string('status')->default('unpaid')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('invoices');
    }
};
