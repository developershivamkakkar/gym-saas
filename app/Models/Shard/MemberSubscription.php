<?php

namespace App\Models\Shard;

class MemberSubscription extends TenantModel
{
    protected $table = 'member_subscriptions';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'plan_id',
        'start_date',
        'end_date',
        'auto_renew',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_renew' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function freezes()
    {
        return $this->hasMany(SubscriptionFreeze::class, 'subscription_id');
    }

    public function events()
    {
        return $this->hasMany(SubscriptionEvent::class, 'subscription_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'subscription_id');
    }
}
