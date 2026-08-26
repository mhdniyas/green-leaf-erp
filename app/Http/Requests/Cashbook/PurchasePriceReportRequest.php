<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

final class PurchasePriceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'period' => ['nullable', 'in:today,yesterday,week,month,custom'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'date_a' => ['nullable', 'date'],
            'date_b' => ['nullable', 'date'],
            'produce_type' => ['nullable', 'in:all,vegetables,fruits'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'purchaser_id' => ['nullable', 'integer', 'exists:users,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'grade' => ['nullable', 'in:A,B'],
            'price_group' => ['nullable', 'in:A,B,C'],
            'sort' => ['nullable', 'in:code,category'],
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', 'in:all,changed'],
        ];
    }

    /** @return array<string, mixed> */
    public function priceFilters(): array
    {
        $validated = $this->validated();
        $date = Carbon::parse($validated['date'] ?? now('Asia/Kolkata'))->toDateString();
        $sort = $validated['sort'] ?? 'code';

        return $this->commonFilters($validated) + [
            'date' => $date,
            'search' => ! empty($validated['search']) ? trim((string) $validated['search']) : null,
            'sort' => in_array($sort, ['code', 'category'], true) ? $sort : 'code',
        ];
    }

    /** @return array<string, mixed> */
    public function comparisonFilters(): array
    {
        $validated = $this->validated();
        $today = now('Asia/Kolkata')->startOfDay();

        return $this->commonFilters($validated) + [
            'date_a' => Carbon::parse($validated['date_a'] ?? $today->copy()->subDay())->toDateString(),
            'date_b' => Carbon::parse($validated['date_b'] ?? $today)->toDateString(),
            'price_group' => $validated['price_group'] ?? 'A',
            'search' => ! empty($validated['search']) ? trim((string) $validated['search']) : null,
            'view' => ($validated['view'] ?? 'all') === 'changed' ? 'changed' : 'all',
            'changed_only' => ($validated['view'] ?? 'all') === 'changed',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function commonFilters(array $validated): array
    {
        return [
            'warehouse_code' => match ($validated['produce_type'] ?? 'all') {
                'vegetables' => 'VEG-WH',
                'fruits' => 'FRT-WH',
                default => null,
            },
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'product_id' => isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            'purchaser_id' => isset($validated['purchaser_id']) ? (int) $validated['purchaser_id'] : null,
            'vendor_id' => isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null,
            'grade' => $validated['grade'] ?? null,
        ];
    }
}
