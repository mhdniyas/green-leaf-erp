<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\StoreStockAdjustmentRequest;
use App\Models\Product;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $service) {}

    public function store(StoreStockAdjustmentRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $adjustment = $this->service->reconcile($product, (float) $data['system_qty'], (float) $data['counted_qty'], $data['business_date'], $data['notes'], (int) $request->user()->id);

        return redirect()->route('inventory.stock.index', ['date' => $data['business_date']])
            ->with('success', $adjustment ? 'Stock adjustment saved.' : 'Physical count matches the current stock. No adjustment was needed.');
    }
}
