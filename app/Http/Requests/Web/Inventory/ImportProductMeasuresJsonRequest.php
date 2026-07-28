<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ImportProductMeasuresJsonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.product.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'import_file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:2048'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function importedProducts(): array
    {
        $payload = $this->decodedPayload();
        $products = Arr::get($payload, 'products');

        if (! is_array($products)) {
            throw ValidationException::withMessages([
                'import_file' => 'JSON must contain a products array.',
            ]);
        }

        $matchedProducts = Product::query()
            ->whereIn('public_uuid', collect($products)->pluck('public_uuid')->filter()->all())
            ->orWhereIn('sku', collect($products)->pluck('sku')->filter()->all())
            ->get()
            ->flatMap(fn (Product $product): array => [
                'uuid:'.$product->public_uuid => $product,
                'sku:'.$product->sku => $product,
            ]);

        return collect($products)
            ->map(function (array $row, int $index) use ($matchedProducts): array {
                /** @var Product|null $product */
                $product = $matchedProducts->get('uuid:'.($row['public_uuid'] ?? ''))
                    ?? $matchedProducts->get('sku:'.($row['sku'] ?? ''));

                if (! $product) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Product at row '.($index + 1).' was not found by public_uuid or sku.',
                    ]);
                }

                $baseUnit = ProductUnit::normalizeUnit((string) ($row['base_unit'] ?? $product->unit));
                if (! in_array($baseUnit, ProductUnit::AVAILABLE_UNITS, true)) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Invalid base_unit for '.$product->sku.'.',
                    ]);
                }

                $measures = collect($row['measures'] ?? []);
                if ($measures->isEmpty()) {
                    $measures = collect([[
                        'unit' => $baseUnit,
                        'label' => strtoupper($baseUnit),
                        'conversion_to_base' => 1,
                        'is_base' => true,
                        'is_orderable' => true,
                    ]]);
                }

                $units = [$baseUnit => 1.0];
                $boxVariants = [];
                $visibleLabels = [];

                foreach ($measures as $measureIndex => $measure) {
                    if (! is_array($measure)) {
                        throw ValidationException::withMessages([
                            'import_file' => 'Invalid measure at '.$product->sku.' row '.($measureIndex + 1).'.',
                        ]);
                    }

                    $unit = ProductUnit::normalizeUnit((string) ($measure['unit'] ?? ''));
                    if (! in_array($unit, ProductUnit::AVAILABLE_UNITS, true)) {
                        throw ValidationException::withMessages([
                            'import_file' => 'Invalid unit for '.$product->sku.'.',
                        ]);
                    }

                    $label = trim((string) ($measure['label'] ?? strtoupper($unit)));
                    $conversion = $unit === $baseUnit
                        ? 1.0
                        : $this->nullablePositiveFloat($measure['conversion_to_base'] ?? null, $product->sku, $label);

                    if ($unit === 'box' && $unit !== $baseUnit && $conversion !== null) {
                        if (! array_key_exists('box', $units)) {
                            $units['box'] = $conversion;
                        } else {
                            $boxVariants[] = $conversion;
                        }
                    } else {
                        $units[$unit] = $conversion;
                    }

                    if ((bool) ($measure['is_orderable'] ?? true)) {
                        $visibleLabels[] = mb_strtolower($label);
                    }
                }

                if ($visibleLabels === []) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Product '.$product->sku.' must have at least one visible shop-owner unit.',
                    ]);
                }

                return [
                    'public_uuid' => $product->public_uuid,
                    'base_unit' => $baseUnit,
                    'units' => $units,
                    'box_variants' => array_values(array_unique($boxVariants)),
                    'visible_labels' => array_values(array_unique($visibleLabels)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedPayload(): array
    {
        $contents = $this->file('import_file')?->get();
        $payload = json_decode((string) $contents, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'import_file' => 'Upload a valid JSON export file.',
            ]);
        }

        return $payload;
    }

    private function nullablePositiveFloat(mixed $value, string $sku, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value <= 0) {
            throw ValidationException::withMessages([
                'import_file' => "{$sku} {$label} conversion must be a positive number.",
            ]);
        }

        return round((float) $value, 4);
    }
}
