<?php

namespace App\Models\Shard;

class Payment extends TenantModel
{
    protected $table = 'payments';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'amount',
        'payment_method',
        'transaction_reference',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
