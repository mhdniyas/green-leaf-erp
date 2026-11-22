@extends('admin.cashbook.layouts.app')
@php
    $activePurchaseTab = $kind === 'purchaser' ? 'purchasers' : ($kind === 'vendor' ? 'vendors' : 'categories');
    $summary = $detail['summary'];
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $detailContext = ['period' => $filters['period']];
    if ($selectedProductFilter) {
        $detailContext['product_filter'] = $selectedProductFilter;
    }
    if (in_array($filters['period'], ['custom', 'between', 'range'], true)) { $detailContext += ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]; }
    if (($filters['payment'] ?? 'all') !== 'all') { $detailContext['payment'] = $filters['payment']; }
    $listRoute = route('admin.cashbook.finance.purchase.'.$activePurchaseTab, $detailContext);
    $tabs = match($kind) { 'purchaser' => [], 'vendor' => ['overview' => 'Overview', 'invoices' => 'Invoices', 'credits' => 'Purchase Credit', 'purchasers' => 'Purchasers', 'categories' => 'Categories', 'payments' => 'Payments'], default => ['overview' => 'Overview', 'vendors' => 'Vendors', 'purchasers' => 'Purchasers', 'invoices' => 'Invoices', 'products' => 'Products'] };
    $cards = match($kind) {
        'purchaser' => [],
        'vendor' => [['Total Purchase', $summary->total_purchase], ['Cash', $summary->cash_purchase], ['Credit', $summary->credit_purchase], ['Paid', $summary->credit_paid], ['Outstanding', $summary->credit_outstanding], ['Credit Notes', $detail['creditKpis']['credit_notes_total'] ?? 0]],
        default => [['Purchase Value', $summary->total_purchase], ['Cash', $summary->cash_purchase], ['Credit', $summary->credit_purchase], ['Vendors', $summary->vendor_count, false], ['Purchasers', $summary->purchaser_count, false], ['Invoices', $summary->invoice_count, false]],
    };
@endphp
@section('title', $record->name.' - Purchase')
@section('header_title')
    <i data-lucide="shopping-basket" class="h-5 w-5 text-emerald-600"></i> Purchase
@endsection
@section('header_subtitle')
    {{ ucfirst($kind) }} detail
