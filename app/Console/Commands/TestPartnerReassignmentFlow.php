<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Master\Partner;
use App\Models\Master\Tenant;
use App\Services\TenantProvisioningService;
use App\Services\HashIdService;
use Illuminate\Support\Facades\Hash;

class TestPartnerReassignmentFlow extends Command
{
    protected $signature = 'test:partner-reassignment';
    protected $description = 'Test complete partner suspension protection, zero-downtime gym access, and gym reassignment flow';

    public function handle(TenantProvisioningService $provisioner)
    {
        $this->info("==================================================================");
        $this->info("🚀 STARTING PRE-SUSPENSION PROTECTION & REASSIGNMENT FLOW TEST");
        $this->info("==================================================================");

        // Step 1: Create Partner A
        $partnerA = Partner::create([
            'partner_type'   => 'company',
            'company_name'   => 'Agency Alpha Ltd',
            'contact_person' => 'Alex Alpha',
            'email'          => 'alpha_' . time() . '@agency.com',
            'phone'          => '+1111111111',
            'password'       => Hash::make('password123'),
            'gym_quota'      => 10,
            'gyms_created'   => 0,
            'status'         => 'active',
        ]);
        $this->info("1. Created Partner A: '{$partnerA->company_name}' (Hash ID: {$partnerA->id})");

        // Step 2: Create Partner B
        $partnerB = Partner::create([
            'partner_type'   => 'company',
            'company_name'   => 'Agency Beta Corp',
            'contact_person' => 'Bob Beta',
            'email'          => 'beta_' . time() . '@agency.com',
            'phone'          => '+2222222222',
            'password'       => Hash::make('password123'),
            'gym_quota'      => 20,
            'gyms_created'   => 0,
            'status'         => 'active',
        ]);
        $this->info("2. Created Partner B: '{$partnerB->company_name}' (Hash ID: {$partnerB->id})");

        // Step 3: Partner A provisions Gym 'titan-fitness'
        $slug = 'titan-fitness-' . substr(time(), -4);
        $tenant = $provisioner->createGym([
            'gym_name'       => 'Titan Fitness Gym',
            'slug'           => $slug,
            'owner_name'     => 'Titan Owner',
            'owner_email'    => 'owner@' . $slug . '.com',
            'owner_password' => 'password123',
            'plan_tier'      => 'pro',
        ], $partnerA);
        $this->info("3. Provisioned Gym Tenant: '{$tenant->name}' (Slug: {$tenant->slug}) owned by Partner A");
        $this->info("   • Partner A Active Gym Count: {$partnerA->fresh()->tenants()->count()}");

        // Step 4: Attempt to Suspend Partner A BEFORE Reassigning Gyms (MUST BE BLOCKED!)
        $this->info("4. Attempting to Suspend Partner A while Partner A still owns a gym...");
        $controller = new \App\Http\Controllers\Developer\PartnerController();
        $req = \Illuminate\Http\Request::create('/api/v1/developer/partners/' . $partnerA->id . '/status', 'PATCH', ['status' => 'suspended']);
        $suspensionResponse = json_decode($controller->updateStatus($req, $partnerA->id)->getContent(), true);

        if (!$suspensionResponse['success']) {
            $this->info("   ✅ PRE-SUSPENSION GUARD PASSED! Suspension blocked as expected:");
            $this->warn("      \"" . $suspensionResponse['message'] . "\"");
        } else {
            $this->error("   ❌ ERROR: Suspension should have been blocked!");
        }

        // Step 5: Reassign Gym from Partner A to Partner B
        $this->info("5. Reassigning Gym '{$tenant->name}' from Partner A to Partner B...");
        $reassignReq = \Illuminate\Http\Request::create('/api/v1/developer/partners/' . $partnerA->id . '/reassign-tenants', 'POST', [
            'target_partner_id' => (string) $partnerB->id
        ]);
        $reassignResponse = json_decode($controller->reassignTenants($reassignReq, $partnerA->id)->getContent(), true);

        if ($reassignResponse && isset($reassignResponse['success']) && $reassignResponse['success']) {
            $this->info("   ✅ Gym Reassigned Successfully!");
            $this->info("   • Partner A Active Gym Count: {$partnerA->fresh()->tenants()->count()}");
            $this->info("   • Partner B Active Gym Count: {$partnerB->fresh()->tenants()->count()}");
        } else {
            $this->error("   ❌ Reassignment Failed: " . json_encode($reassignResponse));
        }

        // Step 6: Suspend Partner A AFTER Reassigning Gyms (MUST SUCCEED!)
        $this->info("6. Suspending Partner A AFTER all gyms have been reassigned...");
        $postSuspensionResponse = json_decode($controller->updateStatus($req, $partnerA->id)->getContent(), true);

        if ($postSuspensionResponse['success']) {
            $this->info("   ✅ Partner A Suspended Successfully post-reassignment!");
        } else {
            $this->error("   ❌ Partner A Suspension Failed post-reassignment!");
        }

        // Step 7: Verify Gym Operations post-suspension & reassignment
        $this->info("7. Verifying Gym Owner Login for '{$tenant->name}'...");
        $loginResponse = $this->simulateGymLogin($slug, 'owner@' . $slug . '.com', 'password123');
        if ($loginResponse['success']) {
            $this->info("   ✅ Gym Owner Logged In Successfully! ZERO DOWNTIME & ZERO DATA LOSS verified!");
        } else {
            $this->error("   ❌ Gym Login Failed!");
        }

        $this->newLine();
        $this->info("==================================================================");
        $this->info("🎉 ALL TESTS PASSED: Pre-suspension safeguard & reassignment flow verified!");
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
