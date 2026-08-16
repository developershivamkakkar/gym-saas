<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Shard\GymConfig;
use Illuminate\Http\Request;

class GymConfigController extends Controller
{
    /**
     * Get current gym configuration
     */
    public function show()
    {
        $tenant = app('tenant');
        $config = GymConfig::where('tenant_id', $tenant->id)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Gym configuration not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Update gym configuration
     */
    public function update(Request $request)
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'gym_name' => 'sometimes|string|max:100',
            'logo_url' => 'nullable|string|url',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'currency' => 'sometimes|string|max:10',
            'tax_rate' => 'sometimes|numeric|between:0,100',
            'member_prefix' => 'sometimes|string|max:20',
            'member_suffix' => 'nullable|string|max:20',
            'support_email' => 'nullable|email|max:150',
            'support_phone' => 'nullable|string|max:20',
        ]);

        $config = GymConfig::where('tenant_id', $tenant->id)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Gym configuration not found',
            ], 404);
        }

        $config->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Gym configuration updated successfully',
            'data' => $config,
        ]);
    }
}
