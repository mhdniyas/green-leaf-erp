@forelse ($pendingSummary as $summary)
    @php
        $hasNonAGrades = ! empty($summary['has_non_a_grades']) && ! empty($summary['grade_breakdown']);
        $isFrequent = ! empty($summary['is_frequent']);
    @endphp
    <div class="product-item block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:bg-slate-50/50 cursor-pointer"
         data-tab="pending"
         data-name="{{ $summary['product_name'] }}"
         data-sku="{{ $summary['sku'] }}"
         data-category="{{ $summary['category_name'] }}"
         data-frequent="{{ $isFrequent ? 'true' : 'false' }}">
        <div class="flex items-start gap-3">
            <div class="flex items-center shrink-0 pt-0.5">
                <input type="checkbox" name="product_ids[]" value="{{ $summary['product_id'] }}" class="product-checkbox h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer">
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="min-w-0 break-words font-black text-slate-900 text-sm">{{ $summary['product_name'] }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $summary['category_name'] ?: 'Other' }}</span>
                        @if ($hasNonAGrades)
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-700 border border-amber-200/60">Multi-grade</span>
                        @endif
                        @if (! empty($summary['is_addon']))
                            <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-teal-700 font-bold border border-teal-200">ADD-ON</span>
                        @endif
                        @if ($summary['draft_qty'] > 0)
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-black text-amber-700">In Cart: {{ number_format($summary['draft_qty'], 1) }} {{ $summary['unit'] }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2.5">
                        @if ($hasNonAGrades)
                            <div class="text-xs font-bold text-slate-500">
                                Total Approved: <span class="text-slate-900 font-black">{{ number_format($summary['total_approved_qty'], 1) }} {{ $summary['unit'] }}</span>
                            </div>
                            <button type="button"
                                    onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $summary['unit'] === 'kg' ? '0.5' : '1' }}', '', 'mixed', 'MIXED')"
                                    class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-indigo-600 px-2.5 text-[10px] font-black text-white hover:bg-indigo-500 shadow-xs transition-colors cursor-pointer">
                                Buy Mixed
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Prominent Stock --}}
                <div class="mt-1 text-xs font-black text-slate-900">
                    Stock: <span class="font-black text-teal-700">{{ number_format($summary['warehouse_balance'] ?? 0, 1) }} kg</span>
                </div>

                @if ($hasNonAGrades)
                    <div class="mt-1 flex items-center justify-between gap-4 text-xs font-semibold text-slate-500">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span>Need: {{ number_format($summary['total_approved_qty'], 1) }} {{ $summary['unit'] }}</span>
                            <span>Bought: {{ number_format($summary['bought_qty'], 1) }}</span>
                            <span class="text-teal-600 font-bold">Left: {{ number_format($summary['remaining_qty'], 1) }}</span>
                        </div>
                    </div>

                    {{-- Multi-grade breakdown table --}}
                    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/70">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Quality Grade</th>
                                    <th class="px-3 py-2 text-right">Approved</th>
                                    <th class="px-3 py-2 text-right">Purchased</th>
                                    <th class="px-3 py-2 text-right">Remaining</th>
                                    <th class="px-3 py-2 text-right">Whse Stock</th>
                                    @if ($purchaseGrade === 'ALL')
                                        <th class="px-3 py-2 text-right w-24">Qty ({{ $summary['unit'] }})</th>
                                        <th class="px-3 py-2 text-right w-24">Rate (₹)</th>
                                        <th class="px-3 py-2 text-right w-24">Line Total</th>
                                    @endif
                                    <th class="px-3 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 bg-white">
                                @foreach ($summary['grade_breakdown'] as $gb)
                                    @php
                                        $gradeBadgeClass = match($gb['grade']) {
                                            'A' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'B' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'C' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                                            default => 'bg-slate-100 text-slate-800 border-slate-200',
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="px-3 py-2 font-black text-slate-900">
                                            <span class="inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $gradeBadgeClass }}">
                                                Grade {{ $gb['grade'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold text-slate-700">
                                            {{ number_format($gb['approved_qty'], 1) }} {{ $summary['unit'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold text-slate-700">
                                            {{ number_format($gb['bought_qty'], 1) }} {{ $summary['unit'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black {{ $gb['remaining_qty'] > 0 ? 'text-teal-700' : 'text-slate-400' }}">
                                            {{ number_format($gb['remaining_qty'], 1) }} {{ $summary['unit'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-slate-800">
                                            {{ number_format($gb['warehouse_balance'] ?? 0, 1) }} kg
                                        </td>
                                        @if ($purchaseGrade === 'ALL')
                                            <td class="px-3 py-2 text-right">
                                                <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][{{ $gb['grade'] }}][quantity]" placeholder="0" class="inline-qty-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500" data-product-id="{{ $summary['product_id'] }}" data-grade="{{ $gb['grade'] }}" oninput="updateInlineBulkTotals()">
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][{{ $gb['grade'] }}][unit_price]" placeholder="0.00" class="inline-rate-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500" data-product-id="{{ $summary['product_id'] }}" data-grade="{{ $gb['grade'] }}" oninput="updateInlineBulkTotals()">
                                            </td>
                                            <td class="px-3 py-2 text-right font-black text-slate-800">
                                                <span class="inline-line-total text-xs" data-product-id="{{ $summary['product_id'] }}" data-grade="{{ $gb['grade'] }}">₹0.00</span>
                                            </td>
                                        @endif
                                        <td class="px-3 py-2 text-right">
                                            @if ($gb['remaining_qty'] > 0)
                                                <button type="button"
                                                        onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $gb['remaining_qty'] }}, {{ $gb['draft_qty'] }}, '{{ $summary['unit'] === 'kg' ? '0.5' : '1' }}', '', 'shop_order', '{{ $gb['grade'] }}')"
                                                        class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-teal-600 px-2.5 text-[10px] font-black text-white hover:bg-teal-500 shadow-xs transition-colors cursor-pointer">
                                                    Buy {{ $gb['grade'] }}
                                                </button>
                                            @else
                                                <span class="inline-flex h-7 items-center justify-center rounded-lg bg-slate-100 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                                    Done
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="bg-indigo-50/40 border-t border-indigo-100/80 hover:bg-indigo-50/70 transition-colors">
                                    <td class="px-3 py-2 font-black text-indigo-950">
                                        <span class="inline-flex items-center rounded border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-indigo-800">
                                            Mixed Lot
                                        </span>
                                    </td>
                                    <td colspan="2" class="px-3 py-2 text-[11px] font-semibold text-indigo-700">
                                        Ungraded purchase (warehouse physical sorting into A/B/C)
                                    </td>
                                    <td class="px-3 py-2 text-right font-black text-indigo-900 text-xs" colspan="2">
                                        @if (($summary['unsorted_warehouse_balance'] ?? 0) > 0)
                                            Unsorted: {{ number_format($summary['unsorted_warehouse_balance'], 1) }} kg
                                        @else
                                            -
                                        @endif
                                    </td>
                                    @if ($purchaseGrade === 'ALL')
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][MIXED][quantity]" placeholder="0" class="inline-qty-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" data-product-id="{{ $summary['product_id'] }}" data-grade="MIXED" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][MIXED][unit_price]" placeholder="0.00" class="inline-rate-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" data-product-id="{{ $summary['product_id'] }}" data-grade="MIXED" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-indigo-900">
                                            <span class="inline-line-total text-xs" data-product-id="{{ $summary['product_id'] }}" data-grade="MIXED">₹0.00</span>
                                        </td>
                                    @endif
                                    <td class="px-3 py-2 text-right">
                                        <button type="button"
                                                onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $summary['unit'] === 'kg' ? '0.5' : '1' }}', '', 'mixed', 'MIXED')"
                                                class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-indigo-600 px-2.5 text-[10px] font-black text-white hover:bg-indigo-500 shadow-xs transition-colors cursor-pointer">
                                            Buy Mixed
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- Compact single grade layout --}}
                    <div class="mt-1 flex items-center justify-between gap-4 text-xs font-semibold text-slate-500">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span>Need: {{ number_format($summary['total_approved_qty'], 1) }} {{ $summary['unit'] }}</span>
                            <span>Bought: {{ number_format($summary['bought_qty'], 1) }}</span>
                            <span class="text-teal-600 font-bold">Left: {{ number_format($summary['remaining_qty'], 1) }}</span>
                        </div>
                        @if ($purchaseGrade !== 'ALL' && $summary['remaining_qty'] > 0)
                            <button type="button"
                                    onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $summary['unit'] === 'kg' ? '0.5' : '1' }}', '', 'shop_order', '{{ $purchaseGrade }}')"
                                    class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-teal-600 px-2.5 text-[10px] font-black text-white hover:bg-teal-500 shadow-xs transition-colors cursor-pointer">
                                Buy {{ $purchaseGrade }}
                            </button>
                        @endif
                    </div>

                    @if ($purchaseGrade === 'ALL')
                        <div class="mt-2.5 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/70">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Quality Grade</th>
                                        <th class="px-3 py-2 text-right">Approved</th>
                                        <th class="px-3 py-2 text-right">Purchased</th>
                                        <th class="px-3 py-2 text-right">Remaining</th>
                                        <th class="px-3 py-2 text-right">Whse Stock</th>
                                        <th class="px-3 py-2 text-right w-24">Qty ({{ $summary['unit'] }})</th>
                                        <th class="px-3 py-2 text-right w-24">Rate (₹)</th>
                                        <th class="px-3 py-2 text-right w-24">Line Total</th>
                                        <th class="px-3 py-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/70 bg-white">
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="px-3 py-2 font-black text-slate-900">
                                            <span class="inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border-emerald-200">
                                                Grade A
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold text-slate-700">
                                            {{ number_format($summary['total_approved_qty'], 1) }} {{ $summary['unit'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold text-slate-700">
                                            {{ number_format($summary['bought_qty'], 1) }} {{ $summary['unit'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black {{ $summary['remaining_qty'] > 0 ? 'text-teal-700' : 'text-slate-400' }}">
                                            {{ number_format($summary['remaining_qty'], 1) }} {{ $summary['unit'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-slate-800">
                                            {{ number_format($summary['warehouse_balance'] ?? 0, 1) }} kg
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][A][quantity]" placeholder="0" class="inline-qty-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500" data-product-id="{{ $summary['product_id'] }}" data-grade="A" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][A][unit_price]" placeholder="0.00" class="inline-rate-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500" data-product-id="{{ $summary['product_id'] }}" data-grade="A" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-slate-800">
                                            <span class="inline-line-total text-xs" data-product-id="{{ $summary['product_id'] }}" data-grade="A">₹0.00</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($summary['remaining_qty'] > 0)
                                                <button type="button"
                                                        onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $summary['unit'] === 'kg' ? '0.5' : '1' }}', '', 'shop_order', 'A')"
                                                        class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-teal-600 px-2.5 text-[10px] font-black text-white hover:bg-teal-500 shadow-xs transition-colors cursor-pointer">
                                                    Buy A
                                                </button>
                                            @else
                                                <span class="inline-flex h-7 items-center justify-center rounded-lg bg-slate-100 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                                    Done
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="px-3 py-2 font-black text-slate-900">
                                            <span class="inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border-amber-200">
                                                Grade B
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium text-slate-400">-</td>
                                        <td class="px-3 py-2 text-right font-medium text-slate-400">-</td>
                                        <td class="px-3 py-2 text-right font-medium text-slate-400">-</td>
                                        <td class="px-3 py-2 text-right font-medium text-slate-400">-</td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][B][quantity]" placeholder="0" class="inline-qty-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-product-id="{{ $summary['product_id'] }}" data-grade="B" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][B][unit_price]" placeholder="0.00" class="inline-rate-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-product-id="{{ $summary['product_id'] }}" data-grade="B" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-slate-800">
                                            <span class="inline-line-total text-xs" data-product-id="{{ $summary['product_id'] }}" data-grade="B">₹0.00</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button"
                                                    onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', 0, 0, '{{ $summary['unit'] === 'kg' ? '0.5' : '1' }}', '', 'green_leaf_direct_purchase', 'B')"
                                                    class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-amber-600 px-2.5 text-[10px] font-black text-white hover:bg-amber-500 shadow-xs transition-colors cursor-pointer">
                                                Buy B
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="bg-indigo-50/40 border-t border-indigo-100/80 hover:bg-indigo-50/70 transition-colors">
                                        <td class="px-3 py-2 font-black text-indigo-950">
                                            <span class="inline-flex items-center rounded border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-indigo-800">
                                                Mixed Lot
                                            </span>
                                        </td>
                                        <td colspan="2" class="px-3 py-2 text-[11px] font-semibold text-indigo-700">
                                            Ungraded purchase (warehouse physical sorting into A/B/C)
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-indigo-900 text-xs" colspan="2">
                                            @if (($summary['unsorted_warehouse_balance'] ?? 0) > 0)
                                                Unsorted: {{ number_format($summary['unsorted_warehouse_balance'], 1) }} kg
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][MIXED][quantity]" placeholder="0" class="inline-qty-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" data-product-id="{{ $summary['product_id'] }}" data-grade="MIXED" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="any" min="0" name="items[{{ $summary['product_id'] }}][MIXED][unit_price]" placeholder="0.00" class="inline-rate-input w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" data-product-id="{{ $summary['product_id'] }}" data-grade="MIXED" oninput="updateInlineBulkTotals()">
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-indigo-900">
                                            <span class="inline-line-total text-xs" data-product-id="{{ $summary['product_id'] }}" data-grade="MIXED">₹0.00</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button"
                                                    onclick="event.stopPropagation(); openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $summary['unit'] === 'kg' ? '0.5' : '1' }}', '', 'mixed', 'MIXED')"
                                                    class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-indigo-600 px-2.5 text-[10px] font-black text-white hover:bg-indigo-500 shadow-xs transition-colors cursor-pointer">
                                                Buy Mixed
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
        No pending products for this date and grade.
    </div>
@endforelse
