<?php

namespace App\Models\Shard;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Staff extends Authenticatable
{
    protected $connection = 'tenant';
    protected $table = 'staff';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant_isolation', function ($builder) {
            if (app()->bound('tenant')) {
                $tenant = app('tenant');
                $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : ($tenant->id ?? $tenant->getKey());
                if ($tenantId) {
                    $builder->where('staff.tenant_id', $tenantId);
                }
            }
        });

        static::creating(function ($model) {
            if (app()->bound('tenant') && !$model->tenant_id) {
                $tenant = app('tenant');
                $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : ($tenant->id ?? $tenant->getKey());
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
