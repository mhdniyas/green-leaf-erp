<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Inventory\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
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

        return $this->repository->create($attributes);
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

        return $this->repository->update($product, $attributes);
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
}
