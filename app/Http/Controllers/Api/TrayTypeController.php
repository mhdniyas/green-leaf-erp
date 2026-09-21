<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrayType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrayTypeController extends Controller
{
    /**
     * Get list of tray types.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TrayType::query();

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $types = $query->orderBy('id')->get();

        return response()->json([
            'data' => $types->map(fn (TrayType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'total_owned' => (int) $type->total_owned,
                'with_shops' => $type->with_shops,
                'in_warehouse' => $type->in_warehouse,
                'is_active' => (bool) $type->is_active,
            ]),
        ]);
    }

    /**
     * Create a new tray type.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'total_owned' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $trayType = TrayType::create([
            'name' => trim($validated['name']),
            'total_owned' => (int) ($validated['total_owned'] ?? 0),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tray type created successfully',
            'data' => [
                'id' => $trayType->id,
                'name' => $trayType->name,
                'total_owned' => (int) $trayType->total_owned,
                'is_active' => (bool) $trayType->is_active,
            ],
        ], 201);
    }

    /**
     * Update an existing tray type (rename, total_owned, or enable/disable).
     */
    public function update(Request $request, TrayType $trayType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'total_owned' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if (array_key_exists('name', $validated)) {
            $trayType->name = trim($validated['name']);
        }

        if (array_key_exists('total_owned', $validated)) {
            $trayType->total_owned = (int) $validated['total_owned'];
        }

        if (array_key_exists('is_active', $validated)) {
            $trayType->is_active = (bool) $validated['is_active'];
        }

        $trayType->save();

        return response()->json([
            'success' => true,
            'message' => 'Tray type updated successfully',
            'data' => [
                'id' => $trayType->id,
                'name' => $trayType->name,
                'total_owned' => (int) $trayType->total_owned,
                'with_shops' => $trayType->with_shops,
                'in_warehouse' => $trayType->in_warehouse,
                'is_active' => (bool) $trayType->is_active,
            ],
        ]);
    }
}
