<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Services\HashIdService;

class Shard extends Model
{
    protected $connection = 'master';
    protected $table = 'shards';

    protected $fillable = [
        'name',
        'db_host',
        'db_port',
        'db_name',
        'db_user',
        'db_password',
        'max_tenants',
        'current_tenants',
        'is_active',
        'is_accepting_tenants',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_accepting_tenants' => 'boolean',
    ];

    public function toArray()
    {
        $array = parent::toArray();

        if (isset($array['id'])) {
            $array['id'] = HashIdService::encode((int) $this->attributes['id'], 'SHD');
        }

        return $array;
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
