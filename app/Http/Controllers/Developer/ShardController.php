<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Master\Shard;
use App\Models\Master\AuditLog;
use App\Services\ShardRouter;
use App\Services\HashIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShardController extends Controller
{
    /**
     * Helper to resolve Shard by numeric ID or Hash ID
     */
    protected function resolveShard($id): ?Shard
    {
        if (is_numeric($id)) {
            return Shard::find($id);
        }

        $numericId = HashIdService::decode($id, 'SHD');
        return $numericId ? Shard::find($numericId) : Shard::where('name', $id)->first();
    }

    /**
     * List all database shards with tenant counts and capacities
     */
    public function index()
    {
        $shards = Shard::withCount('tenants')->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $shards
        ]);
    }

    /**
     * Manually provision a new database shard
     */
    public function store(Request $request, ShardRouter $shardRouter)
    {
        $shard = $shardRouter->createNewShard();

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Developer',
            'actor_id'    => $request->user() ? $request->user()->getKey() : 1,
            'action'      => 'shard.provisioned',
            'target_type' => 'Shard',
            'target_id'   => $shard->id,
            'payload'     => ['hash_id' => $shard->hash_id, 'name' => $shard->name],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Database Shard '{$shard->name}' provisioned successfully",
            'data'    => $shard
        ], 201);
    }

    /**
     * Update max capacity for a database shard
     */
    public function updateCapacity(Request $request, $id)
    {
        $shard = $this->resolveShard($id);

        if (!$shard) {
            return response()->json([
                'success' => false,
                'message' => 'Shard not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'max_tenants' => 'required|integer|min:' . $shard->current_tenants,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $oldMax = $shard->max_tenants;
        $isAccepting = $shard->current_tenants < $request->max_tenants;

        $shard->update([
            'max_tenants'          => $request->max_tenants,
            'is_accepting_tenants' => $isAccepting,
        ]);

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Developer',
            'actor_id'    => $request->user() ? $request->user()->id : 1,
            'action'      => 'shard.capacity_updated',
            'target_type' => 'Shard',
            'target_id'   => $shard->id,
            'payload'     => ['hash_id' => $shard->hash_id, 'old_max' => $oldMax, 'new_max' => $request->max_tenants],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shard capacity updated successfully',
            'data'    => $shard
        ]);
    }
}
