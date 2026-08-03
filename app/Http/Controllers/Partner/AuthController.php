<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Authenticate Partner Reseller Account (Company or Individual)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $partner = Partner::where('email', strtolower($request->email))->first();

        if (!$partner || !Hash::check($request->password, $partner->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid partner credentials'
            ], 401);
        }

        if ($partner->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your Partner reseller account is currently suspended. Please contact FitCore platform support.'
            ], 403);
        }

        // Generate Bearer Token for MVP
        $token = hash('sha256', Str::random(40) . $partner->id . time());
        $partner->update(['updated_at' => now()]);

        $quotaRemaining = max(0, $partner->gym_quota - $partner->gyms_created);

        return response()->json([
            'success' => true,
            'message' => 'Partner authenticated successfully',
            'portal'  => 'partner',
            'token'   => $token,
            'data'    => [
                'id'              => $partner->id,
                'partner_type'    => $partner->partner_type,
                'company_name'    => $partner->company_name,
                'contact_person'  => $partner->contact_person,
                'email'           => $partner->email,
                'phone'           => $partner->phone,
                'gym_quota'       => $partner->gym_quota,
                'gyms_created'    => $partner->gyms_created,
                'quota_remaining' => $quotaRemaining,
                'status'          => $partner->status,
            ]
        ]);
    }

    /**
     * Get Authenticated Partner Profile & Quota Stats
     */
    public function me(Request $request)
    {
        $partner = $request->attributes->get('partner') ?: app('partner');

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner unauthenticated'
            ], 401);
        }

        $partner->refresh();
        $quotaRemaining = max(0, $partner->gym_quota - $partner->gyms_created);

        $partnerData = $partner->toArray();
        $partnerData['quota_remaining'] = $quotaRemaining;

        return response()->json([
            'success' => true,
            'data'    => $partnerData
        ]);
    }

    /**
     * Refresh Token
     */
    public function refresh(Request $request)
    {
        $partner = $request->attributes->get('partner') ?: app('partner');
        $newToken = hash('sha256', Str::random(40) . $partner->id . time());

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'token'   => $newToken
        ]);
    }

    /**
     * Partner Logout
     */
    public function logout()
    {
        return response()->json([
            'success' => true,
            'message' => 'Partner logged out successfully'
        ]);
    }
}
