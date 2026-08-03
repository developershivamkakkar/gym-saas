<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ShardDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Gym Configuration for Gold Gym (tenant_id = 1)
        DB::connection('tenant')->table('gym_configs')->insert([
            'tenant_id'     => 1,
            'gym_name'      => 'Gold Gym Central',
            'primary_color' => '#3B82F6',
            'currency'      => 'INR',
            'tax_rate'      => 18.00,
            'support_email' => 'support@goldsgym.com',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Seed Main Branch
        $branchId = DB::connection('tenant')->table('branches')->insertGetId([
            'tenant_id'      => 1,
            'name'           => 'Downtown Branch',
            'code'           => 'GG-01',
            'is_main_branch' => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Seed Gym Owner Staff Account
        DB::connection('tenant')->table('staff')->insert([
            'tenant_id'  => 1,
            'branch_id'  => $branchId,
            'name'       => 'John Gold Owner',
            'email'      => 'owner@goldsgym.com',
            'phone'      => '+1987654321',
            'password'   => Hash::make('password123'),
            'role'       => 'owner',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
