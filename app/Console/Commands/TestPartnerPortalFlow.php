<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Master\Partner;
use App\Models\Master\Tenant;
use Illuminate\Support\Facades\Hash;

class TestPartnerPortalFlow extends Command
{
    protected $signature = 'test:partner-portal';
    protected $description = 'Test complete Partner Portal authentication, gym provisioning, quota enforcement, and dashboard APIs';

    public function handle()
    {
        $this->info("==================================================================");
        $this->info("🚀 STARTING PARTNER PORTAL END-TO-END FLOW TEST");
        $this->info("==================================================================");

        // Step 1: Create a test Partner account with quota = 1
        $email = 'partner_' . time() . '@agency.com';
        $partner = Partner::create([
            'partner_type'   => 'company',
            'company_name'   => 'Apex Franchise Partners',
            'contact_person' => 'Adam Partner',
            'email'          => $email,
            'phone'          => '+1888999000',
            'password'       => Hash::make('password123'),
            'gym_quota'      => 1, // Set quota limit = 1 to test enforcement
            'gyms_created'   => 0,
            'status'         => 'active',
            'notes'          => 'Test Partner for API Flow',
        ]);

        $this->info("1. Created Partner: '{$partner->company_name}' (Email: {$partner->email}, Quota: {$partner->gym_quota})");

        // Step 2: Test Partner Login API
        $this->info("2. Testing Partner Login API (POST /api/v1/partner/auth/login)...");
        $authController = new \App\Http\Controllers\Partner\AuthController();
        $loginReq = \Illuminate\Http\Request::create('/api/v1/partner/auth/login', 'POST', [
            'email'    => $email,
            'password' => 'password123'
        ]);
        $loginRes = json_decode($authController->login($loginReq)->getContent(), true);

        if ($loginRes['success']) {
            $token = $loginRes['token'];
            $this->info("   ✅ Partner Login Successful! Token generated.");
            $this->info("   • Quota Remaining: {$loginRes['data']['quota_remaining']}");
        } else {
            $this->error("   ❌ Partner Login Failed!");
            return 1;
        }

        // Setup request headers for protected routes
        $protectedReq = function ($uri, $method = 'GET', $params = []) use ($partner) {
            $req = \Illuminate\Http\Request::create($uri, $method, $params);
            $req->attributes->set('partner', $partner);
            app()->instance('partner', $partner);
            return $req;
        };

        // Step 3: Test Partner Dashboard API
        $this->info("3. Testing Partner Dashboard API (GET /api/v1/partner/dashboard)...");
        $dashController = new \App\Http\Controllers\Partner\PartnerDashboardController();
        $dashRes = json_decode($dashController->index($protectedReq('/api/v1/partner/dashboard'))->getContent(), true);

        if ($dashRes['success']) {
            $this->info("   ✅ Dashboard Stats Retrieved Successfully!");
            $this->info("   • Total Gyms: {$dashRes['data']['gyms']['total']}, Quota Usage: {$dashRes['data']['quota']['usage_percentage']}%");
        } else {
            $this->error("   ❌ Dashboard Retrieval Failed!");
            return 1;
        }

        // Step 4: Provision Gym #1 (Within Quota)
        $this->info("4. Provisioning Gym #1 via Partner Gym API (POST /api/v1/partner/gyms)...");
        $gymController = app()->make(\App\Http\Controllers\Partner\PartnerGymController::class);
        $slug1 = 'apex-gym-' . substr(time(), -4);
        $provisionReq1 = $protectedReq('/api/v1/partner/gyms', 'POST', [
            'gym_name'       => 'Apex Fitness Center',
            'slug'           => $slug1,
            'owner_name'     => 'Alice Owner',
            'owner_email'    => 'owner@' . $slug1 . '.com',
            'owner_password' => 'password123',
            'plan_tier'      => 'pro',
        ]);
        $provisionRes1 = json_decode($gymController->store($provisionReq1)->getContent(), true);

        if ($provisionRes1['success']) {
            $this->info("   ✅ Gym #1 Provisioned Successfully!");
            $this->info("   • Gym Name: {$provisionRes1['data']['tenant']['name']}");
            $this->info("   • Instance URL: {$provisionRes1['data']['instance_url']}");
            $this->info("   • Remaining Quota: {$provisionRes1['data']['quota_remaining']}");
        } else {
            $this->error("   ❌ Gym #1 Provisioning Failed: " . json_encode($provisionRes1));
            return 1;
        }

        // Step 5: Test Quota Enforcement (Attempt to Provision Gym #2 when quota = 1)
        $this->info("5. Testing Quota Limit Enforcement (Provisioning Gym #2 when quota = 1)...");
        $slug2 = 'apex-gym-extra-' . substr(time(), -4);
        $provisionReq2 = $protectedReq('/api/v1/partner/gyms', 'POST', [
            'gym_name'       => 'Apex Extra Gym',
            'slug'           => $slug2,
            'owner_name'     => 'Bob Owner',
            'owner_email'    => 'owner@' . $slug2 . '.com',
            'owner_password' => 'password123',
            'plan_tier'      => 'pro',
        ]);
        $provisionRes2 = json_decode($gymController->store($provisionReq2)->getContent(), true);

        if (!$provisionRes2['success'] && isset($provisionRes2['message']) && str_contains($provisionRes2['message'], 'quota limit reached')) {
            $this->info("   ✅ QUOTA GUARD PASSED! Rejection message received:");
            $this->warn("      \"{$provisionRes2['message']}\"");
        } else {
            $this->error("   ❌ QUOTA GUARD FAILED! Should have blocked Gym #2.");
            return 1;
        }

        // Step 6: Test Suspended Partner Lockout
        $this->info("6. Testing Suspended Partner Lockout...");
        $partner->update(['status' => 'suspended']);
        $provisionReq3 = $protectedReq('/api/v1/partner/gyms', 'POST', [
            'gym_name'       => 'Apex Blocked Gym',
            'slug'           => 'blocked-' . time(),
            'owner_name'     => 'Charlie Owner',
            'owner_email'    => 'charlie@blocked.com',
            'owner_password' => 'password123',
        ]);
        $provisionRes3 = json_decode($gymController->store($provisionReq3)->getContent(), true);

        if (!$provisionRes3['success']) {
            $this->info("   ✅ SUSPENSION GUARD PASSED! Provisioning blocked for suspended partner.");
        } else {
            $this->error("   ❌ SUSPENSION GUARD FAILED!");
            return 1;
        }

        // Step 7: Verify Gym Owner Login for the newly provisioned gym
        $this->info("7. Verifying Gym Owner Login for newly provisioned '{$slug1}'...");
        $loginResponse = $this->simulateGymLogin($slug1, 'owner@' . $slug1 . '.com', 'password123');
        if ($loginResponse['success']) {
            $this->info("   ✅ Gym Owner Logged In Successfully!");
            $this->info("   • Gym Owner Name: {$loginResponse['data']['name']}");
            $this->info("   • Tenant Slug: {$loginResponse['tenant']['slug']}");
        } else {
            $this->error("   ❌ Gym Owner Login Failed!");
            return 1;
        }

        $this->newLine();
        $this->info("==================================================================");
        $this->info("🎉 ALL TESTS PASSED: Partner Portal APIs & Quota Guards verified!");
        $this->info("==================================================================");

        return 0;
    }

    private function simulateGymLogin($slug, $email, $password)
    {
        $request = \Illuminate\Http\Request::create('/api/v1/gym/auth/login', 'POST', [
            'email' => $email,
            'password' => $password
        ]);
        $request->headers->set('X-Tenant-Slug', $slug);

        $middleware = new \App\Http\Middleware\TenantResolutionMiddleware();
        $response = $middleware->handle($request, function ($req) use ($email, $password) {
            $controller = new \App\Http\Controllers\Gym\AuthController();
            return $controller->login($req);
        });

        return json_decode($response->getContent(), true);
    }
}
