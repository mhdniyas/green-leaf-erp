@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Petty Cash Full History')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16" x-data="{ showFundPettyModal: false, isSubmitting: false }">
    <!-- Header with Back & Settings buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cashbook.shop.show', $currentShop->slug ?: $currentShop->shop_id) }}"
               class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
               title="Back to Shop Operation Center">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">{{ $currentShop->name }}</h1>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono">{{ $currentShop->code }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Shop Petty Cash Movements &amp; Funding History</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" @click="showFundPettyModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-xs transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Fund Petty</span>
            </button>
            @if(auth()->user() && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                <a href="{{ route('admin.cashbook.settings.shop', $currentShop->slug ?: $currentShop->shop_id) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition"
                   title="Cashbook Settings">
                    <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
                    <span>Settings</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.cashbook.shop.history.petty', $currentShop->slug ?: $currentShop->shop_id) }}"
          class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center gap-3 text-xs font-bold">
        <div class="flex items-center gap-1.5">
            <label class="text-slate-400 uppercase text-[10px]">Month:</label>
            <input type="month" name="month" value="{{ $month }}" class="px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <div class="flex items-center gap-1.5 flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search description, reference..." class="w-full px-3 py-1.5 bg-slate-50 rounded-xl border border-slate-300 text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <button type="submit" class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold shadow-xs transition">
            Filter
        </button>
        @if($month || $search)
            <a href="{{ route('admin.cashbook.shop.history.petty', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                Reset
            </a>
        @endif
    </form>

    <!-- Full History Table Card -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">All Petty Cash Transactions</h2>
            <span class="text-xs text-slate-500 font-mono font-bold">Total: {{ $pettyTransactions->total() }} records</span>
        </div>

        @include('admin.cashbook.shops.partials.petty-table', ['pettyTransactions' => $pettyTransactions, 'isFull' => true])

        @if($pettyTransactions->hasPages())
            <div class="pt-4 border-t border-slate-100">
                {{ $pettyTransactions->links() }}
            </div>
        @endif
    </div>

    <!-- Fund Petty Modal Include -->
    @include('admin.cashbook.shops.partials.fund-petty-modal')
</div>
@endsection
