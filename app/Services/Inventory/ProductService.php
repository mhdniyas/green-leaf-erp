<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Repositories\Inventory\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $repository,
        private readonly ProductWarehouseResolver $warehouseResolver = new ProductWarehouseResolver,
    ) {}

    public function paginate(int $perPage = 15, ?int $categoryId = null, ?string $search = null, ?string $status = null, ?string $unit = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, $categoryId, $search, $status, $unit);
    }

    public function allActive(): Collection
    {
        return $this->repository->findAllActive();
    }

    public function create(ProductData $data): Product
    {
        $attributes = $data->toArray();
        $attributes['default_warehouse_id'] = $this->warehouseResolver->resolve(
            explicitWarehouse: $data->defaultWarehouseId,
            category: $data->categoryId,
        );

        if ($data->imageData) {
            $attributes['image'] = $this->storeImage($data->imageData);
        }

        return DB::transaction(function () use ($attributes, $data): Product {
            $product = $this->repository->create($attributes);
            $this->syncUnits($product, $data->units);

            return $product->fresh(['orderUnits']) ?? $product;
        });
    }

    public function update(Product $product, ProductData $data): Product
    {
        $wasActive = (bool) $product->is_active;
        $attributes = $data->toArray();
        if ($wasActive !== $data->isActive) {
            $attributes['status_changed_by'] = auth()->id();
            $attributes['status_changed_at'] = now();
        }

        if ($data->removeImage) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $attributes['image'] = null;
        } elseif ($data->imageData) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $attributes['image'] = $this->storeImage($data->imageData);
        }

        return DB::transaction(function () use ($product, $attributes, $data): Product {
            $updatedProduct = $this->repository->update($product, $attributes);
            $this->syncUnits($updatedProduct, $data->units);

            return $updatedProduct->fresh(['orderUnits']) ?? $updatedProduct;
        });
    }

    public function bulkUpdateMeasures(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return DB::transaction(function () use ($rows): int {
            $products = Product::query()
                ->with('orderUnits')
                ->whereIn('public_uuid', collect($rows)->pluck('public_uuid')->all())
                ->get()
                ->keyBy('public_uuid');

            $updated = 0;

            foreach ($rows as $row) {
                /** @var Product|null $product */
                $product = $products->get($row['public_uuid']);

                if (! $product) {
                    continue;
                }

                $product->update(['unit' => $row['base_unit']]);
                $this->syncUnits($product, $this->bulkMeasureUnits(
                    $row['base_unit'],
                    $row['units'],
                    $row['box_variants'] ?? [],
                    $row['visible_labels'] ?? [],
                ));
                $updated++;
            }

            return $updated;
        });
    }

    public function updateStatus(Product $product, bool $isActive, User $changedBy): Product
    {
        return $this->repository->update($product, [
            'is_active' => $isActive,
            'status_changed_by' => $changedBy->id,
            'status_changed_at' => now(),
        ]);
    }

    public function delete(Product $product): void
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $this->repository->delete($product);
    }

    private function storeImage(string $base64Data): string
    {
        preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type);
        $data = substr($base64Data, strpos($base64Data, ',') + 1);
        $type = strtolower($type[1] ?? 'png');
        $decoded = base64_decode($data);

        $filename = 'products/'.uniqid().'.'.$type;
        Storage::disk('public')->put($filename, $decoded);

        return $filename;
    }

    private function syncUnits(Product $product, array $units): void
    {
        if ($units === []) {
            $units = [[
                'unit' => $product->unit,
                'label' => strtoupper((string) $product->unit),
                'conversion_to_base' => 1.0,
                'is_base' => true,
                'is_orderable' => true,
                'sort_order' => 0,
            ]];
        }

        $existingUnits = $product->orderUnits()->get();
        $existingById = $existingUnits->keyBy('id');
        $existingByLabel = $existingUnits->keyBy(fn (ProductUnit $unit): string => mb_strtolower(trim((string) $unit->label)));

        $plannedRows = [];
        $keptIds = [];

        foreach ($units as $index => $unit) {
            $normalizedLabel = mb_strtolower(trim((string) $unit['label']));

            $existing = filled($unit['id'] ?? null)
                ? $existingById->get((int) $unit['id'])
                : $existingByLabel->get($normalizedLabel);

            $plannedRows[] = [
                'existing' => $existing,
                'attributes' => [
                    'unit' => $unit['unit'],
                    'label' => $unit['label'],
                    'conversion_to_base' => $unit['conversion_to_base'] ?? null,
                    'is_base' => $unit['is_base'],
                    'is_orderable' => $unit['is_orderable'],
                    'sort_order' => $unit['sort_order'] ?? $index,
                ],
            ];

            if ($existing) {
                $keptIds[] = (int) $existing->id;
            }
        }

        if ($keptIds !== []) {
            $product->orderUnits()->whereNotIn('id', $keptIds)->delete();
        } else {
            $product->orderUnits()->delete();
        }

        $rowsToUpdate = collect($plannedRows)
            ->filter(fn (array $row): bool => $row['existing'] instanceof ProductUnit)
            ->values();

        $rowsToCreate = collect($plannedRows)
            ->filter(fn (array $row): bool => ! ($row['existing'] instanceof ProductUnit))
            ->values();

        // Avoid transient UNIQUE(product_id, label) collisions (including label swaps)
        // by parking existing rows on temporary labels before assigning final labels.
        foreach ($rowsToUpdate as $rowIndex => $row) {
            /** @var ProductUnit $existing */
            $existing = $row['existing'];
            $existing->update([
                'label' => '__tmp_'.$product->id.'_'.$existing->id.'_'.$rowIndex,
            ]);
        }

        foreach ($rowsToUpdate as $row) {
            /** @var ProductUnit $existing */
            $existing = $row['existing'];
            $existing->update($row['attributes']);
        }

        foreach ($rowsToCreate as $row) {
            $product->orderUnits()->create($row['attributes']);
        }
    }

    private function bulkMeasureUnits(string $baseUnit, array $units, array $boxVariants = [], array $visibleLabels = []): array
    {
        $isVisible = function (string $label) use ($visibleLabels): bool {
            if ($visibleLabels === []) {
                return true;
            }

            return in_array(mb_strtolower(trim($label)), $visibleLabels, true);
        };

        $rows = collect(ProductUnit::AVAILABLE_UNITS)
            ->filter(fn (string $unit): bool => $unit === $baseUnit || array_key_exists($unit, $units))
            ->map(function (string $unit, int $index) use ($baseUnit, $units, $isVisible): array {
                $label = $unit === 'box' && $unit !== $baseUnit && filled($units[$unit] ?? null)
                    ? 'BOX '.$this->formatMeasureLabelNumber((float) $units[$unit]).' '.strtoupper($baseUnit)
                    : strtoupper($unit);

                return [
                    'unit' => $unit,
                    'label' => $label,
                    'conversion_to_base' => $unit === $baseUnit ? 1.0 : $units[$unit],
                    'is_base' => $unit === $baseUnit,
                    'is_orderable' => $isVisible($label),
                    'sort_order' => $index,
                ];
            });

        foreach ($boxVariants as $variant) {
            if ($baseUnit === 'box' || abs((float) ($units['box'] ?? 0) - (float) $variant) < 0.0001) {
                continue;
            }

            $label = 'BOX '.$this->formatMeasureLabelNumber((float) $variant).' '.strtoupper($baseUnit);

            $rows->push([
                'unit' => 'box',
                'label' => $label,
                'conversion_to_base' => (float) $variant,
                'is_base' => false,
                'is_orderable' => $isVisible($label),
                'sort_order' => $rows->count(),
            ]);
        }

        return $rows->values()->all();
    }

    private function formatMeasureLabelNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * @return array{products: array<int, array<string, mixed>>, deleted_or_inactive_ids: array<int, int>, sync_token: string, server_time: string}
     */
    public function syncCatalogue(?string $updatedAfter = null): array
    {
        $now = now()->toIso8601String();
        $query = Product::query()
            ->with(['category:id,name', 'orderUnits:id,product_id,unit,is_base,conversion_to_base'])
            ->ordered();

        if ($updatedAfter) {
            try {
                $updatedAfterDate = Carbon::parse($updatedAfter);
            } catch (\Throwable) {
                $updatedAfterDate = null;
            }

            if ($updatedAfterDate) {
                // Fetch changed/new active products
                $changedProducts = (clone $query)
                    ->where('is_active', true)
                    ->where('updated_at', '>', $updatedAfterDate)
                    ->get();

                // Fetch IDs of products that became inactive or were soft-deleted since updatedAfter
                $deletedOrInactiveIds = Product::withTrashed()
                    ->where('updated_at', '>', $updatedAfterDate)
                    ->where(function ($q) {
                        $q->whereNotNull('deleted_at')
                            ->orWhere('is_active', false);
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $productsData = $changedProducts->map(fn (Product $p) => $this->formatProductForSync($p))->values()->all();

                return [
                    'products' => $productsData,
                    'deleted_or_inactive_ids' => $deletedOrInactiveIds,
                    'sync_token' => $now,
                    'server_time' => $now,
                ];
            }
        }

        // Full Sync: All active non-deleted products
        $allActive = $query->where('is_active', true)->get();
        $productsData = $allActive->map(fn (Product $p) => $this->formatProductForSync($p))->values()->all();

        return [
            'products' => $productsData,
            'deleted_or_inactive_ids' => [],
            'sync_token' => $now,
            'server_time' => $now,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProductForSync(Product $product): array
    {
        $allowedUnits = $product->orderUnits->pluck('unit')->unique()->values()->all();
        if (empty($allowedUnits) && $product->unit) {
            $allowedUnits = [$product->unit];
        } elseif (! in_array($product->unit, $allowedUnits, true) && $product->unit) {
            array_unshift($allowedUnits, $product->unit);
        }

        return [
            'id' => $product->id,
            'public_uuid' => $product->public_uuid,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit ?? 'KG',
            'allowed_units' => $allowedUnits,
            'category' => $product->category?->name ?? 'General',
            'is_active' => (bool) $product->is_active,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
