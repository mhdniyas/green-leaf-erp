@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' Finance Overview')

@section('header_title')
    <i data-lucide="store" class="h-5 w-5 text-emerald-700"></i> {{ $currentShop->name }}
@endsection

@section('header_subtitle')
    Shop finance overview for {{ \Illuminate\Support\Carbon::parse($monthStart)->format('F Y') }}
@endsection

@section('header_actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800"><i data-lucide="wallet" class="h-4 w-4"></i> Receive Payment</a>
        <a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->uuid, 'timeframe' => 'monthly', 'date' => $monthEnd]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50"><i data-lucide="book-open" class="h-4 w-4"></i> View Ledger</a>
        <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50"><i data-lucide="badge-check" class="h-4 w-4"></i> Reconciliation</a>
    </div>
@endsection

@section('content')
    @php
        $shopPosition = (float) ($position->closing_shop_position ?? 0);
        $companyPending = (float) ($position->closing_company_pending ?? 0);
        $pettyBalance = (float) ($position->closing_petty ?? 0);
        $netBalance = (float) ($position->total_sales ?? 0) - (float) ($position->total_expense ?? 0);
        $awaitingBank = $floatingPayments;
        $awaitingSettlement = max(0, $cashBankReceived - $ledgerSettled);
    @endphp
    <div class="mx-auto max-w-7xl space-y-5">
        <form method="GET" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div><p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Date Context</p><p class="text-sm font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($monthStart)->format('F Y') }}</p></div>
            <label class="flex items-center gap-2 text-xs font-black text-slate-600">Month <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()" class="min-h-10 rounded-xl border border-slate-300 px-3 font-mono text-sm text-slate-900"></label>
        </form>

        <section>
            <div class="mb-3"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Overview</p><h2 class="text-lg font-black text-slate-950">Shop and company position</h2></div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-300 bg-slate-950 p-4 text-white"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending Payable to Company</p><p class="mt-2 text-right font-mono text-2xl font-black">₹{{ number_format($pendingPayable, 2) }}</p><p class="mt-1 text-xs font-semibold text-slate-300">Configured categories, less reconciled receipts</p></article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Payment Received</p><p class="mt-2 text-right font-mono text-2xl font-black text-amber-950">₹{{ number_format($cashBankReceived, 2) }}</p><p class="mt-1 text-xs font-semibold text-amber-800">Reconciled · Floating ₹{{ number_format($floatingPayments, 2) }}</p></article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-4"><p class="text-[10px] font-black uppercase tracking-wider text-violet-700">Company → Shop Pending</p><p class="mt-2 text-right font-mono text-2xl font-black text-violet-950">₹{{ number_format($companyPending, 2) }}</p><p class="mt-1 text-xs font-semibold text-violet-800">Separate reimbursement position</p></article>
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-4"><p class="text-[10px] font-black uppercase tracking-wider text-sky-700">GL Bill Pending</p><p class="mt-2 text-right font-mono text-2xl font-black text-sky-950">₹{{ number_format($glBillPending, 2) }}</p><p class="mt-1 text-xs font-semibold text-sky-800">Open ShopInvoice balance</p></article>
                <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4"><p class="text-[10px] font-black uppercase tracking-wider text-rose-700">Current Net Balance</p><p class="mt-2 text-right font-mono text-2xl font-black text-rose-950">₹{{ number_format($netBalance, 2) }}</p><p class="mt-1 text-xs font-semibold text-rose-800">Existing sales − expense total</p></article>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Settled Against Shop Finance</p><p class="mt-2 text-right font-mono text-xl font-black text-slate-950">₹{{ number_format($ledgerSettled, 2) }}</p><p class="mt-1 text-xs font-semibold text-slate-500">Operational settlement only</p><a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month]) }}" class="mt-3 inline-flex text-xs font-black text-emerald-700 hover:text-emerald-900">View Details</a></article>
            <article class="rounded-2xl border border-cyan-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-cyan-700">Petty Balance</p><p class="mt-2 text-right font-mono text-xl font-black text-slate-950">₹{{ number_format($pettyBalance, 2) }}</p><p class="mt-1 text-xs font-semibold text-slate-500">{{ $recentPettyActivity->count() }} recent petty movements</p><a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->uuid, 'timeframe' => 'monthly', 'date' => $monthEnd]) }}" class="mt-3 inline-flex text-xs font-black text-cyan-700 hover:text-cyan-900">View Petty Details</a></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Open Shop Ledger Items</p><p class="mt-2 text-right font-mono text-xl font-black text-slate-950">{{ number_format($openLedgerItems) }}</p><p class="mt-1 text-xs font-semibold text-slate-500">Open relations requiring attention</p><a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->uuid, 'timeframe' => 'monthly', 'date' => $monthEnd]) }}" class="mt-3 inline-flex text-xs font-black text-slate-700 hover:text-slate-950">View Open Items</a></article>
            <article class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Still Awaiting</p><p class="mt-2 text-right font-mono text-xl font-black text-slate-950">₹{{ number_format($awaitingBank, 2) }}</p><p class="mt-1 text-xs font-semibold text-slate-500">Statement ₹{{ number_format($awaitingBank, 2) }} · Settlement ₹{{ number_format($awaitingSettlement, 2) }}</p><a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month]) }}" class="mt-3 inline-flex text-xs font-black text-amber-700 hover:text-amber-900">View Payments</a></article>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Recent Payments</p><h2 class="mt-1 text-base font-black text-slate-950">Submission → receipt → settlement</h2></div><a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month]) }}" class="text-xs font-black text-emerald-700">View All Payments</a></div><div class="divide-y divide-slate-100">@forelse($recentPayments as $payment)<div class="grid gap-2 px-5 py-4 sm:grid-cols-[1fr_auto]"><div><p class="font-mono text-sm font-black text-slate-950">₹{{ number_format((float) $payment->requested_amount, 2) }} <span class="font-sans text-xs text-slate-500">{{ $payment->paymentMethodLabel() }} · {{ $payment->payment_date?->format('d M Y') }}</span></p><p class="mt-1 text-xs font-semibold text-slate-500">{{ $payment->payment_reference ?: 'No reference' }}</p><p class="mt-2 text-xs font-black {{ $payment->reconciliation_status === 'reconciled' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $payment->reconciliation_status === 'reconciled' ? ($payment->ledger_allocations_exists ? 'Settled' : 'Awaiting Shop Settlement') : 'Awaiting Reconciliation' }}</p></div><a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month, 'payment_ref' => $payment->reconciliation_status === 'reconciled' ? $payment->secureRouteKey() : null]) }}" class="self-center text-xs font-black text-slate-700">{{ $payment->reconciliation_status === 'reconciled' ? ($payment->ledger_allocations_exists ? 'View' : 'Settle Shop Ledger') : 'View' }}</a></div>@empty<div class="px-5 py-8 text-sm font-semibold text-slate-500">No payment submissions yet.</div>@endforelse</div></article>
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Recent Shop Ledger Activity</p><h2 class="mt-1 text-base font-black text-slate-950">Latest financial relations</h2></div><a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->uuid, 'timeframe' => 'monthly', 'date' => $monthEnd]) }}" class="text-xs font-black text-slate-700">View Full Ledger</a></div><div class="divide-y divide-slate-100">@forelse($recentLedgerActivity as $entry)<div class="grid grid-cols-[1fr_auto] gap-3 px-5 py-4"><div><p class="text-sm font-black text-slate-950">{{ $entry->entryType?->name ?: 'Ledger Entry' }}</p><p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->business_date?->format('d M Y') }} · {{ $entry->notes ?: 'No description' }}</p></div><div class="text-right"><p class="font-mono text-sm font-black {{ $entry->direction === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format((float) $entry->amount, 2) }}</p><p class="mt-1 text-xs font-bold text-slate-500">{{ (float) ($entry->settled_amount ?? 0) > 0 ? 'Cleared' : 'Open' }}</p></div></div>@empty<div class="px-5 py-8 text-sm font-semibold text-slate-500">No recent ledger activity.</div>@endforelse</div></article>
        </section>
    </div>
@endsection
