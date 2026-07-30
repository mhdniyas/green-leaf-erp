@extends('purchase-manager.layouts.app')

@section('title', 'Supplier Bills')
@section('page_title', 'Supplier Bills')
@section('page_description', 'Track matched invoices, payment workflow, and supplier billing status from receipt to settlement.')

@section('content')
    @php
        $tabLinks = [
            'credit' => 'Credit Purchases',
            'other' => 'Other Purchases',
        ];
    @endphp

    <div class="space-y-5">
        <section class="purchase-manager-panel p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Supplier Bills</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ $activeTab === 'credit' ? 'Credit Payable' : 'Other Purchases' }}</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">
                        {{ $activeTab === 'credit'
                            ? 'Credit vendor bills stay payable until company settlement posts cash out.'
                            : 'Cash, GPay, and online purchaser-paid bills are shown for checking only.' }}
                    </p>
                </div>
                <form action="{{ route('purchasing.invoices.index') }}" method="GET" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_160px_auto]">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <input name="search" value="{{ $search }}" placeholder="Search supplier or bill" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    <select name="payment_type" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="all" @selected($paymentFilter === 'all')>All Methods</option>
                        @if ($activeTab === 'credit')
                            <option value="credit" @selected($paymentFilter === 'credit')>Credit</option>
                        @else
                            <option value="cash" @selected($paymentFilter === 'cash')>Cash</option>
                            <option value="gpay" @selected($paymentFilter === 'gpay')>GPay</option>
                            <option value="online" @selected($paymentFilter === 'online')>Online</option>
                            <option value="both" @selected($paymentFilter === 'both')>Cash / GPay</option>
                        @endif
                    </select>
                    <input type="date" name="date" value="{{ $date }}" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    <button type="submit" class="h-11 rounded-2xl bg-slate-950 px-5 text-sm font-black text-white">Apply</button>
                </form>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($tabLinks as $tabKey => $tabLabel)
                    <a href="{{ route('purchasing.invoices.index', ['tab' => $tabKey, 'date' => $date, 'search' => $search]) }}" class="inline-flex h-10 items-center rounded-2xl px-4 text-xs font-black uppercase tracking-[0.14em] {{ $activeTab === $tabKey ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Vendors</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($summary['vendor_count']) }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Bills</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($summary['invoice_count']) }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">₹{{ number_format($summary['total_amount'], 2) }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Payable</p>
                    <p class="mt-2 text-2xl font-black text-amber-900">₹{{ number_format($summary['outstanding_amount'], 2) }}</p>
                </div>
            </div>
        </section>

        <section class="purchase-manager-panel overflow-hidden">
            @if ($activeTab === 'credit' && $canManageSuppliers)
                <div class="border-b border-slate-100 bg-white px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Vendor Credit Requests</p>
                            <p class="mt-1 text-sm font-semibold text-slate-600">Approve vendors for credit use here. Credit bills still stay payable until an admin pays from the company account.</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-amber-800">{{ $pendingVendorCreditRequests->count() }} pending</span>
                    </div>

                    @if ($pendingVendorCreditRequests->isNotEmpty())
                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            @foreach ($pendingVendorCreditRequests as $supplier)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-950">{{ $supplier->name }}</p>
                                            <p class="mt-1 text-xs font-semibold text-amber-800">
                                                Requested by {{ $supplier->creditApprovalRequestedBy?->name ?? 'Unknown' }}
                                                @if ($supplier->credit_approval_requested_at)
                                                    on {{ $supplier->credit_approval_requested_at->format('Y-m-d H:i') }}
                                                @endif
                                            </p>
                                        </div>
                                        <form id="credit-approve-form-{{ $supplier->id }}" method="POST" action="{{ route('purchasing.suppliers.credit-approve', $supplier) }}">
                                            @csrf
                                            <button
                                                type="button"
                                                onclick="confirmCreditApprove({{ $supplier->id }}, '{{ addslashes($supplier->name) }}')"
                                                class="inline-flex h-9 items-center rounded-xl bg-emerald-600 px-3 text-[11px] font-black text-white hover:bg-emerald-500">
                                                Accept
                                            </button>
                                        </form>
                                    </div>
                                    @if ($supplier->credit_approval_note)
                                        <p class="mt-3 rounded-xl bg-white px-3 py-2 text-xs font-semibold leading-5 text-slate-600">{{ $supplier->credit_approval_note }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-500">No vendor credit requests are pending.</p>
                    @endif
                </div>
            @endif

        @if ($invoices->isEmpty())
            <div class="p-5">
                <x-purchase-manager.components.empty-state
                    title="No purchase invoices found"
                    description="Match a supplier invoice to a goods receipt note after warehouse verification is complete."
                    :actionHref="route('purchasing.grns.index')"
                    actionLabel="Open Goods Receipts"
                />
            </div>
        @else
            <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[900px] text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Invoice Number</th>
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4">Payment</th>
                            <th class="px-5 py-4">GRN Reference</th>
                            <th class="px-5 py-4">Matched Date</th>
                            <th class="px-5 py-4">Updated Date</th>
                            <th class="px-5 py-4 text-right">Amount</th>
                            <th class="px-5 py-4 text-right">Paid</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                            <th class="px-5 py-4 text-center">Status</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($invoices as $invoice)
                            @php
                                $balance = max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
                            @endphp
                            <tr>
                                <td class="px-5 py-4 font-mono font-bold text-cyan-700"><a href="{{ route('purchasing.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                                <td class="px-5 py-4 font-semibold text-slate-950">{{ $invoice->supplier?->name ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    <p class="text-xs font-black text-slate-950">{{ $invoice->payment_method ?: 'Pending' }}</p>
                                    <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">{{ $invoice->paymentPaidByLabel() }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    @if ($invoice->goodsReceived)
                                        <a href="{{ route('purchasing.grns.show', $invoice->goodsReceived) }}" class="font-mono text-cyan-700">{{ $invoice->goodsReceived->grn_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $invoice->created_at->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $invoice->updated_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-5 py-4 text-right font-bold text-slate-950">INR {{ number_format((float) $invoice->amount, 2) }}</td>
                                <td class="px-5 py-4 text-right font-bold text-emerald-700">₹{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                <td class="px-5 py-4 text-right font-bold {{ $balance > 0 ? 'text-amber-700' : 'text-slate-950' }}">₹{{ number_format($balance, 2) }}</td>
                                <td class="px-5 py-4 text-center"><span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $invoice->status->color() }}">{{ $invoice->status->label() }}</span></td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        @if ($activeTab === 'credit' && $balance > 0 && $canPayCompanyVendorCredit)
                                            <form method="POST" action="{{ route('purchasing.invoices.update-payment', $invoice) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="payment_method" value="Credit">
                                                <input type="hidden" name="payment_paid_by" value="company">
                                                <input type="hidden" name="paid_amount" value="{{ number_format((float) $invoice->amount - (float) $invoice->discount_amount, 2, '.', '') }}">
                                                <input type="hidden" name="payment_note" value="Company paid vendor credit.">
                                                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl bg-amber-600 px-3 text-[11px] font-black text-white hover:bg-amber-500">
                                                    Pay By Company
                                                </button>
                                            </form>
                                        @endif
                                        <x-purchase-manager.components.action-button :href="route('purchasing.invoices.show', $invoice)" variant="secondary">View</x-purchase-manager.components.action-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-900 bg-slate-950 text-white font-mono text-xs">
                        <tr>
                            <td colspan="6" class="px-5 py-3 font-sans font-black uppercase text-[10px] tracking-wider text-slate-300">
                                Total Summary ({{ $invoices->count() }} {{ \Illuminate\Support\Str::plural('bill', $invoices->count()) }})
                            </td>
                            <td class="px-5 py-3 text-right font-black text-white whitespace-nowrap">
                                ₹{{ number_format((float) $invoices->sum('amount'), 2) }}
                            </td>
                            <td class="px-5 py-3 text-right font-black text-emerald-400 whitespace-nowrap">
                                ₹{{ number_format((float) $invoices->sum('paid_amount'), 2) }}
                            </td>
                            <td class="px-5 py-3 text-right font-black text-amber-400 whitespace-nowrap">
                                ₹{{ number_format((float) $summary['outstanding_amount'], 2) }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
        </section>
    </div>

    {{-- Vendor Credit Approve Confirmation Modal --}}
    <div id="credit-approve-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100">
                    <svg class="h-5 w-5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-950">Approve Vendor Credit?</h3>
                    <p id="credit-approve-supplier-name" class="mt-0.5 text-xs font-semibold text-slate-500"></p>
                </div>
            </div>
            <p class="mt-4 text-xs font-semibold leading-5 text-slate-600">
                This will allow the supplier to use credit for purchases. Credit bills will stay payable until an admin pays from the company account.
            </p>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" onclick="closeCreditApproveModal()" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-slate-100">
                    Cancel
                </button>
                <button type="button" id="credit-approve-confirm-btn" onclick="submitCreditApprove()" class="inline-flex h-9 items-center justify-center rounded-xl bg-emerald-600 px-5 text-xs font-black text-white hover:bg-emerald-500">
                    Confirm Approve
                </button>
            </div>
        </div>
    </div>

    <script>
    let pendingApproveFormId = null;

    function confirmCreditApprove(supplierId, supplierName) {
        pendingApproveFormId = 'credit-approve-form-' + supplierId;
        document.getElementById('credit-approve-supplier-name').textContent = supplierName;
        const modal = document.getElementById('credit-approve-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeCreditApproveModal() {
        const modal = document.getElementById('credit-approve-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        pendingApproveFormId = null;
    }

    function submitCreditApprove() {
        if (pendingApproveFormId) {
            document.getElementById(pendingApproveFormId)?.submit();
        }
    }

    document.getElementById('credit-approve-modal')?.addEventListener('click', function(e) {
        if (e.target === this) closeCreditApproveModal();
    });
    </script>
@endsection
