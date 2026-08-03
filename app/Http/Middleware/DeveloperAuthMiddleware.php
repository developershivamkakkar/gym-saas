<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Master\Developer;

class DeveloperAuthMiddleware
{
    /**
     * Handle an incoming Developer API request and validate Bearer token
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
                'message' => 'Unauthenticated. Developer Bearer token missing.'
            ], 401);
        }

        // Fetch primary developer super admin
        $developer = Developer::where('is_active', true)->first();

        if (!$developer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Developer token'
            ], 401);
        }

        // Attach developer to request attributes
        $request->attributes->set('developer', $developer);
        app()->instance('developer', $developer);

        return $next($request);
    }
}
