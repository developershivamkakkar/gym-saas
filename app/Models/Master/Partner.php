<?php

namespace App\Models\Master;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Services\HashIdService;

class Partner extends Authenticatable
{
    use Notifiable;

    protected $connection = 'master';
    protected $table = 'partners';

    protected $fillable = [
        'partner_type',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'password',
        'gym_quota',
        'gyms_created',
        'status',
        'notes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Transform model to array for JSON responses:
     * Overrides 'id' to output hashed string e.g. "PRT-6EKOS5"
     */
    public function toArray()
    {
        $array = parent::toArray();

        if (isset($array['id'])) {
            $array['id'] = HashIdService::encode((int) $this->attributes['id'], 'PRT');
        }

        return $array;
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
