<?php

namespace App\Models\Shard;

class Branch extends TenantModel
{
    protected $table = 'branches';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'city',
        'phone',
        'is_main_branch',
    ];

    protected $casts = [
        'is_main_branch' => 'boolean',
    ];
}
