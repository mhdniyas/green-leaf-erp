<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\TrayMovement;
use App\Models\TrayType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrayMovementController extends Controller
{
    /**
     * Get daily tray table overview for selected date.
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        // Load active tray types
        $trayTypes = TrayType::query()->where('is_active', true)->orderBy('id')->get();

        // Also check if any inactive tray types have movements on this date
        $activeTypeIds = $trayTypes->pluck('id')->toArray();
        $dateTypeIds = TrayMovement::query()
            ->where('date', $date)
            ->where('sent_qty', '>', 0)
            ->pluck('tray_type_id')
            ->unique()
            ->toArray();

        $additionalTypeIds = array_diff($dateTypeIds, $activeTypeIds);
        if (! empty($additionalTypeIds)) {
            $extraTypes = TrayType::query()->whereIn('id', $additionalTypeIds)->get();
            $trayTypes = $trayTypes->concat($extraTypes)->sortBy('id')->values();
        }

        // Get shops list (all active shops or shops with order/movements)
        $shops = Shop::query()->orderBy('name')->get();

        // Fetch movements for the date
        $movements = TrayMovement::query()
            ->where('date', $date)
            ->get()
            ->groupBy('shop_id');

        $shopsData = [];
        foreach ($shops as $shop) {
            $shopMovements = $movements->get($shop->id, collect());
            $traysMap = $shopMovements->keyBy('tray_type_id');

            $traysList = [];
            $totalSent = 0;

            foreach ($trayTypes as $type) {
                $qty = (int) ($traysMap->has($type->id) ? $traysMap->get($type->id)->sent_qty : 0);
                $traysList[] = [
                    'tray_type_id' => $type->id,
                    'quantity' => $qty,
                ];
                $totalSent += $qty;
            }

            $shopsData[] = [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'trays' => $traysList,
                'total' => $totalSent,
            ];
        }

        return response()->json([
            'date' => $date,
            'tray_types' => $trayTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])->values(),
            'shops' => $shopsData,
        ]);
    }

    /**
     * Save/update sent tray loadout for a shop on a date.
     */
    public function saveLoadout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'date' => 'required|date_format:Y-m-d',
            'trays' => 'required|array',
            'trays.*.tray_type_id' => 'required|integer|exists:tray_types,id',
            'trays.*.quantity' => 'required|integer|min:0',
        ]);

        $shopId = (int) $validated['shop_id'];
        $date = $validated['date'];

        DB::transaction(function () use ($shopId, $date, $validated) {
            foreach ($validated['trays'] as $trayItem) {
                $typeId = (int) $trayItem['tray_type_id'];
                $qty = (int) $trayItem['quantity'];

                TrayMovement::updateOrCreate(
                    [
                        'shop_id' => $shopId,
                        'date' => $date,
                        'tray_type_id' => $typeId,
                    ],
                    [
                        'sent_qty' => $qty,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Tray loadout saved successfully',
        ]);
    }

    /**
     * Save/update returned tray quantities for a shop on a date.
     * Validates that return quantity does not exceed current shop balance.
     */
    public function saveReturn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'date' => 'required|date_format:Y-m-d',
            'trays' => 'required|array',
            'trays.*.tray_type_id' => 'required|integer|exists:tray_types,id',
            'trays.*.quantity' => 'required|integer|min:0',
        ]);

        $shopId = (int) $validated['shop_id'];
        $date = $validated['date'];

        // Validate return rule for each tray type
        foreach ($validated['trays'] as $trayItem) {
            $typeId = (int) $trayItem['tray_type_id'];
            $requestedReturnQty = (int) $trayItem['quantity'];

            if ($requestedReturnQty === 0) {
                continue;
            }

            // Total sent across all history
            $totalSent = (int) TrayMovement::where('shop_id', $shopId)
                ->where('tray_type_id', $typeId)
                ->sum('sent_qty');

            // Total returned across all history excluding current date record
            $otherReturned = (int) TrayMovement::where('shop_id', $shopId)
                ->where('tray_type_id', $typeId)
                ->where('date', '!=', $date)
                ->sum('returned_qty');

            $currentBalance = $totalSent - $otherReturned;

            if ($requestedReturnQty > $currentBalance) {
                throw ValidationException::withMessages([
                    'trays' => ['Return quantity is greater than current tray balance.'],
                ]);
            }
        }

        DB::transaction(function () use ($shopId, $date, $validated) {
            foreach ($validated['trays'] as $trayItem) {
                $typeId = (int) $trayItem['tray_type_id'];
                $qty = (int) $trayItem['quantity'];

                TrayMovement::updateOrCreate(
                    [
                        'shop_id' => $shopId,
                        'date' => $date,
                        'tray_type_id' => $typeId,
                    ],
                    [
                        'returned_qty' => $qty,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Tray return saved successfully',
        ]);
    }

    /**
     * Get current tray balance for a shop.
     */
    public function balance(Request $request): JsonResponse
    {
        $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
        ]);

        $shopId = (int) $request->input('shop_id');
        $shop = Shop::findOrFail($shopId);

        $trayTypes = TrayType::query()->where('is_active', true)->orderBy('id')->get();

        $movementsSummary = TrayMovement::query()
            ->select('tray_type_id', DB::raw('SUM(sent_qty) as total_sent'), DB::raw('SUM(returned_qty) as total_returned'))
            ->where('shop_id', $shopId)
            ->groupBy('tray_type_id')
            ->get()
            ->keyBy('tray_type_id');

        $traysResult = [];
        $totalBalance = 0;

        foreach ($trayTypes as $type) {
            $sent = 0;
            $returned = 0;

            if ($movementsSummary->has($type->id)) {
                $summary = $movementsSummary->get($type->id);
                $sent = (int) $summary->total_sent;
                $returned = (int) $summary->total_returned;
            }

            $bal = $sent - $returned;
            $totalBalance += $bal;

            $traysResult[] = [
                'tray_type_id' => $type->id,
                'name' => $type->name,
                'sent' => $sent,
                'returned' => $returned,
                'balance' => $bal,
            ];
        }

        return response()->json([
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'trays' => $traysResult,
            'total_balance' => $totalBalance,
        ]);
    }
}
