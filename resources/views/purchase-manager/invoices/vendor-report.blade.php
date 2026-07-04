@extends('purchase-manager.layouts.app')

@section('title', 'Vendor Report')
@section('page_title', $vendor->name)
@section('page_description', 'Review supplier billing history, payment exposure, and outstanding balance inside the purchasing dashboard.')

@section('content')
    @php
        $latestInvoice = $historySummary['latest_invoice'];
        $statusTone = $historySummary['outstanding_amount'] > 0
            ? 'border-amber-200 bg-amber-50 text-amber-700'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700';
    @endphp

    <div class="space-y-6">
        <section class="purchase-manager-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Vendor Finance Detail</p>
                        <h2 class="mt-2 text-2xl font-black text-slate-950">{{ $vendor->name }}</h2>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">Current status, payment exposure, and full finance history up to {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-purchase-manager.components.action-button :href="route('purchasing.invoices.index', ['date' => $date, 'search' => $search, 'payment_type' => $paymentFilter])" variant="secondary">
                            Back to Supplier Bills
                        </x-purchase-manager.components.action-button>
                        @if (! $vendor->credit_approved && $vendor->credit_approval_requested_at)
                            <form action="{{ route('purchasing.suppliers.credit-approve', $vendor) }}" method="POST">
                                @csrf
                                <x-purchase-manager.components.action-button type="submit" variant="success">
                                    Approve Credit
                                </x-purchase-manager.components.action-button>
                            </form>
                        @endif
                        @if ($latestInvoice)
                            <x-purchase-manager.components.action-button :href="route('purchasing.invoices.show', $latestInvoice)" variant="primary">
                                Latest Bill
                            </x-purchase-manager.components.action-button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid gap-3 px-5 py-5 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Current Status</p>
                    <p class="mt-2 inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $statusTone }}">{{ $historySummary['current_status'] }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-500">{{ $vendor->credit_approved ? 'Credit approved for this vendor.' : 'Standard payment control applies.' }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Invoices</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($historySummary['invoice_count']) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Finance history entries</p>
                </div>
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Value</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">₹{{ number_format($historySummary['total_amount'], 2) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">All matched vendor bills</p>
                </div>
                <div class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Paid</p>
                    <p class="mt-2 text-2xl font-black text-emerald-800">₹{{ number_format($historySummary['paid_amount'], 2) }}</p>
                    <p class="mt-1 text-xs font-semibold text-emerald-700">Collected against bills</p>
                </div>
                <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Outstanding</p>
                    <p class="mt-2 text-2xl font-black text-amber-900">₹{{ number_format($historySummary['outstanding_amount'], 2) }}</p>
                    <p class="mt-1 text-xs font-semibold text-amber-700">{{ number_format($historySummary['credit_invoices']) }} credit invoice(s)</p>
                </div>
            </div>
        </section>

        <section class="purchase-manager-panel p-5">
            <form action="{{ route('purchasing.invoices.vendor-report', $vendor) }}" method="GET" class="grid gap-3 md:grid-cols-[1.3fr_0.9fr_0.8fr_auto]">
                <div>
                    <label for="search" class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Search History</label>
                    <input id="search" type="text" name="search" value="{{ $search }}" placeholder="Invoice, note, cart, mobile" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:bg-white focus:outline-none">
                </div>
                <div>
                    <label for="payment_type" class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Type</label>
                    <select id="payment_type" name="payment_type" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:bg-white focus:outline-none">
                        <option value="all" @selected($paymentFilter === 'all')>All Methods</option>
                        <option value="cash" @selected($paymentFilter === 'cash')>Cash</option>
                        <option value="credit" @selected($paymentFilter === 'credit')>Credit</option>
                        <option value="gpay" @selected($paymentFilter === 'gpay')>GPay</option>
                        <option value="online" @selected($paymentFilter === 'online')>Online</option>
                        <option value="both" @selected($paymentFilter === 'both')>Cash / GPay</option>
                    </select>
                </div>
                <div>
                    <label for="date" class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">History Until</label>
                    <input id="date" type="date" name="date" value="{{ $date }}" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:bg-white focus:outline-none">
                </div>
                <div class="flex items-end gap-2">
                    <x-purchase-manager.components.action-button type="submit" variant="primary">
                        Apply
                    </x-purchase-manager.components.action-button>
                    <x-purchase-manager.components.action-button :href="route('purchasing.invoices.vendor-report', $vendor, false).'?date='.$date" variant="secondary">
                        Reset
                    </x-purchase-manager.components.action-button>
                </div>
            </form>
        </section>

        <section class="purchase-manager-panel overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Finance History</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $vendor->mobile_number ?: $vendor->contact ?: 'Contact pending' }} @if ($vendor->payment_terms)• {{ $vendor->payment_terms }}@endif</p>
                </div>
                @if ($latestInvoice)
                    <p class="text-sm font-semibold text-slate-500">Latest activity: <span class="font-black text-slate-950">{{ $latestInvoice->invoice_number }}</span> on {{ $latestInvoice->created_at->format('d M Y') }}</p>
                @endif
            </div>

            @if ($historyInvoices->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-lg font-black text-slate-900">No vendor history found for this filter.</p>
                    <p class="mt-2 text-sm font-semibold text-slate-500">Change the payment type or search to inspect another slice of this vendor’s finance history.</p>
                </div>
            @else
                <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                    <table class="min-w-[980px] text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Invoice</th>
                                <th class="px-5 py-4">Date</th>
                                <th class="px-5 py-4">Payment</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4 text-right">Amount</th>
                                <th class="px-5 py-4 text-right">Paid</th>
                                <th class="px-5 py-4 text-right">Balance</th>
                                <th class="px-5 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($historyInvoices as $invoice)
                                @php
                                    $balance = max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
                                    $paymentTone = match ($invoice->payment_method) {
                                        'Cash' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'Credit' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'GPay' => 'bg-sky-50 text-sky-700 border-sky-200',
                                        'Online' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                        default => 'bg-slate-50 text-slate-600 border-slate-200',
                                    };
                                    $statusLabel = str($invoice->payment_status ?: 'unpaid')->replace('_', ' ')->title();
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-mono font-black text-cyan-700">{{ $invoice->invoice_number }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->purchaserCart?->cart_number ?: $invoice->goodsReceived?->grn_number ?: 'Manual bill' }}</p>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-700">{{ $invoice->created_at->format('d M Y') }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $paymentTone }}">
                                            {{ $invoice->payment_method ?: 'Pending' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-700">{{ $statusLabel }}</td>
                                    <td class="px-5 py-4 text-right font-black text-slate-950">₹{{ number_format((float) $invoice->amount, 2) }}</td>
                                    <td class="px-5 py-4 text-right font-black text-emerald-700">₹{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                    <td class="px-5 py-4 text-right font-black {{ $balance > 0 ? 'text-amber-700' : 'text-slate-950' }}">₹{{ number_format($balance, 2) }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <x-purchase-manager.components.action-button :href="route('purchasing.invoices.show', $invoice)" variant="secondary">
                                            View Bill
                                        </x-purchase-manager.components.action-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($historyInvoices->hasPages())
                    <div class="border-t border-slate-100 px-5 py-4">
                        {{ $historyInvoices->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
