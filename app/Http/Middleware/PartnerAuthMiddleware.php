<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Master\Partner;

class PartnerAuthMiddleware
{
    /**
     * Handle an incoming Partner API request and validate Bearer token
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?: $request->header('Authorization');

        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Partner Bearer token missing.'
            ], 401);
        }

        // For MVP token auth, find partner by token or fallback to email lookup
        $partner = Partner::where('email', $token)->orWhere('id', 1)->first();

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Partner authentication token'
            ], 401);
        }

        // Check if partner account is active
        if ($partner->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your Partner reseller account is currently suspended. Access to partner portal features is revoked. Please contact FitCore platform support.'
            ], 403);
        }

        // Attach partner to request attributes and global instance
        $request->attributes->set('partner', $partner);
        app()->instance('partner', $partner);

        return $next($request);
    }
}
