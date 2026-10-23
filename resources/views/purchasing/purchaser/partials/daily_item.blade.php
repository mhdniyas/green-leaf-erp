@php
    $step = $summary['unit'] === 'kg' ? '0.5' : '1';
    $isDirectCatalog = (bool) ($summary['is_direct_catalog'] ?? false);
    $isGradeBCatalog = (bool) ($summary['is_grade_b_catalog'] ?? false);
    $hasNonAGrades = ! empty($summary['has_non_a_grades']) && ! empty($summary['grade_breakdown']);
@endphp
<article class="relative min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex min-w-0 items-center justify-between gap-2 px-2.5 py-2">
        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 items-center gap-2">
                <h2 class="truncate text-sm font-black text-slate-950">{{ $summary['product_name'] }}</h2>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.1em] text-slate-600">{{ $summary['category_name'] ?: 'Other' }}</span>
                @if ($hasNonAGrades)
                    <span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-700">Multi-grade</span>
                @endif
            </div>
            <div class="mt-1 text-xs font-black text-slate-900">
                Stock: <span class="font-black text-teal-700">{{ number_format($summary['warehouse_balance'] ?? 0, 1) }} kg</span>
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
            @if ($hasNonAGrades)
                <button type="button" onclick="openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $step }}', '', 'mixed', 'MIXED')" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-indigo-600 px-2.5 text-[11px] font-black text-white shadow-sm transition-all hover:bg-indigo-500">
                    <span>Buy Mixed</span>
                </button>
            @elseif (! $hasNonAGrades && ($isGradeBCatalog || $summary['remaining_qty'] > 0))
                <button type="button" onclick="openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $step }}', '{{ addslashes(implode(', ', $summary['draft_purchasers'] ?? [])) }}', '{{ $isDirectCatalog ? 'green_leaf_direct_purchase' : 'shop_order' }}')" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-teal-600 px-2.5 text-[11px] font-black text-white shadow-sm transition-all hover:bg-teal-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span class="hidden sm:inline">Cart</span>
                </button>
            @endif
        </div>
    </div>

    @if ($hasNonAGrades)
        <div class="border-t border-slate-100 bg-slate-50/70 px-2.5 py-2 space-y-1.5">
            @foreach ($summary['grade_breakdown'] as $gb)
                @php
                    $gradeBadgeClass = match($gb['grade']) {
                        'A' => 'bg-emerald-100 text-emerald-800',
                        'B' => 'bg-amber-100 text-amber-800',
                        'C' => 'bg-indigo-100 text-indigo-800',
                        default => 'bg-slate-100 text-slate-800',
                    };
                @endphp
                <div class="flex items-center justify-between gap-2 rounded-lg bg-white p-1.5 border border-slate-200/70 text-[11px]">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $gradeBadgeClass }}">
                            Grade {{ $gb['grade'] }}
                        </span>
                        <span class="text-slate-600 font-bold truncate">
                            Need {{ number_format($gb['approved_qty'], 2) }} {{ $summary['unit'] }}
                            @if ($gb['bought_qty'] > 0)
                                · Bought {{ number_format($gb['bought_qty'], 2) }}
                            @endif
                            · <span class="text-emerald-700 font-black">Left {{ number_format($gb['remaining_qty'], 2) }}</span>
                            @if (isset($gb['warehouse_balance']))
                                · <span class="text-slate-900 font-black">Whse {{ number_format($gb['warehouse_balance'], 1) }} kg</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @if ($gb['remaining_qty'] > 0)
                            <button type="button"
                                    onclick="openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $gb['remaining_qty'] }}, {{ $gb['draft_qty'] }}, '{{ $step }}', '', 'shop_order', '{{ $gb['grade'] }}')"
                                    class="inline-flex h-6 items-center justify-center gap-1 rounded bg-teal-600 px-2 text-[10px] font-black text-white hover:bg-teal-500 shadow-xs transition-colors">
                                Buy {{ $gb['grade'] }}
                            </button>
                        @else
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider px-1">Done</span>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="flex items-center justify-between gap-2 rounded-lg bg-indigo-50/50 p-1.5 border border-indigo-100 text-[11px]">
                <div class="flex items-center gap-1.5 min-w-0">
                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider bg-indigo-100 text-indigo-800 border border-indigo-200">
                        Mixed Lot
                    </span>
                    <span class="text-indigo-700 font-bold truncate">
                        Ungraded lot (warehouse sorting into A/B/C)
                    </span>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button type="button"
                            onclick="openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $step }}', '', 'mixed', 'MIXED')"
                            class="inline-flex h-6 items-center justify-center gap-1 rounded bg-indigo-600 px-2 text-[10px] font-black text-white hover:bg-indigo-500 shadow-xs transition-colors">
                        Buy Mixed
                    </button>
                </div>
            </div>
        </div>
    @endif
</article>
