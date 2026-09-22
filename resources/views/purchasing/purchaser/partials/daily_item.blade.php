@php
    $step = $summary['unit'] === 'kg' ? '0.5' : '1';
    $isDirectCatalog = (bool) ($summary['is_direct_catalog'] ?? false);
    $isGradeBCatalog = (bool) ($summary['is_grade_b_catalog'] ?? false);
@endphp
<article class="relative min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex min-w-0 items-center justify-between gap-2 px-2.5 py-2">
        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 items-center gap-2">
                <h2 class="truncate text-sm font-black text-slate-950">{{ $summary['product_name'] }}</h2>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.1em] text-slate-600">{{ $summary['category_name'] ?: 'Other' }}</span>
            </div>
            <div class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5 text-[10px] font-black text-slate-500">
                @if ($isDirectCatalog)
                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-blue-700">Add-on purchase</span>
                @else
                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-blue-700">Need {{ number_format($summary['total_approved_qty'], 2) }} {{ $summary['unit'] }}</span>
                    @if ($summary['bought_qty'] > 0)
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Bought {{ number_format($summary['bought_qty'], 2) }}</span>
                    @endif
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">Left {{ number_format($summary['remaining_qty'], 2) }} {{ $summary['unit'] }}</span>
                @endif
                @if ($summary['draft_qty'] > 0)
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Cart {{ number_format($summary['draft_qty'], 2) }}</span>
                @endif
                @if ($summary['bought_qty'] > 0 && $summary['remaining_qty'] > 0)
                    <span class="rounded-full bg-cyan-50 px-2 py-0.5 text-cyan-700">Partial</span>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            @unless ($isGradeBCatalog || $isDirectCatalog)
                <button type="button" onclick="openDemandDetailsModal({{ $summary['product_id'] }})" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-black text-slate-700 shadow-sm transition-all hover:bg-slate-100">
                    <span>Demand</span>
                </button>
            @endunless
            @if ($isGradeBCatalog || $summary['remaining_qty'] > 0)
                <button type="button" onclick="openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $step }}', '{{ addslashes(implode(', ', $summary['draft_purchasers'] ?? [])) }}', '{{ $isDirectCatalog ? 'green_leaf_direct_purchase' : 'shop_order' }}')" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-teal-600 px-2.5 text-[11px] font-black text-white shadow-sm transition-all hover:bg-teal-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span class="hidden sm:inline">Cart</span>
                </button>
            @endif
        </div>
    </div>
</article>
