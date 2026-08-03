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
                $builder->where('staff.tenant_id', $tenant->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (app()->bound('tenant') && !$model->tenant_id) {
                $tenant = app('tenant');
                $model->tenant_id = $tenant->tenant_id;
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
