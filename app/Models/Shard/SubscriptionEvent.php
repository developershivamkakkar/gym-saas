<?php

namespace App\Models\Shard;

class SubscriptionEvent extends TenantModel
{
    protected $table = 'subscription_events';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'event_type',
        'old_status',
        'new_status',
        'actor_type',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function subscription()
    {
        return $this->belongsTo(MemberSubscription::class, 'subscription_id');
    }
}
