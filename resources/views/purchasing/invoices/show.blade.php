<x-layouts.app title="Purchase Invoice Details">
    @php
        $payableTotal = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
        $paidAmount = (float) $invoice->paid_amount;
        $balanceAmount = max(0, $payableTotal - $paidAmount);
        $paymentMethod = $invoice->payment_method ?: ($invoice->purchaserCart?->payment_method ?: 'Credit');
        $supplier = $invoice->supplier;
        $businessDate = $invoice->purchaserCart?->business_date ?? $invoice->created_at;
        $paidBy = $invoice->payment_paid_by ?? 'purchaser';
        $clearedByCompany = $paidBy === 'company';
        $clearedByVendorCredit = $paidBy === 'vendor_credit';

        $statusRibbonText = match(true) {
            $invoice->status->value === 'paid' => 'PAID',
            $balanceAmount <= 0 => 'PAID',
            $paidAmount > 0 => 'PARTIAL',
            default => 'UNPAID',
        };

        $statusRibbonColor = match($statusRibbonText) {
            'PAID' => 'bg-emerald-600',
            'PARTIAL' => 'bg-cyan-600',
            default => 'bg-amber-500',
        };

        $lineItems = collect();
        if ($invoice->purchaserCart?->items?->isNotEmpty()) {
            $lineItems = $invoice->purchaserCart->items->map(fn($item) => [
                'name' => $item->product?->name ?? 'Item',
                'unit' => $item->product?->unit ?? '',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]);
        } elseif ($invoice->goodsReceived?->items?->isNotEmpty()) {
            $lineItems = $invoice->goodsReceived->items->map(fn($item) => [
                'name' => $item->product?->name ?? 'Item',
                'unit' => $item->product?->unit ?? 'kg',
                'quantity' => (float) $item->received_qty,
                'unit_price' => (float) ($item->purchaseOrderItem?->unit_price ?? 0),
                'line_total' => (float) $item->received_qty * (float) ($item->purchaseOrderItem?->unit_price ?? 0),
            ]);
        }

        $backParameters = array_filter($backRouteParameters ?? []);
        $paymentModalData = [
            'number' => $invoice->invoice_number,
            'supplier' => $invoice->supplier?->name,
            'amount' => round((float) $invoice->amount, 2),
            'discountAmount' => round((float) $invoice->discount_amount, 2),
            'paidAmount' => round((float) $invoice->paid_amount, 2),
            'paymentMethod' => $invoice->payment_method ?: 'Cash',
            'paymentNote' => $invoice->payment_note,
            'paymentDetails' => $invoice->payment_details,
            'creditApproved' => (bool) $invoice->supplier?->credit_approved,
        ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <!-- Top Control Panel (Hidden on print) -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs print:hidden">
            <div>
                <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Invoice Details</span>
                <h1 class="text-sm font-black text-slate-900">{{ $invoice->invoice_number }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route($backRouteName, $backParameters) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-black text-slate-700 hover:bg-slate-100">
                    Back
                </a>
                <a href="{{ route($billPdfRouteName, $invoice) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-black text-slate-700 hover:bg-slate-100">
                    Open Bill PDF
                </a>
                <button type="button" onclick='openShowPaymentModal(@json($paymentModalData), "{{ route($paymentUpdateRouteName, $invoice) }}")' class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">
                    Update Payment
                </button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <!-- Retail Bill Receipt Card -->
            <div class="relative mx-auto w-full max-w-[36rem] overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-6 shadow-md sm:px-7 sm:py-8">
                <!-- Status Ribbon -->
                <div class="absolute -left-11 top-7 w-40 -rotate-45 {{ $statusRibbonColor }} py-1 text-center text-xs font-black uppercase tracking-[0.14em] text-white shadow-xs">
                    {{ $statusRibbonText }}
                </div>

                <!-- Receipt Header -->
                <header class="border-b border-dashed border-slate-400 pb-3 text-center">
                    <h2 class="text-xl font-black uppercase tracking-wide text-slate-950">BILL INVOICE</h2>
                    <p class="mt-1.5 text-base font-black uppercase leading-tight text-slate-950">GREEN LEAF</p>
                    <p class="mt-0.5 text-[11px] font-semibold text-slate-600">Fresh Produce & Supplies Accounting</p>
                </header>

                <!-- Bill Details Grid -->
                <div class="grid grid-cols-1 gap-2 border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Bill No</p>
                        <p class="mt-0.5 font-mono text-sm font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                        @if ($invoice->purchaserCart)
                            <p class="mt-1 text-slate-600">Cart: {{ $invoice->purchaserCart->cart_number }}</p>
                        @endif
                    </div>
                    <div class="sm:text-right">
                        <p class="text-slate-600">Date: <span class="font-black text-slate-950">{{ $businessDate ? $businessDate->format('d M Y') : now()->format('d M Y') }}</span></p>
                        <p class="mt-1 text-slate-600">Source: <span class="font-black text-slate-950">{{ $invoice->purchaserCart?->purchaseSourceLabel() ?: 'Direct Bill' }}</span></p>
                    </div>
                </div>

                <!-- Vendor Block -->
                <div class="border-b border-dashed border-slate-400 py-3 text-[11px] text-slate-700">
                    <p class="font-black uppercase tracking-[0.12em] text-slate-500">VENDOR</p>
                    <p class="mt-1 text-base font-black text-slate-950">{{ $supplier?->name ?: 'Vendor Pending' }}</p>
                    <p class="mt-0.5 font-semibold text-slate-600">
                        {{ $supplier?->mobile_number ?: ($supplier?->contact ?: 'Contact pending') }}
                        {{ $supplier?->location ? ' • '.$supplier->location : '' }}
                    </p>
                    @if ($supplier?->payment_terms)
                        <p class="mt-0.5 font-semibold text-slate-600">Terms: {{ $supplier->payment_terms }}</p>
                    @endif
                </div>

                <!-- Items Table -->
                <div class="border-b border-dashed border-slate-400 py-3">
                    <table class="w-full text-left text-[11px]">
                        <thead class="border-b border-dashed border-slate-400 text-[10px] font-black uppercase text-slate-950">
                            <tr>
                                <th class="w-7 py-1 pr-1">SN</th>
                                <th class="py-1 pr-2">ITEM</th>
                                <th class="w-14 py-1 pr-1 text-right">QTY</th>
                                <th class="w-16 py-1 pr-1 text-right">PRICE</th>
                                <th class="w-20 py-1 text-right">AMT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($lineItems as $item)
                                <tr class="align-top">
                                    <td class="py-2 pr-1 font-bold text-slate-600">{{ $loop->iteration }}</td>
                                    <td class="py-2 pr-2">
                                        <p class="font-black text-slate-950">{{ $item['name'] }}</p>
                                        <p class="mt-0.5 text-[10px] font-semibold text-slate-500">{{ $item['unit'] }}</p>
                                    </td>
                                    <td class="py-2 pr-1 text-right font-bold text-slate-900">{{ number_format($item['quantity'], 2) }}</td>
                                    <td class="py-2 pr-1 text-right text-slate-700">₹{{ number_format($item['unit_price'], 2) }}</td>
                                    <td class="py-2 text-right font-black text-slate-950">Rs. {{ number_format($item['line_total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center font-semibold text-slate-500">No items matched on bill.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Subtotal / Summary Block -->
                <div class="ml-auto w-full border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:max-w-[22rem]">
                    <div class="flex justify-between py-0.5">
                        <span>Subtotal</span>
                        <span>Rs. {{ number_format((float) $invoice->amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-0.5 text-slate-600">
                        <span>Discount</span>
                        <span>Rs. {{ number_format((float) $invoice->discount_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 text-sm font-black text-slate-950">
                        <span class="uppercase">TOTAL</span>
                        <span>Rs. {{ number_format($payableTotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center rounded-xl bg-emerald-50 px-3 py-1.5 text-emerald-800">
                        <span class="font-black">Paid</span>
                        <span class="font-black">Rs. {{ number_format($paidAmount, 2) }}</span>
                    </div>
                    <div class="mt-1 flex justify-between items-center rounded-xl {{ $balanceAmount <= 0 ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900' }} px-3 py-1.5">
                        <span class="font-black">Balance</span>
                        <span class="font-black">Rs. {{ number_format($balanceAmount, 2) }}</span>
                    </div>

                    @if ($clearedByCompany)
                        <div class="mt-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Company Cleared</span>
                                </div>
                                <span class="text-[10px] font-black text-indigo-900">Rs. {{ number_format($payableTotal, 2) }}</span>
                            </div>
                            <p class="mt-1 text-[9px] font-semibold text-indigo-600">This bill was settled by the company on behalf of the purchaser.</p>
                        </div>
                    @elseif ($clearedByVendorCredit)
                        <div class="mt-2 rounded-xl border border-purple-200 bg-purple-50 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-purple-700">Vendor Credit</span>
                                </div>
                                <span class="text-[10px] font-black text-purple-900">Rs. {{ number_format($payableTotal, 2) }}</span>
                            </div>
                            <p class="mt-1 text-[9px] font-semibold text-purple-600">Settled via vendor credit arrangement.</p>
                        </div>
                    @endif
                </div>

                <!-- Payment Method Selector Indicators -->
                <div class="pt-4 text-[11px]">
                    <p class="font-black uppercase tracking-[0.12em] text-slate-500">PAYMENT METHOD</p>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div class="rounded-xl border p-2.5 {{ strcasecmp($paymentMethod, 'Cash') === 0 ? 'border-emerald-500 bg-emerald-50/60 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-slate-50' }}">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full border-2 {{ strcasecmp($paymentMethod, 'Cash') === 0 ? 'border-emerald-600 bg-emerald-600' : 'border-slate-400' }}"></span>
                                <span class="font-black text-slate-950">Cash</span>
                            </div>
                            <p class="mt-0.5 pl-5 text-[10px] text-slate-500">Paid directly</p>
                        </div>
                        <div class="rounded-xl border p-2.5 {{ strcasecmp($paymentMethod, 'GPay') === 0 ? 'border-emerald-500 bg-emerald-50/60 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-slate-50' }}">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full border-2 {{ strcasecmp($paymentMethod, 'GPay') === 0 ? 'border-emerald-600 bg-emerald-600' : 'border-slate-400' }}"></span>
                                <span class="font-black text-slate-950">GPay</span>
                            </div>
                            <p class="mt-0.5 pl-5 text-[10px] text-slate-500">UPI transfer</p>
                        </div>
                        <div class="rounded-xl border p-2.5 {{ strcasecmp($paymentMethod, 'Online') === 0 ? 'border-emerald-500 bg-emerald-50/60 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-slate-50' }}">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full border-2 {{ strcasecmp($paymentMethod, 'Online') === 0 ? 'border-emerald-600 bg-emerald-600' : 'border-slate-400' }}"></span>
                                <span class="font-black text-slate-950">Online</span>
                            </div>
                            <p class="mt-0.5 pl-5 text-[10px] text-slate-500">Bank Transfer</p>
                        </div>
                        <div class="rounded-xl border p-2.5 {{ strcasecmp($paymentMethod, 'Credit') === 0 ? 'border-amber-500 bg-amber-50/60 ring-2 ring-amber-500/20' : 'border-slate-200 bg-slate-50' }}">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full border-2 {{ strcasecmp($paymentMethod, 'Credit') === 0 ? 'border-amber-600 bg-amber-600' : 'border-slate-400' }}"></span>
                                <span class="font-black text-slate-950">Credit</span>
                            </div>
                            <p class="mt-0.5 pl-5 text-[10px] text-amber-800">Pay Later</p>
                        </div>
                    </div>
                    <p class="mt-3 text-[11px] font-semibold text-amber-800">Supplier credit keeps paid amount at zero until purchaser or company settles it.</p>
                </div>
            </div>

            <!-- Sidebar References Panel (Hidden on print) -->
            <aside class="space-y-4 print:hidden">

                @if ($clearedByCompany)
                    <!-- Company Clearance Banner -->
                    <div class="rounded-2xl border border-indigo-300 bg-indigo-600 p-5 text-white shadow-md">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p class="text-xs font-black uppercase tracking-[0.16em]">Company Cleared</p>
                        </div>
                        <p class="mt-3 text-2xl font-black">Rs. {{ number_format($payableTotal, 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-indigo-200">Company settled this bill in full on behalf of the purchaser.</p>
                        <div class="mt-3 rounded-xl bg-indigo-500/50 px-3 py-2 text-[11px]">
                            <div class="flex justify-between">
                                <span class="text-indigo-100">Total Bill</span>
                                <span class="font-bold">Rs. {{ number_format((float) $invoice->amount, 2) }}</span>
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span class="text-indigo-100">Discount</span>
                                <span class="font-bold">Rs. {{ number_format((float) $invoice->discount_amount, 2) }}</span>
                            </div>
                            <div class="mt-1 flex justify-between border-t border-indigo-400/60 pt-1">
                                <span class="font-black text-white">Net Cleared</span>
                                <span class="font-black">Rs. {{ number_format($payableTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @elseif ($clearedByVendorCredit)
                    <!-- Vendor Credit Banner -->
                    <div class="rounded-2xl border border-purple-300 bg-purple-600 p-5 text-white shadow-md">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                            <p class="text-xs font-black uppercase tracking-[0.16em]">Vendor Credit</p>
                        </div>
                        <p class="mt-3 text-2xl font-black">Rs. {{ number_format($payableTotal, 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-purple-200">Settled via vendor credit arrangement.</p>
                    </div>
                @elseif ($balanceAmount <= 0 && $paidAmount > 0)
                    <!-- Fully Paid Banner -->
                    <div class="rounded-2xl border border-emerald-300 bg-emerald-600 p-5 text-white shadow-md">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p class="text-xs font-black uppercase tracking-[0.16em]">Fully Paid</p>
                        </div>
                        <p class="mt-3 text-2xl font-black">Rs. {{ number_format($paidAmount, 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-200">Payment settled by purchaser in full.</p>
                    </div>
                @elseif ($balanceAmount > 0)
                    <!-- Pending Balance Banner -->
                    <div class="rounded-2xl border border-amber-300 bg-amber-500 p-5 text-white shadow-md">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p class="text-xs font-black uppercase tracking-[0.16em]">Balance Pending</p>
                        </div>
                        <p class="mt-3 text-2xl font-black">Rs. {{ number_format($balanceAmount, 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-amber-100">Outstanding balance needs to be cleared.</p>
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">References & Details</p>
                    <div class="mt-4 space-y-3 text-xs">
                        @if ($invoice->goodsReceived)
                            <div class="flex items-center justify-between"><span class="text-slate-500">GRN</span><a href="{{ route('purchaser.suppliers.show', ['supplier' => $invoice->supplier_id, 'date' => $businessDate->format('Y-m-d')]) }}" class="font-mono font-bold text-cyan-700">{{ $invoice->goodsReceived->grn_number }}</a></div>
                        @endif
                        <div class="flex items-center justify-between"><span class="text-slate-500">Payment Terms</span><span class="font-semibold text-slate-950">{{ $invoice->supplier->payment_terms ?: 'Cash' }}</span></div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Paid By</span>
                            <span class="font-semibold {{ $clearedByCompany ? 'text-indigo-700' : ($clearedByVendorCredit ? 'text-purple-700' : 'text-slate-950') }}">{{ $invoice->paymentPaidByLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between"><span class="text-slate-500">Payment Status</span><span class="font-semibold text-slate-950">{{ str($invoice->payment_status ?: 'unpaid')->replace('_', ' ')->title() }}</span></div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    </div>

    <div id="show-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeShowPaymentModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Payment Update</h3>
                    <p id="show-payment-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeShowPaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="show-payment-form" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Total Bill</span>
                        <span id="show-payment-total" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Discount</span>
                        <span id="show-payment-discount" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Net Payable</span>
                        <span id="show-payment-net" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Remaining</span>
                        <span id="show-payment-balance" class="text-amber-700"></span>
                    </div>
                    <p id="show-payment-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount</label>
                    <input id="show_discount_amount" type="number" step="0.01" min="0" name="discount_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                    <select id="show_payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="GPay">GPay</option>
                        <option value="Credit">Credit</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Paid Amount</label>
                    <input id="show_paid_amount" type="number" step="0.01" min="0" name="paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                    <input id="show_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Details</label>
                    <textarea id="show_payment_details" name="payment_details" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500">Save Payment Update</button>
            </form>
        </div>
    </div>

    <script>
        let showPaymentAmount = 0;
        let showPaymentDiscount = 0;
        let showPaymentCreditApproved = false;

        function openShowPaymentModal(invoice, actionUrl) {
            showPaymentAmount = Number(invoice.amount || 0);
            showPaymentDiscount = Number(invoice.discountAmount || 0);
            showPaymentCreditApproved = Boolean(invoice.creditApproved);

            document.getElementById('show-payment-form').action = actionUrl;
            document.getElementById('show-payment-title').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
            document.getElementById('show-payment-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
            document.getElementById('show_discount_amount').value = Number(invoice.discountAmount || 0).toFixed(2);
            document.getElementById('show_payment_method').value = invoice.paymentMethod || 'Cash';
            document.getElementById('show_paid_amount').value = Number(invoice.paidAmount || 0).toFixed(2);
            document.getElementById('show_payment_note').value = invoice.paymentNote || '';
            document.getElementById('show_payment_details').value = invoice.paymentDetails || '';

            updateShowPaymentStatus();
            document.getElementById('show-payment-modal').classList.remove('hidden');
            document.getElementById('show-payment-modal').classList.add('flex');
        }

        function closeShowPaymentModal() {
            document.getElementById('show-payment-modal').classList.add('hidden');
            document.getElementById('show-payment-modal').classList.remove('flex');
        }

        function updateShowPaymentStatus() {
            const method = document.getElementById('show_payment_method').value;
            const discountAmount = Math.max(0, Number(document.getElementById('show_discount_amount').value || 0));
            const paidAmount = Number(document.getElementById('show_paid_amount').value || 0);
            const netAmount = Math.max(0, showPaymentAmount - discountAmount);
            const balance = Math.max(0, netAmount - paidAmount);
            const balanceNode = document.getElementById('show-payment-balance');
            const discountNode = document.getElementById('show-payment-discount');
            const netNode = document.getElementById('show-payment-net');
            const warningNode = document.getElementById('show-payment-warning');

            discountNode.textContent = `₹${discountAmount.toFixed(2)}`;
            netNode.textContent = `₹${netAmount.toFixed(2)}`;
            balanceNode.textContent = `₹${balance.toFixed(2)}`;
            balanceNode.className = balance > 0 ? 'text-amber-700' : 'text-emerald-700';

            if (method === 'Credit') {
                warningNode.textContent = showPaymentCreditApproved
                    ? 'Credit selected. Payment will stay pending until it is cleared in full.'
                    : 'Credit selected but supplier credit is not approved yet.';
                return;
            }

            warningNode.textContent = balance > 0
                ? 'Payment is not done fully. Remaining balance will stay pending.'
                : 'Full payment entered. This purchase will be marked received.';
        }

        document.getElementById('show_discount_amount')?.addEventListener('input', updateShowPaymentStatus);
        document.getElementById('show_payment_method')?.addEventListener('change', updateShowPaymentStatus);
        document.getElementById('show_paid_amount')?.addEventListener('input', updateShowPaymentStatus);
    </script>
</x-layouts.app>
