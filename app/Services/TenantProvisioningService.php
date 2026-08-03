<?php

namespace App\Services;

use App\Models\Master\Tenant;
use App\Models\Master\Partner;
use App\Models\Shard\Staff;
use App\Models\Shard\GymConfig;
use App\Models\Shard\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantProvisioningService
{
    protected ShardRouter $shardRouter;

    public function __construct(ShardRouter $shardRouter)
    {
        $this->shardRouter = $shardRouter;
    }

    /**
     * Create a new Gym / Clinic Tenant Instance
     */
    public function createGym(array $data, Partner $partner): Tenant
    {
        // 1. Get an available shard BEFORE starting transaction (since MySQL DDL statements like CREATE DATABASE auto-commit)
        $shard = $this->shardRouter->getAvailableShard();

        return DB::connection('master')->transaction(function () use ($data, $partner, $shard) {
            // 2. Register Tenant in fitcore_master
            $tenant = Tenant::create([
                'name'          => $data['gym_name'],
                'slug'          => $data['slug'],
                'partner_id'    => $partner->getKey(),
                'shard_id'      => $shard->getKey(),
                'plan_tier'     => $data['plan_tier'] ?? 'starter',
                'status'        => 'active',
                'trial_ends_at' => now()->addDays(14),
            ]);

            // 3. Increment shard tenant counter & partner gyms_created counter
            $shard->increment('current_tenants');
            if ($shard->current_tenants >= $shard->max_tenants) {
                $shard->update(['is_accepting_tenants' => false]);
            }

            $partner->increment('gyms_created');

            // 4. Configure shard connection to seed Gym Config & Owner Staff Account
            $driver = config('database.connections.master.driver', 'sqlite');
            config(['database.connections.tenant.database' => $driver === 'sqlite' ? database_path($shard->db_name . '.sqlite') : $shard->db_name]);
            DB::purge('tenant');
            app()->instance('tenant', (object)[
                'tenant_id'   => $tenant->getKey(),
                'tenant_name' => $tenant->name,
                'slug'        => $tenant->slug,
            ]);

            // Seed or update Gym Config
            GymConfig::updateOrCreate(
                ['tenant_id' => $tenant->getKey()],
                [
                    'gym_name'      => $data['gym_name'],
                    'support_email' => $data['owner_email'],
                    'support_phone' => $data['owner_phone'] ?? null,
                ]
            );

            // Seed Main Branch
            $branch = Branch::create([
                'tenant_id'      => $tenant->getKey(),
                'name'           => 'Main Branch',
                'code'           => 'MAIN-01',
                'is_main_branch' => true,
            ]);

            // Seed Owner Staff User
            Staff::create([
                'tenant_id'  => $tenant->getKey(),
                'branch_id'  => $branch->id,
                'name'       => $data['owner_name'],
                'email'      => $data['owner_email'],
                'phone'      => $data['owner_phone'] ?? null,
                'password'   => Hash::make($data['owner_password']),
                'role'       => 'owner',
                'is_active'  => true,
            ]);

            return $tenant;
        });
    }
}
