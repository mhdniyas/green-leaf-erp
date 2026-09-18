@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Banking Verification Full History')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
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
                <p class="text-xs text-slate-500 mt-0.5">Banking Collections &amp; Statement Verification History</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
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
    <form method="GET" action="{{ route('admin.cashbook.shop.history.banking', $currentShop->slug ?: $currentShop->shop_id) }}"
          class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center gap-3 text-xs font-bold">
        <div class="flex items-center gap-1.5">
            <label class="text-slate-400 uppercase text-[10px]">Bank:</label>
            <select name="bank_account_id" class="px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-300 text-slate-900 focus:bg-white focus:outline-none">
                <option value="">All Connected Banks</option>
                @foreach($connectedBankAccounts as $bank)
                    <option value="{{ $bank->id }}" {{ (int) $selectedBankId === (int) $bank->id ? 'selected' : '' }}>
                        {{ $bank->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-1.5">
            <label class="text-slate-400 uppercase text-[10px]">Status:</label>
            <select name="banking_status" class="px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-300 text-slate-900 focus:bg-white focus:outline-none">
                <option value="all" {{ $bankingStatusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                <option value="verified" {{ $bankingStatusFilter === 'verified' ? 'selected' : '' }}>Verified Only</option>
                <option value="pending" {{ $bankingStatusFilter === 'pending' ? 'selected' : '' }}>Pending Verification</option>
            </select>
        </div>

        <div class="flex items-center gap-1.5">
            <label class="text-slate-400 uppercase text-[10px]">Month:</label>
            <input type="month" name="month" value="{{ $month }}" class="px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <button type="submit" class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold shadow-xs transition">
            Filter
        </button>
        @if($month || $selectedBankId || $bankingStatusFilter !== 'all')
            <a href="{{ route('admin.cashbook.shop.history.banking', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                Reset
            </a>
        @endif
    </form>

    <!-- Full History Table Card -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">All Banking Transactions</h2>
            <span class="text-xs text-slate-500 font-mono font-bold">Total: {{ $bankingTransactions->total() }} records</span>
        </div>

        @include('admin.cashbook.shops.partials.banking-table', ['bankingTransactions' => $bankingTransactions, 'isFull' => true])

        @if($bankingTransactions->hasPages())
            <div class="pt-4 border-t border-slate-100">
                {{ $bankingTransactions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
