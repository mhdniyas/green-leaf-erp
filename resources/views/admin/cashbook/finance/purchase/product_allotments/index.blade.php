@extends('admin.cashbook.layouts.app')

@section('title', 'Product Purchaser Allotments - Purchase Cashbook')
@section('header_title')
    <i data-lucide="user-check" class="h-5 w-5 text-emerald-600"></i> Product Purchaser Allotments
@endsection

@section('header_subtitle')
    Manage effective-dated purchaser assignments for produce responsibility tracking
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5" x-data="{ assignModalOpen: false, modalProduct: null }">
    @if(session('success'))
        <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">
            <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs font-bold text-red-800">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Header / Title Bar -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-xl font-black text-slate-950">Product Purchaser Allotment History</h1>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">
                Assign purchasers to products with effective start dates to maintain stable historical responsibility records.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.finance.purchase') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Purchase Cashbook
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.cashbook.finance.purchase.product-allotments.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Search Product / SKU</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="e.g. Tomato or TOM-01" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Category</label>
                <select name="category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Purchaser</label>
                <select name="purchaser_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">All Purchasers</option>
                    @foreach($purchasers as $purchaser)
                        <option value="{{ $purchaser->id }}" {{ request('purchaser_id') == $purchaser->id ? 'selected' : '' }}>
                            {{ $purchaser->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex min-h-9 w-full items-center justify-center gap-1 rounded-lg bg-emerald-700 px-3 text-xs font-bold text-white hover:bg-emerald-800">
                    <i data-lucide="filter" class="h-3.5 w-3.5"></i> Filter
                </button>
                @if(request()->anyFilled(['search', 'category_id', 'purchaser_id']))
                    <a href="{{ route('admin.cashbook.finance.purchase.product-allotments.index') }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Product Allotments Table -->
    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <th class="p-3">Product Name</th>
                        <th class="p-3">Category</th>
                        <th class="p-3">Current Purchaser</th>
                        <th class="p-3">Effective From</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($products as $product)
                        @php
                            $allotment = $product->currentPurchaserAllotment;
                        @endphp
                        <tr class="hover:bg-slate-50/75">
                            <td class="p-3 font-bold text-slate-950">
                                <div>
                                    <span class="text-sm font-extrabold text-slate-900">{{ $product->name }}</span>
                                    @if($product->sku)
                                        <span class="ml-1 text-[10px] font-mono text-slate-400">({{ $product->sku }})</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ $product->category?->name ?? 'Uncategorized' }}
                                </span>
                            </td>
                            <td class="p-3">
                                @if($allotment?->purchaser)
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black">
                                            {{ strtoupper(substr($allotment->purchaser->name, 0, 1)) }}
                                        </span>
                                        <span class="font-bold text-slate-900">{{ $allotment->purchaser->name }}</span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800 border border-amber-200">
                                        Unassigned
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-600">
                                {{ $allotment?->effective_from ? $allotment->effective_from->format('d M Y') : '-' }}
                            </td>
                            <td class="p-3">
                                @if($allotment?->purchaser)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700 border border-emerald-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">
                                        No Allotment
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" @click="modalProduct = { id: {{ $product->id }}, name: '{{ addslashes($product->name) }}', purchaser_id: {{ $allotment?->purchaser_user_id ?? 'null' }} }; assignModalOpen = true" class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800 hover:bg-emerald-100">
                                        <i data-lucide="user-plus" class="h-3.5 w-3.5"></i> Assign
                                    </button>
                                    <a href="{{ route('admin.cashbook.finance.purchase.product-allotments.history', $product) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                        <i data-lucide="history" class="h-3.5 w-3.5"></i> History
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                No products found matching criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="border-t border-slate-200 p-4">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- Assign Purchaser Modal -->
    <div x-show="assignModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <div @click.away="assignModalOpen = false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-900">Assign Product Purchaser</h3>
                <button type="button" @click="assignModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.cashbook.finance.purchase.product-allotments.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="product_id" :value="modalProduct?.id">
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                <input type="hidden" name="purchaser_id" value="{{ request('purchaser_id') }}">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Product</label>
                    <div class="rounded-lg bg-slate-100 p-2.5 text-xs font-black text-slate-900" x-text="modalProduct?.name"></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">New Purchaser</label>
                    <select name="purchaser_user_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="">Select Purchaser...</option>
                        @foreach($purchasers as $purchaser)
                            <option value="{{ $purchaser->id }}">{{ $purchaser->name }} ({{ $purchaser->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Effective From Date</label>
                    <input type="date" name="effective_from" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <p class="mt-1 text-[10px] text-slate-500">The current active allotment will automatically be closed on the day before this effective date.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="assignModalOpen = false" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white hover:bg-emerald-800">
                        Save Allotment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
