<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchaser;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaserSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('purchaser'), 403);

        return response()->json([
            'success' => true,
            'data' => [
                'assigned_category_ids' => $request->user()->assignedCategoryIds(),
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'description']),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('purchaser'), 403);

        $validated = $request->validate([
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);
        $categoryIds = array_values(array_map('intval', $validated['category_ids']));
        $request->user()->update([
            'assigned_category_ids' => $categoryIds === [] ? null : $categoryIds,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchaser preferences saved.',
            'data' => ['assigned_category_ids' => $categoryIds],
        ]);
    }
}
