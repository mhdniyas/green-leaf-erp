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
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'period_mode' => ['nullable', 'in:month,day,custom'],
            'date' => ['nullable', 'date'],
            'day' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
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
        $sort = $validated['sort'] ?? 'code';

        $monthInput = (string) ($validated['month'] ?? '');
        $periodMode = (string) ($validated['period_mode'] ?? '');

        if ($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            $month = $monthInput;
        } elseif (! empty($validated['date'])) {
            $month = Carbon::parse((string) $validated['date'])->format('Y-m');
        } elseif (! empty($validated['day'])) {
            $month = Carbon::parse((string) $validated['day'])->format('Y-m');
        } elseif (! empty($validated['from'])) {
            $month = Carbon::parse((string) $validated['from'])->format('Y-m');
        } else {
            $month = today('Asia/Kolkata')->format('Y-m');
        }

        $monthCarbon = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $monthCarbon->copy()->startOfMonth()->toDateString();
        $monthEnd = $monthCarbon->copy()->endOfMonth()->toDateString();
        $prevMonth = $monthCarbon->copy()->subMonth()->format('Y-m');
        $nextMonth = $monthCarbon->copy()->addMonth()->format('Y-m');
        $monthTitle = $monthCarbon->format('F Y');

        if (! empty($validated['date'])) {
            $date = Carbon::parse((string) $validated['date'])->toDateString();
            $periodMode = $periodMode ?: 'day';
        } elseif (! empty($validated['day'])) {
            $date = Carbon::parse((string) $validated['day'])->toDateString();
            $periodMode = 'day';
        } elseif (! empty($validated['from'])) {
            $date = Carbon::parse((string) $validated['from'])->toDateString();
            $periodMode = 'custom';
        } elseif ($month === today('Asia/Kolkata')->format('Y-m')) {
            $date = today('Asia/Kolkata')->toDateString();
            $periodMode = $periodMode ?: 'month';
        } else {
            $date = $monthStart;
            $periodMode = $periodMode ?: 'month';
        }

        return $this->commonFilters($validated) + [
            'date' => $date,
            'month' => $month,
            'period_mode' => $periodMode,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
            'month_title' => $monthTitle,
            'start_date' => $date,
            'end_date' => $date,
            'from' => $date,
            'to' => $date,
            'search' => ! empty($validated['search']) ? trim((string) $validated['search']) : null,
            'sort' => in_array($sort, ['code', 'category'], true) ? $sort : 'code',
        ];
    }

    /** @return array<string, mixed> */
    public function comparisonFilters(): array
    {
        $validated = $this->validated();
        $today = now('Asia/Kolkata')->startOfDay();

        $monthInput = (string) ($validated['month'] ?? '');
        $periodMode = (string) ($validated['period_mode'] ?? '');

        if ($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            $month = $monthInput;
        } elseif (! empty($validated['date_b'])) {
            $month = Carbon::parse((string) $validated['date_b'])->format('Y-m');
        } elseif (! empty($validated['date_a'])) {
            $month = Carbon::parse((string) $validated['date_a'])->format('Y-m');
        } elseif (! empty($validated['date'])) {
            $month = Carbon::parse((string) $validated['date'])->format('Y-m');
        } elseif (! empty($validated['from'])) {
            $month = Carbon::parse((string) $validated['from'])->format('Y-m');
        } else {
            $month = today('Asia/Kolkata')->format('Y-m');
        }

        $monthCarbon = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $monthCarbon->copy()->startOfMonth()->toDateString();
        $monthEnd = $monthCarbon->copy()->endOfMonth()->toDateString();
        $prevMonth = $monthCarbon->copy()->subMonth()->format('Y-m');
        $nextMonth = $monthCarbon->copy()->addMonth()->format('Y-m');
        $monthTitle = $monthCarbon->format('F Y');

        if (! empty($validated['date_a']) && ! empty($validated['date_b'])) {
            $dateA = Carbon::parse($validated['date_a'])->toDateString();
            $dateB = Carbon::parse($validated['date_b'])->toDateString();
            $periodMode = $periodMode ?: 'custom';
        } elseif (! empty($validated['from']) && ! empty($validated['to'])) {
            $dateA = Carbon::parse($validated['from'])->toDateString();
            $dateB = Carbon::parse($validated['to'])->toDateString();
            $periodMode = 'custom';
        } elseif (! empty($validated['date'])) {
            $dateB = Carbon::parse($validated['date'])->toDateString();
            $dateA = Carbon::parse($dateB)->subDay()->toDateString();
            $periodMode = 'day';
        } elseif ($month === today('Asia/Kolkata')->format('Y-m')) {
            $dateA = $today->copy()->subDay()->toDateString();
            $dateB = $today->toDateString();
            $periodMode = $periodMode ?: 'month';
        } else {
            $dateA = $monthStart;
            $dateB = Carbon::parse($monthStart)->addDay()->toDateString();
            $periodMode = $periodMode ?: 'month';
        }

        return $this->commonFilters($validated) + [
            'date_a' => $dateA,
            'date_b' => $dateB,
            'date' => $dateB,
            'month' => $month,
            'period_mode' => $periodMode,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
            'month_title' => $monthTitle,
            'from' => $dateA,
            'to' => $dateB,
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
