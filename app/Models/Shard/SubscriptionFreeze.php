<?php

namespace App\Models\Shard;

class SubscriptionFreeze extends TenantModel
{
    protected $table = 'subscription_freezes';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'freeze_start_date',
        'requested_end_date',
        'actual_unfreeze_date',
        'requested_days',
        'actual_days_frozen',
        'reason',
    ];

    protected $casts = [
        'freeze_start_date' => 'date',
        'requested_end_date' => 'date',
        'actual_unfreeze_date' => 'date',
        'requested_days' => 'integer',
        'actual_days_frozen' => 'integer',
    ];

    public function subscription()
    {
        return $this->belongsTo(MemberSubscription::class, 'subscription_id');
    }
}
