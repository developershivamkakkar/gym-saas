<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Master\Partner;
use App\Models\Master\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestGymPortalFlow extends Command
{
    protected $signature = 'test:gym-portal';
    protected $description = 'Test complete Gym Instance authentication, dashboard, multi-branch management, and plan tier branch limit enforcement';

    public function handle(TenantProvisioningService $provisioner)
    {
        $this->info("==================================================================");
        $this->info("🚀 STARTING GYM INSTANCE END-TO-END FLOW TEST");
        $this->info("==================================================================");

        // Step 1: Create Partner
        $partner = Partner::create([
            'partner_type'   => 'company',
            'company_name'   => 'Matrix Agency',
            'contact_person' => 'Mark Matrix',
            'email'          => 'matrix_' . time() . '@agency.com',
            'phone'          => '+1999888777',
            'password'       => Hash::make('password123'),
            'gym_quota'      => 10,
            'gyms_created'   => 0,
            'status'         => 'active',
        ]);

        // Step 2: Provision Gym Tenant on Basic Plan (max_branches = 1)
        $slug = 'matrix-gym-' . substr(time(), -4);
        $tenant = $provisioner->createGym([
            'gym_name'       => 'Matrix Fitness Club',
            'slug'           => $slug,
            'owner_name'     => 'Max Owner',
            'owner_email'    => 'owner@' . $slug . '.com',
            'owner_password' => 'password123',
            'plan_tier'      => 'basic', // Basic Plan = Max 1 Branch
        ], $partner);

        $this->info("1. Provisioned Gym Tenant: '{$tenant->name}' (Slug: {$tenant->slug}, Plan: {$tenant->plan_tier})");

        // Helper request generator
        $gymReq = function ($uri, $method = 'GET', $params = []) use ($slug) {
            $req = \Illuminate\Http\Request::create($uri, $method, $params);
            $req->headers->set('X-Tenant-Slug', $slug);

            $middleware = new \App\Http\Middleware\TenantResolutionMiddleware(app(\App\Services\ShardRouter::class));
            return $middleware->handle($req, function ($request) use ($uri, $method, $params) {
                if (str_contains($uri, '/auth/login')) {
                    return (new \App\Http\Controllers\Gym\AuthController())->login($request);
                } elseif (str_contains($uri, '/auth/me')) {
                    return (new \App\Http\Controllers\Gym\AuthController())->me($request);
                } elseif (str_contains($uri, '/dashboard')) {
                    return (new \App\Http\Controllers\Gym\GymDashboardController())->index($request);
                } elseif (str_contains($uri, '/branches') && $method === 'POST') {
                    return (new \App\Http\Controllers\Gym\BranchController())->store($request);
                } elseif (str_contains($uri, '/branches')) {
                    return (new \App\Http\Controllers\Gym\BranchController())->index($request);
                }
                return response()->json(['success' => false]);
            });
        };

        // Step 3: Gym Owner Login
        $this->info("2. Testing Gym Owner Login (POST /api/v1/gym/auth/login)...");
        $loginRes = json_decode($gymReq('/api/v1/gym/auth/login', 'POST', [
            'email'    => 'owner@' . $slug . '.com',
            'password' => 'password123'
        ])->getContent(), true);

        if ($loginRes['success']) {
            $branchName = $loginRes['data']['branch']['name'] ?? 'Main Branch';
            $this->info("   ✅ Gym Owner Logged In Successfully!");
            $this->info("   • Role: {$loginRes['data']['role']}, Primary Branch: {$branchName}");
        } else {
            $this->error("   ❌ Gym Login Failed!");
            return 1;
        }

        // Step 4: Get Profile & Dashboard Overview
        $this->info("3. Testing Gym Dashboard API (GET /api/v1/gym/dashboard)...");
        $dashRes = json_decode($gymReq('/api/v1/gym/dashboard')->getContent(), true);

        if ($dashRes['success']) {
            $this->info("   ✅ Gym Dashboard Retrieved Successfully!");
            $this->info("   • Total Branches: {$dashRes['data']['counts']['branches']}");
            $this->info("   • Usage Text: \"{$dashRes['data']['branches_summary']['usage_text']}\"");
        } else {
            $this->error("   ❌ Gym Dashboard Failed!");
            return 1;
        }

        // Step 5: Test Plan Tier Branch Limit Guard (Attempt to create Branch #2 on Basic Plan)
        $this->info("4. Testing Plan Tier Branch Guard (Creating Branch #2 on Basic Plan)...");
        $branchRes1 = json_decode($gymReq('/api/v1/gym/branches', 'POST', [
            'name' => 'Matrix Downtown Branch',
            'city' => 'Downtown',
        ])->getContent(), true);

        if (!$branchRes1['success'] && isset($branchRes1['message']) && str_contains($branchRes1['message'], 'Branch limit reached')) {
            $this->info("   ✅ REVENUE PROTECTION GUARD PASSED! Branch creation blocked as expected:");
            $this->warn("      \"{$branchRes1['message']}\"");
        } else {
            $this->error("   ❌ REVENUE PROTECTION GUARD FAILED! Message: " . json_encode($branchRes1));
            return 1;
        }

        // Step 6: Upgrade Gym Plan to Pro Plan (Max 3 Branches) and retry Branch #2 creation
        $this->info("5. Upgrading Gym Plan from 'basic' to 'pro' (Max 3 Branches)...");
        $tenant->update(['plan_tier' => 'pro']);
        \Illuminate\Support\Facades\Cache::forget("tenant:slug:{$slug}");

        $branchRes2 = json_decode($gymReq('/api/v1/gym/branches', 'POST', [
            'name' => 'Matrix Downtown Branch',
            'city' => 'Downtown',
        ])->getContent(), true);

        if ($branchRes2['success']) {
            $this->info("   ✅ Branch #2 Created Successfully on Pro Plan!");
            $this->info("   • New Branch Name: {$branchRes2['data']['name']}");
            $this->info("   • Branch Code: {$branchRes2['data']['code']}");
        } else {
            $this->error("   ❌ Branch Creation Failed on Pro Plan: " . json_encode($branchRes2));
            return 1;
        }

        // Step 7: Verify Branch List
        $this->info("6. Verifying Updated Physical Branch List (GET /api/v1/gym/branches)...");
        $listRes = json_decode($gymReq('/api/v1/gym/branches')->getContent(), true);

        if ($listRes['success']) {
            $this->info("   ✅ Branch List Retrieved!");
            $this->info("   • Total Physical Locations: " . count($listRes['data']));
            $this->info("   • Quota Info: {$listRes['branch_quota']['total_branches']}/{$listRes['branch_quota']['max_allowed']} branches used on {$listRes['branch_quota']['plan_tier']} plan");
        } else {
            $this->error("   ❌ Branch List Failed!");
            return 1;
        }

        $this->newLine();
        $this->info("==================================================================");
        $this->info("🎉 ALL TESTS PASSED: Gym Instance APIs & Revenue Guards verified!");
        $this->info("==================================================================");

        return 0;
    }
}
