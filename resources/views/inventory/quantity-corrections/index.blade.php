<x-layouts.inventory title="Admin — Quantity Corrections">
    <div class="space-y-6" x-data="{ editModalOpen: false, activeItem: null }">
        {{-- Header & Subtitle --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                    <span>Admin</span>
                    <span>&rsaquo;</span>
                    <span>Inventory</span>
                    <span>&rsaquo;</span>
                    <span class="text-amber-600 font-bold">Quantity Corrections</span>
                </nav>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Quantity Corrections & Audit</h1>
                <p class="text-sm font-medium text-slate-500">Correct inflated order quantities, recalculate base units, and clean up duplicate rows without affecting stock movements.</p>
            </div>
        </div>

        {{-- Alerts --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 text-emerald-800 text-sm font-semibold flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-rose-800 text-sm font-semibold shadow-xs">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <span class="font-bold">Errors occurred:</span>
                </div>
                <ul class="list-disc pl-7 space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Metrics Summary Cards --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Items</div>
                <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($totalItemsCount) }}</div>
                <div class="mt-1 text-xs text-slate-400">Order lines loaded</div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4 shadow-xs">
                <div class="text-xs font-bold text-amber-700 uppercase tracking-wider">Total Warnings</div>
                <div class="mt-2 text-2xl font-black text-amber-900">{{ number_format($totalWarningsCount) }}</div>
                <div class="mt-1 text-xs text-amber-600 font-medium">Require review</div>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-4 shadow-xs">
                <div class="text-xs font-bold text-rose-700 uppercase tracking-wider">Inflated (&gt;5000)</div>
                <div class="mt-2 text-2xl font-black text-rose-900">{{ number_format($inflatedCount) }}</div>
                <div class="mt-1 text-xs text-rose-600 font-medium">Corrupted quantities</div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4 shadow-xs">
                <div class="text-xs font-bold text-amber-700 uppercase tracking-wider">Unit Mismatches</div>
                <div class="mt-2 text-2xl font-black text-amber-900">{{ number_format($mismatchCount) }}</div>
                <div class="mt-1 text-xs text-amber-600 font-medium">Calculation mismatch</div>
            </div>

            <div class="rounded-2xl border border-purple-200 bg-purple-50/50 p-4 shadow-xs">
                <div class="text-xs font-bold text-purple-700 uppercase tracking-wider">Duplicate Rows</div>
                <div class="mt-2 text-2xl font-black text-purple-900">{{ number_format($duplicateCount) }}</div>
                <div class="mt-1 text-xs text-purple-600 font-medium">Shop + Product duplicates</div>
            </div>
        </div>

        {{-- Filters Bar --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <form method="GET" action="{{ route('inventory.quantity-corrections.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-6 items-end">
                <div>
                    <label for="date" class="block text-xs font-bold text-slate-700 mb-1">Business Date</label>
                    <input type="date" id="date" name="date" value="{{ $date }}"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-amber-500 focus:ring-amber-500 shadow-xs">
                </div>

                <div>
                    <label for="shop_id" class="block text-xs font-bold text-slate-700 mb-1">Filter by Shop</label>
                    <select id="shop_id" name="shop_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-amber-500 focus:ring-amber-500 shadow-xs">
                        <option value="">All Shops</option>
                        @foreach ($shops as $s)
                            <option value="{{ $s->id }}" {{ (string) $selectedShopId === (string) $s->id ? 'selected' : '' }}>
                                {{ $s->name }} {{ $s->warehouse_tag ? "({$s->warehouse_tag})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category_id" class="block text-xs font-bold text-slate-700 mb-1">Category</label>
                    <select id="category_id" name="category_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-amber-500 focus:ring-amber-500 shadow-xs">
                        <option value="">All Categories</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" {{ (string) $selectedCategoryId === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="search" class="block text-xs font-bold text-slate-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="{{ $search }}" placeholder="Product or shop..."
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-amber-500 focus:ring-amber-500 shadow-xs">
                </div>

                <div class="flex items-center pb-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-amber-800">
                        <input type="checkbox" name="warnings_only" value="1" {{ $warningsOnly ? 'checked' : '' }}
                               class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>Show Warnings Only</span>
                    </label>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="w-full rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700 transition-colors shadow-xs">
                        Apply Filter
                    </button>
                    <a href="{{ route('inventory.quantity-corrections.index', ['date' => $date]) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-xs">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Quantity Items Table --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-sm font-black text-slate-900">Shop Order Lines ({{ count($items) }})</h3>
                <span class="text-xs font-semibold text-slate-500">Showing business date {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
            </div>

            @if ($items->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <h3 class="mt-2 text-sm font-bold text-slate-900">No order items found</h3>
                    <p class="mt-1 text-xs text-slate-500">No matching shop order items found for the selected date and filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">ID / Shop</th>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3 text-center">Unit Qty &amp; Unit</th>
                                <th class="px-4 py-3 text-center">Conv. Factor</th>
                                <th class="px-4 py-3 text-right">Req. Qty (Base)</th>
                                <th class="px-4 py-3 text-right">Approved Qty</th>
                                <th class="px-4 py-3 text-right">Loaded / Actual</th>
                                <th class="px-4 py-3 text-center">Warnings</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @foreach ($items as $item)
                                <tr class="{{ $item->has_any_warning ? 'bg-amber-50/30' : 'hover:bg-slate-50/60' }} transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">#{{ $item->id }} &middot; {{ $item->order?->shop?->name ?? '—' }}</div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            @if ($item->order?->shop?->warehouse_tag)
                                                <span class="rounded px-1.5 py-0.5 text-[10px] font-extrabold bg-slate-100 text-slate-700 border border-slate-200">
                                                    {{ $item->order->shop->warehouse_tag }}
                                                </span>
                                            @endif
                                            <span class="text-[10px] text-slate-400">Order #{{ $item->order?->order_number ?? '—' }}</span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">{{ $item->product?->name ?? '—' }}</div>
                                        <div class="text-[10px] text-slate-400">SKU: {{ $item->product?->sku ?? '—' }} &middot; {{ $item->product?->category?->name ?? 'Uncategorized' }}</div>
                                    </td>

                                    <td class="px-4 py-3 text-center font-mono">
                                        <span class="font-bold text-slate-900">{{ number_format((float) ($item->requested_unit_quantity ?? 0), 2) }}</span>
                                        <span class="text-slate-500 uppercase">{{ $item->requested_unit ?: $item->unit }}</span>
                                    </td>

                                    <td class="px-4 py-3 text-center font-mono font-semibold text-slate-700">
                                        &times; {{ number_format((float) ($item->requested_unit_conversion_to_base ?? 1), 4) }}
                                    </td>

                                    <td class="px-4 py-3 text-right font-mono font-bold {{ $item->has_inflated_requested ? 'text-rose-600 font-extrabold text-sm' : 'text-slate-900' }}">
                                        {{ number_format((float) $item->requested_qty, 2) }} {{ $item->unit }}
                                    </td>

                                    <td class="px-4 py-3 text-right font-mono font-bold {{ $item->has_inflated_approved ? 'text-rose-600 font-extrabold text-sm' : 'text-emerald-700' }}">
                                        {{ number_format((float) $item->approved_qty, 2) }} {{ $item->unit }}
                                    </td>

                                    <td class="px-4 py-3 text-right font-mono text-slate-600">
                                        @if ($item->loaded_qty !== null && (float) $item->loaded_qty > 0)
                                            <span class="font-bold text-blue-700">{{ number_format((float) $item->loaded_qty, 2) }} {{ $item->unit }} (Loaded)</span>
                                        @elseif ($item->actual_weight !== null && (float) $item->actual_weight > 0)
                                            <span class="font-bold text-indigo-700">{{ number_format((float) $item->actual_weight, 2) }} {{ $item->unit }} (Actual)</span>
                                        @else
                                            <span class="text-slate-400">&mdash;</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-center space-y-1">
                                        @if ($item->has_inflated_requested || $item->has_inflated_approved)
                                            <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black text-rose-800 border border-rose-300">
                                                &gt;5000 Corrupted
                                            </span>
                                        @endif
                                        @if ($item->has_mismatch)
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800 border border-amber-300"
                                                  title="Expected: {{ $item->expected_base_qty }}, Got: {{ $item->requested_qty }}">
                                                Calc Mismatch
                                            </span>
                                        @endif
                                        @if ($item->is_duplicate)
                                            <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-black text-purple-800 border border-purple-300">
                                                Duplicate Row
                                            </span>
                                        @endif
                                        @if (!$item->has_any_warning)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600 border border-emerald-200">
                                                Normal
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            {{-- Edit Modal Trigger --}}
                                            <button type="button"
                                                    @click="activeItem = {
                                                        id: {{ $item->id }},
                                                        shop_name: '{{ addslashes($item->order?->shop?->name ?? '') }}',
                                                        product_name: '{{ addslashes($item->product?->name ?? '') }}',
                                                        unit_quantity: {{ (float) ($item->requested_unit_quantity ?? 0) }},
                                                        unit: '{{ addslashes($item->requested_unit ?: $item->unit) }}',
                                                        conversion: {{ (float) ($item->requested_unit_conversion_to_base ?? 1) }},
                                                        requested_qty: {{ (float) $item->requested_qty }},
                                                        approved_qty: {{ (float) $item->approved_qty }},
                                                        action_url: '{{ route('inventory.quantity-corrections.update', $item) }}'
                                                    }; editModalOpen = true;"
                                                    class="rounded-lg bg-amber-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-amber-700 transition-colors shadow-xs">
                                                Edit
                                            </button>

                                            {{-- Recalculate --}}
                                            <form method="POST" action="{{ route('inventory.quantity-corrections.recalculate', $item) }}" class="inline">
                                                @csrf
                                                <button type="submit"
                                                        title="Recalculate requested_qty = unit_qty &times; conversion"
                                                        class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100 transition-colors shadow-xs">
                                                    Recalc
                                                </button>
                                            </form>

                                            {{-- Copy Loaded Qty to Approved Qty --}}
                                            @if (($item->loaded_qty !== null && (float) $item->loaded_qty > 0) || ($item->actual_weight !== null && (float) $item->actual_weight > 0))
                                                <form method="POST" action="{{ route('inventory.quantity-corrections.copy-loaded', $item) }}" class="inline">
                                                    @csrf
                                                    <button type="submit"
                                                            title="Copy loaded quantity to approved quantity"
                                                            class="rounded-lg border border-blue-300 bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-700 hover:bg-blue-100 transition-colors shadow-xs">
                                                        Copy Loaded
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Soft Delete Duplicate --}}
                                            @if ($item->is_duplicate || $item->has_inflated_requested)
                                                <form method="POST" action="{{ route('inventory.quantity-corrections.soft-delete', $item) }}"
                                                      onsubmit="return confirm('Soft-delete duplicate row #{{ $item->id }} for {{ addslashes($item->product?->name ?? '') }}? This will not touch stock movements.');"
                                                      class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            title="Soft delete corrupted or duplicate row"
                                                            class="rounded-lg bg-rose-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-rose-700 transition-colors shadow-xs">
                                                        Soft Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Edit Modal Dialog --}}
        <div x-show="editModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             style="display: none;">
            <div @click.away="editModalOpen = false"
                 class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Correct Order Item Quantity</h3>
                        <p class="text-xs text-slate-500 font-medium" x-text="activeItem ? activeItem.shop_name + ' &middot; ' + activeItem.product_name : ''"></p>
                    </div>
                    <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form x-bind:action="activeItem ? activeItem.action_url : ''" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Requested Unit Qty</label>
                            <input type="number" step="0.01" min="0" name="requested_unit_quantity"
                                   x-model.number="activeItem.unit_quantity"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-mono font-bold text-slate-900 focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Requested Unit</label>
                            <input type="text" name="requested_unit"
                                   x-model="activeItem.unit"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-mono font-bold text-slate-900 focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Conversion Factor to Base Unit</label>
                        <input type="number" step="0.0001" min="0.0001" name="requested_unit_conversion_to_base"
                               x-model.number="activeItem.conversion"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-mono font-bold text-slate-900 focus:border-amber-500 focus:ring-amber-500">
                        <p class="text-[11px] text-amber-700 font-semibold mt-1">
                            Calculated Base Qty: <span x-text="(activeItem.unit_quantity * activeItem.conversion).toFixed(2)"></span>
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Requested Qty (Base)</label>
                            <input type="number" step="0.01" min="0" name="requested_qty"
                                   x-model.number="activeItem.requested_qty"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-mono font-bold text-slate-900 focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Approved Qty</label>
                            <input type="number" step="0.01" min="0" name="approved_qty"
                                   x-model.number="activeItem.approved_qty"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-mono font-bold text-emerald-700 focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Reason for Correction (Audit Log)</label>
                        <input type="text" name="reason" placeholder="e.g. Corrected unit conversion inflation"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-amber-500 focus:ring-amber-500">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="editModalOpen = false" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-xl bg-amber-600 px-5 py-2 text-xs font-bold text-white hover:bg-amber-700 shadow-xs">
                            Save Correction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.inventory>
