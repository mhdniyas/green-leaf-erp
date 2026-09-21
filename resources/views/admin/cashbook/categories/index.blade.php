@extends('admin.cashbook.layouts.app')

@section('title', 'Cashbook Categories — Management')

@section('content')
<div class="space-y-6">
    <!-- Session Messages -->
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-sm flex items-center gap-3">
            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <a href="{{ route('admin.cashbook.settings') }}" class="hover:text-slate-600 transition">Settings</a>
                <span>/</span>
                <span class="text-indigo-600">Categories</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">Cashbook Categories</h1>
            <p class="text-xs font-semibold text-slate-500 mt-1">Manage global category identities, shop assignments, headers, settlement flows, company accounts, and report flags.</p>
        </div>
        <div class="shrink-0">
            <a href="{{ route('admin.cashbook.categories.create') }}" class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-xs font-black text-white shadow-md hover:bg-indigo-700 transition">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Add Category</span>
            </a>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.cashbook.categories.index') }}" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div class="relative min-w-[240px]">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search category or code..." class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>
            <select name="category" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                <option value="">All Base Types</option>
                <option value="income" {{ request('category') === 'income' ? 'selected' : '' }}>Income</option>
                <option value="expense" {{ request('category') === 'expense' ? 'selected' : '' }}>Expense</option>
                <option value="transfer" {{ request('category') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                <option value="settlement" {{ request('category') === 'settlement' ? 'selected' : '' }}>Settlement</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-xs font-extrabold hover:bg-slate-900 transition">Filter</button>
            @if(request()->anyFilled(['search', 'category']))
                <a href="{{ route('admin.cashbook.categories.index') }}" class="text-xs font-bold text-slate-500 hover:text-slate-700">Clear</a>
            @endif
        </form>
        <div class="text-xs font-bold text-slate-500">
            Total Categories: <span class="text-slate-900 font-extrabold">{{ $categories->count() }}</span>
        </div>
    </div>

    <!-- Table List -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-black uppercase text-slate-500 tracking-wider">
                    <tr>
                        <th class="py-3.5 px-4">Category</th>
                        <th class="py-3.5 px-4">Type</th>
                        <th class="py-3.5 px-4">Shops</th>
                        <th class="py-3.5 px-4">Header</th>
                        <th class="py-3.5 px-4">Settlement</th>
                        <th class="py-3.5 px-4">Company Relation</th>
                        <th class="py-3.5 px-4">Vendor</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($categories as $cat)
                        @php
                            $sum = $categorySummaries[$cat->id] ?? [];
                            $typeBadgeClass = match(strtolower((string)$cat->category)) {
                                'income' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'expense' => 'bg-rose-50 text-rose-700 border-rose-200',
                                'transfer' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'settlement' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                default => 'bg-slate-50 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 font-extrabold text-slate-900">
                                <div class="flex flex-col">
                                    <span class="text-slate-900">{{ $cat->name }}</span>
                                    <span class="text-[10px] font-mono text-slate-400 font-normal">{{ $cat->code }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded-lg border text-[10px] font-extrabold uppercase {{ $typeBadgeClass }}">
                                    {{ $cat->category }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-slate-800">{{ $sum['shop_count'] ?? 0 }} / {{ $sum['total_shops'] ?? 0 }} Shops</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs font-bold {{ ($sum['header'] ?? '') === 'Varies' ? 'text-amber-700 font-black' : 'text-slate-600' }}">
                                    {{ $sum['header'] ?? 'None' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs font-bold {{ ($sum['settlement'] ?? '') === 'Varies' ? 'text-amber-700 font-black' : 'text-slate-600' }}">
                                    {{ $sum['settlement'] ?? 'None' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs font-bold text-slate-600">
                                    {{ $sum['company'] ?? 'None' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs font-bold text-slate-600">
                                    {{ $sum['vendor'] ?? 'None' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($cat->active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-black">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 border border-slate-200 text-[10px] font-black">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="{{ route('admin.cashbook.categories.show', $cat->code) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 font-black text-xs hover:bg-indigo-100 transition">
                                    <span>View / Edit</span>
                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400 font-bold">
                                No categories found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
