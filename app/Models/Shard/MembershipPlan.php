<?php

namespace App\Models\Shard;

class MembershipPlan extends TenantModel
{
    protected $table = 'membership_plans';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'duration_days',
        'price',
        'max_freeze_days',
        'is_active',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'price' => 'decimal:2',
        'max_freeze_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subscriptions()
    {
        return $this->hasMany(MemberSubscription::class, 'plan_id');
    }
}
