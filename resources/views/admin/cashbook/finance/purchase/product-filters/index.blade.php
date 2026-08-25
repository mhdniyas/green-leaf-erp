@extends('admin.cashbook.layouts.app')

@section('title', 'Saved Product Filters - Purchase Cashbook')
@section('header_title')
    <i data-lucide="filter" class="h-5 w-5 text-emerald-600"></i> Saved Product Filters
@endsection

@section('header_subtitle')
    Manage reusable product groups for purchase cashbook reports
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @if(session('success'))
        <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">
            <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-xl font-black text-slate-950">Purchase Product Filters</h1>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Create and manage reusable sets of products to filter purchase reports without relying on warehouse codes.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.finance.purchase') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Purchase Cashbook
            </a>
            <a href="{{ route('admin.cashbook.finance.purchase.product-filters.create') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800">
                <i data-lucide="plus" class="h-4 w-4"></i> Create Product Filter
            </a>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <th class="p-3">Filter Name</th>
                        <th class="p-3">Products Included</th>
                        <th class="p-3">Created By</th>
                        <th class="p-3">Created Date</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($filters as $filter)
                        <tr class="hover:bg-slate-50/75">
                            <td class="p-3 font-bold text-slate-950">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800 font-black">
                                        <i data-lucide="tag" class="h-3.5 w-3.5"></i>
                                    </span>
                                    <span>{{ $filter->name }}</span>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-800">
                                    {{ $filter->products_count }} {{ str('product')->plural($filter->products_count) }}
                                </span>
                            </td>
                            <td class="p-3 text-slate-500">
                                {{ $filter->createdBy?->name ?? 'System' }}
                            </td>
                            <td class="p-3 text-slate-500">
                                {{ $filter->created_at?->format('d M Y, h:i A') ?? '-' }}
                            </td>
                            <td class="p-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('admin.cashbook.finance.purchase', ['product_filter' => $filter->uuid]) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-emerald-700" title="Apply filter to purchase dashboard">
                                        <i data-lucide="play" class="h-3.5 w-3.5"></i> Apply
                                    </a>
                                    <a href="{{ route('admin.cashbook.finance.purchase.product-filters.edit', $filter->uuid) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-emerald-700">
                                        <i data-lucide="edit-2" class="h-3.5 w-3.5"></i> Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.purchase.product-filters.destroy', $filter->uuid) }}" onsubmit="return confirm('Are you sure you want to delete the filter \'{{ addslashes($filter->name) }}\'?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400 font-medium">
                                <i data-lucide="filter-x" class="mx-auto h-8 w-8 text-slate-300"></i>
                                <p class="mt-2 text-sm font-bold text-slate-700">No saved product filters yet</p>
                                <p class="mt-1 text-xs text-slate-500">Create a saved product filter to quickly categorize and analyze your purchases.</p>
                                <a href="{{ route('admin.cashbook.finance.purchase.product-filters.create') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800">
                                    <i data-lucide="plus" class="h-4 w-4"></i> Create First Filter
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($filters->hasPages())
            <div class="border-t border-slate-200 p-3">
                {{ $filters->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
