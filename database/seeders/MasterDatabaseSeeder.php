<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MasterDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Developer Super Admin
        DB::connection('master')->table('developers')->updateOrInsert(
            ['email' => 'admin@fitcore.io'],
            [
                'name'       => 'FitCore Super Admin',
                'password'   => Hash::make('password123'),
                'role'       => 'super_admin',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Seed Partner Account
        $partnerId = DB::connection('master')->table('partners')->insertGetId([
            'company_name'   => 'Fitness Resellers Ltd',
            'contact_person' => 'Alex Partner',
            'email'          => 'partner@agency.com',
            'phone'          => '+1234567890',
            'password'       => Hash::make('password123'),
            'gym_quota'      => 10,
            'gyms_created'   => 1,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // 3. Seed Shard Database Entry
        $shardId = DB::connection('master')->table('shards')->insertGetId([
            'name'                 => 'fitcore_shard_01',
            'db_host'              => '127.0.0.1',
            'db_port'              => '3306',
            'db_name'              => 'fitcore_shard_01',
            'db_user'              => 'root',
            'db_password'          => '',
            'max_tenants'          => 20,
            'current_tenants'      => 1,
            'is_active'            => true,
            'is_accepting_tenants' => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // 4. Seed Tenant Entry (Gold Gym)
        DB::connection('master')->table('tenants')->insertGetId([
            'name'          => 'Gold Gym',
            'slug'          => 'gold-gym',
            'partner_id'    => $partnerId,
            'shard_id'      => $shardId,
            'plan_tier'     => 'pro',
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
