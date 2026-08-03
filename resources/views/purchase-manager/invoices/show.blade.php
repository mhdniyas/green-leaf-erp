@extends('purchase-manager.layouts.app')

@section('title', 'Supplier Bill Details - '.$invoice->invoice_number)
@section('page_title', 'Bill Invoice: '.$invoice->invoice_number)
@section('page_description', 'Official thermal retail invoice view, payment workflow, and supplier settlement details.')

@section('content')
    @php
        $payableTotal = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
        $paidAmount = (float) $invoice->paid_amount;
        $balanceAmount = max(0, $payableTotal - $paidAmount);
        $paymentMethod = $invoice->payment_method ?: ($invoice->purchaserCart?->payment_method ?: 'Credit');
        $supplier = $invoice->supplier;
        
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

        $items = collect();
        if ($invoice->purchaserCart?->items?->isNotEmpty()) {
            $items = $invoice->purchaserCart->items->map(fn($item) => [
                'name' => $item->product?->name ?? 'Item',
                'unit' => $item->product?->unit ?? '',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]);
        } elseif ($invoice->goodsReceived?->items?->isNotEmpty()) {
            $items = $invoice->goodsReceived->items->map(fn($item) => [
                'name' => $item->product?->name ?? 'Item',
                'unit' => $item->product?->unit ?? 'kg',
                'quantity' => (float) $item->received_qty,
                'unit_price' => (float) ($item->purchaseOrderItem?->unit_price ?? 0),
                'line_total' => (float) $item->received_qty * (float) ($item->purchaseOrderItem?->unit_price ?? 0),
            ]);
        }
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <!-- Top Bar Actions -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs print:hidden">
            <div>
                <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Retail Bill Invoice</span>
                <h1 class="text-lg font-black text-slate-950">{{ $invoice->invoice_number }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button onclick="window.print()" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-black text-slate-700 hover:bg-slate-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3v18" />
                    </svg>
                    Print Bill
                </button>
                <a href="{{ route('purchasing.invoices.pdf', $invoice) }}" target="_blank" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-black text-slate-700 hover:bg-slate-100">
                    PDF Invoice
                </a>
                <a href="{{ route('purchasing.invoices.index') }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">
                    Back to Invoices
                </a>
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
                        <p class="text-slate-600">Date: <span class="font-black text-slate-950">{{ $invoice->created_at->format('d M Y') }}</span></p>
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
                            @forelse ($items as $item)
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
                    <div class="mt-1 flex justify-between items-center rounded-xl bg-amber-50 px-3 py-1.5 text-amber-900">
                        <span class="font-black">Balance</span>
                        <span class="font-black">Rs. {{ number_format($balanceAmount, 2) }}</span>
                    </div>
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

            <!-- Sidebar Actions & Workflow Panel -->
            <aside class="space-y-4 print:hidden">

                {{-- Payment Status Banner --}}
                @if ($balanceAmount <= 0 && $paidAmount > 0)
                    <div class="rounded-2xl border border-emerald-300 bg-emerald-600 p-4 text-white shadow-sm">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span class="text-xs font-black uppercase tracking-[0.14em]">Fully Settled</span>
                        </div>
                        <p class="mt-2 text-xl font-black">Rs. {{ number_format($paidAmount, 2) }}</p>
                        <p class="mt-0.5 text-xs font-semibold text-emerald-200">Paid by {{ $invoice->paymentPaidByLabel() }}</p>
                    </div>
                @elseif ($balanceAmount > 0)
                    <div class="rounded-2xl border border-amber-300 bg-amber-500 p-4 text-white shadow-sm">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span class="text-xs font-black uppercase tracking-[0.14em]">Balance Pending</span>
                        </div>
                        <p class="mt-2 text-xl font-black">Rs. {{ number_format($balanceAmount, 2) }}</p>
                        <p class="mt-0.5 text-xs font-semibold text-amber-100">Outstanding needs clearance</p>
                    </div>
                @endif

                {{-- Actions Panel --}}
                @if ($invoice->hasCalculationError())
                    <div class="mb-4 rounded-2xl border border-rose-300 bg-rose-50/90 p-4 text-rose-950 shadow-xs">
                        <div class="flex items-start gap-2.5">
                            <span class="text-base">⚠️</span>
                            <div class="flex-1">
                                <h3 class="text-xs font-black uppercase tracking-wider text-rose-900">Calculation Error Flagged</h3>
                                <p class="mt-1 text-[11px] font-semibold leading-relaxed text-rose-800">
                                    Gross item total (<strong>₹{{ number_format($invoice->itemsGrossTotal(), 2) }}</strong>) does not match stored amount (<strong>₹{{ number_format((float) $invoice->amount, 2) }}</strong>).
                                    Can <strong>only be updated by Admin</strong>.
                                </p>
                                @if (auth()->user()?->hasRole('admin'))
                                    <form action="{{ route('purchasing.invoices.fix-calculation', $invoice) }}" method="POST" class="mt-2.5">
                                        @csrf
                                        <button type="submit" class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-xl bg-rose-700 px-3 text-xs font-black text-white hover:bg-rose-800 shadow-xs transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                            <span>Fix & Recalculate Bill (Admin)</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Payment Actions</p>
                    <div class="mt-3 flex flex-col gap-2">

                        {{-- Main Payment Update Button --}}
                        @can('update', $invoice)
                            @if (! $invoice->hasCalculationError() || auth()->user()?->hasRole('admin'))
                                <button
                                    type="button"
                                    onclick="openPaymentModal()"
                                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 text-xs font-black text-white hover:bg-indigo-500 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                                    {{ $balanceAmount > 0 ? 'Record Payment (Rs. '.number_format($balanceAmount, 2).')' : 'Update Payment' }}
                                </button>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-100/60 px-4 text-xs font-black text-rose-700 cursor-not-allowed opacity-80"
                                    title="Calculation error flagged. Only Admin can update."
                                >
                                    🔒 Admin Only Update
                                </button>
                            @endif

                            {{-- Approve Invoice --}}
                            @if ($invoice->status->value === 'pending')
                                <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800 transition-colors">
                                        Approve Invoice
                                    </button>
                                </form>
                            @endif

                            {{-- Mark Fully Paid --}}
                            @if (in_array($invoice->status->value, ['pending', 'approved']))
                                <button
                                    type="button"
                                    onclick="openPaymentModal(true)"
                                    class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-emerald-600 px-4 text-xs font-black text-white hover:bg-emerald-500 transition-colors"
                                >
                                    Mark Fully Paid
                                </button>
                            @endif

                            {{-- Revert --}}
                            @if (in_array($invoice->status->value, ['approved', 'paid']))
                                <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}"
                                    onsubmit="return confirm('Revert this invoice back to Pending? This will undo the approval/paid status.')">
                                    @csrf
                                    <input type="hidden" name="status" value="pending">
                                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-black text-rose-700 hover:bg-rose-100 transition-colors">
                                        ↩ Revert to Pending
                                    </button>
                                </form>
                            @endif
                        @endcan

                        <a href="{{ route('purchasing.invoices.index') }}" class="inline-flex h-9 w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-slate-100 transition-colors">
                            ← Back to Invoices
                        </a>
                    </div>
                </div>

                {{-- References Panel --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">References & Details</p>
                    <div class="mt-3 space-y-2.5 text-xs">
                        @if ($invoice->goodsReceived)
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">GRN</span>
                                <a href="{{ route('purchasing.grns.show', $invoice->goodsReceived) }}" class="font-mono font-bold text-cyan-700 hover:underline">{{ $invoice->goodsReceived->grn_number }}</a>
                            </div>
                        @endif
                        @if ($invoice->goodsReceived?->purchaseOrder)
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">PO</span>
                                <a href="{{ route('purchasing.orders.show', $invoice->goodsReceived->purchaseOrder) }}" class="font-mono font-bold text-cyan-700 hover:underline">{{ $invoice->goodsReceived->purchaseOrder->po_number }}</a>
                            </div>
                        @endif
                        <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Status</span><span class="font-semibold capitalize text-slate-950">{{ str($invoice->status->value)->replace('_',' ') }}</span></div>
                        <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Payment Status</span><span class="font-semibold capitalize text-slate-950">{{ str($invoice->payment_status ?: 'unpaid')->replace('_',' ') }}</span></div>
                        <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Payment Terms</span><span class="font-semibold text-slate-950">{{ $invoice->supplier->payment_terms ?: 'Cash' }}</span></div>
                        <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Paid By</span><span class="font-semibold text-slate-950">{{ $invoice->paymentPaidByLabel() }}</span></div>
                        @if ($invoice->payment_note)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-black uppercase text-slate-500">Note</p>
                                <p class="mt-1 text-xs font-semibold text-slate-700">{{ $invoice->payment_note }}</p>
                            </div>
                        @endif

                        @if (auth()->user()?->hasRole('admin') && isset($allSuppliers))
                            <div class="border-t border-slate-100 pt-2">
                                <details class="group">
                                    <summary class="cursor-pointer text-[11px] font-black uppercase tracking-wider text-indigo-600 hover:text-indigo-800">
                                        ✏️ Change Vendor (Admin)
                                    </summary>
                                    <form action="{{ route('purchasing.invoices.change-supplier', $invoice) }}" method="POST" class="mt-2 flex flex-col gap-2 rounded-xl border border-indigo-200 bg-indigo-50/60 p-2.5">
                                        @csrf
                                        <select name="supplier_id" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-900 focus:outline-none">
                                            @foreach ($allSuppliers as $sup)
                                                <option value="{{ $sup->id }}" @selected($supplier?->id === $sup->id)>{{ $sup->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="h-8 w-full rounded-lg bg-indigo-600 text-xs font-black text-white hover:bg-indigo-500">Update Vendor</button>
                                    </form>
                                </details>
                            </div>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- ===== PAYMENT MODAL ===== --}}
    <div id="pm-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if(event.target===this)closePaymentModal()">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-2xl">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Record Payment</h3>
                    <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $invoice->invoice_number }} — {{ $invoice->supplier?->name }}</p>
                </div>
                <button type="button" onclick="closePaymentModal()" class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Modal Form --}}
            <form id="pm-form" method="POST" action="{{ route('purchasing.invoices.update-payment', $invoice) }}">
                @csrf
                @method('PATCH')

                <div class="space-y-4 px-5 py-4">

                    {{-- Amount --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500" for="pm-paid-amount">Amount to Pay (Rs.)</label>
                        <input
                            type="number"
                            id="pm-paid-amount"
                            name="paid_amount"
                            step="0.01"
                            min="0"
                            max="{{ number_format($payableTotal, 2, '.', '') }}"
                            value="{{ number_format($balanceAmount > 0 ? $balanceAmount : $payableTotal, 2, '.', '') }}"
                            class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-black text-slate-950 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/20"
                            required
                        >
                        <p class="mt-1 text-[10px] font-semibold text-slate-400">Total: Rs. {{ number_format($payableTotal, 2) }} · Paid: Rs. {{ number_format($paidAmount, 2) }} · Balance: Rs. {{ number_format($balanceAmount, 2) }}</p>
                    </div>

                    {{-- Paid By --}}
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Paid By</p>
                        <div class="mt-1.5 grid grid-cols-2 gap-2">
                            <label class="pm-paidby-option cursor-pointer" data-value="purchaser">
                                <input type="radio" name="payment_paid_by" value="purchaser" class="sr-only" checked>
                                <div class="pm-paidby-card rounded-xl border-2 border-indigo-500 bg-indigo-50/60 p-3 ring-2 ring-indigo-500/20 transition-all">
                                    <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                    <p class="mt-1 text-xs font-black text-slate-950">Purchaser</p>
                                    <p class="text-[10px] text-slate-500">Updates cash ledger</p>
                                </div>
                            </label>
                            <label class="pm-paidby-option cursor-pointer" data-value="company">
                                <input type="radio" name="payment_paid_by" value="company" class="sr-only">
                                <div class="pm-paidby-card rounded-xl border-2 border-slate-200 bg-slate-50 p-3 transition-all">
                                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" /></svg>
                                    <p class="mt-1 text-xs font-black text-slate-950">Company</p>
                                    <p class="text-[10px] text-slate-500">Green Leaf settles</p>
                                </div>
                            </label>
                        </div>

                        {{-- Purchaser Selector (shown when Purchaser selected) --}}
                        <div id="pm-purchaser-selector" class="mt-2">
                            @if ($purchasers->isEmpty())
                                <p class="rounded-lg bg-amber-50 px-3 py-2 text-[10px] font-semibold text-amber-700">No purchasers found with cash balance.</p>
                            @else
                                <label class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Select Purchaser</label>
                                <div class="mt-1.5 space-y-1.5">
                                    @foreach ($purchasers as $p)
                                        <label class="pm-purchaser-option flex cursor-pointer items-center justify-between rounded-xl border-2 border-slate-200 bg-slate-50 px-3 py-2.5 transition-all hover:border-indigo-300 hover:bg-indigo-50/40">
                                            <div class="flex items-center gap-2">
                                                <input type="radio" name="payment_purchaser_id" value="{{ $p['id'] }}" class="h-3.5 w-3.5 accent-indigo-600 sr-only pm-purchaser-radio">
                                                <span class="pm-purchaser-dot h-3.5 w-3.5 rounded-full border-2 border-slate-300 bg-white transition-all"></span>
                                                <span class="text-xs font-black text-slate-950">{{ $p['name'] }}</span>
                                            </div>
                                            <span class="text-[10px] font-semibold {{ $p['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                                Rs. {{ number_format($p['balance'], 2) }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p id="pm-purchaser-balance-warning" class="mt-1.5 hidden rounded-lg bg-rose-50 px-3 py-2 text-[10px] font-semibold text-rose-700">
                                    Selected purchaser may not have sufficient cash balance.
                                </p>
                            @endif
                        </div>

                        <div id="pm-company-note" class="mt-1.5 hidden rounded-lg bg-amber-50 px-3 py-2 text-[10px] font-semibold text-amber-700">
                            Company pays on behalf of purchaser. Recorded as company settlement.
                        </div>
                    </div>

                    {{-- Payment Method --}}
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Payment Mode</p>
                        <div class="mt-1.5 grid grid-cols-2 gap-2">
                            @foreach (['Cash' => 'Paid directly', 'GPay' => 'UPI transfer', 'Online' => 'Bank transfer', 'Credit' => 'Pay later'] as $method => $desc)
                                <label class="pm-method-option cursor-pointer" data-value="{{ $method }}">
                                    <input type="radio" name="payment_method" value="{{ $method }}" class="sr-only" {{ $method === 'Cash' ? 'checked' : '' }}>
                                    <div class="pm-method-card rounded-xl border-2 {{ $method === 'Cash' ? 'border-emerald-500 bg-emerald-50/60 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-slate-50' }} p-2.5 transition-all">
                                        <p class="text-xs font-black text-slate-950">{{ $method }}</p>
                                        <p class="text-[10px] text-slate-500">{{ $desc }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500" for="pm-notes">Payment Note <span class="font-semibold normal-case text-slate-400">(optional)</span></label>
                        <textarea
                            id="pm-notes"
                            name="payment_note"
                            rows="2"
                            placeholder="e.g. Paid via GPay ref #12345, or company settled on behalf..."
                            class="mt-1.5 w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-950 placeholder-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/20"
                        >{{ $invoice->payment_note }}</textarea>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                    <button type="button" onclick="recalculatePMPaymentModal()" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-black text-slate-800 shadow-xs hover:bg-slate-50">
                        <svg class="h-3.5 w-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Recheck & Recalculate</span>
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="closePaymentModal()" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-slate-100">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl bg-indigo-600 px-5 text-xs font-black text-white hover:bg-indigo-500">
                            Save Payment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openPaymentModal(fullPay = false) {
        const overlay = document.getElementById('pm-overlay');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        if (fullPay) {
            document.getElementById('pm-paid-amount').value = '{{ number_format($payableTotal, 2, '.', '') }}';
        }
    }

    function closePaymentModal() {
        const overlay = document.getElementById('pm-overlay');
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    }

    // Payment method pill selection
    document.querySelectorAll('.pm-method-option').forEach(label => {
        label.addEventListener('click', () => {
            document.querySelectorAll('.pm-method-card').forEach(card => {
                card.className = card.className.replace(/border-emerald-500|bg-emerald-50\/60|ring-2|ring-emerald-500\/20/g, '').trim();
                card.classList.add('border-slate-200', 'bg-slate-50');
            });
            const card = label.querySelector('.pm-method-card');
            card.classList.remove('border-slate-200', 'bg-slate-50');
            card.classList.add('border-emerald-500', 'bg-emerald-50/60', 'ring-2', 'ring-emerald-500/20');
            label.querySelector('input').checked = true;
        });
    });

    // Paid-by selection
    document.querySelectorAll('.pm-paidby-option').forEach(label => {
        label.addEventListener('click', () => {
            document.querySelectorAll('.pm-paidby-card').forEach(card => {
                card.classList.remove('border-indigo-500', 'bg-indigo-50/60', 'ring-2', 'ring-indigo-500/20');
                card.classList.add('border-slate-200', 'bg-slate-50');
            });
            const card = label.querySelector('.pm-paidby-card');
            card.classList.remove('border-slate-200', 'bg-slate-50');
            card.classList.add('border-indigo-500', 'bg-indigo-50/60', 'ring-2', 'ring-indigo-500/20');
            label.querySelector('input').checked = true;

            const val = label.dataset.value;
            const purchaserSelector = document.getElementById('pm-purchaser-selector');
            const companyNote = document.getElementById('pm-company-note');

            if (purchaserSelector) purchaserSelector.classList.toggle('hidden', val !== 'purchaser');
            if (companyNote) companyNote.classList.toggle('hidden', val !== 'company');
        });
    });

    // Purchaser row selection
    document.querySelectorAll('.pm-purchaser-option').forEach(label => {
        label.addEventListener('click', () => {
            // Reset all rows
            document.querySelectorAll('.pm-purchaser-option').forEach(l => {
                l.classList.remove('border-indigo-500', 'bg-indigo-50/60', 'ring-2', 'ring-indigo-400/20');
                l.classList.add('border-slate-200', 'bg-slate-50');
                l.querySelector('.pm-purchaser-dot')?.classList.remove('border-indigo-600', 'bg-indigo-600');
                l.querySelector('.pm-purchaser-dot')?.classList.add('border-slate-300', 'bg-white');
            });
            // Highlight selected
            label.classList.remove('border-slate-200', 'bg-slate-50');
            label.classList.add('border-indigo-500', 'bg-indigo-50/60', 'ring-2', 'ring-indigo-400/20');
            const dot = label.querySelector('.pm-purchaser-dot');
            dot?.classList.remove('border-slate-300', 'bg-white');
            dot?.classList.add('border-indigo-600', 'bg-indigo-600');
            label.querySelector('input').checked = true;

            // Balance warning
            const balanceText = label.querySelector('span.text-\\[10px\\]')?.textContent ?? '';
            const balance = parseFloat(balanceText.replace(/[^0-9.\-]/g, '')) || 0;
            const paidAmount = parseFloat(document.getElementById('pm-paid-amount')?.value || 0);
            const warning = document.getElementById('pm-purchaser-balance-warning');
            if (warning) warning.classList.toggle('hidden', balance >= paidAmount);
        });
    });

    function recalculatePMPaymentModal() {
        const discountInput = document.getElementById('pm-discount-amount');
        const paidInput = document.getElementById('pm-paid-amount');
        if (!discountInput || !paidInput) return;

        const grossAmount = {{ (float) $invoice->amount }};
        const discount = Math.max(0, parseFloat(discountInput.value || 0));
        const netTotal = Math.max(0, grossAmount - discount);
        const paid = Math.max(0, parseFloat(paidInput.value || 0));
        const balance = Math.max(0, netTotal - paid);

        const netDisplay = document.getElementById('pm-net-payable-display');
        const balanceDisplay = document.getElementById('pm-balance-display');
        if (netDisplay) netDisplay.textContent = '₹' + netTotal.toFixed(2);
        if (balanceDisplay) balanceDisplay.textContent = '₹' + balance.toFixed(2);
    }

    document.getElementById('pm-discount-amount')?.addEventListener('input', recalculatePMPaymentModal);
    document.getElementById('pm-paid-amount')?.addEventListener('input', recalculatePMPaymentModal);
    </script>
@endsection
