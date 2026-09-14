<x-layouts.app title="Sale Invoice {{ $sale->invoice_number }}">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-4 py-3 lg:max-w-2xl lg:px-4 lg:py-4">

        <!-- Top Actions (Screen only) -->
        <div class="no-print flex items-center justify-between gap-3 px-1">
            <a href="{{ route('warehouse.sales.index', ['warehouse_id' => $sale->warehouse_id, 'date' => $sale->business_date->toDateString()]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                <span>Back to Sales</span>
            </a>

            <div class="flex items-center gap-2">
                @if($sale->isConfirmed())
                    <button type="button"
                            onclick="if(confirm('Are you sure you want to cancel this sale and revert all deducted inventory?')) { document.getElementById('cancel-sale-form').submit(); }"
                            class="px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition shadow-2xs cursor-pointer">
                        Cancel Sale
                    </button>
                    <form id="cancel-sale-form" method="POST" action="{{ route('warehouse.sales.cancel', $sale) }}" class="hidden">
                        @csrf
                        <input type="hidden" name="cancel_reason" value="Cancelled by user">
                    </form>
                @endif

                <button onclick="window.print()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-black transition shadow-xs cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656h10.5Z" /></svg>
                    <span>Print Invoice</span>
                </button>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">✕</button>
            </div>
        @endif

        @if($sale->isCancelled())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                <div>
                    <span>This sale was cancelled on {{ $sale->cancelled_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} by {{ $sale->cancelledBy?->name ?? 'User' }}.</span>
                    @if($sale->cancel_reason)
                        <span class="block text-[11px] font-normal text-rose-700">Reason: {{ $sale->cancel_reason }}</span>
                    @endif
                </div>
            </div>
        @endif

        <!-- Printable Invoice Receipt Card -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6 font-sans text-slate-900 print:border-none print:shadow-none print:p-0">

            <!-- Invoice Top Header -->
            <div class="border-b border-slate-200 pb-5 text-center sm:text-left sm:flex sm:items-start sm:justify-between gap-4">
                <div>
                    <div class="flex items-center justify-center sm:justify-start gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                            GREEN LEAF ERP
                        </span>
                        <span class="text-xs font-bold text-slate-400">·</span>
                        <span class="text-xs font-black uppercase tracking-wider text-slate-500">Warehouse Direct Sale</span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-slate-950 mt-1.5 tracking-tight font-mono">
                        {{ $sale->invoice_number }}
                    </h2>
                </div>

                <div class="text-center sm:text-right text-xs text-slate-500 mt-2 sm:mt-0 space-y-0.5">
                    <div><span class="font-bold text-slate-700">Date:</span> {{ $sale->business_date->format('d M Y') }}</div>
                    <div><span class="font-bold text-slate-700">Warehouse:</span> {{ $sale->warehouse?->name }}</div>
                    <div><span class="font-bold text-slate-700">Sold By:</span> {{ $sale->soldBy?->name }}</div>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Customer</span>
                    <span class="text-sm font-bold text-slate-900 block mt-0.5">{{ $sale->customer_name_snapshot }}</span>
                    @if($sale->customer_phone_snapshot)
                        <span class="font-mono text-slate-500 block">{{ $sale->customer_phone_snapshot }}</span>
                    @endif
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Status</span>
                    @if($sale->isCancelled())
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-black uppercase bg-rose-100 text-rose-800 border border-rose-200 mt-0.5">
                            Cancelled
                        </span>
                    @else
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-black uppercase bg-emerald-100 text-emerald-800 border border-emerald-200 mt-0.5">
                            Confirmed
                        </span>
                    @endif
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="space-y-2">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b-2 border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-2 px-2">#</th>
                            <th class="py-2 px-3">Item Description</th>
                            <th class="py-2 px-3 text-right">Qty</th>
                            <th class="py-2 px-3 text-right">Price</th>
                            <th class="py-2 px-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @foreach($sale->items as $idx => $item)
                            <tr>
                                <td class="py-3 px-2 font-mono text-slate-400 font-bold">{{ $idx + 1 }}</td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900">{{ $item->product?->name ?? 'Product' }}</div>
                                    @if($item->product?->sku)
                                        <div class="font-mono text-[10px] text-slate-400">{{ $item->product->sku }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-slate-700 whitespace-nowrap">
                                    {{ (float)$item->entered_qty }} {{ $item->entered_unit }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-slate-700 whitespace-nowrap">
                                    ₹{{ number_format((float)$item->unit_price, 2) }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-black text-slate-900 whitespace-nowrap">
                                    ₹{{ number_format((float)$item->line_total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals & Payment Summary -->
            <div class="border-t-2 border-slate-200 pt-4 space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                    <span>Subtotal:</span>
                    <span class="font-mono font-bold text-slate-900">₹{{ number_format((float)$sale->subtotal, 2) }}</span>
                </div>

                @if((float)$sale->discount > 0)
                    <div class="flex items-center justify-between text-xs font-semibold text-rose-600">
                        <span>Discount:</span>
                        <span class="font-mono font-bold">-₹{{ number_format((float)$sale->discount, 2) }}</span>
                    </div>
                @endif

                <div class="pt-2 border-t border-slate-200 flex items-center justify-between text-base sm:text-lg font-black text-slate-950">
                    <span>TOTAL AMOUNT:</span>
                    <span class="font-mono text-emerald-800 text-xl">₹{{ number_format((float)$sale->total_amount, 2) }}</span>
                </div>
            </div>

            <!-- Payment & Money Holder Details Box -->
            @php($payment = $sale->primaryPayment())
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">Payment Method:</span>
                    <span class="font-black text-slate-900 uppercase font-mono">{{ $payment?->payment_method ?? 'Cash' }}</span>
                </div>

                @if($sale->isCashSale())
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">Cash Money Location:</span>
                        @if($sale->isUserHeldCash())
                            <span class="font-black text-amber-800 bg-amber-100 px-2 py-0.5 rounded border border-amber-200">
                                Held by User ({{ $payment?->moneyHolderUser?->name ?? 'User' }})
                            </span>
                        @else
                            <span class="font-black text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded border border-emerald-200">
                                Received by Company
                            </span>
                        @endif
                    </div>
                @endif

                @if($sale->notes)
                    <div class="pt-2 border-t border-slate-200/60 text-slate-600 font-medium">
                        <span class="font-bold text-slate-500">Note:</span> {{ $sale->notes }}
                    </div>
                @endif
            </div>

            <!-- Print Footer -->
            <div class="hidden print:block pt-6 border-t border-slate-300 text-center text-[10px] text-slate-400 space-y-1">
                <div>Thank you for your business!</div>
                <div>Generated: {{ now()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</div>
            </div>

        </div>

    </div>
</x-layouts.app>
