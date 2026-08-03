<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Master\AuditLog;
use App\Services\HashIdService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List system-wide, partner-specific, or gym-specific audit logs with filtering
     */
    public function index(Request $request)
    {
        $query = AuditLog::query();

        // Filter by Action (e.g. 'partner.created', 'tenant.status_updated')
        if ($request->has('action') && !empty($request->input('action'))) {
            $query->where('action', $request->input('action'));
        }

        // Filter by Actor Type (e.g. 'Developer', 'Partner', 'Staff')
        if ($request->has('actor_type') && !empty($request->input('actor_type'))) {
            $query->where('actor_type', $request->input('actor_type'));
        }

        // Filter by Target Type ('Partner', 'Tenant', 'Shard')
        if ($request->has('target_type') && !empty($request->input('target_type'))) {
            $query->where('target_type', ucfirst($request->input('target_type')));
        }

        // Filter by Target ID (Partner ID, Tenant ID, Shard ID - supports Hash ID)
        if ($request->has('target_id') && !empty($request->input('target_id'))) {
            $targetId = $request->input('target_id');

            if (is_numeric($targetId)) {
                $numericId = (int) $targetId;
            } else {
                $prefix = strtoupper(explode('-', $targetId)[0] ?? '');
                $numericId = HashIdService::decode($targetId, $prefix);
            }

            if ($numericId) {
                $query->where('target_id', $numericId);
            }
        }

        // Search in Action, Target Type, or IP Address
        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('target_type', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('id', 'desc')->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $logs
        ]);
    }
}
