<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\MemberStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Shard\Invoice;
use App\Models\Shard\Member;
use App\Models\Shard\MembershipPlan;
use App\Models\Shard\MemberSubscription;
use App\Models\Shard\SubscriptionEvent;
use App\Models\Shard\SubscriptionFreeze;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class SubscriptionService
{
    /**
     * Subscribe a member to a plan for the first time or as a new term.
     */
    public function subscribe(int $tenantId, int $memberId, int $planId, ?string $startDate = null, bool $autoRenew = false, $actor = null): MemberSubscription
    {
        return DB::transaction(function () use ($tenantId, $memberId, $planId, $startDate, $autoRenew, $actor) {
            $plan = MembershipPlan::where('tenant_id', $tenantId)->where('is_active', true)->findOrFail($planId);
            $member = Member::where('tenant_id', $tenantId)->findOrFail($memberId);

            $start = $startDate ? Carbon::parse($startDate) : Carbon::today();
            $end = (clone $start)->addDays($plan->duration_days);

            $subscription = MemberSubscription::create([
                'tenant_id' => $tenantId,
                'member_id' => $memberId,
                'plan_id' => $planId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'auto_renew' => $autoRenew,
                'status' => SubscriptionStatus::ACTIVE->value,
            ]);

            $member->update(['status' => MemberStatus::ACTIVE->value]);

            $invoiceNumber = 'INV-' . strtoupper(uniqid());
            Invoice::create([
                'tenant_id' => $tenantId,
                'member_id' => $memberId,
                'subscription_id' => $subscription->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $plan->price,
                'tax' => 0.00,
                'total' => $plan->price,
                'status' => InvoiceStatus::UNPAID->value,
            ]);

            SubscriptionEvent::create([
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription->id,
                'event_type' => 'subscription.created',
                'old_status' => null,
                'new_status' => SubscriptionStatus::ACTIVE->value,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id' => $actor ? $actor->id : null,
                'metadata' => ['plan_name' => $plan->name, 'price' => $plan->price],
            ]);

            return $subscription;
        });
    }

    /**
     * Renew an existing subscription (Active, Past Due Grace Period, or Expired Re-join).
     */
    public function renew(int $subscriptionId, ?int $newPlanId = null, ?string $overrideStartDate = null, bool $autoRenew = false, $actor = null): MemberSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $newPlanId, $overrideStartDate, $autoRenew, $actor) {
            $oldSubscription = MemberSubscription::query()
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            $tenantId = $oldSubscription->tenant_id;
            $planId = $newPlanId ?? $oldSubscription->plan_id;
            $plan = MembershipPlan::where('tenant_id', $tenantId)->findOrFail($planId);

            $oldStatus = $oldSubscription->status;

            // Determine default start date
            if ($overrideStartDate) {
                $start = Carbon::parse($overrideStartDate);
            } elseif ($oldStatus === SubscriptionStatus::PAST_DUE->value) {
                $start = Carbon::parse($oldSubscription->end_date)->addDay();
            } elseif ($oldStatus === SubscriptionStatus::ACTIVE->value) {
                $start = Carbon::parse($oldSubscription->end_date)->addDay();
            } else {
                $start = Carbon::today();
            }

            $end = (clone $start)->addDays($plan->duration_days);

            $oldSubscription->update(['status' => SubscriptionStatus::RENEWED->value]);

            $newSubscription = MemberSubscription::create([
                'tenant_id' => $tenantId,
                'member_id' => $oldSubscription->member_id,
                'plan_id' => $planId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'auto_renew' => $autoRenew,
                'status' => SubscriptionStatus::ACTIVE->value,
            ]);

            Member::where('tenant_id', $tenantId)
                ->where('id', $oldSubscription->member_id)
                ->update(['status' => MemberStatus::ACTIVE->value]);

            $invoiceNumber = 'INV-' . strtoupper(uniqid());
            Invoice::create([
                'tenant_id' => $tenantId,
                'member_id' => $oldSubscription->member_id,
                'subscription_id' => $newSubscription->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $plan->price,
                'tax' => 0.00,
                'total' => $plan->price,
                'status' => InvoiceStatus::UNPAID->value,
            ]);

            SubscriptionEvent::create([
                'tenant_id' => $tenantId,
                'subscription_id' => $newSubscription->id,
                'event_type' => 'subscription.renewed',
                'old_status' => $oldStatus,
                'new_status' => SubscriptionStatus::ACTIVE->value,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id' => $actor ? $actor->id : null,
                'metadata' => [
                    'previous_subscription_id' => $oldSubscription->id,
                    'override_start_date' => $overrideStartDate,
                ],
            ]);

            return $newSubscription;
        });
    }

    /**
     * Freeze a subscription.
     */
    public function freeze(int $subscriptionId, string $freezeStartDate, int $requestedDays, ?string $reason = null, $actor = null): SubscriptionFreeze
    {
        return DB::transaction(function () use ($subscriptionId, $freezeStartDate, $requestedDays, $reason, $actor) {
            $subscription = MemberSubscription::query()
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            if ($subscription->status !== SubscriptionStatus::ACTIVE->value) {
                throw new Exception("Only active subscriptions can be frozen.");
            }

            $plan = $subscription->plan;
            $usedDays = SubscriptionFreeze::where('subscription_id', $subscriptionId)
                ->sum('actual_days_frozen');

            if (($usedDays + $requestedDays) > $plan->max_freeze_days) {
                throw new Exception("Requested freeze days exceed remaining freeze quota ({$plan->max_freeze_days} days max).");
            }

            $start = Carbon::parse($freezeStartDate);
            $requestedEnd = (clone $start)->addDays($requestedDays);

            $freeze = SubscriptionFreeze::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'freeze_start_date' => $start->toDateString(),
                'requested_end_date' => $requestedEnd->toDateString(),
                'requested_days' => $requestedDays,
                'reason' => $reason,
            ]);

            $subscription->update(['status' => SubscriptionStatus::FROZEN->value]);

            SubscriptionEvent::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'event_type' => 'subscription.frozen',
                'old_status' => SubscriptionStatus::ACTIVE->value,
                'new_status' => SubscriptionStatus::FROZEN->value,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id' => $actor ? $actor->id : null,
                'metadata' => ['requested_days' => $requestedDays],
            ]);

            return $freeze;
        });
    }

    /**
     * Unfreeze a subscription and extend the end date by actual frozen days.
     */
    public function unfreeze(int $subscriptionId, $actor = null): MemberSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $actor) {
            $subscription = MemberSubscription::query()
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            if ($subscription->status !== SubscriptionStatus::FROZEN->value) {
                throw new Exception("Only frozen subscriptions can be unfrozen.");
            }

            $freeze = SubscriptionFreeze::where('subscription_id', $subscriptionId)
                ->whereNull('actual_unfreeze_date')
                ->latest()
                ->firstOrFail();

            $today = Carbon::today();
            $freezeStart = Carbon::parse($freeze->freeze_start_date);
            $actualDaysFrozen = max(1, $freezeStart->diffInDays($today));

            $freeze->update([
                'actual_unfreeze_date' => $today->toDateString(),
                'actual_days_frozen' => $actualDaysFrozen,
            ]);

            $newEndDate = Carbon::parse($subscription->end_date)->addDays($actualDaysFrozen);

            $subscription->update([
                'end_date' => $newEndDate->toDateString(),
                'status' => SubscriptionStatus::ACTIVE->value,
            ]);

            SubscriptionEvent::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'event_type' => 'subscription.unfrozen',
                'old_status' => SubscriptionStatus::FROZEN->value,
                'new_status' => SubscriptionStatus::ACTIVE->value,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id' => $actor ? $actor->id : null,
                'metadata' => [
                    'actual_days_frozen' => $actualDaysFrozen,
                    'new_end_date' => $newEndDate->toDateString(),
                ],
            ]);

            return $subscription;
        });
    }

    /**
     * Upgrade a subscription to a higher plan using Term Replacement with prorated credit.
     */
    public function upgrade(int $subscriptionId, int $newPlanId, $actor = null): MemberSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $newPlanId, $actor) {
            $oldSubscription = MemberSubscription::query()
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            $tenantId = $oldSubscription->tenant_id;
            $newPlan = MembershipPlan::where('tenant_id', $tenantId)->findOrFail($newPlanId);
            $oldPlan = $oldSubscription->plan;

            $today = Carbon::today();
            $endDate = Carbon::parse($oldSubscription->end_date);
            $remainingDays = max(0, $today->diffInDays($endDate, false));

            $dailyRate = $oldPlan->price / max(1, $oldPlan->duration_days);
            $unusedCredit = round($dailyRate * $remainingDays, 2);

            $netPayable = max(0.00, round($newPlan->price - $unusedCredit, 2));

            $oldSubscription->update(['status' => SubscriptionStatus::UPGRADED->value]);

            $newEnd = (clone $today)->addDays($newPlan->duration_days);
            $newSubscription = MemberSubscription::create([
                'tenant_id' => $tenantId,
                'member_id' => $oldSubscription->member_id,
                'plan_id' => $newPlanId,
                'start_date' => $today->toDateString(),
                'end_date' => $newEnd->toDateString(),
                'auto_renew' => $oldSubscription->auto_renew,
                'status' => SubscriptionStatus::ACTIVE->value,
            ]);

            $invoiceNumber = 'INV-' . strtoupper(uniqid());
            Invoice::create([
                'tenant_id' => $tenantId,
                'member_id' => $oldSubscription->member_id,
                'subscription_id' => $newSubscription->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $newPlan->price,
                'tax' => 0.00,
                'total' => $netPayable,
                'status' => $netPayable == 0.00 ? InvoiceStatus::PAID->value : InvoiceStatus::UNPAID->value,
            ]);

            SubscriptionEvent::create([
                'tenant_id' => $tenantId,
                'subscription_id' => $newSubscription->id,
                'event_type' => 'subscription.upgraded',
                'old_status' => $oldSubscription->status,
                'new_status' => SubscriptionStatus::ACTIVE->value,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id' => $actor ? $actor->id : null,
                'metadata' => [
                    'old_plan_id' => $oldPlan->id,
                    'unused_credit' => $unusedCredit,
                    'net_payable' => $netPayable,
                ],
            ]);

            return $newSubscription;
        });
    }
}
