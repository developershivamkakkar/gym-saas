<?php

namespace App\Services;

use App\Models\Master\Shard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class ShardRouter
{
    /**
     * Connect the 'tenant' database connection dynamically to a specific shard ID or model
     */
    public function connectTenantToShard($tenantId, $shardIdOrModel): Shard
    {
        if ($shardIdOrModel instanceof Shard) {
            $shard = $shardIdOrModel;
        } else {
            $shard = Shard::find($shardIdOrModel) ?? Shard::first();
        }

        $driver = config('database.connections.master.driver', 'sqlite');

        if ($driver === 'sqlite') {
            $dbPath = database_path($shard->name . '.sqlite');
            if (!file_exists($dbPath)) {
                touch($dbPath);
            }
            config(['database.connections.tenant.database' => $dbPath]);
        } else {
            config([
                'database.connections.tenant.host'     => $shard->db_host,
                'database.connections.tenant.port'     => $shard->db_port,
                'database.connections.tenant.database' => $shard->db_name,
                'database.connections.tenant.username' => $shard->db_user,
                'database.connections.tenant.password' => $shard->db_password ?? '',
            ]);
        }

        DB::purge('tenant');

        return $shard;
    }

    /**
     * Get an available database shard that has capacity (< max_tenants).
     * If no shard has room, automatically provision a new shard database!
     */
    public function getAvailableShard(): Shard
    {
        // 1. Look for an existing active shard with free capacity
        $shard = Shard::where('is_active', true)
            ->where('is_accepting_tenants', true)
            ->whereRaw('current_tenants < max_tenants')
            ->orderBy('id', 'asc')
            ->first();

        if ($shard) {
            return $shard;
        }

        // 2. If all existing shards are full (reached 20 gyms), automatically create fitcore_shard_02, 03...
        return $this->createNewShard();
    }

    /**
     * Automatically provision a new Database Shard (fitcore_shard_XX)
     */
    public function createNewShard(): Shard
    {
        $lastShard = Shard::orderBy('id', 'desc')->first();
        $nextNumber = $lastShard ? ($lastShard->id + 1) : 1;
        $shardName = sprintf('fitcore_shard_%02d', $nextNumber);

        // A. Create physical database instance
        $driver = config('database.connections.master.driver', 'sqlite');

        if ($driver === 'sqlite') {
            $dbPath = database_path($shardName . '.sqlite');
            if (!file_exists($dbPath)) {
                touch($dbPath);
            }
        } else {
            // MySQL: execute CREATE DATABASE statement
            DB::connection('master')->statement("CREATE DATABASE IF NOT EXISTS `{$shardName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        }

        // B. Register the new shard in fitcore_master.shards
        $shard = Shard::create([
            'name'                 => $shardName,
            'db_host'              => config('database.connections.master.host', '127.0.0.1'),
            'db_port'              => config('database.connections.master.port', '3306'),
            'db_name'              => $shardName,
            'db_user'              => config('database.connections.master.username', 'root'),
            'db_password'          => config('database.connections.master.password', ''),
            'max_tenants'          => config('fitcore.default_shard_max_tenants', 20),
            'current_tenants'      => 0,
            'is_active'            => true,
            'is_accepting_tenants' => true,
        ]);

        // C. Run shard database migrations on the newly created shard DB
        config(['database.connections.tenant.database' => $driver === 'sqlite' ? database_path($shardName . '.sqlite') : $shardName]);
        DB::purge('tenant');
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/shard',
            '--force'    => true,
        ]);

        return $shard;
    }
}
