<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Warehouse\CancelWarehouseSaleRequest;
use App\Http\Requests\Web\Warehouse\StoreWarehouseCustomerRequest;
use App\Http\Requests\Web\Warehouse\StoreWarehouseSaleRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\WarehouseCustomer;
use App\Models\WarehouseSale;
use App\Services\Inventory\StockLedgerService;
use App\Services\Warehouse\WarehouseSalesAccessService;
use App\Services\Warehouse\WarehouseSaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseSalesController extends Controller
{
    public function __construct(
        private readonly WarehouseSalesAccessService $accessService,
        private readonly WarehouseSaleService $saleService,
        private readonly StockLedgerService $stockLedgerService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAuthorized($request);

        $user = $request->user();
        $allowedWarehouses = $this->accessService->allowedWarehousesForUser($user);

        if ($allowedWarehouses->isEmpty()) {
            abort(403, 'You do not have any assigned warehouses for Warehouse Sales.');
        }

        $selectedWarehouseId = $request->filled('warehouse_id')
            ? $request->integer('warehouse_id')
            : (int) $allowedWarehouses->first()->id;

        if (! $this->accessService->canUserSellFromWarehouse($user, $selectedWarehouseId)) {
            abort(403, 'You are not authorized for the selected warehouse.');
        }

        $date = $request->input('date', Carbon::today()->toDateString());
        $prevDate = Carbon::parse($date)->subDay()->toDateString();
        $nextDate = Carbon::parse($date)->addDay()->toDateString();

        $sales = WarehouseSale::query()
            ->with(['items.product', 'payments.moneyHolderUser', 'customer', 'soldBy', 'shop'])
            ->whereDate('business_date', $date)
            ->where('warehouse_id', $selectedWarehouseId)
            ->orderByDesc('id')
            ->get();

        $totalSalesAmount = (float) $sales->where('status', WarehouseSale::STATUS_CONFIRMED)->sum('total_amount');
        $confirmedCount = $sales->where('status', WarehouseSale::STATUS_CONFIRMED)->count();
        $cashSalesAmount = (float) $sales->where('status', WarehouseSale::STATUS_CONFIRMED)
            ->filter(fn (WarehouseSale $s) => $s->isCashSale())
            ->sum('total_amount');
        $otherSalesAmount = max(0.0, $totalSalesAmount - $cashSalesAmount);

        return view('warehouse.sales.index', [
            'sales' => $sales,
            'allowedWarehouses' => $allowedWarehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'date' => $date,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'totalSalesAmount' => $totalSalesAmount,
            'confirmedCount' => $confirmedCount,
            'cashSalesAmount' => $cashSalesAmount,
            'otherSalesAmount' => $otherSalesAmount,
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureAuthorized($request);

        $user = $request->user();
        $allowedWarehouses = $this->accessService->allowedWarehousesForUser($user);

        if ($allowedWarehouses->isEmpty()) {
            abort(403, 'You do not have any assigned warehouses for Warehouse Sales.');
        }

        $selectedWarehouseId = $request->filled('warehouse_id')
            ? $request->integer('warehouse_id')
            : (int) $allowedWarehouses->first()->id;

        if (! $this->accessService->canUserSellFromWarehouse($user, $selectedWarehouseId)) {
            $selectedWarehouseId = (int) $allowedWarehouses->first()->id;
        }

        $shops = Shop::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'contact_phone']);

        $customers = WarehouseCustomer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'phone']);

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'base_price']);

        $productIds = $products->pluck('id')->all();
        $stockMap = $this->stockLedgerService->availableSortedStockForProducts($productIds, $selectedWarehouseId);

        $productsWithStock = $products->map(function (Product $p) use ($stockMap) {
            $stock = (float) ($stockMap[$p->id] ?? 0);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit ?: 'kg',
                'price' => (float) ($p->base_price ?? 0),
                'available_stock' => $stock,
            ];
        })->values();

        $activeUsers = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $date = $request->input('date', Carbon::today()->toDateString());

        return view('warehouse.sales.create', [
            'allowedWarehouses' => $allowedWarehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'shops' => $shops,
            'customers' => $customers,
            'productsWithStock' => $productsWithStock,
            'activeUsers' => $activeUsers,
            'date' => $date,
        ]);
    }

    public function store(StoreWarehouseSaleRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $sale = $this->saleService->createSale($validated, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Sale {$sale->invoice_number} created successfully.",
                'sale' => $sale,
                'redirect_url' => route('warehouse.sales.show', $sale),
            ]);
        }

        return redirect()
            ->route('warehouse.sales.show', $sale)
            ->with('success', "Sale #{$sale->invoice_number} confirmed successfully.");
    }

    public function show(WarehouseSale $warehouseSale): View
    {
        $this->ensureAuthorized(request());

        $user = request()->user();
        if (! $this->accessService->canUserSellFromWarehouse($user, (int) $warehouseSale->warehouse_id) && ! $user->isMainAdmin()) {
            abort(403, 'You are not authorized to view sales from this warehouse.');
        }

        $warehouseSale->loadMissing([
            'items.product',
            'payments.moneyHolderUser',
            'warehouse',
            'customer',
            'soldBy',
            'cancelledBy',
            'shop',
        ]);

        return view('warehouse.sales.show', [
            'sale' => $warehouseSale,
        ]);
    }

    public function cancel(CancelWarehouseSaleRequest $request, WarehouseSale $warehouseSale): RedirectResponse
    {
        $validated = $request->validated();

        $this->saleService->cancelSale($warehouseSale, $request->user(), $validated['reason'] ?? null);

        return redirect()
            ->route('warehouse.sales.show', $warehouseSale)
            ->with('success', "Sale #{$warehouseSale->invoice_number} has been cancelled and stock restored.");
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $q = trim((string) $request->input('q', ''));

        $customers = WarehouseCustomer::query()
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->limit(20)
            ->get(['id', 'uuid', 'name', 'phone']);

        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }

    public function storeCustomer(StoreWarehouseCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customer = WarehouseCustomer::query()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Customer created successfully.',
            'customer' => $customer,
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $warehouseId = $request->integer('warehouse_id');
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'base_price']);

        $stockMap = $this->stockLedgerService->availableSortedStockForProducts($products->pluck('id')->all(), $warehouseId ?: null);

        $data = $products->map(function (Product $p) use ($stockMap) {
            $stock = (float) ($stockMap[$p->id] ?? 0);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit ?: 'kg',
                'price' => (float) ($p->base_price ?? 0),
                'available_stock' => $stock,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    private function ensureAuthorized(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (! $this->accessService->canUserMakeSales($user)) {
            if (($user->hasRole('admin') || $user->isMainAdmin()) && ! $this->accessService->isFeatureEnabled()) {
                abort(403, 'Warehouse Sales is currently disabled in Company Settings. Please go to Company Settings (/admin/company-settings) and turn on Warehouse Sales.');
            }

            abort(403, 'You do not have access to Warehouse Sales.');
        }
    }
}
