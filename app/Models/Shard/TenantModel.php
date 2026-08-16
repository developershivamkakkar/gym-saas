<?php

namespace App\Models\Shard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * ==============================================================================
 * 🛡️ BASE SHARD TENANT MODEL (AUTOMATIC DATA ISOLATION ENGINE)
 * ==============================================================================
 *
 * Purpose:
 * Every model that belongs to a Gym Instance (e.g., Staff, Member, GymConfig, Branch)
 * MUST extend this TenantModel base class instead of Laravel's raw Eloquent Model.
 *
 * It provides two crucial security mechanisms:
 * 1. Configures `$connection = 'tenant'` so queries execute on the dynamically resolved shard DB.
 * 2. Applies an Eloquent Global Scope (`tenant_isolation`) appending `WHERE tenant_id = ?`
 *    to EVERY query, ensuring strict data isolation within pooled database shards (20 gyms per shard).
 * 3. Auto-populates `tenant_id` on new record creation.
 */
abstract class TenantModel extends Model
{
    // Force connection to the dynamically resolved 'tenant' PDO database instance
    protected $connection = 'tenant';

    /**
     * Boot function to register Eloquent global scopes and model lifecycle hooks.
     */
    protected static function booted(): void
    {
        // ----------------------------------------------------------------------
        // 1. GLOBAL SCOPE: Automatic `WHERE tenant_id = ?` Query Filter
        // ----------------------------------------------------------------------
        // Whenever any code calls Member::all(), Member::find(), or Staff::where(...),
        // Eloquent will automatically append: `WHERE table.tenant_id = app('tenant')->tenant_id`
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            if (app()->bound('tenant')) {
                $tenant = app('tenant');
                $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : ($tenant->id ?? $tenant->getKey());
                if ($tenantId) {
                    $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
                }
            }
        });

        // ----------------------------------------------------------------------
        // 2. CREATING HOOK: Auto-populate `tenant_id` on Model Save
        // ----------------------------------------------------------------------
        // Whenever a new record is created (e.g. Member::create([...])), Eloquent
        // automatically sets `$model->tenant_id = $tenantId`.
        // Developers do NOT need to pass 'tenant_id' manually!
        static::creating(function (Model $model) {
            if (app()->bound('tenant') && !$model->tenant_id) {
                $tenant = app('tenant');
                $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : ($tenant->id ?? $tenant->getKey());
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }
}
