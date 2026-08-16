<?php

namespace App\Models\Shard;

class GymConfig extends TenantModel
{
    protected $table = 'gym_configs';

    protected $fillable = [
        'tenant_id',
        'gym_name',
        'logo_url',
        'primary_color',
        'currency',
        'tax_rate',
        'member_prefix',
        'member_suffix',
        'next_member_number',
        'support_email',
        'support_phone',
    ];

    protected $casts = [
        'next_member_number' => 'integer',
        'tax_rate' => 'decimal:2',
    ];
}
