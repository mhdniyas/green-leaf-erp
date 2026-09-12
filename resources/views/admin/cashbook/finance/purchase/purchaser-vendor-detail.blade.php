@extends('admin.cashbook.layouts.app')

@php
    $activePurchaseTab = 'overview';
    $detailContext = ['period' => $filters['period']];
    if (in_array($filters['period'], ['custom', 'between', 'range'], true)) {
        $detailContext += ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']];
    }
    if (($filters['payment'] ?? 'all') !== 'all') {
        $detailContext['payment'] = $filters['payment'];
    }
    $backToPurchaserUrl = route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser->public_uuid] + $detailContext);
@endphp

@section('title', $purchaser->name . ' → ' . $supplier->name . ' - Purchase Vendor Detail')
@section('header_title')
    <i data-lucide="shopping-bag" class="h-5 w-5 text-emerald-600"></i> Purchase Finance
@endsection
@section('header_subtitle')
    Purchaser &rarr; Vendor Detail
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase._nav')

    {{-- Breadcrumb & Header --}}
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <nav class="flex flex-wrap items-center gap-1 text-xs font-bold text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('admin.cashbook.index') }}" class="hover:text-emerald-700">Cashbook</a><span>/</span>
                <a href="{{ route('admin.cashbook.finance.purchase') }}" class="hover:text-emerald-700">Purchase</a><span>/</span>
                <a href="{{ route('admin.cashbook.finance.purchase.purchasers') }}" class="hover:text-emerald-700">Purchasers</a><span>/</span>
                <a href="{{ $backToPurchaserUrl }}" class="hover:text-emerald-700">{{ $purchaser->name }}</a><span>/</span>
                <span class="text-slate-900">{{ $supplier->name }}</span>
            </nav>
            <div class="mt-2 flex flex-wrap items-baseline gap-2">
                <h1 class="text-2xl font-black tracking-tight text-slate-950">
                    {{ $purchaser->name }} <span class="text-slate-400 font-medium">&rarr;</span> {{ $supplier->name }}
                </h1>
                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">Purchaser Scope</span>
            </div>
            <p class="mt-1 text-xs font-bold text-slate-500">
                Purchases and company settlement breakdown for {{ $filters['start_date'] }} to {{ $filters['end_date'] }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ $backToPurchaserUrl }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-black text-slate-700 shadow-xs hover:bg-slate-50 transition">
                <i data-lucide="arrow-left" class="h-4 w-4 text-slate-500"></i>
                <span>Back to Purchaser</span>
            </a>
        </div>
    </header>

    {{-- Period Filter Engine --}}
    <form method="GET" action="{{ route('admin.cashbook.finance.purchase.purchasers.vendors.show', ['purchaser' => $purchaser->public_uuid, 'supplier' => $supplier->public_uuid]) }}" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Filter Period:</span>
            @include('admin.cashbook.finance.purchase._period-controls', [
                'periodRoute' => 'admin.cashbook.finance.purchase.purchasers.vendors.show',
                'periodBaseQuery' => ['purchaser' => $purchaser->public_uuid, 'supplier' => $supplier->public_uuid],
                'filters' => $filters,
            ])
        </div>
    </form>

    {{-- Vendor Detail KPI Summary Cards --}}
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <span class="text-[10px] font-black uppercase text-slate-400">Total Purchase</span>
            <strong class="mt-1.5 block font-mono text-xl font-black text-slate-950">₹{{ number_format((float) $vendorSummary['total_purchase'], 2) }}</strong>
            <span class="mt-0.5 block text-[10px] text-slate-500">{{ $vendorSummary['bills_count'] }} total bills</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <span class="text-[10px] font-black uppercase text-slate-400">Cash</span>
            <strong class="mt-1.5 block font-mono text-xl font-black text-emerald-700">₹{{ number_format((float) $vendorSummary['cash_purchase'], 2) }}</strong>
            <span class="mt-0.5 block text-[10px] text-slate-500">Cash purchase amount</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <span class="text-[10px] font-black uppercase text-slate-400">Credit Purchase</span>
            <strong class="mt-1.5 block font-mono text-xl font-black text-amber-700">₹{{ number_format((float) $vendorSummary['credit_purchase'], 2) }}</strong>
            <span class="mt-0.5 block text-[10px] text-slate-500">Payable amount</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <span class="text-[10px] font-black uppercase text-slate-400">Paid by Company</span>
            <strong class="mt-1.5 block font-mono text-xl font-black text-blue-700">₹{{ number_format((float) $vendorSummary['credit_paid_by_company'], 2) }}</strong>
            <span class="mt-0.5 block text-[10px] text-slate-500">Company settlement allocations</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <span class="text-[10px] font-black uppercase text-slate-400">Credit Outstanding</span>
            <strong class="mt-1.5 block font-mono text-xl font-black {{ $vendorSummary['credit_outstanding'] > 0 ? 'text-rose-700' : 'text-slate-700' }}">₹{{ number_format((float) $vendorSummary['credit_outstanding'], 2) }}</strong>
            <span class="mt-0.5 block text-[10px] text-slate-500">Remaining credit</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <span class="text-[10px] font-black uppercase text-slate-400">Other Modes</span>
            <strong class="mt-1.5 block font-mono text-xl font-black text-purple-700">₹{{ number_format((float) $vendorSummary['other_modes_purchase'], 2) }}</strong>
            <span class="mt-0.5 block text-[10px] text-slate-500">UPI, Bank, Online</span>
        </div>
    </section>

    {{-- Vendor Transaction Table --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="border-b border-slate-200 p-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black text-slate-950">Purchase Bills &amp; Invoices</h2>
                <p class="mt-0.5 text-xs text-slate-500">Showing bills issued by {{ $supplier->name }} under {{ $purchaser->name }}.</p>
            </div>
            <span class="text-xs font-bold text-slate-500">Total: {{ $transactions->total() }} bills</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[64rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Bill / Ref</th>
                        <th class="p-3 text-right">Purchase Amount</th>
                        <th class="p-3 text-center">Mode</th>
                        <th class="p-3 text-right">Cash</th>
                        <th class="p-3 text-right">Credit</th>
                        <th class="p-3 text-right">Other</th>
                        <th class="p-3 text-right">Company Paid</th>
                        <th class="p-3 text-right">Outstanding</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-center">Settlement Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $txn)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3 font-mono font-medium text-slate-600">{{ $txn->business_date }}</td>
                            <td class="p-3">
                                <a href="{{ route('purchasing.invoices.show', $txn->public_uuid) }}" class="font-black text-emerald-700 hover:underline">
                                    {{ $txn->bill_number }}
                                </a>
                                @if($txn->bill_number !== $txn->invoice_number)
                                    <span class="block text-[10px] font-mono text-slate-400">{{ $txn->invoice_number }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-950">₹{{ number_format((float) $txn->purchase_amount, 2) }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase
                                    {{ $txn->mode_category === 'credit' ? 'bg-amber-100 text-amber-800' : ($txn->mode_category === 'cash' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800') }}">
                                    {{ $txn->display_mode }}
                                </span>
                            </td>
                            <td class="p-3 text-right font-mono font-medium text-emerald-700">₹{{ number_format((float) $txn->cash_amount, 2) }}</td>
                            <td class="p-3 text-right font-mono font-medium text-amber-700">₹{{ number_format((float) $txn->credit_amount, 2) }}</td>
                            <td class="p-3 text-right font-mono font-medium text-purple-700">₹{{ number_format((float) $txn->other_amount, 2) }}</td>
                            <td class="p-3 text-right font-mono font-medium text-blue-700">₹{{ number_format((float) $txn->company_paid, 2) }}</td>
                            <td class="p-3 text-right font-mono font-medium {{ (float) $txn->outstanding > 0 ? 'text-rose-700 font-bold' : 'text-slate-500' }}">₹{{ number_format((float) $txn->outstanding, 2) }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase
                                    {{ $txn->status === 'Paid' ? 'bg-emerald-100 text-emerald-800' : ($txn->status === 'Partial' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                    {{ $txn->status }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                @if(count($txn->settlement_allocations) > 0)
                                    <button type="button" onclick="toggleSettlementDetails('settlement-row-{{ $txn->id }}')" class="inline-flex items-center gap-1 rounded-md bg-blue-50 border border-blue-200 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-100 transition cursor-pointer">
                                        <i data-lucide="layers" class="h-3 w-3"></i>
                                        <span>{{ count($txn->settlement_allocations) }} Allocation{{ count($txn->settlement_allocations) > 1 ? 's' : '' }}</span>
                                    </button>
                                @elseif($txn->mode_category === 'credit')
                                    <span class="text-[10px] text-slate-400 italic">No allocations</span>
                                @else
                                    <span class="text-[10px] text-slate-300">&mdash;</span>
                                @endif
                            </td>
                        </tr>

                        {{-- Expandable Settlement Details Row --}}
                        @if(count($txn->settlement_allocations) > 0)
                            <tr id="settlement-row-{{ $txn->id }}" class="hidden bg-blue-50/40">
                                <td colspan="11" class="p-4 border-t border-b border-blue-100">
                                    <div class="rounded-xl border border-blue-200 bg-white p-3 shadow-xs space-y-2">
                                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="shield-check" class="h-4 w-4 text-blue-600"></i>
                                                <h4 class="text-xs font-black text-slate-900">Credit Company Settlement Allocation Details</h4>
                                                <span class="text-[10px] text-slate-500 font-mono">Bill: {{ $txn->bill_number }}</span>
                                            </div>
                                            <span class="text-[10px] font-bold text-blue-800">Total Allocated: ₹{{ number_format((float) $txn->company_paid, 2) }}</span>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs">
                                                <thead class="text-[10px] font-black uppercase text-slate-400 border-b border-slate-100">
                                                    <tr>
                                                        <th class="py-1.5 px-2">Payment Date</th>
                                                        <th class="py-1.5 px-2">Reference</th>
                                                        <th class="py-1.5 px-2">Method / Account</th>
                                                        <th class="py-1.5 px-2 text-right">Settlement Total Paid</th>
                                                        <th class="py-1.5 px-2 text-right">Allocated To Bill</th>
                                                        <th class="py-1.5 px-2 text-center">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-50 text-[11px]">
                                                    @foreach($txn->settlement_allocations as $alloc)
                                                        <tr>
                                                            <td class="py-1.5 px-2 font-mono text-slate-700">{{ $alloc['payment_date'] }}</td>
                                                            <td class="py-1.5 px-2 font-mono font-bold text-slate-900">{{ $alloc['reference'] }}</td>
                                                            <td class="py-1.5 px-2 text-slate-600">{{ $alloc['payment_method'] }} @if($alloc['company_account']) &middot; {{ $alloc['company_account'] }} @endif</td>
                                                            <td class="py-1.5 px-2 text-right font-mono text-slate-700">₹{{ number_format((float) $alloc['settlement_total_paid'], 2) }}</td>
                                                            <td class="py-1.5 px-2 text-right font-mono font-bold text-blue-700">₹{{ number_format((float) $alloc['allocated_amount'], 2) }}</td>
                                                            <td class="py-1.5 px-2 text-center">
                                                                <span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-bold uppercase {{ $alloc['is_reversed'] ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                                                                    {{ $alloc['status'] }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="11" class="p-8 text-center text-slate-400">No purchase bills found for this purchaser and vendor in the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($transactions->isNotEmpty())
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50/90 font-black">
                        <tr>
                            <td colspan="2" class="p-3 text-xs uppercase tracking-wider text-slate-900">TOTAL</td>
                            <td class="p-3 text-right font-mono text-slate-950 text-sm">₹{{ number_format((float) $vendorSummary['total_purchase'], 2) }}</td>
                            <td class="p-3 text-center text-slate-400">&mdash;</td>
                            <td class="p-3 text-right font-mono text-emerald-800">₹{{ number_format((float) $vendorSummary['cash_purchase'], 2) }}</td>
                            <td class="p-3 text-right font-mono text-amber-800">₹{{ number_format((float) $vendorSummary['credit_purchase'], 2) }}</td>
                            <td class="p-3 text-right font-mono text-purple-800">₹{{ number_format((float) $vendorSummary['other_modes_purchase'], 2) }}</td>
                            <td class="p-3 text-right font-mono text-blue-800">₹{{ number_format((float) $vendorSummary['credit_paid_by_company'], 2) }}</td>
                            <td class="p-3 text-right font-mono text-rose-800">₹{{ number_format((float) $vendorSummary['credit_outstanding'], 2) }}</td>
                            <td colspan="2" class="p-3 text-center text-xs text-slate-500 font-normal">
                                {{ $vendorSummary['bills_count'] }} bills
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="border-t border-slate-200 p-4">
                {{ $transactions->links() }}
            </div>
        @endif
    </section>
</div>

<script>
    function toggleSettlementDetails(rowId) {
        const row = document.getElementById(rowId);
        if (row) {
            row.classList.toggle('hidden');
        }
    }
</script>
@endsection
