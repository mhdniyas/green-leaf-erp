<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Invoice - {{ $invoice->invoice_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 10mm;
            }
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
            .a4-print-card {
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 auto !important;
                padding: 1.25rem 1.75rem !important;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-slate-100 py-6 text-slate-950">
    @php
        $payableTotal = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
        $paidAmount = (float) $invoice->paid_amount;
        $balanceAmount = max(0, $payableTotal - $paidAmount);
        $paymentMethod = $invoice->payment_method ?: ($invoice->purchaserCart?->payment_method ?: 'Credit');
        $supplier = $invoice->supplier;
        $businessDate = $invoice->purchaserCart?->business_date ?? $invoice->created_at;

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
    @endphp

    <div class="mx-auto max-w-full min-w-0 space-y-4 px-3 sm:px-6">
        <!-- Top Bar Actions (Hidden on Print) -->
        <div class="no-print mx-auto flex max-w-[36rem] items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs print:hidden">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Invoice PDF View</p>
                <h1 class="text-sm font-black text-slate-950">{{ $invoice->invoice_number }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3v18" />
                    </svg>
                    Print / Save PDF
                </button>
            </div>
        </div>

        <!-- Retail Thermal Invoice Card -->
        <div class="relative mx-auto w-full max-w-[36rem] overflow-hidden rounded-2xl border border-slate-300 bg-white px-4 py-6 shadow-md sm:px-7 sm:py-8">
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
                <div class="mt-1 flex justify-between items-center rounded-xl bg-amber-50 px-3 py-1.5 text-amber-900">
                    <span class="font-black">Balance</span>
                    <span class="font-black">Rs. {{ number_format($balanceAmount, 2) }}</span>
                </div>
            </div>

            <!-- Selected Payment Method Only (PDF View) -->
            @php
                $selectedPaymentMeta = match(strtolower((string) $paymentMethod)) {
                    'cash' => ['label' => 'Cash', 'desc' => 'Paid directly', 'color' => 'border-emerald-500 bg-emerald-50 text-emerald-950'],
                    'gpay' => ['label' => 'GPay', 'desc' => 'UPI transfer', 'color' => 'border-emerald-500 bg-emerald-50 text-emerald-950'],
                    'online' => ['label' => 'Online', 'desc' => 'Bank Transfer', 'color' => 'border-emerald-500 bg-emerald-50 text-emerald-950'],
                    default => ['label' => 'Credit', 'desc' => 'Pay Later', 'color' => 'border-amber-500 bg-amber-50 text-amber-950'],
                };
            @endphp
            <div class="pt-4 text-[11px]">
                <p class="font-black uppercase tracking-[0.12em] text-slate-500">PAYMENT METHOD</p>
                <div class="mt-2 rounded-xl border p-3 {{ $selectedPaymentMeta['color'] }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-current"></span>
                            <span class="font-black text-sm">{{ $selectedPaymentMeta['label'] }}</span>
                        </div>
                        <span class="font-bold text-xs">{{ $selectedPaymentMeta['desc'] }}</span>
                    </div>
                </div>
                @if (strcasecmp($paymentMethod, 'Credit') === 0 || $balanceAmount > 0)
                    <p class="mt-2 text-[10px] font-semibold text-amber-800">Supplier credit keeps paid amount at zero until purchaser or company settles it.</p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
