<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method');
            $table->string('transaction_reference')->nullable();
            $table->string('status')->default('completed')->index();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payments');
    }
};
