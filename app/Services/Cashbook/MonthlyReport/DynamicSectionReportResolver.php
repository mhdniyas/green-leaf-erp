<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\PurchaseProductFilter;
use App\Services\Cashbook\MonthlyReport\DTO\SectionReportDefinition;
use Illuminate\Support\Collection;

final class DynamicSectionReportResolver
{
    /**
     * Resolve all active reportable sections dynamically from configuration.
     *
     * @return Collection<string, SectionReportDefinition>
     */
    public function resolveActiveSections(): Collection
    {
        $sections = collect();

        // 1. Trading / Product sections from active PurchaseProductFilters
        $activeFilters = PurchaseProductFilter::query()
            ->active()
            ->with(['filterItems'])
            ->orderBy('id')
            ->get();

        foreach ($activeFilters as $filter) {
            $key = 'filter_'.$filter->id;
            $sections->put($key, new SectionReportDefinition(
                id: (string) $filter->id,
                key: $key,
                name: (string) $filter->name,
                type: 'trading',
                productIds: $filter->getProductIds(),
                monthlyReportGroup: $filter->monthly_report_group,
                enabled: (bool) $filter->is_active,
                sortOrder: (int) $filter->id,
                description: 'Product category section for '.$filter->name
            ));
        }

        return $sections;
    }

    /**
     * Find a section definition by key.
     */
    public function findSectionByKey(string $key): ?SectionReportDefinition
    {
        return $this->resolveActiveSections()->get($key);
    }
}
