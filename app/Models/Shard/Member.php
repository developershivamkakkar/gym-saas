<?php

namespace App\Models\Shard;

class Member extends TenantModel
{
    protected $table = 'members';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'member_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
