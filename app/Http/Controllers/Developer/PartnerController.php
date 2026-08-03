<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use App\Models\Master\Tenant;
use App\Models\Master\AuditLog;
use App\Services\HashIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PartnerController extends Controller
{
    /**
     * Helper to resolve Partner by numeric ID or Hash ID
     */
    protected function resolvePartner($id): ?Partner
    {
        if (is_numeric($id)) {
            return Partner::find($id);
        }

        $numericId = HashIdService::decode($id, 'PRT');
        return $numericId ? Partner::find($numericId) : Partner::where('email', $id)->first();
    }

    /**
     * List all partners with gym counts and status
     */
    public function index(Request $request)
    {
        $query = Partner::withCount('tenants');

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('partner_type') && !empty($request->input('partner_type'))) {
            $query->where('partner_type', $request->input('partner_type'));
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $partners = $query->orderBy('id', 'desc')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $partners
        ]);
    }

    /**
     * Create a new Partner account (Company or Individual)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partner_type'   => 'nullable|in:company,individual',
            'company_name'   => 'required_if:partner_type,company|nullable|string|max:191',
            'contact_person' => 'required|string|max:191',
            'email'          => 'required|email|unique:master.partners,email',
            'phone'          => 'nullable|string|max:50',
            'password'       => 'required|string|min:6',
            'gym_quota'      => 'required|integer|min:1',
            'notes'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $partnerType = $request->input('partner_type', 'company');
        $companyName = $partnerType === 'individual' ? ($request->company_name ?? $request->contact_person) : $request->company_name;

        $partner = Partner::create([
            'partner_type'   => $partnerType,
            'company_name'   => $companyName,
            'contact_person' => $request->contact_person,
            'email'          => strtolower($request->email),
            'phone'          => $request->phone,
            'password'       => Hash::make($request->password),
            'gym_quota'      => $request->gym_quota,
            'gyms_created'   => 0,
            'status'         => 'active',
            'notes'          => $request->notes,
        ]);

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Developer',
            'actor_id'    => $request->user() ? $request->user()->getKey() : 1,
            'action'      => 'partner.created',
            'target_type' => 'Partner',
            'target_id'   => $partner->getKey(),
            'payload'     => [
                'hash_id'      => $partner->id,
                'partner_type' => $partner->partner_type,
                'name'         => $partner->contact_person,
                'company'      => $partner->company_name,
                'quota'        => $partner->gym_quota
            ],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Partner created successfully',
            'data'    => $partner
        ], 201);
    }

    /**
     * Get single partner details by numeric ID or Hash ID
     */
    public function show($id)
    {
        $partner = $this->resolvePartner($id);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found'
            ], 404);
        }

        $partner->load('tenants');

        return response()->json([
            'success' => true,
            'data'    => $partner
        ]);
    }

    /**
     * Update Partner Gym Quota by numeric ID or Hash ID
     */
    public function updateQuota(Request $request, $id)
    {
        $partner = $this->resolvePartner($id);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'gym_quota' => 'required|integer|min:' . $partner->gyms_created,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $oldQuota = $partner->gym_quota;
        $partner->update(['gym_quota' => $request->gym_quota]);

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Developer',
            'actor_id'    => $request->user() ? $request->user()->getKey() : 1,
            'action'      => 'partner.quota_updated',
            'target_type' => 'Partner',
            'target_id'   => $partner->getKey(),
            'payload'     => ['hash_id' => $partner->id, 'old_quota' => $oldQuota, 'new_quota' => $request->gym_quota],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Partner quota updated successfully',
            'data'    => $partner
        ]);
    }

    /**
     * Suspend or Activate Partner Account
     * STRICT RULE: Cannot suspend a partner if they still own active gym instances!
     */
    public function updateStatus(Request $request, $id)
    {
        $partner = $this->resolvePartner($id);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,suspended,pending',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // STRICT PRE-SUSPENSION CHECK: Block suspension if partner still owns gym instances
        if ($request->status === 'suspended') {
            $activeGymCount = $partner->tenants()->count();

            if ($activeGymCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot suspend partner '{$partner->contact_person}'. This partner still has {$activeGymCount} active gym instance(s) assigned. Please reassign all gym instances to another partner account first using POST /api/v1/developer/partners/{$partner->id}/reassign-tenants before suspending this partner."
                ], 422);
            }
        }

        $partner->update(['status' => $request->status]);

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Developer',
            'actor_id'    => $request->user() ? $request->user()->getKey() : 1,
            'action'      => 'partner.status_updated',
            'target_type' => 'Partner',
            'target_id'   => $partner->getKey(),
            'payload'     => [
                'hash_id' => $partner->id,
                'status'  => $request->status,
            ],
            'ip_address'  => $request->ip(),
        ]);

        $statusMsg = $request->status === 'suspended'
            ? "Partner account '{$partner->contact_person}' suspended successfully. All gyms were previously reassigned."
            : "Partner account '{$partner->contact_person}' activated.";

        return response()->json([
            'success' => true,
            'message' => $statusMsg,
            'data'    => $partner
        ]);
    }

    /**
     * Reassign all or selected Gym Tenants from one Partner to another Partner
     */
    public function reassignTenants(Request $request, $id)
    {
        $sourcePartner = $this->resolvePartner($id);

        if (!$sourcePartner) {
            return response()->json([
                'success' => false,
                'message' => 'Source Partner not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'target_partner_id' => 'required|string',
            'tenant_ids'        => 'nullable|array', // If null/empty, reassign ALL tenants
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $targetPartner = $this->resolvePartner($request->target_partner_id);

        if (!$targetPartner) {
            return response()->json([
                'success' => false,
                'message' => 'Target Partner not found'
            ], 404);
        }

        if ($sourcePartner->getKey() === $targetPartner->getKey()) {
            return response()->json([
                'success' => false,
                'message' => 'Source and Target Partner cannot be the same account'
            ], 422);
        }

        $count = DB::connection('master')->transaction(function () use ($sourcePartner, $targetPartner, $request) {
            $query = Tenant::where('partner_id', $sourcePartner->getKey());

            if ($request->has('tenant_ids') && is_array($request->tenant_ids) && count($request->tenant_ids) > 0) {
                $decodedTenantIds = array_map(function ($tId) {
                    return is_numeric($tId) ? (int)$tId : HashIdService::decode($tId, 'TNT');
                }, $request->tenant_ids);

                $query->whereIn('id', $decodedTenantIds);
            }

            $affectedCount = $query->count();

            if ($affectedCount > 0) {
                $query->update(['partner_id' => $targetPartner->getKey()]);

                // Update gyms_created counters
                $sourcePartner->decrement('gyms_created', $affectedCount);
                $targetPartner->increment('gyms_created', $affectedCount);
            }

            return $affectedCount;
        });

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Developer',
            'actor_id'    => $request->user() ? $request->user()->getKey() : 1,
            'action'      => 'partner.tenants_reassigned',
            'target_type' => 'Partner',
            'target_id'   => $sourcePartner->getKey(),
            'payload'     => [
                'source_partner_id' => $sourcePartner->id,
                'target_partner_id' => $targetPartner->id,
                'reassigned_count'  => $count,
            ],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Successfully reassigned {$count} gym tenant(s) from '{$sourcePartner->contact_person}' to '{$targetPartner->contact_person}' with zero data loss or downtime.",
            'data'    => [
                'reassigned_count' => $count,
                'source_partner'   => $sourcePartner->fresh(),
                'target_partner'   => $targetPartner->fresh(),
            ]
        ]);
    }
}
