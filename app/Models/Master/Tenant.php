<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Services\HashIdService;

class Tenant extends Model
{
    protected $connection = 'master';
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'custom_domain',
        'partner_id',
        'shard_id',
        'plan_tier',
        'status',
        'trial_ends_at',
        'suspended_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'suspended_at'  => 'datetime',
    ];

    protected $appends = [
        'instance_url',
    ];

    /**
     * Compute full hosted URL for this Gym Tenant instance
     * e.g. https://gold-gym.fitcore.io (or custom domain)
     */
    public function getInstanceUrlAttribute(): string
    {
        if (!empty($this->custom_domain)) {
            return str_starts_with($this->custom_domain, 'http') ? $this->custom_domain : 'https://' . $this->custom_domain;
        }

        $mainDomain = config('fitcore.main_domain', 'fitcore.io');
        $scheme = app()->environment('local') ? 'http' : 'https';

        if (app()->environment('local')) {
            return "http://{$this->slug}.localhost:8000";
        }

        return "{$scheme}://{$this->slug}.{$mainDomain}";
    }

    /**
     * Get numeric integer primary key safely
     */
    public function getNumericId(): int
    {
        $rawId = $this->attributes['id'] ?? null;
        if (is_numeric($rawId)) {
            return (int) $rawId;
        }
        return HashIdService::decode($rawId, 'TNT') ?: (int) $rawId;
    }

    public function toArray()
    {
        $array = parent::toArray();

        if (isset($array['id']) && is_numeric($array['id'])) {
            $array['id'] = HashIdService::encode((int) $this->attributes['id'], 'TNT');
        }

        if (isset($array['partner_id']) && is_numeric($array['partner_id'])) {
            $array['partner_id'] = HashIdService::encode((int) $array['partner_id'], 'PRT');
        }

        if (isset($array['shard_id']) && is_numeric($array['shard_id'])) {
            $array['shard_id'] = HashIdService::encode((int) $array['shard_id'], 'SHD');
        }

        return $array;
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function shard()
    {
        return $this->belongsTo(Shard::class);
    }
}
