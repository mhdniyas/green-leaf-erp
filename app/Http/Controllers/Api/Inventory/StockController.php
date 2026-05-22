<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Repositories\Inventory\StockMovementRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $repository,
    ) {}

    /**
     * Current stock levels grouped by product and grade.
     */
    public function index(): JsonResponse
    {
        $stock = $this->repository->currentStockByProductAndGrade();

        return ApiResponse::success($stock);
    }

    /**
     * Movement log (paginated).
     */
    public function movements(): JsonResponse
    {
        $movements = $this->repository->paginateFiltered(20);

        return ApiResponse::paginated(StockMovementResource::collection($movements));
    }
}
