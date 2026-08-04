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
                        This page shows the weighted-average buying cost for each active product on the selected business day.
                        Selling prices are shown only as reference so the two flows stay separated.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
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
                        @foreach ([20, 30, 50] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-4 flex flex-wrap items-center justify-between gap-3 pt-1">
                    <p class="text-xs font-medium text-slate-500">Weighted GRN purchase average is the source of truth for buying cost. Selling prices are comparison only.</p>
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

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Product price list</h3>
                    <p class="mt-1 text-xs font-medium text-slate-500">Previous day comparison uses the latest approved purchase price before {{ \Illuminate\Support\Carbon::parse($purchaseDate)->format('d M Y') }}.</p>
                </div>
                <div class="text-xs font-semibold text-slate-500">
                    Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }}
                </div>
            </div>

            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3">Category</th>
                                <th class="px-5 py-3 text-right">Purchase price</th>
                                <th class="px-5 py-3 text-right">Prev day</th>
                                <th class="px-5 py-3 text-right">Change</th>
                                <th class="px-5 py-3 text-right">Selling A</th>
                                <th class="px-5 py-3 text-right">Selling B</th>
                                <th class="px-5 py-3 text-right">Selling C</th>
                                <th class="px-5 py-3 text-right">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($products as $row)
                                @php
                                    $purchasePrice = (float) $row['purchase_price'];
                                    $previousPrice = $row['previous_purchase_price'] !== null ? (float) $row['previous_purchase_price'] : null;
                                    $change = $row['purchase_diff'];
                                    $unit = strtoupper($row['unit'] ?: 'KG');
                                    $marginA = $row['selling_price_a'] > 0 ? round($row['selling_price_a'] - $purchasePrice, 2) : null;
                                    $marginB = $row['selling_price_b'] > 0 ? round($row['selling_price_b'] - $purchasePrice, 2) : null;
                                    $marginC = $row['selling_price_c'] > 0 ? round($row['selling_price_c'] - $purchasePrice, 2) : null;
                                @endphp
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-5 py-4">
                                        <div class="font-black text-slate-900">{{ $row['name'] }}</div>
                                        <div class="mt-1 text-xs font-semibold text-slate-500">{{ $row['sku'] ?: 'No SKU' }} · {{ $unit }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-xs font-bold text-slate-600">{{ $row['category_name'] }}</td>
                                    <td class="px-5 py-4 text-right font-black text-cyan-700">{{ number_format($purchasePrice, 2) }}</td>
                                    <td class="px-5 py-4 text-right text-xs font-bold text-slate-600">{{ $previousPrice !== null ? number_format($previousPrice, 2) : '—' }}</td>
                                    <td class="px-5 py-4 text-right">
                                        @if ($change !== null)
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black {{ $change > 0 ? 'bg-emerald-100 text-emerald-700' : ($change < 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700') }}">
                                                {{ $change > 0 ? '+' : '' }}{{ number_format((float) $change, 2) }}
                                            </span>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="font-black text-slate-900">{{ $row['selling_price_a'] > 0 ? number_format($row['selling_price_a'], 2) : '—' }}</div>
                                        @if ($marginA !== null)
                                            <div class="mt-1 text-[10px] font-semibold {{ $marginA >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $marginA >= 0 ? '+' : '' }}{{ number_format($marginA, 2) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="font-black text-slate-900">{{ $row['selling_price_b'] > 0 ? number_format($row['selling_price_b'], 2) : '—' }}</div>
                                        @if ($marginB !== null)
                                            <div class="mt-1 text-[10px] font-semibold {{ $marginB >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $marginB >= 0 ? '+' : '' }}{{ number_format($marginB, 2) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="font-black text-slate-900">{{ $row['selling_price_c'] > 0 ? number_format($row['selling_price_c'], 2) : '—' }}</div>
                                        @if ($marginC !== null)
                                            <div class="mt-1 text-[10px] font-semibold {{ $marginC >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $marginC >= 0 ? '+' : '' }}{{ number_format($marginC, 2) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right text-xs font-black {{ $row['is_updated_today'] ? 'text-cyan-700' : 'text-slate-500' }}">{{ $row['source_label'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-12 text-center text-sm font-semibold text-slate-400">No active products matched the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-3 p-4 lg:hidden">
                @forelse ($products as $row)
                    @php
                        $purchasePrice = (float) $row['purchase_price'];
                        $previousPrice = $row['previous_purchase_price'] !== null ? (float) $row['previous_purchase_price'] : null;
                        $change = $row['purchase_diff'];
                        $unit = strtoupper($row['unit'] ?: 'KG');
                        $marginA = $row['selling_price_a'] > 0 ? round($row['selling_price_a'] - $purchasePrice, 2) : null;
                        $marginB = $row['selling_price_b'] > 0 ? round($row['selling_price_b'] - $purchasePrice, 2) : null;
                        $marginC = $row['selling_price_c'] > 0 ? round($row['selling_price_c'] - $purchasePrice, 2) : null;
                    @endphp
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-base font-black text-slate-950">{{ $row['name'] }}</h4>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['sku'] ?: 'No SKU' }} · {{ $unit }} · {{ $row['category_name'] }}</p>
                            </div>
                            <span class="inline-flex rounded-full bg-cyan-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-cyan-700">{{ $row['source_label'] }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Purchase price</p>
                                <p class="mt-1 text-lg font-black text-cyan-700">{{ number_format($purchasePrice, 2) }}</p>
                                <p class="mt-1 text-[10px] font-semibold text-slate-500">Prev: {{ $previousPrice !== null ? number_format($previousPrice, 2) : '—' }}</p>
                            </div>
                            <div class="rounded-2xl bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Change</p>
                                @if ($change !== null)
                                    <p class="mt-1 text-lg font-black {{ $change > 0 ? 'text-emerald-700' : ($change < 0 ? 'text-rose-700' : 'text-slate-700') }}">
                                        {{ $change > 0 ? '+' : '' }}{{ number_format((float) $change, 2) }}
                                    </p>
                                @else
                                    <p class="mt-1 text-lg font-black text-slate-400">—</p>
                                @endif
                                <p class="mt-1 text-[10px] font-semibold text-slate-500">vs previous day</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-2xl bg-white p-3 text-right">
                                <p class="font-black uppercase tracking-wide text-slate-500">Sell A</p>
                                <p class="mt-1 text-sm font-black text-slate-900">{{ $row['selling_price_a'] > 0 ? number_format($row['selling_price_a'], 2) : '—' }}</p>
                                @if ($marginA !== null)
                                    <p class="mt-1 font-semibold {{ $marginA >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $marginA >= 0 ? '+' : '' }}{{ number_format($marginA, 2) }}</p>
                                @endif
                            </div>
                            <div class="rounded-2xl bg-white p-3 text-right">
                                <p class="font-black uppercase tracking-wide text-slate-500">Sell B</p>
                                <p class="mt-1 text-sm font-black text-slate-900">{{ $row['selling_price_b'] > 0 ? number_format($row['selling_price_b'], 2) : '—' }}</p>
                                @if ($marginB !== null)
                                    <p class="mt-1 font-semibold {{ $marginB >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $marginB >= 0 ? '+' : '' }}{{ number_format($marginB, 2) }}</p>
                                @endif
                            </div>
                            <div class="rounded-2xl bg-white p-3 text-right">
                                <p class="font-black uppercase tracking-wide text-slate-500">Sell C</p>
                                <p class="mt-1 text-sm font-black text-slate-900">{{ $row['selling_price_c'] > 0 ? number_format($row['selling_price_c'], 2) : '—' }}</p>
                                @if ($marginC !== null)
                                    <p class="mt-1 font-semibold {{ $marginC >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $marginC >= 0 ? '+' : '' }}{{ number_format($marginC, 2) }}</p>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-slate-200 bg-white px-5 py-10 text-center text-sm font-semibold text-slate-400">No active products matched the selected filters.</div>
                @endforelse
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                {{ $products->links() }}
            </div>
        </section>
    </div>
@endsection
