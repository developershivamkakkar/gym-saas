<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Shard\GymConfig;
use App\Models\Shard\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $tenant = app('tenant');
        $query = Member::where('tenant_id', $tenant->id);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('member_code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $members = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $members->items(),
            'pagination' => [
                'total' => $members->total(),
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'branch_id' => 'nullable|integer',
            'member_code' => 'nullable|string|max:50',
            'member_prefix' => 'nullable|string|max:20',
            'member_suffix' => 'nullable|string|max:20',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'required|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
        ]);

        $validated['tenant_id'] = $tenant->id;
        $validated['status'] = 'active';

        // Dynamic Member Code Generation Logic (Prefix + Number + Suffix)
        if (!empty($validated['member_code'])) {
            // Owner/Staff explicitly provided a custom member code (e.g. SVS-1001-VIP, SFP-88)
            $memberCode = strtoupper(trim($validated['member_code']));
        } else {
            // Resolve Prefix: Request ➔ GymConfig ➔ Default 'SVS'
            $prefix = !empty($validated['member_prefix']) 
                ? strtoupper(trim($validated['member_prefix']))
                : null;

            if (!$prefix) {
                $config = GymConfig::where('tenant_id', $tenant->id)->first();
                $prefix = $config && $config->member_prefix ? strtoupper($config->member_prefix) : 'SVS';
            }

            // Resolve Optional Suffix: Request ➔ GymConfig ➔ None
            $suffix = '';
            if (!empty($validated['member_suffix'])) {
                $suffix = '-' . strtoupper(trim($validated['member_suffix']));
            } else {
                $config = GymConfig::where('tenant_id', $tenant->id)->first();
                if ($config && $config->member_suffix) {
                    $suffix = '-' . strtoupper($config->member_suffix);
                }
            }

            // Fetch & increment sequential number
            $config = GymConfig::where('tenant_id', $tenant->id)->first();
            if ($config) {
                $num = $config->next_member_number ?? 1001;
                $memberCode = "{$prefix}-{$num}{$suffix}";
                $config->increment('next_member_number');
            } else {
                $randomHash = strtoupper(substr(md5(uniqid()), 0, 5));
                $memberCode = "{$prefix}-{$randomHash}{$suffix}";
            }
        }

        unset($validated['member_prefix'], $validated['member_suffix']);
        $validated['member_code'] = $memberCode;

        $member = Member::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Member registered successfully with Code [{$memberCode}]",
            'data' => $member,
        ], 201);
    }

    public function show($id)
    {
        $tenant = app('tenant');
        $member = Member::where('tenant_id', $tenant->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $member,
        ]);
    }

    public function update(Request $request, $id)
    {
        $tenant = app('tenant');
        $member = Member::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'branch_id' => 'nullable|integer',
            'member_code' => 'nullable|string|max:50',
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'sometimes|required|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'status' => 'nullable|in:active,inactive,disabled,terminated',
        ]);

        $member->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Member updated successfully',
            'data' => $member,
        ]);
    }
}
