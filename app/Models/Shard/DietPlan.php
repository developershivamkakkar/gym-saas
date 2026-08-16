<?php

namespace App\Models\Shard;

class DietPlan extends TenantModel
{
    protected $table = 'diet_plans';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'staff_id',
        'title',
        'description',
        'target_calories',
        'protein_grams',
        'carbs_grams',
        'fat_grams',
        'meals',
        'is_template',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'meals' => 'array',
        'is_template' => 'boolean',
        'target_calories' => 'integer',
        'protein_grams' => 'integer',
        'carbs_grams' => 'integer',
        'fat_grams' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
