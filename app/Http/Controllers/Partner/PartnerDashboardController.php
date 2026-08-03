<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Master\Tenant;
use Illuminate\Http\Request;

class PartnerDashboardController extends Controller
{
    /**
     * Get Partner Dashboard Summary Metrics & Recent Gym Activity
     */
    public function index(Request $request)
    {
        $partner = $request->attributes->get('partner') ?: app('partner');
        $partner->refresh();

        $gymsQuery = Tenant::where('partner_id', $partner->getKey());

        $totalGyms = $gymsQuery->count();
        $activeGyms = (clone $gymsQuery)->where('status', 'active')->count();
        $trialGyms = (clone $gymsQuery)->where('status', 'trial')->count();
        $suspendedGyms = (clone $gymsQuery)->where('status', 'suspended')->count();

        $recentGyms = (clone $gymsQuery)->orderBy('id', 'desc')->take(5)->get();

        $quotaRemaining = max(0, $partner->gym_quota - $partner->gyms_created);
        $quotaPercentage = $partner->gym_quota > 0 
            ? round(($partner->gyms_created / $partner->gym_quota) * 100, 1) 
            : 100;

        return response()->json([
            'success' => true,
            'data'    => [
                'partner' => [
                    'id'             => $partner->id,
                    'company_name'   => $partner->company_name,
                    'contact_person' => $partner->contact_person,
                    'email'          => $partner->email,
                    'status'         => $partner->status,
                ],
                'quota' => [
                    'gym_quota'        => $partner->gym_quota,
                    'gyms_created'     => $partner->gyms_created,
                    'quota_remaining'  => $quotaRemaining,
                    'usage_percentage' => $quotaPercentage,
                ],
                'gyms' => [
                    'total'     => $totalGyms,
                    'active'    => $activeGyms,
                    'trial'     => $trialGyms,
                    'suspended' => $suspendedGyms,
                ],
                'recent_gyms' => $recentGyms,
            ]
        ]);
    }
}
