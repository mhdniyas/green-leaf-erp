<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Repositories\Inventory\StockMovementRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StockController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $repository,
    ) {}

    /**
     * Current stock levels grouped by product and grade.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            abort_unless(
                $user->hasRole(['admin', 'warehouse_receiver', 'warehouse', 'purchase', 'purchaser']) ||
                $user->can('inventory.stock.view') ||
                $user->can('inventory.product.view'),
                403,
                'Unauthorized to view inventory stock.'
            );
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $date = $validated['date'] ?? null;
        $search = trim((string) ($validated['search'] ?? ''));
        $warehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        // Warehouse scoping: If user is scoped to specific warehouses, restrict access
        if ($user && ! $user->hasRole('admin') && ! $user->hasAllWarehouseAccess()) {
            $assignedWarehouseIds = $user->warehouses()->pluck('warehouses.id')->all();
            if (! empty($assignedWarehouseIds)) {
                if ($warehouseId !== null) {
                    abort_unless(in_array($warehouseId, $assignedWarehouseIds, true), 403, 'Unauthorized warehouse access.');
                } else {
                    $warehouseId = count($assignedWarehouseIds) === 1 ? (int) $assignedWarehouseIds[0] : null;
                }
            }
        }

        $categoryIds = $validated['category_ids'] ?? ($user?->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : null);

        $stock = $this->repository->currentStockByProductAndGrade($date, $warehouseId, $categoryIds, $search);
        if (! empty($validated['category'])) {
            $category = (string) $validated['category'];
            $stock = $stock->filter(fn ($item): bool => (string) ($item->category_name ?? '') === $category)->values();
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $page = max(1, (int) $request->input('page', 1));
        $items = $stock->values();
        $paginated = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return ApiResponse::paginated($paginated);
    }

    /**
     * Movement log (paginated).
     */
    public function movements(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            abort_unless(
                $user->hasRole(['admin', 'warehouse_receiver', 'warehouse', 'purchase', 'purchaser']) ||
                $user->can('inventory.stock.view'),
                403,
                'Unauthorized to view stock movements.'
            );
        }

        $perPage = (int) $request->input('per_page', 20);
        $productId = $request->integer('product_id') ?: null;
        $movements = $this->repository->paginateFiltered($perPage, $productId);

        return ApiResponse::paginated(StockMovementResource::collection($movements));
    }
}
