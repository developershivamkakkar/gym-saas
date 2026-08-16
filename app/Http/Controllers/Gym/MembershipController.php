<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Shard\MembershipPlan;
use App\Models\Shard\MemberSubscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Exception;

class MembershipController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function plans(Request $request)
    {
        $tenant = app('tenant');
        $plans = MembershipPlan::where('tenant_id', $tenant->id)->where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function createPlan(Request $request)
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'max_freeze_days' => 'nullable|integer|min:0',
        ]);

        $validated['tenant_id'] = $tenant->id;
        $plan = MembershipPlan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Membership plan created successfully',
            'data' => $plan,
        ], 201);
    }

    public function subscribe(Request $request, $memberId)
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'plan_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'auto_renew' => 'nullable|boolean',
        ]);

        try {
            $subscription = $this->subscriptionService->subscribe(
                $tenant->id,
                $memberId,
                $validated['plan_id'],
                $validated['start_date'] ?? null,
                $validated['auto_renew'] ?? false,
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => $subscription,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function renew(Request $request, $id)
    {
        $validated = $request->validate([
            'plan_id' => 'nullable|integer',
            'override_start_date' => 'nullable|date',
            'auto_renew' => 'nullable|boolean',
        ]);

        try {
            $subscription = $this->subscriptionService->renew(
                $id,
                $validated['plan_id'] ?? null,
                $validated['override_start_date'] ?? null,
                $validated['auto_renew'] ?? false,
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription renewed successfully',
                'data' => $subscription,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function freeze(Request $request, $id)
    {
        $validated = $request->validate([
            'freeze_start_date' => 'required|date',
            'requested_days' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        try {
            $freeze = $this->subscriptionService->freeze(
                $id,
                $validated['freeze_start_date'],
                $validated['requested_days'],
                $validated['reason'] ?? null,
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription frozen successfully',
                'data' => $freeze,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function unfreeze(Request $request, $id)
    {
        try {
            $subscription = $this->subscriptionService->unfreeze($id, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Subscription unfrozen successfully',
                'data' => $subscription,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function upgrade(Request $request, $id)
    {
        $validated = $request->validate([
            'new_plan_id' => 'required|integer',
        ]);

        try {
            $subscription = $this->subscriptionService->upgrade($id, $validated['new_plan_id'], auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Subscription upgraded successfully',
                'data' => $subscription,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
