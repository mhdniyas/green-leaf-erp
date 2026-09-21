<?php

declare(strict_types=1);

namespace App\Queries\Purchasing\V2;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PurchaserProductSearchQuery
{
    /**
     * Search products with bounded limits, category security, and minimal fields.
     *
     * @return array{
     *     items: array<int, array{
     *         id: int,
     *         sku: string,
     *         name: string,
     *         unit: string,
     *         category_id: int,
     *         category_name: string
     *     }>,
     *     pagination: array{
     *         current_page: int,
     *         per_page: int,
     *         total_count: int,
     *         has_more: bool
     *     }
     * }
     */
    public function search(
        string $keyword,
        User $user,
        ?int $categoryId = null,
        int $perPage = 25,
        int $page = 1,
    ): array {
        $perPage = min(50, max(5, $perPage));
        $page = max(1, $page);
        $keyword = trim($keyword);

        $query = Product::query()
            ->select(['products.id', 'products.sku', 'products.name', 'products.unit', 'products.category_id'])
            ->with(['category:id,name'])
            ->where('products.is_active', true)
            ->where('products.show_in_purchaser_order', true);

        // Enforce user category restriction
        if ($user->hasAssignedCategoryFilter()) {
            $query->whereIn('products.category_id', $user->assignedCategoryIds());
        }

        // Optional category filter
        if ($categoryId !== null && $categoryId > 0) {
            $query->where('products.category_id', $categoryId);
        }

        // Search by name or SKU prefix
        if ($keyword !== '') {
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('products.name', 'like', "%{$keyword}%")
                    ->orWhere('products.sku', 'like', "{$keyword}%");
            });
        }

        $paginator = $query->orderBy('products.name')
            ->paginate(perPage: $perPage, page: $page);

        $items = [];
        foreach ($paginator->items() as $product) {
            $items[] = [
                'id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'name' => (string) $product->name,
                'unit' => (string) $product->unit,
                'category_id' => (int) $product->category_id,
                'category_name' => (string) ($product->category?->name ?? 'General'),
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total_count' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
