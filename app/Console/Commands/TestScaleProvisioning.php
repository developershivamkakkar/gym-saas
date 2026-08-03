<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Master\Partner;
use App\Models\Master\Shard;
use App\Models\Master\Tenant;
use App\Services\TenantProvisioningService;

class TestScaleProvisioning extends Command
{
    protected $signature = 'test:scale-provisioning {count=60}';
    protected $description = 'Test scale provisioning by adding N gyms and checking shard allocation behavior';

    public function handle(TenantProvisioningService $provisioner)
    {
        $count = (int) $this->argument('count');
        $this->info("🚀 Starting Scale Test: Provisioning {$count} Gyms...");

        // Ensure partner has enough quota
        $partner = Partner::firstOrCreate(
            ['email' => 'partner@agency.com'],
            [
                'company_name'   => 'Fitness Resellers Ltd',
                'contact_person' => 'Alex Partner',
                'password'       => bcrypt('password123'),
                'gym_quota'      => 100,
                'gyms_created'   => 0,
                'status'         => 'active',
            ]
        );

        $partner->update(['gym_quota' => 200]);

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 1; $i <= $count; $i++) {
            $slug = "test-gym-" . str_pad($i, 3, '0', STR_PAD_LEFT);

            // Skip if already exists
            if (!Tenant::where('slug', $slug)->exists()) {
                $provisioner->createGym([
                    'gym_name'       => "Test Gym #" . $i,
                    'slug'           => $slug,
                    'owner_name'     => "Owner #" . $i,
                    'owner_email'    => "owner{$i}@testgym.com",
                    'owner_password' => 'password123',
                    'plan_tier'      => 'pro',
                ], $partner);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Scale Provisioning Completed!");
        $this->newLine();

        // Display Shard Breakdown Table
        $shards = Shard::all(['id', 'name', 'db_name', 'current_tenants', 'max_tenants', 'is_accepting_tenants']);

        $tableData = $shards->map(function ($shard) {
            return [
                'Shard ID'        => $shard->id,
                'Shard Name'      => $shard->name,
                'Current Gyms'    => $shard->current_tenants,
                'Max Capacity'    => $shard->max_tenants,
                'Status'          => $shard->is_accepting_tenants ? 'Accepting Gyms ✅' : 'Full (Locked) 🔒',
            ];
        })->toArray();

        $this->table(['Shard ID', 'Shard Name', 'Current Gyms', 'Max Capacity', 'Status'], $tableData);

        $totalGyms = Tenant::count();
        $totalShards = Shard::count();
        $this->info("📊 Total Gyms in System: {$totalGyms}");
        $this->info("📊 Total Shards Created: {$totalShards}");

        return 0;
    }
}
