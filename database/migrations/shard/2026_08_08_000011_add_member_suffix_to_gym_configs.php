<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('gym_configs', function (Blueprint $table) {
            // Add member_prefix if it doesn't exist
            if (!Schema::connection('tenant')->hasColumn('gym_configs', 'member_prefix')) {
                $table->string('member_prefix', 20)->default('SVS')->after('tax_rate');
            }
            
            // Add member_suffix if it doesn't exist
            if (!Schema::connection('tenant')->hasColumn('gym_configs', 'member_suffix')) {
                $table->string('member_suffix', 20)->nullable()->after('member_prefix');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('gym_configs', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('gym_configs', 'member_suffix')) {
                $table->dropColumn('member_suffix');
            }
        });
    }
};
