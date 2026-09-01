@extends('admin.cashbook.layouts.app')

@section('title', $purchaser->name.' - Purchaser Finance')

@section('header_title')
    <i data-lucide="wallet-cards" class="h-5 w-5 text-emerald-600"></i> {{ $purchaser->name }}
@endsection

@section('header_subtitle')
    Cash utilization and vendor-credit splits for this purchaser.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser->public_uuid, 'period' => 'month']) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
            <i data-lucide="shopping-basket" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Purchase Profile</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.purchasers') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            <span>Back</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cash Given</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($kpi['cash_given'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cash Used</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-amber-700">₹{{ number_format($kpi['cash_used'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Remaining Advance</span>
                <div class="mt-2 font-mono text-2xl font-extrabold {{ $kpi['remaining_advance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($kpi['remaining_advance'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Credit Purchases</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-rose-700">₹{{ number_format($kpi['credit_purchases'], 2) }}</div>
            </div>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Cash and Credit Splits</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Cash rows consume purchaser advance. Credit rows stay in Vendor Credit.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $splits->total() }} rows</span>
            </div>

            <form method="GET" action="{{ route('admin.cashbook.finance.purchasers.details', $purchaser->public_uuid) }}" class="mb-4 grid gap-3 md:grid-cols-[auto_1fr_1fr_1.5fr_auto]">
                <x-cashbook.previous-month-button mode="range" size="sm" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                <input type="date" name="start_date" value="{{ $startDate }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                <input type="date" name="end_date" value="{{ $endDate }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search supplier or bill" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                <button class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-500">
                    <i data-lucide="filter" class="h-4 w-4"></i> Filter
                </button>
            </form>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Supplier</th>
                            <th class="px-3 py-3">Invoice / Bill</th>
                            <th class="px-3 py-3">Payment Type</th>
                            <th class="px-3 py-3 text-right">Amount</th>
                            <th class="px-3 py-3">Funding / Utilization Reference</th>
                            <th class="px-3 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($splits as $split)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ \Illuminate\Support\Carbon::parse($split->row_date)->format('Y-m-d') }}</td>
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $split->supplier_name }}</td>
                                <td class="px-3 py-3 font-mono font-bold text-slate-800">{{ $split->invoice_number }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $split->payment_type === 'Cash' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $split->payment_type }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-extrabold text-slate-950">₹{{ number_format((float) $split->amount, 2) }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-600">{{ $split->movement_reference ?: '—' }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">
                                        {{ str_replace('_', ' ', (string) $split->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm font-bold text-slate-400">No purchaser splits found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $splits->links() }}</div>
        </section>
    </div>
@endsection
