<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Shard\DietPlan;
use Illuminate\Http\Request;

class DietPlanController extends Controller
{
    public function index(Request $request)
    {
        $tenant = app('tenant');
        $query = DietPlan::where('tenant_id', $tenant->id)->with(['member', 'staff']);

        if ($request->has('member_id')) {
            $query->where('member_id', $request->get('member_id'));
        }

        if ($request->has('is_template')) {
            $query->where('is_template', filter_var($request->get('is_template'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $dietPlans = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $dietPlans->items(),
            'pagination' => [
                'total' => $dietPlans->total(),
                'current_page' => $dietPlans->currentPage(),
                'last_page' => $dietPlans->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'member_id' => 'nullable|integer',
            'staff_id' => 'nullable|integer',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'target_calories' => 'nullable|integer|min:0',
            'protein_grams' => 'nullable|integer|min:0',
            'carbs_grams' => 'nullable|integer|min:0',
            'fat_grams' => 'nullable|integer|min:0',
            'meals' => 'nullable|array',
            'is_template' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $validated['tenant_id'] = $tenant->id;
        $validated['is_template'] = $validated['is_template'] ?? false;

        $dietPlan = DietPlan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Diet plan created successfully',
            'data' => $dietPlan,
        ], 201);
    }

    public function show($id)
    {
        $tenant = app('tenant');
        $dietPlan = DietPlan::where('tenant_id', $tenant->id)
            ->with(['member', 'staff'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $dietPlan,
        ]);
    }

    public function update(Request $request, $id)
    {
        $tenant = app('tenant');
        $dietPlan = DietPlan::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'target_calories' => 'nullable|integer|min:0',
            'protein_grams' => 'nullable|integer|min:0',
            'carbs_grams' => 'nullable|integer|min:0',
            'fat_grams' => 'nullable|integer|min:0',
            'meals' => 'nullable|array',
            'is_template' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $dietPlan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Diet plan updated successfully',
            'data' => $dietPlan,
        ]);
    }

    public function destroy($id)
    {
        $tenant = app('tenant');
        $dietPlan = DietPlan::where('tenant_id', $tenant->id)->findOrFail($id);
        $dietPlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Diet plan deleted successfully',
        ]);
    }

    public function assignToMember(Request $request, $id)
    {
        $tenant = app('tenant');
        $template = DietPlan::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'member_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $memberDiet = DietPlan::create([
            'tenant_id' => $tenant->id,
            'member_id' => $validated['member_id'],
            'staff_id' => auth()->id(),
            'title' => $template->title,
            'description' => $template->description,
            'target_calories' => $template->target_calories,
            'protein_grams' => $template->protein_grams,
            'carbs_grams' => $template->carbs_grams,
            'fat_grams' => $template->fat_grams,
            'meals' => $template->meals,
            'is_template' => false,
            'start_date' => $validated['start_date'] ?? now()->toDateString(),
            'end_date' => $validated['end_date'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Diet plan assigned to member successfully',
            'data' => $memberDiet,
        ], 201);
    }
}
