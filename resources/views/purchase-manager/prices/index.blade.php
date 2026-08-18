@extends('purchase-manager.layouts.app')

@section('title', 'Daily Purchasing Prices')
@section('page_title', 'Daily Purchasing Prices')
@section('page_description', 'Daily purchasing cost dashboard with business-day average cost, previous-day comparison, and selling-price reference.')

@section('content')
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Purchasing flow</p>
                    <h2 class="text-2xl font-black text-slate-950">Daily purchasing price table</h2>
                    <p class="max-w-3xl text-sm font-medium leading-6 text-slate-600">
                        This page shows buying costs &amp; daily selling prices (A, B, C).
                        Updating Selling Price A will automatically copy to B &amp; C unless custom values are specified.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('purchasing.prices.toggle-publish') }}" class="inline-flex items-center">
                        @csrf
                        <input type="hidden" name="date" value="{{ $purchaseDate }}">
                        <input type="hidden" name="is_published" value="{{ $isPublished ? '0' : '1' }}">
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl px-5 py-3 text-xs font-black uppercase tracking-wider transition shadow-sm {{ $isPublished ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-amber-500 text-white hover:bg-amber-600' }}"
                            title="{{ $isPublished ? 'Click to unpublish prices (set to draft)' : 'Click to publish daily prices to shop incharges' }}"
                        >
                            <span class="h-2 w-2 rounded-full {{ $isPublished ? 'bg-white animate-pulse' : 'bg-amber-200' }}"></span>
                            <span>{{ $isPublished ? 'Prices Published (Live)' : 'Publish Today\'s Prices' }}</span>
                        </button>
                    </form>
                    <a href="{{ route('purchasing.prices.matrix.index', ['date' => $purchaseDate]) }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white hover:bg-slate-800">
                        Open Selling Matrix
                    </a>
                    <span class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-xs font-black uppercase tracking-wide text-cyan-800">
                        {{ \Illuminate\Support\Carbon::parse($purchaseDate)->format('d M Y') }}
                    </span>
                </div>
            </div>

            <form method="GET" action="{{ route('purchasing.prices.index') }}" class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,0.8fr)_minmax(0,0.8fr)_auto]">
                <div>
                    <label for="search" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Search</label>
                    <input id="search" name="search" value="{{ $search }}" placeholder="Search product or SKU" autocomplete="off" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:outline-none" />
                </div>
                <div>
                    <label for="category_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Category</label>
                    <select id="category_id" name="category_id" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Business date</label>
                    <input id="date" type="date" name="date" value="{{ $purchaseDate }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-cyan-700 focus:border-cyan-500 focus:outline-none" />
                </div>
                <div>
                    <label for="per_page" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Per page</label>
                    <select id="per_page" name="per_page" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                        @foreach ([20, 30, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-4 flex flex-wrap items-center justify-between gap-3 pt-1">
                    <p class="text-xs font-medium text-slate-500">Enter or edit Selling Price A to automatically update B &amp; C. Enter B or C explicitly when tier pricing differs.</p>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-cyan-600 px-5 py-3 text-sm font-black text-white hover:bg-cyan-500">Apply Filters</button>
                </div>
            </form>
        </section>

        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('warning'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold text-amber-800">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('purchasing.prices.update') }}" id="batch-prices-form">
            @csrf
            <input type="hidden" name="date" value="{{ $purchaseDate }}">

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Product price list &amp; daily updates</h3>
                        <p class="mt-1 text-xs font-medium text-slate-500">
                            Updating Price A will auto-fill B and C if left blank. User activity is tracked for all price modifications.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-slate-500">
                            Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }}
                        </span>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-700 transition shadow-xs cursor-pointer"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Save All Prices</span>
                        </button>
                    </div>
                </div>

                {{-- Desktop Table View --}}
                <div class="hidden lg:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3 text-right">Purchase Price</th>
                                    <th class="px-3 py-3 text-right">Prev Day</th>
                                    <th class="px-3 py-3 text-right">Change</th>
                                    <th class="px-4 py-3 text-center">Selling A (Main)</th>
                                    <th class="px-4 py-3 text-center">Selling B</th>
                                    <th class="px-4 py-3 text-center">Selling C</th>
                                    <th class="px-3 py-3 text-center">Source</th>
                                    <th class="px-4 py-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($products as $index => $row)
                                    @php
                                        $purchasePrice = (float) $row['purchase_price'];
                                        $previousPrice = $row['previous_purchase_price'] !== null ? (float) $row['previous_purchase_price'] : null;
                                        $change = $row['purchase_diff'];
                                        $unit = strtoupper($row['unit'] ?: 'KG');
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $row['product_id'] }}">

                                        <td class="px-4 py-3.5">
                                            <div class="font-black text-slate-900">{{ $row['name'] }}</div>
                                            <div class="mt-0.5 text-xs font-semibold text-slate-500">{{ $row['sku'] ?: 'No SKU' }} · {{ $unit }}</div>
                                        </td>

                                        <td class="px-4 py-3.5 text-xs font-bold text-slate-600">{{ $row['category_name'] }}</td>

                                        {{-- Purchase Price Input --}}
                                        <td class="px-4 py-3.5 text-right">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="products[{{ $index }}][purchase_price]"
                                                value="{{ $purchasePrice > 0 ? number_format($purchasePrice, 2, '.', '') : '' }}"
                                                placeholder="0.00"
                                                class="w-24 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right text-xs font-bold text-cyan-700 focus:border-cyan-500 focus:bg-white focus:outline-none transition shadow-2xs"
                                            >
                                        </td>

                                        <td class="px-3 py-3.5 text-right text-xs font-bold text-slate-600">
                                            {{ $previousPrice !== null ? number_format($previousPrice, 2) : '—' }}
                                        </td>

                                        <td class="px-3 py-3.5 text-right">
                                            @if ($change !== null)
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-black {{ $change > 0 ? 'bg-emerald-100 text-emerald-700' : ($change < 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700') }}">
                                                    {{ $change > 0 ? '+' : '' }}{{ number_format((float) $change, 2) }}
                                                </span>
                                            @else
                                                <span class="text-xs font-semibold text-slate-400">—</span>
                                            @endif
                                        </td>

                                        {{-- Selling A Input --}}
                                        <td class="px-4 py-3.5 text-center">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="products[{{ $index }}][price_a]"
                                                value="{{ $row['selling_price_a'] > 0 ? number_format($row['selling_price_a'], 2, '.', '') : '' }}"
                                                placeholder="0.00"
                                                class="w-24 rounded-xl border border-slate-300 bg-emerald-50/30 px-2.5 py-1.5 text-right text-xs font-black text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none transition shadow-2xs"
                                            >
                                        </td>

                                        {{-- Selling B Input --}}
                                        <td class="px-4 py-3.5 text-center">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="products[{{ $index }}][price_b]"
                                                value="{{ $row['selling_price_b'] > 0 ? number_format($row['selling_price_b'], 2, '.', '') : '' }}"
                                                placeholder="Auto (A)"
                                                class="w-24 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right text-xs font-bold text-slate-800 focus:border-cyan-500 focus:bg-white focus:outline-none transition shadow-2xs"
                                            >
                                        </td>

                                        {{-- Selling C Input --}}
                                        <td class="px-4 py-3.5 text-center">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="products[{{ $index }}][price_c]"
                                                value="{{ $row['selling_price_c'] > 0 ? number_format($row['selling_price_c'], 2, '.', '') : '' }}"
                                                placeholder="Auto (A)"
                                                class="w-24 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right text-xs font-bold text-slate-800 focus:border-cyan-500 focus:bg-white focus:outline-none transition shadow-2xs"
                                            >
                                        </td>

                                        <td class="px-3 py-3.5 text-center text-xs font-black {{ $row['is_updated_today'] ? 'text-cyan-700' : 'text-slate-500' }}">
                                            {{ $row['source_label'] }}
                                        </td>

                                        {{-- Single Row Save Button --}}
                                        <td class="px-4 py-3.5 text-center">
                                            <button
                                                type="submit"
                                                formaction="{{ route('purchasing.prices.update') }}"
                                                formmethod="POST"
                                                class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-[11px] font-black text-white hover:bg-slate-800 transition shadow-2xs cursor-pointer"
                                                onclick="this.form.querySelectorAll('input[name^=products]').forEach(i => { if (!i.name.includes('[{{ $index }}]')) i.disabled = true; });"
                                            >
                                                Save
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-5 py-12 text-center text-sm font-semibold text-slate-400">No active products matched the selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Mobile Card View --}}
                <div class="space-y-4 p-4 lg:hidden">
                    @forelse ($products as $index => $row)
                        @php
                            $purchasePrice = (float) $row['purchase_price'];
                            $previousPrice = $row['previous_purchase_price'] !== null ? (float) $row['previous_purchase_price'] : null;
                            $change = $row['purchase_diff'];
                            $unit = strtoupper($row['unit'] ?: 'KG');
                        @endphp
                        <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm space-y-3">
                            <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $row['product_id'] }}">

                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-black text-slate-950">{{ $row['name'] }}</h4>
                                    <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $row['sku'] ?: 'No SKU' }} · {{ $unit }} · {{ $row['category_name'] }}</p>
                                </div>
                                <span class="inline-flex rounded-full bg-cyan-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-cyan-700">{{ $row['source_label'] }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="rounded-2xl bg-white p-3 space-y-1">
                                    <label class="font-black uppercase text-[10px] tracking-wide text-slate-500 block">Purchase Price</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="products[{{ $index }}][purchase_price]"
                                        value="{{ $purchasePrice > 0 ? number_format($purchasePrice, 2, '.', '') : '' }}"
                                        placeholder="0.00"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right font-black text-cyan-700 focus:border-cyan-500 focus:bg-white focus:outline-none"
                                    >
                                    <p class="text-[9px] font-semibold text-slate-400">Prev: {{ $previousPrice !== null ? number_format($previousPrice, 2) : '—' }}</p>
                                </div>

                                <div class="rounded-2xl bg-white p-3 space-y-1">
                                    <label class="font-black uppercase text-[10px] tracking-wide text-slate-500 block">Selling A (Main)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="products[{{ $index }}][price_a]"
                                        value="{{ $row['selling_price_a'] > 0 ? number_format($row['selling_price_a'], 2, '.', '') : '' }}"
                                        placeholder="0.00"
                                        class="w-full rounded-xl border border-emerald-300 bg-emerald-50/40 px-2.5 py-1.5 text-right font-black text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none"
                                    >
                                    <p class="text-[9px] font-semibold text-emerald-700">Auto-copies to B &amp; C if empty</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="rounded-2xl bg-white p-3 space-y-1">
                                    <label class="font-black uppercase text-[10px] tracking-wide text-slate-500 block">Selling B (Optional)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="products[{{ $index }}][price_b]"
                                        value="{{ $row['selling_price_b'] > 0 ? number_format($row['selling_price_b'], 2, '.', '') : '' }}"
                                        placeholder="Auto (A)"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right font-bold text-slate-800 focus:border-cyan-500 focus:bg-white focus:outline-none"
                                    >
                                </div>

                                <div class="rounded-2xl bg-white p-3 space-y-1">
                                    <label class="font-black uppercase text-[10px] tracking-wide text-slate-500 block">Selling C (Optional)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="products[{{ $index }}][price_c]"
                                        value="{{ $row['selling_price_c'] > 0 ? number_format($row['selling_price_c'], 2, '.', '') : '' }}"
                                        placeholder="Auto (A)"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right font-bold text-slate-800 focus:border-cyan-500 focus:bg-white focus:outline-none"
                                    >
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-3xl border border-slate-200 bg-white px-5 py-10 text-center text-sm font-semibold text-slate-400">No active products matched the selected filters.</div>
                    @endforelse
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                    <div>{{ $products->links() }}</div>
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-emerald-700 transition shadow-sm w-full sm:w-auto"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Save All Daily Prices</span>
                    </button>
                </div>
            </section>
        </form>
    </div>
@endsection
