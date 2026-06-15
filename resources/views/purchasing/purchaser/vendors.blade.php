<x-layouts.app title="Purchaser Daily Vendors">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        {{-- Page header --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 4</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Daily Vendor Orders</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Track and update active vendor orders, share lists via WhatsApp, and check them off when delivered.</p>
                </div>
                <form action="{{ route('purchaser.vendors') }}" method="GET">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                </form>
            </div>
        </section>

        {{-- Tab Switcher --}}
        <div class="flex rounded-xl bg-slate-100 p-1 shadow-sm">
            <button type="button" id="tab-orders-btn" onclick="switchVendorTab('orders')" class="flex-1 rounded-lg bg-white py-2 text-center text-[10px] sm:text-xs font-black text-slate-950 shadow-sm transition-all duration-150">
                Orders ({{ $orders->count() }}) • ₹{{ number_format($orders->sum(fn($c) => $c->items->sum('line_total') - $c->discount_amount), 2) }}
            </button>
            <button type="button" id="tab-delivered-btn" onclick="switchVendorTab('delivered')" class="flex-1 rounded-lg py-2 text-center text-[10px] sm:text-xs font-bold text-slate-500 hover:text-slate-700 transition-all duration-150">
                Delivered ({{ $delivered->count() }}) • ₹{{ number_format($delivered->sum(fn($c) => $c->items->sum('line_total') - $c->discount_amount), 2) }}
            </button>
        </div>

        {{-- Section Containers --}}
        <div class="space-y-3">
            
            {{-- 1. Active Orders --}}
            <div id="section-orders" class="space-y-3">
                @forelse ($orders as $cart)
                    <article class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        
                        {{-- Cart Header --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }}</p>
                                <h3 class="mt-0.5 flex items-center gap-1.5 text-xs font-black text-slate-950">
                                    <span class="truncate max-w-[120px]">{{ $cart->supplier?->name ?: 'Vendor Pending' }}</span>
                                    <button type="button" onclick="openChangeVendorModal({{ $cart->id }}, {{ $cart->supplier_id ?: 'null' }})" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors" title="Change Vendor">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    @if ($cart->supplier)
                                        <div class="flex items-center gap-1">
                                            <form action="{{ route('purchaser.carts.send', $cart) }}" method="POST" class="inline-flex">
                                                @csrf
                                                <input type="hidden" name="return_to" value="vendors">
                                                <input type="hidden" name="supplier_id" value="{{ $cart->supplier_id }}">
                                                <input type="hidden" name="vendor_mobile_number" value="{{ $cart->supplier->mobile_number }}">
                                                <button type="submit" class="text-emerald-600 hover:text-emerald-500 flex items-center focus:outline-none transition-colors" title="Share via WhatsApp (Saved Number)">
                                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M12.012 2c-5.506 0-9.969 4.471-9.969 9.986 0 1.764.459 3.419 1.258 4.873L2 22l5.304-1.393c1.42.776 3.033 1.213 4.708 1.213 5.506 0 9.969-4.473 9.969-9.987S17.518 2 12.012 2zm6.275 14.286c-.256.721-1.5 1.302-2.073 1.393-.509.079-1.18.149-3.414-.775-2.856-1.181-4.701-4.089-4.843-4.28-.143-.19-1.146-1.524-1.146-2.909 0-1.385.726-2.062.981-2.348.256-.286.561-.357.747-.357.187 0 .375.002.537.009.169.007.394-.063.616.48.226.552.773 1.895.84 2.03.067.137.112.296.022.477-.09.18-.135.295-.27.456-.135.161-.286.357-.406.48-.135.137-.278.286-.12.562.158.277.702 1.159 1.503 1.875.803.717 1.48.94 1.691 1.045.21.106.333.09.456-.053.123-.143.528-.616.67-.828.141-.21.282-.176.476-.105.195.07.1.24 1.233.805 1.133.565 1.2.94 1.2.94 0 .423-.88 1.163-1.136 1.884z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <button type="button" onclick="sendWhatsAppToCustomNumber('{{ route('purchaser.carts.send', $cart) }}', {{ $cart->supplier_id }})" class="text-sky-600 hover:text-sky-500 flex items-center focus:outline-none transition-colors" title="Share via WhatsApp (Any Number)">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 100-2.684 3 3 0 000 2.684zm0 9a3 3 0 100-2.684 3 3 0 000 2.684z" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                </h3>
                            </div>
                            <div class="flex items-center gap-1">
                                @if ($cart->status === 'draft')
                                    <button type="button" onclick="openCreateVendorModal({{ $cart->id }})" class="rounded-md bg-teal-50 hover:bg-teal-100 border border-teal-100 px-2 py-0.5 text-[9px] font-black text-teal-700 transition-colors shadow-xs cursor-pointer mr-1">
                                        + New Vendor
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
                                <div class="flex flex-col gap-2 rounded-lg bg-slate-50 p-2.5 shadow-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <h4 class="font-black text-slate-900 text-[11px] truncate">{{ $item->product->name }}</h4>
                                            @if ($item->is_extra_purchase)
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.12em] text-amber-700">Extra</span>
                                            @endif
                                        </div>
                                        <span class="text-[10px] font-semibold text-slate-500 shrink-0">
                                            {{ $item->product->category?->name ?: 'Other' }} • {{ $item->product->unit }}
                                        </span>
                                    </div>

                                    {{-- Controls Container (Form + Delete) --}}
                                    <div class="flex flex-wrap items-center justify-between gap-2 min-w-0 w-full">
                                        {{-- Inline Update Form --}}
                                        <form action="{{ route('purchaser.cart-items.update', $item) }}" method="POST" class="flex flex-wrap items-center gap-1.5 min-w-0">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="return_to" value="vendors">
                                            
                                            {{-- Stepper --}}
                                            <div class="flex items-center border border-slate-200 bg-white rounded-lg overflow-hidden h-9 shrink-0 shadow-xs">
                                                <button type="button" onclick="this.nextElementSibling.stepDown(); updateCartItemTotal({{ $item->id }})" class="w-8 h-full flex items-center justify-center text-sm font-bold text-slate-500 bg-slate-50 hover:bg-slate-100 transition-colors cursor-pointer">-</button>
                                                <input type="number" step="{{ $item->product->unit === 'kg' ? '0.5' : '1' }}" min="{{ $item->product->unit === 'kg' ? '0.5' : '1' }}" name="quantity" id="quantity-{{ $item->id }}" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" oninput="updateCartItemTotal({{ $item->id }})" class="w-12 h-full text-center text-xs font-black bg-transparent focus:outline-none text-slate-900">
                                                <button type="button" onclick="this.previousElementSibling.stepUp(); updateCartItemTotal({{ $item->id }})" class="w-8 h-full flex items-center justify-center text-sm font-bold text-slate-500 bg-slate-50 hover:bg-slate-100 transition-colors cursor-pointer">+</button>
                                            </div>

                                            <span class="text-xs text-slate-400 font-bold">@</span>

                                            {{-- Price Input --}}
                                            <input type="number" step="0.01" min="0" name="unit_price" id="price-{{ $item->id }}" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" oninput="updateCartItemTotal({{ $item->id }})" placeholder="Price" class="h-9 w-16 text-center text-xs font-bold border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-teal-500 shrink-0 text-slate-900 shadow-xs">

                                            {{-- Total Price Badge --}}
                                            <span class="h-9 flex items-center justify-center text-xs font-bold text-slate-700 bg-[#f1f5f9] border border-slate-200/60 px-2.5 rounded-lg shrink-0">
                                                ₹<span id="total-{{ $item->id }}">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</span>
                                            </span>

                                            {{-- Save Button --}}
                                            <button type="submit" class="h-9 rounded-lg bg-slate-950 hover:bg-slate-900 px-3 text-xs font-black text-white transition-colors cursor-pointer shrink-0">Save</button>
                                        </form>

                                        {{-- Delete Form --}}
                                        @if ($cart->status === 'draft')
                                            <form action="{{ route('purchaser.cart-items.destroy', $item) }}" method="POST" class="shrink-0">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="return_to" value="vendors">
                                                <button type="submit" class="h-9 w-9 flex items-center justify-center rounded-lg bg-[#fff1f2] text-[#e11d48] hover:bg-[#ffe4e6] border border-[#fecdd3] cursor-pointer" title="Delete">
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



                        {{-- Bottom Actions (Total, Process Bill, Mark Delivered) --}}
                        <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-2 w-full">
                            <span class="text-[10px] font-bold text-slate-500 shrink-0">Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                            @if ($cart->status === 'draft')
                                <a href="{{ route('purchaser.bill', ['cart' => $cart, 'date' => $date]) }}" class="h-8 rounded-lg bg-teal-600 px-3 text-[10px] font-black text-white flex items-center justify-center hover:bg-teal-500 shadow-sm">
                                    Process Bill
                                </a>
                            @endif
                            @if ($cart->status === 'submitted')
                                <form action="{{ route('purchaser.carts.status', $cart) }}" method="POST" class="shrink-0">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="flag" value="goods_received">
                                    <button type="submit" class="h-8 rounded-lg bg-emerald-600 px-3 text-[10px] font-black text-white hover:bg-emerald-500 flex items-center gap-1 shadow-sm">
                                        Mark Delivered ✓
                                    </button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="text-center text-xs font-bold text-slate-400 py-6 bg-white rounded-xl border border-slate-200">No active vendor orders for this date.</p>
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
                                    <span class="truncate max-w-[120px]">{{ $cart->supplier?->name ?: 'Vendor Pending' }}</span>
                                    <button type="button" onclick="openChangeVendorModal({{ $cart->id }}, {{ $cart->supplier_id ?: 'null' }})" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors" title="Change Vendor">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    @if ($cart->supplier)
                                        <div class="flex items-center gap-1">
                                            <form action="{{ route('purchaser.carts.send', $cart) }}" method="POST" class="inline-flex">
                                                @csrf
                                                <input type="hidden" name="return_to" value="vendors">
                                                <input type="hidden" name="supplier_id" value="{{ $cart->supplier_id }}">
                                                <input type="hidden" name="vendor_mobile_number" value="{{ $cart->supplier->mobile_number }}">
                                                <button type="submit" class="text-emerald-600 hover:text-emerald-500 flex items-center focus:outline-none transition-colors" title="Share via WhatsApp (Saved Number)">
                                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M12.012 2c-5.506 0-9.969 4.471-9.969 9.986 0 1.764.459 3.419 1.258 4.873L2 22l5.304-1.393c1.42.776 3.033 1.213 4.708 1.213 5.506 0 9.969-4.473 9.969-9.987S17.518 2 12.012 2zm6.275 14.286c-.256.721-1.5 1.302-2.073 1.393-.509.079-1.18.149-3.414-.775-2.856-1.181-4.701-4.089-4.843-4.28-.143-.19-1.146-1.524-1.146-2.909 0-1.385.726-2.062.981-2.348.256-.286.561-.357.747-.357.187 0 .375.002.537.009.169.007.394-.063.616.48.226.552.773 1.895.84 2.03.067.137.112.296.022.477-.09.18-.135.295-.27.456-.135.161-.286.357-.406.48-.135.137-.278.286-.12.562.158.277.702 1.159 1.503 1.875.803.717 1.48.94 1.691 1.045.21.106.333.09.456-.053.123-.143.528-.616.67-.828.141-.21.282-.176.476-.105.195.07.1.24 1.233.805 1.133.565 1.2.94 1.2.94 0 .423-.88 1.163-1.136 1.884z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <button type="button" onclick="sendWhatsAppToCustomNumber('{{ route('purchaser.carts.send', $cart) }}', {{ $cart->supplier_id }})" class="text-sky-600 hover:text-sky-500 flex items-center focus:outline-none transition-colors" title="Share via WhatsApp (Any Number)">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 100-2.684 3 3 0 000 2.684zm0 9a3 3 0 100-2.684 3 3 0 000 2.684z" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                </h3>
                            </div>
                            <div>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] text-emerald-700">
                                    Delivered ✓
                                </span>
                            </div>
                        </div>

                        {{-- Cart Items --}}
                        <details class="mt-2 rounded-lg bg-slate-50 border border-slate-100 p-1.5">
                            <summary class="cursor-pointer text-[10px] font-black text-slate-700 py-1 px-1.5 select-none">View Ordered Products</summary>
                            <div class="mt-1.5 space-y-1 border-t border-slate-200/60 pt-1.5">
                                @foreach ($cart->items as $item)
                                    <div class="flex min-w-0 items-center justify-between gap-2 px-1.5 py-1 text-[10px] font-bold text-slate-600">
                                        <span class="truncate">{{ $item->product->name }}</span>
                                        <span>{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} • ₹{{ number_format((float) $item->line_total, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </details>

                        {{-- Bottom Actions (Undo Delivery, Bill number, Total) --}}
                        <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-2 w-full">
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500">
                                <span>Bill: {{ $cart->bill_number ?: 'Pending' }}</span>
                                <span>•</span>
                                <span>₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                            </div>
                            <form action="{{ route('purchaser.carts.status', $cart) }}" method="POST" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="flag" value="goods_received">
                                <button type="submit" class="h-7 rounded-md border border-slate-200 bg-white px-2.5 text-[10px] font-black text-slate-700 hover:bg-slate-100">
                                    Undo
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-center text-xs font-bold text-slate-400 py-6 bg-white rounded-xl border border-slate-200">No delivered orders for this date.</p>
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

    {{-- Change Vendor Modal --}}
    <div id="change-vendor-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" onclick="if(event.target === this) closeChangeVendorModal()">
        <div class="relative w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-3 shadow-xl flex flex-col max-h-[80vh]">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 mb-2">
                <h3 class="text-xs font-black text-slate-950">Change Vendor</h3>
                <button type="button" onclick="closeChangeVendorModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            {{-- Search Bar --}}
            <div class="relative mb-2">
                <input type="text" id="vendor-search-input" oninput="filterVendors()" placeholder="Search vendors..." class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none transition-all">
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
                <h3 class="text-xs font-black text-slate-950">Create New Vendor</h3>
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
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500 mb-1">Vendor Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="vendor_name" required placeholder="Vendor Name" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
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

        function sendWhatsAppToCustomNumber(actionUrl, supplierId) {
            const number = window.prompt("Enter mobile number to send WhatsApp text to (e.g. 919876543210):");
            if (!number) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            
            // Add CSRF
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }
            
            // Add return_to
            const returnInput = document.createElement('input');
            returnInput.type = 'hidden';
            returnInput.name = 'return_to';
            returnInput.value = 'vendors';
            form.appendChild(returnInput);
            
            // Add supplier_id
            if (supplierId) {
                const supplierInput = document.createElement('input');
                supplierInput.type = 'hidden';
                supplierInput.name = 'supplier_id';
                supplierInput.value = supplierId;
                form.appendChild(supplierInput);
            }
            
            // Add custom mobile number
            const mobileInput = document.createElement('input');
            mobileInput.type = 'hidden';
            mobileInput.name = 'vendor_mobile_number';
            mobileInput.value = number;
            form.appendChild(mobileInput);
            
            document.body.appendChild(form);
            form.submit();
        }

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
