@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Settings - Cashbook')

@section('content')
<div class="space-y-5">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">Shop Settings</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Select one shop and edit only that shop's cashbook rows.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $shops->count() }} shops</span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($shops as $shop)
            <a href="{{ route('admin.cashbook.settings.shop', $shop->slug ?: $shop->shop_id) }}"
               class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-black text-slate-950 group-hover:text-emerald-700">{{ $shop->name }}</h2>
                        <p class="mt-1 font-mono text-xs font-bold text-slate-400">{{ $shop->code ?: 'SHOP-'.$shop->shop_id }}</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                        <i data-lucide="store" class="h-5 w-5"></i>
                    </span>
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                    <span class="text-xs font-bold text-slate-500">Income, Expense, Transfer, Collection</span>
                    <span class="inline-flex items-center gap-1 text-xs font-black text-emerald-700">
                        Open Settings
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
