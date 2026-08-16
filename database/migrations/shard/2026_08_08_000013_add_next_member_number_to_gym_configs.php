<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('gym_configs', function (Blueprint $table) {
            // Add next_member_number if it doesn't exist
            if (!Schema::connection('tenant')->hasColumn('gym_configs', 'next_member_number')) {
                $table->unsignedBigInteger('next_member_number')->default(1001)->after('tax_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('gym_configs', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('gym_configs', 'next_member_number')) {
                $table->dropColumn('next_member_number');
            }
        });
    }
};