@endsection
@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase._nav')
    @include('admin.cashbook.finance.purchase._dashboard-tabs')
    @if($kind === 'purchaser')
        @include('admin.cashbook.finance.purchase._purchaser-detail')
    @else
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><a href="{{ $listRoute }}" class="text-xs font-black text-emerald-700">Back to {{ ucfirst($activePurchaseTab) }}</a><h1 class="mt-2 text-2xl font-black text-slate-950">{{ $record->name }}</h1><p class="mt-1 text-xs font-bold text-slate-500">{{ $filters['start_date'] }} to {{ $filters['end_date'] }}</p></div>@if($kind === 'vendor')<a href="{{ route('admin.cashbook.finance.vendor-credit.show', $record) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700">Open Vendor Finance</a>@endif</header>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">@foreach($cards as $card)<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><span class="text-[10px] font-black uppercase text-slate-400">{{ $card[0] }}</span><strong class="mt-2 block font-mono text-lg">{{ ($card[2] ?? true) ? '₹'.number_format((float) $card[1], 2) : number_format((int) $card[1]) }}</strong></div>@endforeach</section>
    <nav class="flex gap-2 overflow-x-auto pb-1 text-xs font-black">@foreach($tabs as $anchor => $label)<a href="#{{ $anchor }}" class="min-w-max rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-700">{{ $label }}</a>@endforeach</nav>

    <section id="overview" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="font-black text-slate-950">Recent Purchase Activity</h2><p class="mt-1 text-xs text-slate-500">Latest matching invoices and purchase mix for selected period.</p></section>
    <section id="invoices" class="rounded-lg border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-4"><h2 class="font-black">Invoices</h2></div><div class="overflow-x-auto"><table class="w-full min-w-[52rem] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">Date</th><th class="p-3">Invoice</th><th class="p-3">Vendor</th><th class="p-3">Purchaser</th><th class="p-3">Categories</th><th class="p-3 text-right">Amount</th><th class="p-3 text-right">Outstanding</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($detail['invoices'] as $invoice)<tr><td class="p-3 font-mono">{{ $invoice->business_date }}</td><td class="p-3"><a class="font-black text-emerald-700" href="{{ route('purchasing.invoices.show', $invoice->invoice_public_uuid) }}">{{ $invoice->invoice_number }}</a></td><td class="p-3">{{ $invoice->supplier_name }}</td><td class="p-3">{{ $invoice->purchaser_name }}</td><td class="p-3">{{ $invoice->categories }}</td><td class="p-3 text-right font-mono">₹{{ number_format((float) $invoice->total_purchase, 2) }}</td><td class="p-3 text-right font-mono">₹{{ number_format((float) $invoice->outstanding, 2) }}</td></tr>@empty<tr><td colspan="7" class="p-6 text-center text-slate-400">No invoices.</td></tr>@endforelse</tbody></table></div>@if($detail['invoices']->hasPages())<div class="border-t p-4">{{ $detail['invoices']->links() }}</div>@endif</section>

    @if(isset($detail['purchaseCredits']))
    <section id="credits" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-black text-slate-950">Purchase Credit Notes</h2>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $detail['creditKpis']['credit_notes_count'] ?? 0 }} active</span>
                </div>
                <p class="mt-0.5 text-xs text-slate-500">Returned stock, vendor price adjustments, damage compensation, and invoice credit deductions.</p>
            </div>
            <div class="flex items-center gap-2">
                @can('cashbook.reconciliation.manage')
                    <a href="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.create', $record) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-black text-white hover:bg-emerald-700">
                        <i data-lucide="plus" class="h-4 w-4"></i> New Credit Note
                    </a>
                @endcan
            </div>
        </div>

        {{-- KPI sub-bar --}}
        <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 border-b border-slate-100 bg-slate-50/50 sm:grid-cols-4 sm:divide-y-0">
            <div class="p-3">
                <span class="text-[10px] font-bold uppercase text-slate-400">Total Credited</span>
                <span class="block font-mono text-sm font-black text-slate-900">₹{{ number_format((float) ($detail['creditKpis']['credit_notes_total'] ?? 0), 2) }}</span>
            </div>
            <div class="p-3">
                <span class="text-[10px] font-bold uppercase text-slate-400">Applied to Bills</span>
                <span class="block font-mono text-sm font-black text-emerald-700">₹{{ number_format((float) ($detail['creditKpis']['credit_notes_applied'] ?? 0), 2) }}</span>
            </div>
            <div class="p-3">
                <span class="text-[10px] font-bold uppercase text-slate-400">Vendor Advance</span>
                <span class="block font-mono text-sm font-black text-indigo-700">₹{{ number_format((float) ($detail['creditKpis']['credit_notes_advance'] ?? 0), 2) }}</span>
            </div>
            <div class="p-3">
                <span class="text-[10px] font-bold uppercase text-slate-400">Refunds (In/Out)</span>
                <span class="block font-mono text-sm font-black text-slate-700">
                    <span class="text-emerald-600">+₹{{ number_format((float) ($detail['creditKpis']['credit_notes_money_in'] ?? 0), 2) }}</span>
                    @if(($detail['creditKpis']['credit_notes_money_out'] ?? 0) > 0)
                        <span class="text-rose-600 ml-1">-₹{{ number_format((float) ($detail['creditKpis']['credit_notes_money_out'] ?? 0), 2) }}</span>
                    @endif
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[56rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Credit Note #</th>
                        <th class="p-3">Invoice</th>
                        <th class="p-3">Items Summary</th>
                        <th class="p-3">Money Effect</th>
                        <th class="p-3">Inventory Action</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($detail['purchaseCredits'] as $credit)
                        <tr class="hover:bg-slate-50/60">
                            <td class="p-3 font-mono text-slate-600">{{ $credit->credit_date?->format('d M Y') ?? '—' }}</td>
                            <td class="p-3 font-black text-slate-900">
                                <a href="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.show', ['supplier' => $record->public_uuid, 'creditNote' => $credit->public_uuid]) }}" class="hover:text-emerald-700 hover:underline">
                                    {{ $credit->credit_note_number }}
                                </a>
                            </td>
                            <td class="p-3">
                                @if($credit->purchaseInvoice)
                                    <a href="{{ route('purchasing.invoices.show', $credit->purchaseInvoice->public_uuid) }}" class="font-bold text-emerald-700 hover:underline">
                                        {{ $credit->purchaseInvoice->invoice_number }}
                                    </a>
                                @else
                                    <span class="text-slate-400">Direct Vendor</span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-600">
                                @php
                                    $itemNames = $credit->items->take(2)->map(fn ($it) => ($it->product?->name ?? 'Item') . ' (' . (float) $it->quantity . ($it->product?->unit ?? 'kg') . ')')->implode(', ');
                                    $moreCount = $credit->items->count() - 2;
                                @endphp
                                <span>{{ $itemNames }}</span>
                                @if($moreCount > 0)
                                    <span class="font-bold text-slate-400">+{{ $moreCount }} more</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if($credit->money_effect->value === 'vendor_credit')
                                    <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-0.5 text-[11px] font-bold text-purple-700 ring-1 ring-inset ring-purple-600/20">Vendor Credit</span>
                                @elseif($credit->money_effect->value === 'in')
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Money IN</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20">Money OUT</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if($credit->inventory_action->value === 'out')
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 ring-1 ring-inset ring-blue-600/20">Stock OUT</span>
                                @elseif($credit->inventory_action->value === 'in')
                                    <span class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-0.5 text-[11px] font-bold text-cyan-700 ring-1 ring-inset ring-cyan-600/20">Stock IN</span>
                                @elseif($credit->inventory_action->value === 'damage')
                                    <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700 ring-1 ring-inset ring-rose-600/20">Damage</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">None</span>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format((float) $credit->total_amount, 2) }}
                            </td>
                            <td class="p-3 text-center">
                                @if($credit->isActive())
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800" title="{{ $credit->void_reason }}">Voided</span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                <a href="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.show', ['supplier' => $record->public_uuid, 'creditNote' => $credit->public_uuid]) }}" class="inline-flex items-center rounded border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-6 text-center text-slate-400">
                                No purchase credit notes for this vendor in the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($detail['purchaseCredits']->hasPages())
            <div class="border-t border-slate-200 p-4">
                {{ $detail['purchaseCredits']->links() }}
            </div>
        @endif
    </section>
    @endif

    @if(isset($detail['vendors']))<section id="vendors" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="mb-3 font-black">Vendors</h2><div class="grid gap-2 sm:grid-cols-2">@forelse($detail['vendors'] as $row)<a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $row->supplier_public_uuid] + $detailContext) }}" class="flex justify-between gap-3 rounded-lg border border-slate-200 p-3 text-xs"><strong>{{ $row->supplier_name }}</strong><span class="font-mono">₹{{ number_format((float) $row->total_purchase, 2) }}</span></a>@empty<p class="text-xs text-slate-400">No vendors.</p>@endforelse</div></section>@endif
    @if(isset($detail['purchasers']))<section id="purchasers" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="mb-3 font-black">Purchasers</h2><div class="grid gap-2 sm:grid-cols-2">@forelse($detail['purchasers'] as $row)<a href="{{ route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $row->purchaser_public_uuid] + $detailContext) }}" class="flex justify-between gap-3 rounded-lg border border-slate-200 p-3 text-xs"><strong>{{ $row->purchaser_name }}</strong><span class="font-mono">₹{{ number_format((float) $row->total_purchase, 2) }}</span></a>@empty<p class="text-xs text-slate-400">No purchasers.</p>@endforelse</div></section>@endif
    @if(isset($detail['categories']))<section id="categories" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="mb-3 font-black">Categories</h2><div class="grid gap-2 sm:grid-cols-2">@forelse($detail['categories'] as $row)<a href="{{ route('admin.cashbook.finance.purchase.categories.show', ['category' => $row->category_id] + $detailContext) }}" class="flex justify-between gap-3 rounded-lg border border-slate-200 p-3 text-xs"><strong>{{ $row->category_name }}</strong><span class="font-mono">₹{{ number_format((float) $row->total_purchase, 2) }}</span></a>@empty<p class="text-xs text-slate-400">No categories.</p>@endforelse</div></section>@endif
    @if(isset($detail['fundingHistory']))<section id="funding" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="mb-3 font-black">Funding</h2><div class="space-y-2">@forelse($detail['fundingHistory'] as $movement)<div class="flex justify-between gap-3 border-b border-slate-100 py-2 text-xs"><span>{{ $movement->business_date }} · {{ $movement->description }}</span><strong class="font-mono {{ $movement->type === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $movement->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $movement->amount, 2) }}</strong></div>@empty<p class="text-xs text-slate-400">No funding movements.</p>@endforelse</div>@if($detail['fundingHistory']->hasPages())<div class="mt-4">{{ $detail['fundingHistory']->links() }}</div>@endif</section>@endif
    @if(isset($detail['payments']))<section id="payments" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="mb-3 font-black">Payments</h2><div class="space-y-2">@forelse($detail['payments'] as $payment)<div class="flex justify-between gap-3 border-b border-slate-100 py-2 text-xs"><span>{{ $payment->payment_date }} · {{ $payment->reference ?: 'Settlement' }}</span><strong class="font-mono">₹{{ number_format((float) $payment->actual_payment_amount, 2) }}</strong></div>@empty<p class="text-xs text-slate-400">No payments.</p>@endforelse</div></section>@endif
    @if(isset($detail['products']))<section id="products" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="mb-3 font-black">Products</h2><div class="grid gap-2 sm:grid-cols-2">@forelse($detail['products'] as $product)<div class="flex justify-between gap-3 rounded-lg border border-slate-200 p-3 text-xs"><strong>{{ $product->product_name }}</strong><span class="font-mono">₹{{ number_format((float) $product->total_purchase, 2) }}</span></div>@empty<p class="text-xs text-slate-400">No products.</p>@endforelse</div></section>@endif
    @endif
</div>
@endsection
