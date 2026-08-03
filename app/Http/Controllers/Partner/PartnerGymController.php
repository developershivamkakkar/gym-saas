<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Master\Tenant;
use App\Models\Master\Partner;
use App\Models\Master\AuditLog;
use App\Services\TenantProvisioningService;
use App\Services\HashIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PartnerGymController extends Controller
{
    protected TenantProvisioningService $provisioningService;

    public function __construct(TenantProvisioningService $provisioningService)
    {
        $this->provisioningService = $provisioningService;
    }

    /**
     * List all gym instances provisioned by the authenticated Partner
     */
    public function index(Request $request)
    {
        $partner = $request->attributes->get('partner') ?: app('partner');

        $query = Tenant::where('partner_id', $partner->getKey())
            ->with(['shard:id,name,db_name']);

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $tenants = $query->orderBy('id', 'desc')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'quota_info' => [
                'gym_quota'       => $partner->gym_quota,
                'gyms_created'    => $partner->gyms_created,
                'quota_remaining' => max(0, $partner->gym_quota - $partner->gyms_created),
            ],
            'data' => $tenants
        ]);
    }

    /**
     * Provision a new Gym Instance for a Gym Owner using Partner Quota
     */
    public function store(Request $request)
    {
        $partner = $request->attributes->get('partner') ?: app('partner');

        // Check 1: Verify partner account is active
        if ($partner->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Partner account is suspended. You cannot provision new gym instances.'
            ], 403);
        }

        // Check 2: Strict Quota Limit Enforcement
        if ($partner->gyms_created >= $partner->gym_quota) {
            return response()->json([
                'success' => false,
                'message' => "Gym quota limit reached ({$partner->gyms_created}/{$partner->gym_quota}). Please contact FitCore developers to request a quota increase before provisioning new gyms."
            ], 422);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'gym_name'       => 'required|string|max:191',
            'slug'           => 'required|string|max:64|unique:master.tenants,slug|alpha_dash',
            'owner_name'     => 'required|string|max:191',
            'owner_email'    => 'required|email',
            'owner_password' => 'required|string|min:6',
            'plan_tier'      => 'nullable|in:basic,pro,enterprise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // Provision Gym Instance via TenantProvisioningService
        $tenant = $this->provisioningService->createGym([
            'gym_name'       => $request->gym_name,
            'slug'           => strtolower($request->slug),
            'owner_name'     => $request->owner_name,
            'owner_email'    => strtolower($request->owner_email),
            'owner_password' => $request->owner_password,
            'plan_tier'      => $request->input('plan_tier', 'pro'),
        ], $partner);

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Partner',
            'actor_id'    => $partner->getKey(),
            'action'      => 'gym.provisioned',
            'target_type' => 'Tenant',
            'target_id'   => $tenant->getKey(),
            'payload'     => [
                'gym_name'     => $tenant->name,
                'slug'         => $tenant->slug,
                'partner_id'   => $partner->id,
                'instance_url' => $tenant->instance_url,
            ],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Gym instance '{$tenant->name}' provisioned successfully",
            'data'    => [
                'tenant'          => $tenant,
                'instance_url'    => $tenant->instance_url,
                'owner_login'     => [
                    'email' => $request->owner_email,
                    'url'   => $tenant->instance_url . '/login'
                ],
                'quota_remaining' => max(0, $partner->gym_quota - $partner->fresh()->gyms_created),
            ]
        ], 201);
    }

    /**
     * Get single gym tenant details created by this partner
     */
    public function show(Request $request, $id)
    {
        $partner = $request->attributes->get('partner') ?: app('partner');

        if (is_numeric($id)) {
            $numericId = (int) $id;
        } else {
            $numericId = HashIdService::decode($id, 'TNT');
        }

        $query = Tenant::where('partner_id', $partner->getKey());

        if ($numericId) {
            $query->where('id', $numericId);
        } else {
            $query->where('slug', $id);
        }

        $tenant = $query->with('shard:id,name,db_name')->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym instance not found or does not belong to your partner account'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $tenant
        ]);
    }
}
