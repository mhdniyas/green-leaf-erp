<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing;

use App\Http\Controllers\Controller;
use App\Models\Printing\PrintV1Preset;
use App\Models\Printing\PrintV1PresetBatch;
use App\Models\Printing\PrintV1Setting;
use App\Models\Shop;
use App\Models\Warehouse;
use App\Services\Printing\PrintV1MatrixBuilderService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintV1DashboardController extends Controller
{
    public function __construct(
        private readonly PrintV1MatrixBuilderService $matrixBuilder,
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    public function index(Request $request): View
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized access to Printing V1.');
        }

        $userId = $request->user()?->id;
        $date = $request->input('date', $this->matrixBuilder->defaultOperationalDate());

        $sortSettings = PrintV1Setting::getForSection('sort-sheet', $userId);
        $selectionSettings = PrintV1Setting::getForSection('selection', $userId);
        $gridSettings = PrintV1Setting::getForSection('segregation-grid', $userId);
        $portraitSettings = PrintV1Setting::getForSection('shop-wise-portrait', $userId);
        $shopSettings = PrintV1Setting::getForSection('shops', $userId);

        $presets = PrintV1Preset::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $batches = PrintV1PresetBatch::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::orderBy('name')->get();
        $totalActiveShops = Shop::where('status', 'active')->count();
        $totalInactiveShops = Shop::where('status', '!=', 'active')->count();

        return view('print-v1.dashboard', compact(
            'date',
            'sortSettings',
            'selectionSettings',
            'gridSettings',
            'portraitSettings',
            'shopSettings',
            'presets',
            'batches',
            'warehouses',
            'totalActiveShops',
            'totalInactiveShops'
        ));
    }
}
