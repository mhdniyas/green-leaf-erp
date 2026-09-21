<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Cashbook;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\TrayMovement;
use App\Models\TrayType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminTrayAssetController extends Controller
{
    /**
     * Display the Admin Cashbook -> Assets -> Trays main overview page.
     */
    public function index(Request $request): View
    {
        $trayTypes = TrayType::query()->orderBy('id')->get();

        $movementsSummary = TrayMovement::query()
            ->select('tray_type_id', DB::raw('SUM(sent_qty) as total_sent'), DB::raw('SUM(returned_qty) as total_returned'))
            ->groupBy('tray_type_id')
            ->get()
            ->keyBy('tray_type_id');

        $traysData = $trayTypes->map(function (TrayType $type) use ($movementsSummary) {
            $sent = 0;
            $returned = 0;

            if ($movementsSummary->has($type->id)) {
                $summary = $movementsSummary->get($type->id);
                $sent = (int) $summary->total_sent;
                $returned = (int) $summary->total_returned;
            }

            $withShops = max(0, $sent - $returned);
            $inWarehouse = (int) $type->total_owned - $withShops;

            return [
                'id' => $type->id,
                'name' => $type->name,
                'total_owned' => (int) $type->total_owned,
                'with_shops' => $withShops,
                'in_warehouse' => $inWarehouse,
                'is_active' => (bool) $type->is_active,
                'sent_total' => $sent,
                'returned_total' => $returned,
                'model' => $type,
            ];
        });

        $totalOwnedAll = (int) $traysData->sum('total_owned');
        $totalWithShopsAll = (int) $traysData->sum('with_shops');
        $totalInWarehouseAll = (int) $traysData->sum('in_warehouse');

        return view('admin.cashbook.assets.trays.index', [
            'trays' => $traysData,
            'totalOwned' => $totalOwnedAll,
            'totalWithShops' => $totalWithShopsAll,
            'totalInWarehouse' => $totalInWarehouseAll,
        ]);
    }

    /**
     * Store a newly created tray type from the Admin page.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'total_owned' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        TrayType::create([
            'name' => trim($validated['name']),
            'total_owned' => (int) $validated['total_owned'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.cashbook.assets.trays.index')
            ->with('success', "Tray type '{$validated['name']}' created successfully.");
    }

    /**
     * Display detailed tray type statistics and per-shop balance.
     */
    public function show(TrayType $trayType): View
    {
        $movements = TrayMovement::query()
            ->where('tray_type_id', $trayType->id)
            ->with('shop')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(50);

        // Calculate per-shop balances for this tray type
        $shopBalancesRaw = TrayMovement::query()
            ->where('tray_type_id', $trayType->id)
            ->select('shop_id', DB::raw('SUM(sent_qty) as total_sent'), DB::raw('SUM(returned_qty) as total_returned'))
            ->groupBy('shop_id')
            ->with('shop')
            ->get();

        $shopBalances = $shopBalancesRaw->map(function ($row) {
            $sent = (int) $row->total_sent;
            $returned = (int) $row->total_returned;
            $held = max(0, $sent - $returned);

            return [
                'shop_id' => $row->shop_id,
                'shop_name' => $row->shop?->name ?? 'Unknown Shop',
                'sent' => $sent,
                'returned' => $returned,
                'held' => $held,
            ];
        })->sortByDesc('held')->values();

        $totalSent = (int) $trayType->movements()->sum('sent_qty');
        $totalReturned = (int) $trayType->movements()->sum('returned_qty');
        $withShops = max(0, $totalSent - $totalReturned);
        $inWarehouse = (int) $trayType->total_owned - $withShops;

        return view('admin.cashbook.assets.trays.show', [
            'trayType' => $trayType,
            'totalOwned' => (int) $trayType->total_owned,
            'withShops' => $withShops,
            'inWarehouse' => $inWarehouse,
            'totalSent' => $totalSent,
            'totalReturned' => $totalReturned,
            'shopBalances' => $shopBalances,
            'movements' => $movements,
        ]);
    }

    /**
     * Show the form for editing a tray type.
     */
    public function edit(TrayType $trayType): View
    {
        return view('admin.cashbook.assets.trays.edit', [
            'trayType' => $trayType,
        ]);
    }

    /**
     * Update the specified tray type.
     */
    public function update(Request $request, TrayType $trayType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'total_owned' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $trayType->update([
            'name' => trim($validated['name']),
            'total_owned' => (int) $validated['total_owned'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.cashbook.assets.trays.index')
            ->with('success', "Tray type '{$trayType->name}' updated successfully.");
    }

    /**
     * Show form for editing shop daily tray sent/returned quantities for a specific date.
     */
    public function editShopDate(Shop $shop, string $date): View
    {
        $trayTypes = TrayType::query()->where('is_active', true)->orderBy('id')->get();

        $existingMovements = TrayMovement::query()
            ->where('shop_id', $shop->id)
            ->where('date', $date)
            ->get()
            ->keyBy('tray_type_id');

        $rows = $trayTypes->map(function (TrayType $type) use ($existingMovements) {
            $m = $existingMovements->get($type->id);
            $sent = (int) ($m ? $m->sent_qty : 0);
            $returned = (int) ($m ? $m->returned_qty : 0);
            $net = $sent - $returned;

            return [
                'tray_type_id' => $type->id,
                'tray_name' => $type->name,
                'sent' => $sent,
                'returned' => $returned,
                'net' => $net,
            ];
        });

        return view('admin.cashbook.assets.trays.shop-date-edit', [
            'shop' => $shop,
            'date' => $date,
            'rows' => $rows,
            'totalHeld' => $rows->sum('net'),
        ]);
    }

    /**
     * Update shop daily tray sent/returned quantities for a specific date.
     */
    public function updateShopDate(Request $request, Shop $shop, string $date): RedirectResponse
    {
        $validated = $request->validate([
            'trays' => 'required|array',
            'trays.*.tray_type_id' => 'required|integer|exists:tray_types,id',
            'trays.*.sent_qty' => 'required|integer|min:0',
            'trays.*.returned_qty' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($shop, $date, $validated) {
            foreach ($validated['trays'] as $item) {
                $typeId = (int) $item['tray_type_id'];
                $sent = (int) $item['sent_qty'];
                $returned = (int) $item['returned_qty'];

                TrayMovement::updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'date' => $date,
                        'tray_type_id' => $typeId,
                    ],
                    [
                        'sent_qty' => $sent,
                        'returned_qty' => $returned,
                    ]
                );
            }
        });

        return redirect()->route('admin.cashbook.assets.trays.index')
            ->with('success', "Daily tray data for {$shop->name} on {$date} updated successfully.");
    }
}
