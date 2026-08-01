@php
    $step = $summary['unit'] === 'kg' ? '0.5' : '1';
    $directPurchaseCount = collect($summary['shop_details'])->where('is_direct_purchase', true)->count();
    $shopDemandCount = count($summary['shop_details']) - $directPurchaseCount;
    $purchaseSource = $directPurchaseCount > 0
        ? ($shopDemandCount > 0 ? 'mixed' : 'green_leaf_direct_purchase')
        : 'shop_order';
    $dailyItemId = 'daily-item-'.$summary['product_id'].'-'.\Illuminate\Support\Carbon::parse($summary['order_date'])->format('Ymd');
@endphp
<article class="relative min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex min-w-0 items-center gap-2 px-2.5 py-2">
        <button type="button" onclick="toggleDailyItemDetails('{{ $dailyItemId }}')" class="flex min-w-0 flex-1 items-center gap-2 text-left">
            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 items-center gap-2">
                    <h2 class="truncate text-sm font-black text-slate-950">{{ $summary['product_name'] }}</h2>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.1em] text-slate-600">{{ $summary['category_name'] ?: 'Other' }}</span>
                </div>
                <div class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5 text-[10px] font-black text-slate-500">
                    <span class="rounded-full bg-slate-50 px-2 py-0.5">Need {{ number_format($summary['total_approved_qty'], 2) }} {{ $summary['unit'] }}</span>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">Left {{ number_format($summary['remaining_qty'], 2) }} {{ $summary['unit'] }}</span>
                    @if ($summary['draft_qty'] > 0)
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Cart {{ number_format($summary['draft_qty'], 2) }}</span>
                    @endif
                    @if ($summary['remaining_qty'] <= 0 && $summary['bought_qty'] > 0)
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">Purchased</span>
                    @endif
                </div>
            </div>
            <svg id="{{ $dailyItemId }}-icon" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        @if ($summary['remaining_qty'] > 0)
            <button type="button" onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $step }}', '{{ addslashes(implode(', ', $summary['draft_purchasers'])) }}', '{{ $purchaseSource }}')" class="inline-flex h-8 shrink-0 items-center justify-center gap-1 rounded-lg bg-teal-600 px-2.5 text-[11px] font-black text-white shadow-sm transition-all hover:bg-teal-500">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span class="hidden sm:inline">Cart</span>
            </button>
        @endif
    </div>

    <div id="{{ $dailyItemId }}" class="hidden border-t border-slate-100 p-3">
        <div class="flex flex-col gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if (isset($summary['order_date']) && isset($currentDate) && \Illuminate\Support\Carbon::parse($summary['order_date'])->format('Y-m-d') !== $currentDate)
                        <span class="rounded-full bg-rose-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-rose-700">
                            Pending ({{ \Illuminate\Support\Carbon::parse($summary['order_date'])->format('d M Y') }})
                        </span>
                    @endif
                    @if ($summary['draft_qty'] > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800">
                            {{ number_format($summary['draft_qty'], 2) }} {{ $summary['unit'] }} in cart
                            @if(!empty($summary['draft_purchasers']))
                                (by {{ implode(', ', $summary['draft_purchasers']) }})
                            @endif
                        </span>
                    @endif
                    @if ($summary['bought_qty'] > 0 && $summary['remaining_qty'] > 0)
                        <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-cyan-800">
                            {{ number_format($summary['bought_qty'], 2) }} {{ $summary['unit'] }} submitted today
                        </span>
                    @endif
                    @if ($summary['draft_qty'] <= 0 && $summary['bought_qty'] <= 0)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-700">Not in cart</span>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs font-semibold text-slate-600">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Need</span>
                        <span class="mt-0.5 block truncate font-black text-slate-955">{{ number_format($summary['total_approved_qty'], 2) }} {{ $summary['unit'] }}</span>
                    </div>
                    <div class="min-w-0 rounded-xl bg-amber-50 p-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-600">Bought</span>
                        <span class="mt-0.5 block truncate font-black text-amber-700">{{ number_format($summary['bought_qty'], 2) }} {{ $summary['unit'] }}</span>
                    </div>
                    <div class="min-w-0 rounded-xl bg-emerald-50 p-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-600">Left</span>
                        <span class="mt-0.5 block truncate font-black text-emerald-700">{{ number_format($summary['remaining_qty'], 2) }} {{ $summary['unit'] }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($summary['quantity_buckets'] as $bucket)
                        <span class="inline-flex rounded-full bg-cyan-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-cyan-700">{{ $bucket['formatted'] }} x {{ $bucket['count'] }}</span>
                    @endforeach
                </div>
                @if(! empty($summary['measure_breakdown']))
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($summary['measure_breakdown'] as $measure)
                            <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-600 shadow-sm">
                                {{ number_format((float) $measure['requested_qty'], 2) }} {{ $measure['label'] }}
                                @if(strtoupper((string) $measure['label']) !== strtoupper((string) $summary['unit']))
                                    / {{ number_format((float) $measure['approved_qty'], 2) }} {{ $summary['unit'] }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
                <button type="button" onclick="document.getElementById('info-product-{{ $summary['product_id'] }}').classList.remove('hidden'); document.body.classList.add('overflow-hidden')" class="mt-3 flex w-full items-center justify-between text-left text-[11px] font-black text-slate-500">
                    <span>Demand split</span>
                    <span class="rounded-full bg-white px-2.5 py-1 text-[10px] text-slate-700 shadow-sm">
                        {{ $directPurchaseCount > 0 ? 'Direct + ' : '' }}{{ $shopDemandCount }} shops
                    </span>
                </button>
            </div>
        </div>
    </div>
</article>

<div id="info-product-{{ $summary['product_id'] }}" class="fixed inset-0 z-[90] hidden p-4" onclick="if (event.target === this) { this.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }">
    <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
    <div class="relative mx-auto flex min-h-full max-w-lg items-center justify-center">
        <div class="w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-xl lg:rounded-[2rem] lg:p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Demand Details</p>
                    <h3 class="mt-1 text-lg font-black text-slate-950">{{ $summary['product_name'] }}</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ number_format($summary['total_approved_qty'], 2) }} {{ $summary['unit'] }} total needed</p>
                </div>
                <button type="button" onclick="document.getElementById('info-product-{{ $summary['product_id'] }}').classList.add('hidden'); document.body.classList.remove('overflow-hidden')" class="rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-black text-slate-700">Close</button>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($summary['quantity_buckets'] as $bucket)
                    <span class="inline-flex rounded-full bg-cyan-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-cyan-700">{{ $bucket['formatted'] }} x {{ $bucket['count'] }}</span>
                @endforeach
            </div>
            @if(! empty($summary['measure_breakdown']))
                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Requested package summary</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($summary['measure_breakdown'] as $measure)
                            <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700 shadow-sm">
                                {{ number_format((float) $measure['requested_qty'], 2) }} {{ $measure['label'] }} = {{ number_format((float) $measure['approved_qty'], 2) }} {{ $summary['unit'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="mt-4 space-y-2">
                @foreach ($summary['shop_details'] as $detail)
                    <div class="flex min-w-0 items-center justify-between gap-3 rounded-xl border {{ $detail['is_direct_purchase'] ?? false ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }} px-3 py-3 text-sm font-semibold text-slate-700 lg:rounded-2xl">
                        <div class="min-w-0">
                            <p class="truncate font-black {{ $detail['is_direct_purchase'] ?? false ? 'text-emerald-800' : 'text-slate-900' }}">{{ $detail['shop_name'] }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $detail['order_number'] }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="block">{{ number_format($detail['approved_qty'], 2) }} {{ $detail['unit'] }}</span>
                            @if(! empty($detail['requested_measure_label']))
                                <span class="block text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">{{ $detail['requested_measure_label'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
