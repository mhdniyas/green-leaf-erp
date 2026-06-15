<x-layouts.app title="Purchaser Daily Carts">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        {{-- Page header --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 4</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Daily Carts</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Track active carts, open bills, and complete payment updates from the daily cart flow.</p>
                </div>
                <form action="{{ route('purchaser.vendors') }}" method="GET">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                </form>
            </div>
        </section>

        @if ($mergeSuggestions->isNotEmpty())
            <section class="space-y-2">
                @foreach ($mergeSuggestions as $suggestion)
                    <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900 shadow-sm lg:flex-row lg:items-center lg:justify-between lg:px-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Merge Suggestion</p>
                            <p class="mt-1 font-bold">
                                {{ $suggestion['count'] }} draft carts are open for {{ $suggestion['label'] }}. Merge them as one cart?
                            </p>
                        </div>
                        <form action="{{ route('purchaser.carts.merge-drafts', $suggestion['target_cart']) }}" method="POST" class="shrink-0">
                            @csrf
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-amber-500 px-4 text-xs font-black text-white hover:bg-amber-400">
                                Merge as One
                            </button>
                        </form>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Tab Switcher --}}
        <div class="flex rounded-xl bg-slate-100 p-1 shadow-sm">
            <button type="button" id="tab-orders-btn" onclick="switchVendorTab('orders')" class="flex-1 rounded-lg bg-white py-2 text-center text-[10px] sm:text-xs font-black text-slate-950 shadow-sm transition-all duration-150">
                Active ({{ $orders->count() }}) • ₹{{ number_format($orders->sum(fn($c) => $c->items->sum('line_total') - $c->discount_amount), 2) }}
            </button>
            <button type="button" id="tab-delivered-btn" onclick="switchVendorTab('delivered')" class="flex-1 rounded-lg py-2 text-center text-[10px] sm:text-xs font-bold text-slate-500 hover:text-slate-700 transition-all duration-150">
                Received ({{ $delivered->count() }}) • ₹{{ number_format($delivered->sum(fn($c) => $c->items->sum('line_total') - $c->discount_amount), 2) }}
            </button>
        </div>

        {{-- Section Containers --}}
        <div class="space-y-3">
            
            {{-- 1. Active Carts --}}
            <div id="section-orders" class="space-y-3">
                @forelse ($orders as $cart)
                    <article class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        
                        {{-- Cart Header --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }}</p>
                                <h3 class="mt-0.5 flex items-center gap-1.5 text-xs font-black text-slate-950">
                                    <span class="truncate max-w-[120px]">{{ $cart->supplier?->name ?: 'Draft Cart' }}</span>
                                    <button type="button" onclick="openChangeVendorModal({{ $cart->id }}, {{ $cart->supplier_id ?: 'null' }})" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors" title="Assign Supplier">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    @if ($cart->supplier)
                                        <button
                                            type="button"
                                            onclick="openCartShareModal('{{ route('purchaser.carts.send', $cart) }}', {{ $cart->supplier_id }}, @js($cart->supplier->mobile_number), @js($cart->cart_number))"
                                            class="text-emerald-600 hover:text-emerald-500 flex items-center focus:outline-none transition-colors"
                                            title="Share Cart"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12.012 2c-5.506 0-9.969 4.471-9.969 9.986 0 1.764.459 3.419 1.258 4.873L2 22l5.304-1.393c1.42.776 3.033 1.213 4.708 1.213 5.506 0 9.969-4.473 9.969-9.987S17.518 2 12.012 2zm6.275 14.286c-.256.721-1.5 1.302-2.073 1.393-.509.079-1.18.149-3.414-.775-2.856-1.181-4.701-4.089-4.843-4.28-.143-.19-1.146-1.524-1.146-2.909 0-1.385.726-2.062.981-2.348.256-.286.561-.357.747-.357.187 0 .375.002.537.009.169.007.394-.063.616.48.226.552.773 1.895.84 2.03.067.137.112.296.022.477-.09.18-.135.295-.27.456-.135.161-.286.357-.406.48-.135.137-.278.286-.12.562.158.277.702 1.159 1.503 1.875.803.717 1.48.94 1.691 1.045.21.106.333.09.456-.053.123-.143.528-.616.67-.828.141-.21.282-.176.476-.105.195.07.1.24 1.233.805 1.133.565 1.2.94 1.2.94 0 .423-.88 1.163-1.136 1.884z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </h3>
                            </div>
                            <div class="flex items-center gap-1">
                                @if ($cart->status === 'draft')
                                    @if (($mergeableDraftCounts[$cart->id] ?? 0) > 0)
                                        <form action="{{ route('purchaser.carts.merge-drafts', $cart) }}" method="POST" class="inline-flex">
                                            @csrf
                                            <button type="submit" class="rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[9px] font-black text-amber-700 transition-colors shadow-xs cursor-pointer">
                                                Merge {{ $mergeableDraftCounts[$cart->id] + 1 }} Carts
                                            </button>
                                        </form>
                                    @endif
                                    <button type="button" onclick="openCreateVendorModal({{ $cart->id }})" class="rounded-md bg-teal-50 hover:bg-teal-100 border border-teal-100 px-2 py-0.5 text-[9px] font-black text-teal-700 transition-colors shadow-xs cursor-pointer mr-1">
                                        + New Supplier
                                    </button>
                                @endif
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] text-slate-600">
                                    {{ $cart->workflow_label }}
                                </span>
                            </div>
                        </div>

                        {{-- Cart Items --}}
                        <div class="mt-2 space-y-1.5">
                            @forelse ($cart->items as $item)
                                @php
                                    $approvedQty = \App\Models\ShopOrderItem::query()
                                        ->where('product_id', $item->product_id)
                                        ->whereHas('order', function ($q) use ($cart) {
                                            $q->whereDate('business_date', $cart->business_date)->where('state', 'approved');
                                        })
                                        ->sum('approved_qty');
                                @endphp
                                <div class="flex flex-col gap-2 rounded-lg bg-slate-50 p-2.5 shadow-xs">
                                    {{-- Row 1: Title, Info, and Total --}}
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <h4 class="font-black text-slate-900 text-[11px] truncate">{{ $item->product->name }}</h4>
                                                @if ($item->is_extra_purchase)
                                                    @php
                                                        $extraQty = max(0, (float) $item->quantity - (float) $approvedQty);
                                                        $formattedExtra = $item->product->unit === 'kg' ? number_format($extraQty, 1) : number_format($extraQty, 0);
                                                    @endphp
                                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.12em] text-amber-700">
                                                        Extra (+{{ $formattedExtra }} {{ $item->product->unit }})
                                                    </span>
                                                @endif
                                            </div>
                                            

                                        </div>

                                        {{-- Total Price Badge --}}
                                        <div class="shrink-0 flex items-center justify-end">
                                            <span class="text-xs font-black text-slate-900">
                                                ₹<span id="total-{{ $item->id }}">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Row 2: Controls Container (Form + Delete) --}}
                                    <div class="flex flex-nowrap items-end justify-between gap-1.5 w-full mt-1">
                                        {{-- Inline Update Form --}}
                                        <form action="{{ route('purchaser.cart-items.update', $item) }}" method="POST" class="flex flex-nowrap items-end gap-1.5 min-w-0 flex-1">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="return_to" value="vendors">
                                            
                                            {{-- Stepper --}}
                                            <div class="flex flex-col gap-0.5 min-w-0">
                                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider">Qty</span>
                                                <div class="flex items-center border border-slate-200 bg-white rounded-lg overflow-hidden h-8 shrink-0 shadow-xs">
                                                    <button type="button" onclick="this.nextElementSibling.stepDown(); updateCartItemTotal({{ $item->id }})" class="w-7 h-full flex items-center justify-center text-xs font-bold text-slate-500 bg-slate-50 hover:bg-slate-100 transition-colors cursor-pointer">-</button>
                                                    <input type="number" step="{{ $item->product->unit === 'kg' ? 'any' : '1' }}" min="{{ $item->product->unit === 'kg' ? '0.01' : '1' }}" name="quantity" id="quantity-{{ $item->id }}" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" oninput="updateCartItemTotal({{ $item->id }})" class="w-10 h-full text-center text-[10px] font-black bg-transparent focus:outline-none text-slate-900">
                                                    <button type="button" onclick="this.previousElementSibling.stepUp(); updateCartItemTotal({{ $item->id }})" class="w-7 h-full flex items-center justify-center text-xs font-bold text-slate-500 bg-slate-50 hover:bg-slate-100 transition-colors cursor-pointer">+</button>
                                                </div>
                                            </div>

                                            <span class="text-[10px] text-slate-400 font-bold mb-2 shrink-0">@</span>

                                            {{-- Price Input --}}
                                            <div class="flex flex-col gap-0.5 min-w-0">
                                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider">Per {{ $item->product->unit }}</span>
                                                <input type="number" step="0.01" min="0" name="unit_price" id="price-{{ $item->id }}" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" oninput="updateCartItemTotal({{ $item->id }})" placeholder="Price" class="h-8 w-14 text-center text-[10px] font-bold border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-teal-500 shrink-0 text-slate-900 shadow-xs">
                                            </div>

                                            {{-- Save Button --}}
                                            <button type="submit" class="h-8 rounded-lg bg-slate-950 hover:bg-slate-900 px-3.5 text-[10px] font-black text-white transition-colors cursor-pointer shrink-0">Save</button>
                                        </form>

                                        {{-- Delete Form --}}
                                        @if ($cart->status === 'draft')
                                            <form action="{{ route('purchaser.cart-items.destroy', $item) }}" method="POST" class="shrink-0">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="return_to" value="vendors">
                                                <button type="submit" class="h-8 w-8 flex items-center justify-center rounded-lg bg-[#fff1f2] text-[#e11d48] hover:bg-[#ffe4e6] border border-[#fecdd3] cursor-pointer" title="Delete">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-[10px] font-bold text-slate-400 py-2 bg-slate-50 rounded-lg border border-dashed border-slate-200">No products in this cart.</p>
                            @endforelse
                        </div>



                        {{-- Bottom Actions --}}
                        <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 w-full">
                            <span class="text-[10px] font-bold text-slate-500 shrink-0">Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                            @if ($cart->status === 'draft')
                                <a href="{{ route('purchaser.bill', ['cart' => $cart, 'date' => $date]) }}" class="h-8 rounded-lg bg-teal-600 px-3 text-[10px] font-black text-white flex items-center justify-center hover:bg-teal-500 shadow-sm">
                                    Process Bill
                                </a>
                            @endif
                            @if ($cart->status === 'submitted')
                                <div class="flex flex-wrap gap-2">
                                    @if ($cart->purchaseInvoice)
                                        @php
                                            $paymentModalData = [
                                                'number' => $cart->purchaseInvoice->invoice_number,
                                                'supplier' => $cart->supplier?->name,
                                                'amount' => round((float) $cart->purchaseInvoice->amount, 2),
                                                'paidAmount' => round((float) $cart->purchaseInvoice->paid_amount, 2),
                                                'paymentMethod' => $cart->purchaseInvoice->payment_method ?: 'Cash',
                                                'paymentNote' => $cart->purchaseInvoice->payment_note,
                                                'paymentDetails' => $cart->purchaseInvoice->payment_details,
                                                'creditApproved' => (bool) $cart->supplier?->credit_approved,
                                            ];
                                        @endphp
                                        <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="inline-flex h-8 items-center rounded-lg border border-teal-200 bg-teal-50 px-3 text-[10px] font-black text-teal-700 shadow-sm hover:bg-teal-100">
                                            View Bill
                                        </a>
                                        <button type="button" onclick='openCartPaymentModal(@json($paymentModalData), "{{ route('purchaser.invoices.payment', $cart->purchaseInvoice) }}")' class="inline-flex h-8 items-center rounded-lg border border-slate-200 bg-slate-950 px-3 text-[10px] font-black text-white shadow-sm hover:bg-slate-800">
                                            Update Payment
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="text-center text-xs font-bold text-slate-400 py-6 bg-white rounded-xl border border-slate-200">No active carts for this date.</p>
                @endforelse
            </div>

            {{-- 2. Delivered --}}
            <div id="section-delivered" class="hidden space-y-3">
                @forelse ($delivered as $cart)
                    <article class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        
                        {{-- Cart Header --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }}</p>
                                <h3 class="mt-0.5 flex items-center gap-1.5 text-xs font-black text-slate-950">
                                    <span class="truncate max-w-[120px]">{{ $cart->supplier?->name ?: 'Draft Cart' }}</span>
                                    <button type="button" onclick="openChangeVendorModal({{ $cart->id }}, {{ $cart->supplier_id ?: 'null' }})" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors" title="Assign Supplier">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    @if ($cart->supplier)
                                        <button
                                            type="button"
                                            onclick="openCartShareModal('{{ route('purchaser.carts.send', $cart) }}', {{ $cart->supplier_id }}, @js($cart->supplier->mobile_number), @js($cart->cart_number))"
                                            class="text-emerald-600 hover:text-emerald-500 flex items-center focus:outline-none transition-colors"
                                            title="Share Cart"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12.012 2c-5.506 0-9.969 4.471-9.969 9.986 0 1.764.459 3.419 1.258 4.873L2 22l5.304-1.393c1.42.776 3.033 1.213 4.708 1.213 5.506 0 9.969-4.473 9.969-9.987S17.518 2 12.012 2zm6.275 14.286c-.256.721-1.5 1.302-2.073 1.393-.509.079-1.18.149-3.414-.775-2.856-1.181-4.701-4.089-4.843-4.28-.143-.19-1.146-1.524-1.146-2.909 0-1.385.726-2.062.981-2.348.256-.286.561-.357.747-.357.187 0 .375.002.537.009.169.007.394-.063.616.48.226.552.773 1.895.84 2.03.067.137.112.296.022.477-.09.18-.135.295-.27.456-.135.161-.286.357-.406.48-.135.137-.278.286-.12.562.158.277.702 1.159 1.503 1.875.803.717 1.48.94 1.691 1.045.21.106.333.09.456-.053.123-.143.528-.616.67-.828.141-.21.282-.176.476-.105.195.07.1.24 1.233.805 1.133.565 1.2.94 1.2.94 0 .423-.88 1.163-1.136 1.884z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </h3>
                            </div>
                            <div>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] text-emerald-700">
                                    Goods Received ✓
                                </span>
                            </div>
                        </div>

                        {{-- Cart Items --}}
                        <details class="mt-2 rounded-lg bg-slate-50 border border-slate-100 p-1.5">
                            <summary class="cursor-pointer text-[10px] font-black text-slate-700 py-1 px-1.5 select-none">View Cart Items</summary>
                            <div class="mt-1.5 space-y-1 border-t border-slate-200/60 pt-1.5">
                                @foreach ($cart->items as $item)
                                    <div class="flex min-w-0 items-center justify-between gap-2 px-1.5 py-1 text-[10px] font-bold text-slate-600">
                                        <span class="truncate">{{ $item->product->name }}</span>
                                        <span>{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} • ₹{{ number_format((float) $item->line_total, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </details>

                        {{-- Bottom Actions --}}
                        <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 w-full">
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500">
                                <span>Bill: {{ $cart->bill_number ?: 'Pending' }}</span>
                                <span>•</span>
                                <span>₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($cart->purchaseInvoice)
                                    @php
                                        $paymentModalData = [
                                            'number' => $cart->purchaseInvoice->invoice_number,
                                            'supplier' => $cart->supplier?->name,
                                            'amount' => round((float) $cart->purchaseInvoice->amount, 2),
                                            'paidAmount' => round((float) $cart->purchaseInvoice->paid_amount, 2),
                                            'paymentMethod' => $cart->purchaseInvoice->payment_method ?: 'Cash',
                                            'paymentNote' => $cart->purchaseInvoice->payment_note,
                                            'paymentDetails' => $cart->purchaseInvoice->payment_details,
                                            'creditApproved' => (bool) $cart->supplier?->credit_approved,
                                        ];
                                    @endphp
                                    <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="inline-flex h-8 items-center rounded-lg border border-teal-200 bg-teal-50 px-3 text-[10px] font-black text-teal-700 shadow-sm hover:bg-teal-100">
                                        View Bill
                                    </a>
                                    <button type="button" onclick='openCartPaymentModal(@json($paymentModalData), "{{ route('purchaser.invoices.payment', $cart->purchaseInvoice) }}")' class="inline-flex h-8 items-center rounded-lg border border-slate-200 bg-slate-950 px-3 text-[10px] font-black text-white shadow-sm hover:bg-slate-800">
                                        Update Payment
                                    </button>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="text-center text-xs font-bold text-slate-400 py-6 bg-white rounded-xl border border-slate-200">No received carts for this date.</p>
                @endforelse
            </div>

        </div>
    </div>

    {{-- Hidden form for changing supplier --}}
    <form id="change-vendor-form" method="POST" action="">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="vendors">
        <input type="hidden" id="change-vendor-supplier-id" name="supplier_id" value="">
    </form>

    <form id="cart-share-form" method="POST" action="">
        @csrf
        <input type="hidden" name="return_to" value="vendors">
        <input type="hidden" id="cart-share-supplier-id" name="supplier_id" value="">
        <input type="hidden" id="cart-share-mode" name="share_mode" value="saved">
        <input type="hidden" id="cart-share-mobile-hidden" name="vendor_mobile_number" value="">
        <input type="hidden" id="cart-share-show-price" name="show_price" value="0">
    </form>

    {{-- Change Vendor Modal --}}
    <div id="change-vendor-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" onclick="if(event.target === this) closeChangeVendorModal()">
        <div class="relative w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-3 shadow-xl flex flex-col max-h-[80vh]">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 mb-2">
                <h3 class="text-xs font-black text-slate-950">Assign Supplier</h3>
                <button type="button" onclick="closeChangeVendorModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            {{-- Search Bar --}}
            <div class="relative mb-2">
                <input type="text" id="vendor-search-input" oninput="filterVendors()" placeholder="Search suppliers..." class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none transition-all">
            </div>

            {{-- Vendor List --}}
            <div class="flex-1 overflow-y-auto min-h-0 space-y-1 max-h-56 pr-0.5 scrollbar-thin">
                @foreach ($suppliers as $supplier)
                    @php
                        $lastFour = '';
                        if ($supplier->mobile_number) {
                            $clean = preg_replace('/\D+/', '', $supplier->mobile_number);
                            if (strlen($clean) >= 4) {
                                $lastFour = ' (...'.substr($clean, -4).')';
                            }
                        }
                    @endphp
                    <button type="button" onclick="selectVendorForCart({{ $supplier->id }})" data-name="{{ $supplier->name }}" class="vendor-list-item w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-[10px] font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors border border-transparent hover:border-slate-100 focus:outline-none">
                        <span class="truncate mr-2">{{ $supplier->name }}</span>
                        <span class="text-[9px] font-semibold text-slate-400 shrink-0">{{ $lastFour }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Create Vendor Modal --}}
    <div id="create-vendor-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" onclick="if(event.target === this) closeCreateVendorModal()">
        <div class="relative w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-4 shadow-xl flex flex-col">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                <h3 class="text-xs font-black text-slate-950">Create New Supplier</h3>
                <button type="button" onclick="closeCreateVendorModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            {{-- Form --}}
            <form id="create-vendor-form" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_to" value="vendors">
                <input type="hidden" name="supplier_id" value="">

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500 mb-1">Supplier Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="vendor_name" required placeholder="Supplier Name" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500 mb-1">Phone Number <span class="text-rose-500">*</span></label>
                    <input type="tel" name="vendor_mobile_number" required placeholder="Phone number" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500 mb-1">Location (Optional)</label>
                    <input type="text" name="vendor_location" placeholder="Location" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                <input type="hidden" name="vendor_type" value="Vendor">
                <input type="hidden" name="payment_terms" value="Cash">
                <input type="hidden" name="preferred_payment_method" value="Cash">

                <button type="submit" class="w-full flex h-9 items-center justify-center rounded-lg bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-sm transition-colors duration-150 mt-1 cursor-pointer">
                    Create & Assign
                </button>
            </form>
        </div>
    </div>

    <div id="cart-share-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeCartShareModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Share Cart</h3>
                    <p id="cart-share-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeCartShareModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-2">
                    <span class="text-xs font-bold text-slate-700">Show price in message</span>
                    <button type="button" role="switch" aria-checked="false" id="toggle-show-price" 
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-slate-200 transition-colors duration-200 ease-in-out focus:outline-none"
                            onclick="togglePriceCheckbox()">
                        <span aria-hidden="true" id="toggle-switch-handle" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out translate-x-0"></span>
                    </button>
                </div>

                <button type="button" id="cart-share-saved-button" onclick="submitCartShare('saved')" class="flex h-10 w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-black text-emerald-700 hover:bg-emerald-100">
                    Share to Saved Number
                </button>
                <button type="button" onclick="submitCartShare('any')" class="flex h-10 w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-xs font-black text-slate-700 hover:bg-slate-100">
                    Share to Any WhatsApp
                </button>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Custom India Mobile</label>
                    <div class="mt-2 flex gap-2">
                        <div class="flex h-10 items-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-600">+91</div>
                        <input id="cart-share-mobile-input" type="tel" inputmode="numeric" maxlength="10" placeholder="10 digit number" class="h-10 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:outline-none" oninput="this.value = this.value.replace(/\\D/g, '').slice(0, 10)">
                    </div>
                    <p id="cart-share-mobile-error" class="mt-2 hidden text-[10px] font-bold text-rose-600">Enter exactly 10 digits.</p>
                    <button type="button" onclick="submitCartShare('custom')" class="mt-3 flex h-10 w-full items-center justify-center rounded-xl bg-slate-950 text-xs font-black text-white hover:bg-slate-800">
                        Share to This Number
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="cart-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeCartPaymentModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Payment Update</h3>
                    <p id="cart-payment-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeCartPaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="cart-payment-form" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Total Bill</span>
                        <span id="cart-payment-total" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Remaining</span>
                        <span id="cart-payment-balance" class="text-amber-700"></span>
                    </div>
                    <p id="cart-payment-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                    <select id="cart_payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="GPay">GPay</option>
                        <option value="Credit">Credit</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Paid Amount</label>
                    <input id="cart_paid_amount" type="number" step="0.01" min="0" name="paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                    <input id="cart_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Details</label>
                    <textarea id="cart_payment_details" name="payment_details" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500">Save Payment Update</button>
            </form>
        </div>
    </div>

    {{-- Script for tab switching and vendor updating --}}
    <script>
        function switchVendorTab(tab) {
            const ordersBtn = document.getElementById('tab-orders-btn');
            const deliveredBtn = document.getElementById('tab-delivered-btn');
            const ordersSec = document.getElementById('section-orders');
            const deliveredSec = document.getElementById('section-delivered');

            if (tab === 'orders') {
                ordersBtn.className = "flex-1 rounded-lg bg-white py-2 text-center text-[10px] sm:text-xs font-black text-slate-950 shadow-sm transition-all duration-150";
                deliveredBtn.className = "flex-1 rounded-lg py-2 text-center text-[10px] sm:text-xs font-bold text-slate-500 hover:text-slate-700 transition-all duration-150";
                ordersSec.classList.remove('hidden');
                deliveredSec.classList.add('hidden');
            } else {
                deliveredBtn.className = "flex-1 rounded-lg bg-white py-2 text-center text-[10px] sm:text-xs font-black text-slate-950 shadow-sm transition-all duration-150";
                ordersBtn.className = "flex-1 rounded-lg py-2 text-center text-[10px] sm:text-xs font-bold text-slate-500 hover:text-slate-700 transition-all duration-150";
                deliveredSec.classList.remove('hidden');
                ordersSec.classList.add('hidden');
            }
        }

        let currentCartId = null;

        function openChangeVendorModal(cartId, currentSupplierId) {
            currentCartId = cartId;
            const form = document.getElementById('change-vendor-form');
            form.action = `/purchaser/carts/${cartId}/supplier`;
            
            document.getElementById('change-vendor-modal').classList.remove('hidden');
            const searchInput = document.getElementById('vendor-search-input');
            searchInput.value = '';
            filterVendors();
            setTimeout(() => searchInput.focus(), 50);
        }

        function closeChangeVendorModal() {
            document.getElementById('change-vendor-modal').classList.add('hidden');
        }

        function filterVendors() {
            const query = document.getElementById('vendor-search-input').value.toLowerCase();
            const items = document.querySelectorAll('.vendor-list-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name').toLowerCase();
                if (name.includes(query)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }

        function selectVendorForCart(supplierId) {
            document.getElementById('change-vendor-supplier-id').value = supplierId;
            document.getElementById('change-vendor-form').submit();
        }

        let cartShareState = {
            actionUrl: '',
            supplierId: '',
            supplierMobile: '',
            title: '',
        };

        let cartPaymentAmount = 0;
        let cartPaymentCreditApproved = false;

        function setPriceToggle(isOn) {
            const btn = document.getElementById('toggle-show-price');
            const handle = document.getElementById('toggle-switch-handle');
            const input = document.getElementById('cart-share-show-price');
            
            if (isOn) {
                btn.classList.remove('bg-slate-200');
                btn.classList.add('bg-teal-600');
                btn.setAttribute('aria-checked', 'true');
                handle.classList.remove('translate-x-0');
                handle.classList.add('translate-x-4');
                input.value = '1';
            } else {
                btn.classList.remove('bg-teal-600');
                btn.classList.add('bg-slate-200');
                btn.setAttribute('aria-checked', 'false');
                handle.classList.remove('translate-x-4');
                handle.classList.add('translate-x-0');
                input.value = '0';
            }
        }

        function togglePriceCheckbox() {
            const input = document.getElementById('cart-share-show-price');
            const isOn = input.value === '1';
            setPriceToggle(!isOn);
        }

        function openCartShareModal(actionUrl, supplierId, supplierMobile, title) {
            cartShareState = {
                actionUrl,
                supplierId,
                supplierMobile: supplierMobile || '',
                title: title || 'Cart',
            };

            document.getElementById('cart-share-title').textContent = cartShareState.title;
            document.getElementById('cart-share-mobile-input').value = '';
            document.getElementById('cart-share-mobile-error').classList.add('hidden');
            document.getElementById('cart-share-saved-button').disabled = !cartShareState.supplierMobile;
            document.getElementById('cart-share-saved-button').classList.toggle('opacity-50', !cartShareState.supplierMobile);
            document.getElementById('cart-share-modal').classList.remove('hidden');
            document.getElementById('cart-share-modal').classList.add('flex');
            
            // Default show price toggle to OFF
            setPriceToggle(false);
        }

        function closeCartShareModal() {
            document.getElementById('cart-share-modal').classList.add('hidden');
            document.getElementById('cart-share-modal').classList.remove('flex');
        }

        function submitCartShare(mode) {
            const form = document.getElementById('cart-share-form');
            const mobileInput = document.getElementById('cart-share-mobile-input');
            const mobileHidden = document.getElementById('cart-share-mobile-hidden');
            const mobileError = document.getElementById('cart-share-mobile-error');

            form.action = cartShareState.actionUrl;
            document.getElementById('cart-share-supplier-id').value = cartShareState.supplierId || '';
            document.getElementById('cart-share-mode').value = mode;
            mobileHidden.value = '';
            mobileError.classList.add('hidden');

            if (mode === 'saved') {
                if (!cartShareState.supplierMobile) {
                    return;
                }
                mobileHidden.value = cartShareState.supplierMobile;
            }

            if (mode === 'custom') {
                const value = (mobileInput.value || '').replace(/\D/g, '');
                if (value.length !== 10) {
                    mobileError.classList.remove('hidden');
                    return;
                }
                mobileHidden.value = value;
            }

            closeCartShareModal();
            form.submit();
        }

        function openCartPaymentModal(invoice, actionUrl) {
            cartPaymentAmount = Number(invoice.amount || 0);
            cartPaymentCreditApproved = Boolean(invoice.creditApproved);

            document.getElementById('cart-payment-form').action = actionUrl;
            document.getElementById('cart-payment-title').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
            document.getElementById('cart-payment-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
            document.getElementById('cart_payment_method').value = invoice.paymentMethod || 'Cash';
            document.getElementById('cart_paid_amount').value = Number(invoice.paidAmount || 0).toFixed(2);
            document.getElementById('cart_payment_note').value = invoice.paymentNote || '';
            document.getElementById('cart_payment_details').value = invoice.paymentDetails || '';

            updateCartPaymentStatus();
            document.getElementById('cart-payment-modal').classList.remove('hidden');
            document.getElementById('cart-payment-modal').classList.add('flex');
        }

        function closeCartPaymentModal() {
            document.getElementById('cart-payment-modal').classList.add('hidden');
            document.getElementById('cart-payment-modal').classList.remove('flex');
        }

        function updateCartPaymentStatus() {
            const method = document.getElementById('cart_payment_method').value;
            const paidAmount = Number(document.getElementById('cart_paid_amount').value || 0);
            const balance = Math.max(0, cartPaymentAmount - paidAmount);
            const balanceNode = document.getElementById('cart-payment-balance');
            const warningNode = document.getElementById('cart-payment-warning');

            balanceNode.textContent = `₹${balance.toFixed(2)}`;
            balanceNode.className = balance > 0 ? 'text-amber-700' : 'text-emerald-700';

            if (method === 'Credit') {
                warningNode.textContent = cartPaymentCreditApproved
                    ? 'Credit selected. Payment will stay pending until the bill is fully cleared.'
                    : 'Credit selected but supplier credit is not approved yet.';
                return;
            }

            warningNode.textContent = balance > 0
                ? 'Payment is not done fully. Remaining balance will stay pending.'
                : 'Full payment entered. This bill will be marked as completed.';
        }

        document.getElementById('cart_payment_method')?.addEventListener('change', updateCartPaymentStatus);
        document.getElementById('cart_paid_amount')?.addEventListener('input', updateCartPaymentStatus);

        function updateCartItemTotal(itemId) {
            setTimeout(() => {
                const qtyInput = document.getElementById('quantity-' + itemId);
                const priceInput = document.getElementById('price-' + itemId);
                const totalSpan = document.getElementById('total-' + itemId);
                if (qtyInput && priceInput && totalSpan) {
                    const qty = parseFloat(qtyInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;
                    totalSpan.textContent = (qty * price).toFixed(2);
                }
            }, 10);
        }

        function openCreateVendorModal(cartId) {
            const form = document.getElementById('create-vendor-form');
            form.action = `/purchaser/carts/${cartId}/supplier`;
            document.getElementById('create-vendor-modal').classList.remove('hidden');
        }

        function closeCreateVendorModal() {
            document.getElementById('create-vendor-modal').classList.add('hidden');
        }
    </script>
</x-layouts.app>
