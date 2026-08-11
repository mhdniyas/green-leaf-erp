<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\StoreStockAdjustmentRequest;
use App\Http\Requests\Web\Inventory\EmptyWarehouseStockRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $service) {}

    public function store(StoreStockAdjustmentRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $warehouseId = $data['warehouse_id'] ?? null;
        if ($warehouseId !== null) {
            abort_unless(Warehouse::query()->active()->whereKey($warehouseId)->exists(), 422, 'Select an active warehouse.');
        }
        $notes = trim(implode(' — ', array_filter([
            $data['preset_reason'] ?? null,
            $data['notes'] ?? null,
        ])));
        $adjustment = $this->service->reconcile($product, (float) $data['system_qty'], (float) $data['counted_qty'], $data['business_date'], $notes, (int) $request->user()->id, $warehouseId);

        return redirect()->route('inventory.stock.index', array_filter(['date' => $data['business_date'], 'warehouse_id' => $warehouseId]))
            ->with('success', $adjustment ? 'Stock adjustment saved.' : 'Physical count matches the current stock. No adjustment was needed.');
    }

    public function emptyWarehouse(EmptyWarehouseStockRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($warehouse->is_active, 404);

        $adjustmentCount = $this->service->emptyWarehouse($warehouse, $data['business_date'], (int) $request->user()->id);

        return redirect()->route('inventory.stock.index', [
            'date' => $data['business_date'],
            'warehouse_id' => $warehouse->id,
        ])->with('success', $adjustmentCount > 0
            ? "{$warehouse->name} was emptied. Add the physical old stock back in the 10-product count pages."
            : "{$warehouse->name} already has no recorded stock.");
    }
}
