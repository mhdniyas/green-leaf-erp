<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\DTOs\Purchasing\SupplierData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchasing\StoreSupplierRequest;
use App\Http\Requests\Api\Purchasing\UpdateSupplierRequest;
use App\Http\Resources\Purchasing\SupplierResource;
use App\Models\Supplier;
use App\Services\Purchasing\SupplierService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $service,
    ) {}

    public function index(): JsonResponse
    {
        $suppliers = $this->service->paginate();

        return ApiResponse::paginated($suppliers);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->service->create(SupplierData::fromRequest($request));

        return ApiResponse::success(new SupplierResource($supplier), 'Supplier created successfully', 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return ApiResponse::success(new SupplierResource($supplier));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $updated = $this->service->update($supplier, SupplierData::fromRequest($request));

        return ApiResponse::success(new SupplierResource($updated), 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        Gate::authorize('delete', $supplier);
        $this->service->delete($supplier);

        return ApiResponse::success(null, 'Supplier deleted successfully');
    }
}
