<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\DTOs\Inventory\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\StoreCategoryRequest;
use App\Http\Requests\Api\Inventory\UpdateCategoryRequest;
use App\Http\Resources\Inventory\CategoryResource;
use App\Models\Category;
use App\Services\Inventory\CategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
    ) {}

    public function index(): JsonResponse
    {
        $categories = $this->service->paginate();

        return ApiResponse::paginated($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create(CategoryData::fromRequest($request));

        return ApiResponse::success(new CategoryResource($category), 'Category created successfully', 201);
    }

    public function show(Category $category): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $updated = $this->service->update($category, CategoryData::fromRequest($request));

        return ApiResponse::success(new CategoryResource($updated), 'Category updated successfully');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);
        $this->service->delete($category);

        return ApiResponse::success(null, 'Category deleted successfully');
    }
}
