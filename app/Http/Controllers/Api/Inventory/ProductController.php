<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\StoreProductRequest;
use App\Http\Requests\Api\Inventory\UpdateProductRequest;
use App\Http\Resources\Inventory\ProductResource;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
    ) {}

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updated_after' => ['nullable', 'string'],
        ]);

        $result = $this->service->syncCatalogue($validated['updated_after'] ?? null);

        return ApiResponse::success($result);
    }

    public function index(Request $request): JsonResponse
    {
        $products = $this->service->paginate(
            perPage: 15,
            categoryId: $request->integer('category_id') ?: null,
            search: $request->string('search')->toString() ?: null,
        );

        return ApiResponse::paginated(ProductResource::collection($products));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->create(ProductData::fromRequest($request));

        return ApiResponse::success(new ProductResource($product->load('category')), 'Product created successfully', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success(new ProductResource($product->load('category')));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $updated = $this->service->update($product, ProductData::fromRequest($request));

        return ApiResponse::success(new ProductResource($updated->load('category')), 'Product updated successfully');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $this->service->delete($product);

        return ApiResponse::success(null, 'Product deleted successfully');
    }
}
