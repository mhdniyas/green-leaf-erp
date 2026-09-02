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
                'vendor_visibility' => $request->user()->vendorVisibility(),
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
            'vendor_visibility' => ['nullable', 'string', 'in:all,related'],
        ]);
        $categoryIds = array_values(array_map('intval', $validated['category_ids']));
        $vendorVisibility = in_array($validated['vendor_visibility'] ?? 'all', ['all', 'related'], true)
            ? $validated['vendor_visibility']
            : 'all';

        $request->user()->update([
            'assigned_category_ids' => $categoryIds === [] ? null : $categoryIds,
            'vendor_visibility' => $vendorVisibility,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchaser preferences saved.',
            'data' => [
                'assigned_category_ids' => $categoryIds,
                'vendor_visibility' => $vendorVisibility,
            ],
        ]);
    }
}
