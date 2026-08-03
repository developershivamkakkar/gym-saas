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
        'support_email',
        'support_phone',
    ];
}
