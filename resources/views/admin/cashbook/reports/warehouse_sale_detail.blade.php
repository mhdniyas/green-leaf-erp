@extends('admin.cashbook.layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.cashbook.warehouse-sales') }}" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold text-slate-800">Invoice: {{ $sale->invoice_number }}</h1>
                        @if($sale->isConfirmed())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Confirmed
                            </span>
                        @elseif($sale->isCancelled())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                                Cancelled
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                Draft
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Created on {{ $sale->created_at->format('d M Y, h:i A') }} • Business Date: {{ \Carbon\Carbon::parse($sale->business_date)->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50 shadow-sm transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print Invoice
            </button>
        </div>
    </div>

    {{-- Overview Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Sale & Customer Info --}}
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Customer & Warehouse</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Customer Type:</span>
                    <span class="font-medium text-slate-800">
                        @if($sale->isShopSale())
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">Shop</span>
                        @elseif($sale->isWalkingCustomer())
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800">Walking Customer</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">Cash Sales</span>
                        @endif
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Customer:</span>
                    <span class="font-medium text-slate-800">
                        @if($sale->isShopSale() && $sale->shop)
                            {{ $sale->shop->name }}
                        @else
                            {{ $sale->customerDisplayName() }}
                        @endif
                    </span>
                </div>
                @if($sale->customer_phone_snapshot)
                    <div class="flex justify-between">
                        <span class="text-slate-500">Phone:</span>
                        <span class="text-slate-700 font-mono">{{ $sale->customer_phone_snapshot }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-slate-500">Warehouse:</span>
                    <span class="font-medium text-slate-800">{{ $sale->warehouse->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Sold By:</span>
                    <span class="font-medium text-slate-800">{{ $sale->soldBy?->name ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Payment & Money Holder --}}
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Payment & Cash Ownership</h3>
            <div class="space-y-2 text-sm">
                @forelse($sale->payments as $payment)
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Method:</span>
                        <span class="font-semibold text-slate-800">{{ ucfirst($payment->payment_method) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Money Holder:</span>
                        @if($payment->isHeldByCompany())
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Company Account
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                Held by {{ $payment->moneyHolderUser->name ?? 'User' }}
                            </span>
                        @endif
                    </div>
                    @if($payment->companyAccount)
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Company Account:</span>
                            <span class="text-xs font-medium text-slate-700">{{ $payment->companyAccount->account_name }}</span>
                        </div>
                    @endif
                    @if($payment->reference)
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Reference:</span>
                            <span class="text-xs font-mono text-slate-700">{{ $payment->reference }}</span>
                        </div>
                    @endif
                @empty
                    <p class="text-xs text-slate-400">No payment record found.</p>
                @endforelse
            </div>
        </div>

        {{-- Financial Summary --}}
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Financial Total</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between text-slate-600">
                    <span>Subtotal:</span>
                    <span>₹{{ number_format($sale->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Discount:</span>
                    <span>-₹{{ number_format($sale->discount, 2) }}</span>
                </div>
                <div class="border-t border-slate-100 pt-2 flex justify-between font-bold text-slate-800 text-base">
                    <span>Total Amount:</span>
                    <span class="text-emerald-600">₹{{ number_format($sale->total_amount, 2) }}</span>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Paid Amount:</span>
                    <span>₹{{ number_format($sale->paid_amount, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
            <h3 class="text-sm font-semibold text-slate-800">Items Sold</h3>
            <span class="text-xs text-slate-500">{{ $sale->items->count() }} item(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3">#</th>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Grade</th>
                        <th class="px-5 py-3 text-right">Quantity</th>
                        <th class="px-5 py-3 text-right">Unit Price</th>
                        <th class="px-5 py-3 text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($sale->items as $index => $item)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 text-xs text-slate-400">{{ $index + 1 }}</td>
                            <td class="px-5 py-3 font-medium text-slate-800">
                                {{ $item->product->name ?? 'Product #' . $item->product_id }}
                                @if($item->product?->code)
                                    <span class="text-xs text-slate-400 font-mono">({{ $item->product->code }})</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($item->grade)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                        {{ is_object($item->grade) ? ($item->grade->value ?? $item->grade->name) : $item->grade }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-medium text-slate-800">
                                {{ number_format($item->entered_qty, 2) }} {{ $item->entered_unit }}
                            </td>
                            <td class="px-5 py-3 text-right text-slate-700">
                                ₹{{ number_format($item->unit_price, 2) }} / {{ $item->entered_unit }}
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-900">
                                ₹{{ number_format($item->line_total, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Inventory Movement Traceability --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Auditable Inventory Movements</h3>
                <p class="text-xs text-slate-500">Traceable stock movements recorded in the inventory ledger for this sale</p>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                Ledger Linked
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3">Movement ID</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Warehouse</th>
                        <th class="px-5 py-3 text-right">Quantity</th>
                        <th class="px-5 py-3">Date / Time</th>
                        <th class="px-5 py-3">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @php
                        $movements = collect();
                        foreach ($sale->items as $item) {
                            $movements = $movements->concat($item->stockMovements);
                        }
                    @endphp
                    @forelse($movements as $movement)
                        <tr class="hover:bg-slate-50 font-mono text-xs">
                            <td class="px-5 py-3 font-semibold text-slate-700">#{{ $movement->id }}</td>
                            <td class="px-5 py-3 font-sans">
                                @if($movement->type === \App\Enums\Inventory\StockMovementType::Sale || ($movement->type?->value ?? $movement->type) === 'sale')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800">
                                        SALE_OUT
                                    </span>
                                @elseif($movement->type === \App\Enums\Inventory\StockMovementType::SaleReversal || ($movement->type?->value ?? $movement->type) === 'sale_reversal')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                                        SALE_REVERSAL
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">
                                        {{ $movement->type?->value ?? $movement->type ?? 'Movement' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-sans font-medium text-slate-800">{{ $movement->product->name ?? 'Product #' . $movement->product_id }}</td>
                            <td class="px-5 py-3 font-sans text-slate-600">{{ $movement->warehouse->name ?? 'N/A' }}</td>
                            <td class="px-5 py-3 text-right font-bold {{ $movement->quantity < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity, 2) }} {{ $movement->product->unit ?? 'kg' }}
                            </td>
                            <td class="px-5 py-3 text-slate-500 font-sans">{{ $movement->created_at->format('d M Y, h:i A') }}</td>
                            <td class="px-5 py-3 text-slate-700 font-sans">{{ $movement->user->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-4 text-center text-xs text-slate-400 font-sans">
                                No stock movements linked to this sale.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Audit & Timestamps --}}
    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 text-xs text-slate-500 flex flex-wrap gap-x-8 gap-y-2">
        <div><span class="font-medium text-slate-600">Created At:</span> {{ $sale->created_at->format('d M Y, h:i:s A') }}</div>
        @if($sale->confirmed_at)
            <div><span class="font-medium text-slate-600">Confirmed At:</span> {{ $sale->confirmed_at->format('d M Y, h:i:s A') }}</div>
        @endif
        @if($sale->cancelled_at)
            <div><span class="font-medium text-slate-600">Cancelled At:</span> {{ $sale->cancelled_at->format('d M Y, h:i:s A') }} by {{ $sale->cancelledByUser->name ?? 'User' }}</div>
            @if($sale->cancellation_reason)
                <div><span class="font-medium text-slate-600">Reason:</span> {{ $sale->cancellation_reason }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
