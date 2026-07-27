<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Inventory\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $repository,
    ) {}

    public function paginate(int $perPage = 15, ?int $categoryId = null, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, $categoryId, $search, $status);
    }

    public function allActive(): Collection
    {
        return $this->repository->findAllActive();
    }

    public function create(ProductData $data): Product
    {
        $attributes = $data->toArray();
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

        $submittedUnits = collect($units)->pluck('unit')->all();

        $product->orderUnits()
            ->whereNotIn('unit', $submittedUnits)
            ->delete();

        foreach ($units as $unit) {
            $product->orderUnits()->updateOrCreate(
                ['unit' => $unit['unit']],
                [
                    'label' => $unit['label'],
                    'conversion_to_base' => $unit['conversion_to_base'],
                    'is_base' => $unit['is_base'],
                    'is_orderable' => $unit['is_orderable'],
                    'sort_order' => $unit['sort_order'],
                ],
            );
        }
    }
}
