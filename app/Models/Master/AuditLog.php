<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'master';
    protected $table = 'audit_logs';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
