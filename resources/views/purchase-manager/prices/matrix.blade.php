@extends('purchase-manager.layouts.app')

@section('title', 'Daily Price Matrix')
@section('page_title', 'Daily Price Matrix Table')
@section('page_description', 'Dedicated matrix view to manage and update daily product prices across dates. Search products, filter by category, and update prices inline.')

@section('content')
    @php
        $isAdminViewer = auth()->user()?->hasRole('admin');
    @endphp
    <div class="space-y-6">
        <!-- Top Search and Filter Controls -->
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <form method="GET" action="{{ route('purchasing.prices.matrix.index') }}" class="space-y-4">
                <input type="hidden" name="matrix_category" id="filter-matrix-category" value="{{ $matrixCategory }}">
                <input type="hidden" name="week_start" id="filter-week-start" value="{{ $weekStartDate }}">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
                    <div>
                        <label for="search" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Product Search</label>
                        <div class="relative mt-2">
                            <input
                                id="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Search product, SKU..."
                                autocomplete="off"
                                class="w-full rounded-2xl border border-slate-200 bg-white pl-10 pr-4 py-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:outline-none"
                            >
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label for="category_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Category Filter</label>
                        <select
                            id="category_id"
                            name="category_id"
                            onchange="this.form.submit()"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none"
                        >
                            <option value="">All Categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) $categoryId === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Month / Purchase Date</label>
                        <input
                            id="date"
                            type="date"
                            name="date"
                            value="{{ $purchaseDate }}"
                            onchange="this.form.submit()"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-cyan-700 focus:border-cyan-500 focus:outline-none"
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="w-full inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white hover:bg-slate-800">
                            Apply Filters
                        </button>
                        <a href="{{ route('purchasing.prices.index', ['date' => $purchaseDate]) }}" class="inline-flex min-h-12 items-center justify-center whitespace-nowrap rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs font-black text-slate-700 hover:bg-slate-50">
                            Proposal Board
                        </a>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-slate-100">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-500">Price Category:</span>
                        <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-100 p-1">
                            <button
                                type="button"
                                onclick="switchMatrixCategory('a')"
                                id="btn-matrix-cat-a"
                                class="rounded-xl px-4 py-2 text-xs font-black transition {{ $matrixCategory === 'a' ? 'bg-cyan-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                            >
                                Category A (Default)
                            </button>
                            <button
                                type="button"
                                onclick="switchMatrixCategory('b')"
                                id="btn-matrix-cat-b"
                                class="rounded-xl px-4 py-2 text-xs font-black transition {{ $matrixCategory === 'b' ? 'bg-cyan-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                            >
                                Category B
                            </button>
                            <button
                                type="button"
                                onclick="switchMatrixCategory('c')"
                                id="btn-matrix-cat-c"
                                class="rounded-xl px-4 py-2 text-xs font-black transition {{ $matrixCategory === 'c' ? 'bg-cyan-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                            >
                                Category C
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            href="{{ route('purchasing.prices.matrix.index', ['date' => $purchaseDate, 'search' => $search, 'category_id' => $categoryId, 'matrix_category' => $matrixCategory, 'week_start' => $previousWeekStartDate]) }}"
                            class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50"
                        >
                            Previous Week
                        </a>
                        <span class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700">
                            {{ \Illuminate\Support\Carbon::parse($weekStartDate)->format('d M') }} - {{ \Illuminate\Support\Carbon::parse($weekEndDate)->format('d M Y') }}
                        </span>
                        <a
                            href="{{ route('purchasing.prices.matrix.index', ['date' => $purchaseDate, 'search' => $search, 'category_id' => $categoryId, 'matrix_category' => $matrixCategory, 'week_start' => $nextWeekStartDate]) }}"
                            class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50"
                        >
                            Next Week
                        </a>
                    </div>
                </div>
                <p class="text-xs font-medium text-slate-500">
                    Displaying matrix for selected week ({{ count($matrixProducts) }} products). Numbers in red indicate price change.
                </p>
            </form>
        </section>

        @if ($isAdminViewer)
            <!-- Admin Import Section -->
            <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-black text-indigo-900 uppercase tracking-wider">Admin: Import Prices from JSON</h3>
                        <p class="text-xs font-medium text-indigo-700 mt-1">Import bulk prices from pre-configured JSON files (storage/app/price-imports/)</p>
                    </div>
                    <button
                        type="button"
                        onclick="showImportModal()"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white hover:bg-indigo-500"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Import from JSON
                    </button>
                </div>
            </section>
        @endif

        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Dedicated Matrix Table Card -->
        <form method="POST" action="{{ url('/purchasing/prices/matrix') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            @csrf
            <input type="hidden" name="date" value="{{ $purchaseDate }}">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="category_id" value="{{ $categoryId }}">
            <input type="hidden" name="week_start" value="{{ $weekStartDate }}">
            <input type="hidden" name="matrix_category" id="update-matrix-category" value="{{ $matrixCategory }}">
            <input type="hidden" name="action" id="matrix-form-action" value="update">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-3">
                <p class="text-sm font-semibold text-cyan-900">
                    @if ($isAdminViewer)
                        Update Price saves matrix values, and <span class="font-black">Approve &amp; Publish</span> immediately publishes live prices and reprices shop invoices.
                    @else
                        Update Price saves matrix values as proposal. Admin approval is required before prices are published.
                    @endif
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        onclick="document.getElementById('matrix-form-action').value='update'"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-50"
                    >
                        Update Price
                    </button>
                    @if ($isAdminViewer)
                        <button
                            type="submit"
                            onclick="document.getElementById('matrix-form-action').value='approve_publish'"
                            class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white hover:bg-emerald-500"
                        >
                            Approve &amp; Publish
                        </button>
                    @endif
                </div>
            </div>
            <div class="relative overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
                            <th scope="col" class="sticky left-0 z-20 bg-slate-100 px-3 py-3 text-center border-r border-slate-200 w-14">SL NO</th>
                            <th scope="col" class="sticky left-14 z-20 bg-slate-100 px-4 py-3 border-r border-slate-200 min-w-[180px]">Item</th>
                            <th scope="col" class="px-2 py-3 text-center border-r border-slate-200 min-w-[100px] bg-amber-100 text-amber-900">
                                Prev Day<br>{{ \Illuminate\Support\Carbon::parse($previousDate)->format('d-M') }}
                            </th>
                            @foreach ($matrixDates as $dateStr => $dateInfo)
                                <th scope="col" class="px-2 py-3 text-center border-r border-slate-200 min-w-[100px] {{ $dateInfo['is_selected'] ? 'bg-cyan-100 text-cyan-900 font-black' : '' }}">
                                    {{ $dateInfo['label'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white font-medium text-slate-800">
                        @forelse ($matrixProducts as $prod)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="sticky left-0 z-10 bg-white px-3 py-2 text-center font-black text-slate-500 border-r border-slate-200">
                                    {{ $prod['sl_no'] }}
                                </td>
                                <td class="sticky left-14 z-10 bg-white px-4 py-2 font-bold text-slate-900 border-r border-slate-200">
                                    <span class="block truncate max-w-[170px]" title="{{ $prod['name'] }}">{{ $prod['name'] }}</span>
                                    @if ($prod['sku'])
                                        <span class="block text-[10px] font-semibold text-slate-400">{{ $prod['sku'] }} ({{ strtoupper($prod['unit'] ?: 'KG') }})</span>
                                    @endif
                                </td>
                                <td class="p-1 border-r border-slate-200 text-center bg-amber-50/40">
                                    @php
                                        $prevDayPrice = match($matrixCategory) {
                                            'a' => $prod['previous_day']['price_a'] ?? null,
                                            'b' => $prod['previous_day']['price_b'] ?? null,
                                            'c' => $prod['previous_day']['price_c'] ?? null,
                                            default => null,
                                        };
                                    @endphp
                                    <div class="flex items-center justify-center gap-1">
                                        @if ($prevDayPrice !== null)
                                            <span class="text-xs font-bold text-amber-800">{{ number_format($prevDayPrice, 2) }}</span>
                                        @else
                                            <span class="text-[10px] text-slate-400">—</span>
                                        @endif
                                    </div>
                                </td>
                                @foreach ($matrixDates as $dateStr => $dateInfo)
                                    @php
                                        $cellData = $prod['prices'][$dateStr] ?? null;
                                        $priceValA = $cellData['price_a'] ?? null;
                                        $priceValB = $cellData['price_b'] ?? null;
                                        $priceValC = $cellData['price_c'] ?? null;
                                        $cellUnit = strtoupper($cellData['unit'] ?? 'KG');

                                        $hasChangedA = $cellData['changed_a'] ?? false;
                                        $hasChangedB = $cellData['changed_b'] ?? false;
                                        $hasChangedC = $cellData['changed_c'] ?? false;
                                        
                                        $isLocked = $cellData['is_locked'] ?? false;
                                    @endphp
                                    <td class="p-1 border-r border-slate-200 text-center {{ $dateInfo['is_selected'] ? 'bg-cyan-50/40' : '' }}">
                                        <div class="matrix-cell-container space-y-1" data-product-id="{{ $prod['product_id'] }}" data-date="{{ $dateStr }}" data-is-locked="{{ $isLocked ? '1' : '0' }}">
                                            @if ($isLocked)
                                                <div class="flex items-center justify-center gap-1 p-1">
                                                    <div class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-red-50 border border-red-200">
                                                        <svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span class="text-xs font-black text-red-900">
                                                            {{ $matrixCategory === 'a' ? ($priceValA !== null ? number_format($priceValA, 2) : '—') : ($matrixCategory === 'b' ? ($priceValB !== null ? number_format($priceValB, 2) : '—') : ($priceValC !== null ? number_format($priceValC, 2) : '—')) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                @if ($isAdminViewer)
                                                    <div class="flex items-center justify-center">
                                                        <button
                                                            type="button"
                                                            title="Unlock price"
                                                            onclick="toggleLockPrice(this)"
                                                            class="lock-toggle-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-red-100 text-red-700 text-[9px] font-bold hover:bg-red-200 transition"
                                                        >
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z" />
                                                            </svg>
                                                            <span>Unlock</span>
                                                        </button>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="flex items-center gap-1">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        name="matrix_prices[{{ $prod['product_id'] }}][{{ $dateStr }}]"
                                                        data-product-id="{{ $prod['product_id'] }}"
                                                        data-date="{{ $dateStr }}"
                                                        data-price-a="{{ $priceValA !== null ? number_format($priceValA, 2, '.', '') : '' }}"
                                                        data-price-b="{{ $priceValB !== null ? number_format($priceValB, 2, '.', '') : '' }}"
                                                        data-price-c="{{ $priceValC !== null ? number_format($priceValC, 2, '.', '') : '' }}"
                                                        data-changed-a="{{ $hasChangedA ? '1' : '0' }}"
                                                        data-changed-b="{{ $hasChangedB ? '1' : '0' }}"
                                                        data-changed-c="{{ $hasChangedC ? '1' : '0' }}"
                                                        value="{{ $matrixCategory === 'a' ? ($priceValA !== null ? number_format($priceValA, 2, '.', '') : '') : ($matrixCategory === 'b' ? ($priceValB !== null ? number_format($priceValB, 2, '.', '') : '') : ($priceValC !== null ? number_format($priceValC, 2, '.', '') : '')) }}"
                                                        onkeydown="if(event.key==='Enter'){ event.preventDefault(); saveMatrixCell(this.nextElementSibling); }"
                                                        class="matrix-cell-input flex-1 rounded-lg border border-slate-200 bg-white py-1 px-1.5 text-center font-extrabold text-xs focus:border-cyan-500 focus:outline-none transition {{ ($matrixCategory === 'a' && $hasChangedA) || ($matrixCategory === 'b' && $hasChangedB) || ($matrixCategory === 'c' && $hasChangedC) ? 'text-red-600 font-black' : 'text-slate-900' }}"
                                                    >
                                                    <button
                                                        type="button"
                                                        title="Save cell price"
                                                        onclick="saveMatrixCell(this)"
                                                        class="matrix-cell-save-btn flex-shrink-0 inline-flex items-center justify-center rounded-lg bg-slate-900 text-white p-1 text-[10px] font-black hover:bg-cyan-600 transition"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="flex items-center justify-center gap-1">
                                                    <button
                                                        type="button"
                                                        title="Copy from previous day"
                                                        onclick="copyFromPreviousDay(this)"
                                                        class="copy-prev-day-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[9px] font-bold hover:bg-amber-200 transition"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                                        </svg>
                                                        <span>Copy</span>
                                                    </button>
                                                    @if ($isAdminViewer)
                                                        <button
                                                            type="button"
                                                            title="Lock price"
                                                            onclick="toggleLockPrice(this)"
                                                            class="lock-toggle-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[9px] font-bold hover:bg-slate-200 transition"
                                                        >
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                                            </svg>
                                                            <span>Lock</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                            <div class="flex items-center justify-center gap-1">
                                                <span class="text-[9px] font-bold text-slate-500">Unit:</span>
                                                <input
                                                    type="hidden"
                                                    class="matrix-cell-unit"
                                                    name="matrix_price_units[{{ $prod['product_id'] }}][{{ $dateStr }}]"
                                                    value="{{ $cellUnit }}"
                                                >
                                                <button
                                                    type="button"
                                                    title="Click to change unit"
                                                    onclick="toggleUnitSelector(this)"
                                                    class="matrix-cell-unit-btn inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[9px] font-bold hover:bg-cyan-100 hover:text-cyan-700 transition"
                                                >
                                                    <span class="unit-display">{{ $cellUnit }}</span>
                                                </button>
                                                <div class="unit-selector hidden absolute z-50 bg-white border border-slate-200 rounded-lg shadow-lg p-2 mt-1 min-w-max" style="display: none;">
                                                    @foreach(['KG', 'BOX', 'PIECE', 'BAG'] as $unit)
                                                        <button
                                                            type="button"
                                                            onclick="selectUnit(this, '{{ $unit }}')"
                                                                class="block w-full text-left px-3 py-2 text-[10px] font-semibold text-slate-600 hover:bg-cyan-100 rounded transition"
                                                        >
                                                            {{ $unit }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($matrixDates) + 3 }}" class="p-8 text-center text-slate-400 font-bold">
                                    No products found matching search or category filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <button
                    type="submit"
                    onclick="document.getElementById('matrix-form-action').value='update'"
                    class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-50"
                >
                    Update Price
                </button>
                @if ($isAdminViewer)
                    <button
                        type="submit"
                        onclick="document.getElementById('matrix-form-action').value='approve_publish'"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white hover:bg-emerald-500"
                    >
                        Approve &amp; Publish
                    </button>
                @endif
            </div>
        </form>
    </div>

    @once
        @push('scripts')
            <script>
                let currentMatrixCategory = "{{ $matrixCategory }}";

                function switchMatrixCategory(cat) {
                    currentMatrixCategory = cat;
                    document.getElementById('filter-matrix-category').value = cat;
                    const updateCategoryInput = document.getElementById('update-matrix-category');
                    if (updateCategoryInput) {
                        updateCategoryInput.value = cat;
                    }

                    ['a', 'b', 'c'].forEach(c => {
                        const btn = document.getElementById('btn-matrix-cat-' + c);
                        if (btn) {
                            if (c === cat) {
                                btn.className = 'rounded-xl px-4 py-2 text-xs font-black transition bg-cyan-700 text-white shadow-sm';
                            } else {
                                btn.className = 'rounded-xl px-4 py-2 text-xs font-black transition text-slate-600 hover:text-slate-900';
                            }
                        }
                    });

                    document.querySelectorAll('.matrix-cell-input').forEach(input => {
                        const valA = input.dataset.priceA;
                        const valB = input.dataset.priceB;
                        const valC = input.dataset.priceC;
                        const changedA = input.dataset.changedA === '1';
                        const changedB = input.dataset.changedB === '1';
                        const changedC = input.dataset.changedC === '1';

                        let targetVal = '';
                        let isChanged = false;

                        if (cat === 'a') {
                            targetVal = valA || '';
                            isChanged = changedA;
                        } else if (cat === 'b') {
                            targetVal = valB || '';
                            isChanged = changedB;
                        } else if (cat === 'c') {
                            targetVal = valC || '';
                            isChanged = changedC;
                        }

                        input.value = targetVal;
                        if (isChanged) {
                            input.classList.remove('text-slate-900');
                            input.classList.add('text-red-600', 'font-black');
                        } else {
                            input.classList.remove('text-red-600', 'font-black');
                            input.classList.add('text-slate-900');
                        }
                    });
                }

                async function saveMatrixCell(btn) {
                    const container = btn.closest('.matrix-cell-container');
                    if (!container) return;
                    const input = container.querySelector('.matrix-cell-input');
                    if (!input) return;

                    const productId = input.dataset.productId;
                    const dateStr = input.dataset.date;
                    const priceVal = input.value;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                      document.querySelector('input[name="_token"]')?.value || '';

                    btn.disabled = true;
                    btn.className = 'matrix-cell-save-btn flex-shrink-0 inline-flex items-center justify-center rounded-lg bg-amber-500 text-white p-1 text-[10px] font-black opacity-80';

                    try {
                        const response = await fetch("{{ route('purchasing.prices.matrix.cell.update') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                date: dateStr,
                                price_category: currentMatrixCategory,
                                price: priceVal,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            input.dataset.priceA = data.price_a !== null ? Number(data.price_a).toFixed(2) : '';
                            input.dataset.priceB = data.price_b !== null ? Number(data.price_b).toFixed(2) : '';
                            input.dataset.priceC = data.price_c !== null ? Number(data.price_c).toFixed(2) : '';

                            btn.className = 'matrix-cell-save-btn flex-shrink-0 inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white p-1 text-[10px] font-black shadow-md';
                            input.classList.add('bg-emerald-50', 'border-emerald-300');

                            setTimeout(() => {
                                btn.disabled = false;
                                btn.className = 'matrix-cell-save-btn flex-shrink-0 inline-flex items-center justify-center rounded-lg bg-slate-900 text-white p-1 text-[10px] font-black hover:bg-cyan-600 transition';
                                input.classList.remove('bg-emerald-50', 'border-emerald-300');
                            }, 1200);
                        } else {
                            alert(data.message || 'Error saving price.');
                            btn.disabled = false;
                            btn.className = 'matrix-cell-save-btn flex-shrink-0 inline-flex items-center justify-center rounded-lg bg-rose-600 text-white p-1 text-[10px] font-black';
                        }
                    } catch (err) {
                        alert('Network error saving price.');
                        btn.disabled = false;
                        btn.className = 'matrix-cell-save-btn flex-shrink-0 inline-flex items-center justify-center rounded-lg bg-slate-900 text-white p-1 text-[10px] font-black';
                    }
                }

                function toggleUnitSelector(btn) {
                    const selector = btn.nextElementSibling;
                    if (!selector) return;
                    
                    const isHidden = selector.style.display === 'none' || selector.classList.contains('hidden');
                    selector.style.display = isHidden ? 'block' : 'none';
                    selector.classList.toggle('hidden', !isHidden);
                }

                function selectUnit(btn, unit) {
                    const selector = btn.parentElement;
                    const unitBtn = selector.previousElementSibling;
                    const hiddenInput = unitBtn.previousElementSibling;
                    
                    if (hiddenInput && hiddenInput.classList.contains('matrix-cell-unit')) {
                       hiddenInput.value = unit;
                    }
                    
                    if (unitBtn) {
                       const display = unitBtn.querySelector('.unit-display');
                       if (display) {
                           display.textContent = unit;
                       }
                    }
                    
                    selector.style.display = 'none';
                    selector.classList.add('hidden');
                }

                async function copyFromPreviousDay(btn) {
                    const container = btn.closest('.matrix-cell-container');
                    if (!container) return;
                    const input = container.querySelector('.matrix-cell-input');
                    if (!input) return;

                    const productId = input.dataset.productId;
                    const dateStr = input.dataset.date;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                      document.querySelector('input[name="_token"]')?.value || '';

                    btn.disabled = true;
                    btn.className = 'copy-prev-day-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-500 text-white text-[9px] font-bold opacity-80';

                    try {
                        const response = await fetch("{{ route('purchasing.prices.matrix.copy-previous') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                date: dateStr,
                                price_category: currentMatrixCategory,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            input.dataset.priceA = data.price_a !== null ? Number(data.price_a).toFixed(2) : '';
                            input.dataset.priceB = data.price_b !== null ? Number(data.price_b).toFixed(2) : '';
                            input.dataset.priceC = data.price_c !== null ? Number(data.price_c).toFixed(2) : '';

                            // Update the input value based on current category
                            if (currentMatrixCategory === 'a') {
                                input.value = data.price_a !== null ? Number(data.price_a).toFixed(2) : '';
                            } else if (currentMatrixCategory === 'b') {
                                input.value = data.price_b !== null ? Number(data.price_b).toFixed(2) : '';
                            } else {
                                input.value = data.price_c !== null ? Number(data.price_c).toFixed(2) : '';
                            }

                            btn.className = 'copy-prev-day-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-emerald-600 text-white text-[9px] font-bold shadow-md';
                            input.classList.add('bg-emerald-50', 'border-emerald-300');

                            setTimeout(() => {
                                btn.disabled = false;
                                btn.className = 'copy-prev-day-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[9px] font-bold hover:bg-amber-200 transition';
                                input.classList.remove('bg-emerald-50', 'border-emerald-300');
                            }, 1200);
                        } else {
                            alert(data.message || 'Error copying price from previous day.');
                            btn.disabled = false;
                            btn.className = 'copy-prev-day-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-rose-600 text-white text-[9px] font-bold';
                        }
                    } catch (err) {
                        alert('Network error copying price.');
                        btn.disabled = false;
                        btn.className = 'copy-prev-day-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[9px] font-bold hover:bg-amber-200 transition';
                    }
                }

                async function toggleLockPrice(btn) {
                    const container = btn.closest('.matrix-cell-container');
                    if (!container) return;

                    const productId = container.dataset.productId;
                    const dateStr = container.dataset.date;
                    const isLocked = container.dataset.isLocked === '1';
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                      document.querySelector('input[name="_token"]')?.value || '';

                    btn.disabled = true;
                    const originalClass = btn.className;
                    btn.className = 'lock-toggle-btn inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-500 text-white text-[9px] font-bold opacity-80';

                    try {
                        const response = await fetch("{{ route('purchasing.prices.matrix.toggle-lock') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                date: dateStr,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            // Reload the page to reflect the lock state change
                            location.reload();
                        } else {
                            alert(data.message || 'Error toggling lock status.');
                            btn.disabled = false;
                            btn.className = originalClass;
                        }
                    } catch (err) {
                        alert('Network error toggling lock status.');
                        btn.disabled = false;
                        btn.className = originalClass;
                    }
                }

                function showImportModal() {
                    const modal = document.getElementById('importModal');
                    if (!modal) {
                        console.error('Import modal not found');
                        alert('Import modal not found. Please refresh the page.');
                        return;
                    }
                    modal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }

                function hideImportModal() {
                    const modal = document.getElementById('importModal');
                    if (!modal) return;
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                    // Reset status
                    const statusDiv = document.getElementById('importStatus');
                    if (statusDiv) {
                        statusDiv.classList.add('hidden');
                        statusDiv.textContent = '';
                    }
                }

                async function executeImport() {
                    const jsonFile = document.getElementById('importJsonFile').value;
                    const unlockLocked = document.getElementById('unlockLocked').checked;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                      document.querySelector('input[name="_token"]')?.value || '';

                    const btn = document.getElementById('executeImportBtn');
                    const statusDiv = document.getElementById('importStatus');
                    
                    btn.disabled = true;
                    btn.textContent = 'Importing...';
                    statusDiv.classList.remove('hidden');
                    statusDiv.textContent = 'Processing import...';
                    statusDiv.className = 'mt-3 p-3 rounded-xl bg-blue-100 text-blue-800 text-sm font-medium';

                    try {
                        const response = await fetch("{{ route('purchasing.prices.matrix.import-json') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                json_file: jsonFile,
                                unlock_locked: unlockLocked,
                            }),
                        });

                        const data = await response.json();
                        
                        if (data.succeclassList.remove('hidden');
                            statusDiv.textContent = data.message;
                            statusDiv.className = 'mt-3 p-3 rounded-xl bg-emerald-100 text-emerald-800 text-sm font-bold';
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            statusDiv.classList.remove('hidden');000);
                        } else {
                            statusDiv.textContent = data.message || 'Import failed.';
                            statusDiv.className = 'mt-3 p-3 rounded-xl bg-rose-100 text-rose-800 text-sm font-bold';
                            btn.disabled = false;
                            btn.textContent = 'Execute Import';
                        }classList.remove('hidden');
                        statusDiv.
                    } catch (err) {
                        statusDiv.textContent = 'Network error during import: ' + err.message;
                        statusDiv.className = 'mt-3 p-3 rounded-xl bg-rose-100 text-rose-800 text-sm font-bold';
                        btn.disabled = false;
                        btn.textContent = 'Execute Import';
                    }
                }

                // Close modal on ESC key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideImportModal();
                    }
                });
            </script>
        @endpush
    @endonce

    <!-- Import Modal -->
    <div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" onclick="hideImportModal()"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-3xl px-6 pt-5 pb-6 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-black text-slate-900 uppercase tracking-wider" id="modal-title">
                            Import Prices from JSON
                        </h3>
                        <button type="button" onclick="hideImportModal()" class="text-slate-400 hover:text-slate-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label for="importJsonFile" class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-2">
                                JSON File Name
                            </label>
                            <input 
                                type="text" 
                                id="importJsonFile" 
                                value="aug-2-3-2026.json"
                                placeholder="aug-2-3-2026.json"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:outline-none"
                            />
                            <p class="mt-1.5 text-xs text-slate-500 font-medium">
                                File must exist in: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">storage/app/price-imports/</code>
                            </p>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-xl bg-amber-50 border border-amber-200">
                            <input 
                                type="checkbox" 
                                id="unlockLocked" 
                                class="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500"
                            />
                            <label for="unlockLocked" class="text-xs font-semibold text-amber-900">
                                <span class="font-black">Force unlock locked prices</span><br>
                                <span class="text-amber-700 font-medium">If unchecked, locked prices will be skipped during import.</span>
                            </label>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <p class="text-xs font-semibold text-slate-700">
                                <strong class="font-black text-slate-900">Safety:</strong> Import validates both product code (SKU) and name must match exactly.
                            </p>
                        </div>
                    </div>

                    <div id="importStatus" class="hidden"></div>

                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="button"
                            id="executeImportBtn"
                            onclick="executeImport()"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3.5 text-sm font-black text-white hover:bg-indigo-500"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Execute Import
                        </button>
                        <button
                            type="button"
                            onclick="hideImportModal()"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3.5 text-sm font-black text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
