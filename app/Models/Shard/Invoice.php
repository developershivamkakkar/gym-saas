<?php

namespace App\Models\Shard;

class Invoice extends TenantModel
{
    protected $table = 'invoices';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'subscription_id',
        'invoice_number',
        'subtotal',
        'tax',
        'total',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function subscription()
    {
        return $this->belongsTo(MemberSubscription::class, 'subscription_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
