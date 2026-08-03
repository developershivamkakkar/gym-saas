<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Master\Developer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Developer Super Admin Login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $developer = Developer::where('email', $request->email)->first();

        if (!$developer || !Hash::check($request->password, $developer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid developer credentials'
            ], 401);
        }

        if (!$developer->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Developer account is deactivated'
            ], 403);
        }

        $developer->update(['last_login_at' => now()]);

        $token = bin2hex(random_bytes(32));

        return response()->json([
            'success' => true,
            'message' => 'Developer authenticated successfully',
            'portal'  => 'developer',
            'token'   => $token,
            'data'    => [
                'id'    => $developer->id,
                'name'  => $developer->name,
                'email' => $developer->email,
                'role'  => $developer->role,
            ]
        ]);
    }

    /**
     * Refresh Token for Developer
     */
    public function refresh(Request $request)
    {
        $token = bin2hex(random_bytes(32));

        return response()->json([
            'success' => true,
            'message' => 'Developer token refreshed successfully',
            'portal'  => 'developer',
            'token'   => $token
        ]);
    }

    /**
     * Logout Developer
     */
    public function logout(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Developer logged out successfully'
        ]);
    }

    /**
     * Get authenticated developer profile
     */
    public function me(Request $request)
    {
        $developer = $request->attributes->get('developer');

        return response()->json([
            'success' => true,
            'data'    => $developer
        ]);
    }
}
