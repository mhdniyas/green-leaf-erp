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
                    <p class="text-xs font-medium text-slate-500">
                        Displaying matrix for <span class="font-bold text-slate-900">{{ \Illuminate\Support\Carbon::parse($purchaseDate)->format('F Y') }}</span> ({{ count($matrixProducts) }} products). Numbers in red indicate price change.
                    </p>
                </div>
            </form>
        </section>

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
        <form method="POST" action="{{ route('purchasing.prices.matrix.update') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            @csrf
            <input type="hidden" name="date" value="{{ $purchaseDate }}">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="category_id" value="{{ $categoryId }}">
            <input type="hidden" name="matrix_category" id="update-matrix-category" value="{{ $matrixCategory }}">
            <div class="relative overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
                            <th scope="col" class="sticky left-0 z-20 bg-slate-100 px-3 py-3 text-center border-r border-slate-200 w-14">SL NO</th>
                            <th scope="col" class="sticky left-14 z-20 bg-slate-100 px-4 py-3 border-r border-slate-200 min-w-[180px]">Item</th>
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
                                @foreach ($matrixDates as $dateStr => $dateInfo)
                                    @php
                                        $cellData = $prod['prices'][$dateStr] ?? null;
                                        $priceValA = $cellData['price_a'] ?? null;
                                        $priceValB = $cellData['price_b'] ?? null;
                                        $priceValC = $cellData['price_c'] ?? null;

                                        $hasChangedA = $cellData['changed_a'] ?? false;
                                        $hasChangedB = $cellData['changed_b'] ?? false;
                                        $hasChangedC = $cellData['changed_c'] ?? false;
                                    @endphp
                                    <td class="p-1 border-r border-slate-200 text-center {{ $dateInfo['is_selected'] ? 'bg-cyan-50/40' : '' }}">
                                        <div class="matrix-cell-container flex items-center gap-1">
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
                                                class="matrix-cell-input w-full rounded-lg border border-slate-200 bg-white py-1 px-1.5 text-center font-extrabold text-xs focus:border-cyan-500 focus:outline-none transition {{ ($matrixCategory === 'a' && $hasChangedA) || ($matrixCategory === 'b' && $hasChangedB) || ($matrixCategory === 'c' && $hasChangedC) ? 'text-red-600 font-black' : 'text-slate-900' }}"
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
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($matrixDates) + 2 }}" class="p-8 text-center text-slate-400 font-bold">
                                    No products found matching search or category filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm font-semibold text-cyan-900">
                @if ($isAdminViewer)
                    Update Price saves matrix values, and <span class="font-black">Approve &amp; Publish</span> immediately publishes live prices and reprices shop invoices.
                @else
                    Update Price saves matrix values as proposal. Admin approval is required before prices are published.
                @endif
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <button
                    type="submit"
                    name="action"
                    value="update"
                    class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-50"
                >
                    Update Price
                </button>
                @if ($isAdminViewer)
                    <button
                        type="submit"
                        name="action"
                        value="approve_publish"
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
            </script>
        @endpush
    @endonce
@endsection
