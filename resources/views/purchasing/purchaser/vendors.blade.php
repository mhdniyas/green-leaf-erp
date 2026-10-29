<x-layouts.app title="Purchaser Carts">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-955 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#134e4a_100%)] px-4 py-4 sm:px-5 lg:px-6 lg:py-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-200 sm:text-[11px] sm:tracking-[0.22em]">Stage 4 · Purchaser Operations</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:mt-2 sm:text-2xl">Purchaser Carts & Bills</h1>
                        <p class="mt-1.5 max-w-2xl text-xs font-medium text-slate-200 sm:text-sm">Manage daily draft carts, assign vendors, adjust quantities & prices, and process final bills.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('purchaser.suppliers', ['date' => $date, 'tab' => 'pending']) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-xs font-black text-white backdrop-blur transition hover:bg-white/20">
                            <span>Vendor Hub</span>
                            @if (($deadlineAlert['pending_total_count'] ?? 0) > 0)
                                <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-black text-white shadow-xs">
                                    {{ $deadlineAlert['pending_total_count'] }}
                                </span>
                            @endif
                        </a>
                        <form action="{{ route('purchaser.vendors') }}" method="GET" class="shrink-0">
                            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 rounded-xl border border-white/20 bg-white/10 px-3 text-xs font-bold text-white backdrop-blur focus:bg-slate-900 focus:outline-none">
                        </form>
                    </div>
                </div>
            </div>
        </section>

        @if ($mergeSuggestions->isNotEmpty())
            <section class="space-y-2">
                @foreach ($mergeSuggestions as $suggestion)
                    <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900 shadow-sm lg:flex-row lg:items-center lg:justify-between lg:px-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Merge Suggestion</p>
                            <p class="mt-1 font-bold">{{ $suggestion['count'] }} draft carts are open for {{ $suggestion['label'] }}. Merge them as one cart?</p>
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

        <div class="grid grid-cols-2 gap-1.5 rounded-2xl border border-slate-200 bg-slate-100/90 p-1.5 shadow-xs sm:grid-cols-4 lg:rounded-[1.5rem]">
            <button type="button" id="tab-draft-btn" onclick="switchVendorTab('draft')" class="rounded-xl py-2.5 text-center text-xs font-black transition-all">
                Draft ({{ $draftCarts->count() }})
            </button>
            <button type="button" id="tab-pending-btn" onclick="switchVendorTab('pending')" class="rounded-xl py-2.5 text-center text-xs font-black transition-all">
                Pending ({{ $pendingCount ?? $pendingCarts->count() }})
            </button>
            <button type="button" id="tab-completed-btn" onclick="switchVendorTab('completed')" class="rounded-xl py-2.5 text-center text-xs font-black transition-all">
                Completed ({{ $completedCount ?? $completedCarts->count() }})
            </button>
            <button type="button" id="tab-cancelled-btn" onclick="switchVendorTab('cancelled')" class="rounded-xl py-2.5 text-center text-xs font-black transition-all">
                Cancelled ({{ $totalCancelledCount ?? $cancelledCarts->count() }})
            </button>
        </div>

        <div id="section-draft" class="space-y-4">
            @forelse ($draftCarts as $cart)
                <article id="cart-card-{{ $cart->id }}" class="overflow-hidden rounded-2xl border {{ $focusCartId === $cart->id ? 'border-teal-400 ring-2 ring-teal-200' : 'border-slate-200' }} bg-white shadow-sm transition-all duration-200 hover:shadow-md">
                    {{-- Accordion Trigger Header --}}
                    <div
                        onclick="toggleCartCollapse({{ $cart->id }})"
                        class="flex cursor-pointer items-center justify-between gap-3 bg-white p-4 transition hover:bg-slate-50/80 sm:px-5 select-none"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="truncate text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'No Vendor Selected' }}</h3>
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">Draft</span>
                                    @if (($cart->purchase_grade ?? 'A') === 'B')
                                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[9px] font-black uppercase text-blue-800">Grade B</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-500">
                                    {{ $cart->cart_number }} • {{ $cart->items->count() }} item{{ $cart->items->count() !== 1 ? 's' : '' }}{{ $cart->supplier?->mobile_number ? ' • '.$cart->supplier->mobile_number : '' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                                <p class="font-mono text-sm font-black text-slate-950 sm:text-base">
                                    ₹<span id="header-total-{{ $cart->id }}">{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                                </p>
                            </div>
                            <div id="chevron-{{ $cart->id }}" class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-transform duration-200 {{ $focusCartId === $cart->id || $loop->first ? 'rotate-180' : '' }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </div>
                        </div>
                    </div>

                    {{-- Collapsible Content Body --}}
                    <div id="cart-body-{{ $cart->id }}" class="{{ $focusCartId === $cart->id || $loop->first ? '' : 'hidden' }} border-t border-dashed border-slate-200">
                        {{-- Bill Header Info --}}
                        <div class="border-b border-dashed border-slate-300 bg-slate-50/40 px-4 pt-4 pb-3 sm:px-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">BILL NO</p>
                                    <p class="text-sm font-black text-slate-900">{{ $cart->bill_number ?: 'Pending' }}</p>
                                    <div class="mt-1 space-y-0.5 text-[11px] font-semibold text-slate-500">
                                        <p>Cart: {{ $cart->cart_number }}</p>
                                        <p>Date: {{ $cart->business_date?->format('d M Y') ?: \Carbon\Carbon::parse($date)->format('d M Y') }}</p>
                                        <p>Source: {{ $cart->purchaseSourceLabel() }}</p>
                                    </div>
                                </div>
                                @if (($cart->purchase_grade ?? 'A') === 'B')
                                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-blue-800">Grade B</span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-800">Grade A</span>
                                @endif
                            </div>
                        </div>

                        {{-- Vendor Info Section --}}
                        <div class="border-b border-dashed border-slate-300 bg-slate-50/20 px-4 py-3 sm:px-6">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">VENDOR</p>
                                    <p class="mt-0.5 text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'No vendor selected' }}</p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $cart->supplier?->mobile_number ?: 'Select a vendor before submitting.' }}{{ $cart->supplier?->location ? ' • '.$cart->supplier->location : '' }}</p>
                                    @if ($cart->supplier?->payment_terms)
                                        <p class="text-[11px] font-semibold text-slate-500">Terms: {{ $cart->supplier->payment_terms }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($cart->supplier && $cart->supplier->mobile_number)
                                        @php
                                            $supplierPhoneDigits = preg_replace('/\D+/', '', $cart->supplier->mobile_number);
                                            $shareItemsText = $cart->items->map(fn($item, $idx) => ($idx+1).'. '.$item->product->name.': '.(float)$item->quantity.' '.$item->product->unit.' @ Rs. '.(float)$item->unit_price)->implode("\n");
                                            $formattedDateStr = $cart->business_date?->format('d M Y') ?: \Carbon\Carbon::parse($date)->format('d M Y');
                                            $shareMessage = "Green Leaf ERP - Purchase Order\nDate: {$formattedDateStr}\nCart: {$cart->cart_number}\n\n{$shareItemsText}\n\nTotal: Rs. ".number_format($cart->items->sum('line_total') - $cart->discount_amount, 2);
                                            $waLink = 'https://wa.me/'.$supplierPhoneDigits.'?text='.rawurlencode($shareMessage);
                                        @endphp
                                        <a
                                            href="{{ $waLink }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100"
                                        >
                                            <svg class="h-3.5 w-3.5 fill-current text-emerald-600" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.316 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.818-.981z"/></svg>
                                            <span>Share</span>
                                        </a>
                                    @endif
                                    @if (($mergeableDraftCounts[$cart->id] ?? 0) > 0)
                                        <form action="{{ route('purchaser.carts.merge-drafts', $cart) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 text-[11px] font-bold text-amber-700 hover:bg-amber-100 transition">
                                                Merge {{ $mergeableDraftCounts[$cart->id] + 1 }}
                                            </button>
                                        </form>
                                    @endif
                                    <button
                                        type="button"
                                        onclick="openChangeVendorModal(@js($cart->cart_number), 'draft', {{ $cart->id }})"
                                        class="inline-flex h-8 items-center justify-center rounded-lg border border-teal-200 bg-teal-50 px-2.5 text-[11px] font-bold text-teal-700 hover:bg-teal-100 transition"
                                    >
                                        Change Vendor
                                    </button>
                                    <button
                                        type="button"
                                        onclick="openCreateVendorModal(@js($cart->cart_number), 'draft', {{ $cart->id }})"
                                        class="inline-flex h-8 items-center justify-center rounded-lg bg-teal-600 px-2.5 text-[11px] font-bold text-white hover:bg-teal-500 transition"
                                    >
                                        + New
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Form for Cart Items --}}
                        <form action="{{ route('purchaser.carts.items.update-all', $cart) }}" method="POST">
                            @csrf
                            @method('PATCH')

                            {{-- Receipt Items Table --}}
                            <div class="overflow-x-auto px-4 py-2 sm:px-6">
                                <table class="w-full text-left text-xs">
                                    <thead class="border-b border-dashed border-slate-300 text-[10px] font-black uppercase text-slate-950">
                                        <tr>
                                            <th class="w-6 py-2 pr-1">SN</th>
                                            <th class="py-2 pr-2">ITEM</th>
                                            <th class="w-14 sm:w-16 py-2 pr-1 text-right">QTY</th>
                                            <th class="w-16 sm:w-20 py-2 pr-1 text-right">PRICE</th>
                                            <th class="w-20 py-2 text-right">AMT</th>
                                            <th class="w-6 py-2 text-right"><span class="sr-only">Remove</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-dashed divide-slate-200/80">
                                        @forelse ($cart->items as $item)
                                            @php
                                                $vendorPriceHint = $vendorPriceHintsByCart[$cart->id][$item->product_id] ?? 0;
                                            @endphp
                                            <tr class="align-top">
                                                <td class="py-2.5 pr-1 text-[11px] font-bold text-slate-400">{{ $loop->iteration }}</td>
                                                <td class="py-2.5 pr-2">
                                                    <p class="font-bold text-slate-900 text-xs leading-snug break-words">{{ $item->product->name }}</p>
                                                    <p class="mt-0.5 text-[10px] font-semibold text-slate-500">
                                                        {{ $item->product->unit }}
                                                        @if ($vendorPriceHint > 0)
                                                            • Prev Rs. {{ number_format((float) $vendorPriceHint, 2) }}
                                                        @endif
                                                        @if ($item->is_extra_purchase)
                                                            <span class="ml-1 inline-flex items-center rounded bg-amber-100 px-1 py-0.2 text-[8px] font-black uppercase text-amber-700">EXTRA</span>
                                                        @endif
                                                    </p>
                                                </td>
                                                <td class="py-2.5 pr-1 text-right">
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        min="0.01"
                                                        name="items[{{ $item->id }}][quantity]"
                                                        id="quantity-{{ $item->id }}"
                                                        value="{{ number_format((float) $item->quantity, 2, '.', '') }}"
                                                        oninput="updateCartItemTotal({{ $item->id }}, {{ $cart->id }})"
                                                        class="h-7 w-12 sm:w-14 rounded-md border border-slate-200 bg-slate-50 px-1 text-right font-mono text-xs font-bold text-slate-950 focus:bg-white focus:outline-none"
                                                    >
                                                </td>
                                                <td class="py-2.5 pr-1 text-right">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        name="items[{{ $item->id }}][unit_price]"
                                                        id="price-{{ $item->id }}"
                                                        value="{{ number_format((float) $item->unit_price, 2, '.', '') }}"
                                                        oninput="updateCartItemTotal({{ $item->id }}, {{ $cart->id }})"
                                                        class="h-7 w-14 sm:w-16 rounded-md border border-slate-200 bg-slate-50 px-1 text-right font-mono text-xs font-bold text-slate-950 focus:bg-white focus:outline-none"
                                                    >
                                                </td>
                                                <td class="py-2.5 text-right font-mono font-black text-slate-950 text-xs whitespace-nowrap">
                                                    ₹<span id="total-{{ $item->id }}">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</span>
                                                </td>
                                                <td class="py-2.5 pl-1 text-right">
                                                    <button
                                                        type="button"
                                                        onclick="confirmDeleteItem({{ $item->id }}, '{{ route('purchaser.cart-items.destroy', $item) }}')"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded text-base font-bold text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                        title="Remove item"
                                                    >
                                                        ×
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-8 text-center text-xs font-bold text-slate-400">
                                                    No products in this draft cart.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{-- Total Summary Row --}}
                            <div class="border-t border-dashed border-slate-300 px-4 py-3 sm:px-6">
                                <div class="flex items-center justify-between font-black text-slate-950">
                                    <span class="text-xs uppercase tracking-wider text-slate-500">Total Bill</span>
                                    <span class="font-mono text-base text-slate-950">
                                        Total: ₹<span id="cart-total-{{ $cart->id }}">{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                                    </span>
                                </div>
                            </div>

                            {{-- Bill Footer Actions --}}
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-6">
                                <span class="text-[11px] font-bold text-slate-500">{{ $cart->items->count() }} item{{ $cart->items->count() !== 1 ? 's' : '' }}</span>
                                <div class="flex items-center gap-2">
                                    @if ($cart->items->isNotEmpty())
                                        @if ($cart->supplier)
                                            <button
                                                type="submit"
                                                name="action"
                                                value="process"
                                                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-xs transition hover:bg-teal-500 active:scale-95"
                                            >
                                                <span>Save & Process</span>
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                disabled
                                                class="inline-flex h-9 items-center justify-center rounded-xl bg-teal-600/50 px-4 text-xs font-black text-white cursor-not-allowed"
                                                title="Assign a supplier first"
                                            >
                                                Save & Process
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">No draft carts for this business day.</p>
            @endforelse
        </div>

        <div id="section-pending" class="hidden space-y-3" data-loaded="{{ $pendingCarts->isNotEmpty() ? 'true' : 'false' }}">
            @if ($pendingCarts->isNotEmpty())
                @include('purchasing.purchaser.partials.vendors_pending', ['pendingCarts' => $pendingCarts])
            @else
                <div class="flex justify-center py-8 text-xs font-bold text-slate-400">Loading pending carts...</div>
            @endif
        </div>

        <div id="section-completed" class="hidden space-y-3" data-loaded="{{ $completedCarts->isNotEmpty() ? 'true' : 'false' }}">
            @if ($completedCarts->isNotEmpty())
                @include('purchasing.purchaser.partials.vendors_completed', ['completedCarts' => $completedCarts])
            @else
                <div class="flex justify-center py-8 text-xs font-bold text-slate-400">Loading completed carts...</div>
            @endif
        </div>

        <div id="section-cancelled" class="hidden space-y-3" data-loaded="{{ ($cancelledInvoices->isNotEmpty() || $cancelledCarts->isNotEmpty()) ? 'true' : 'false' }}">
            @if ($cancelledInvoices->isNotEmpty() || $cancelledCarts->isNotEmpty())
                @include('purchasing.purchaser.partials.vendors_cancelled', ['cancelledInvoices' => $cancelledInvoices, 'cancelledCarts' => $cancelledCarts])
            @else
                <div class="flex justify-center py-8 text-xs font-bold text-slate-400">Loading cancelled records...</div>
            @endif
        </div>
    </div>
    </div>

    <form id="change-vendor-form" method="POST" action="">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="vendors">
        <input type="hidden" id="change-vendor-tab" name="tab" value="">
        <input type="hidden" id="change-vendor-focus-cart" name="focus_cart" value="">
        <input type="hidden" id="change-vendor-supplier-id" name="supplier_id" value="">
    </form>

    <form id="cart-share-form" method="POST" action="">
        @csrf
        <input type="hidden" name="return_to" value="vendors">
        <input type="hidden" id="cart-share-supplier-id" name="supplier_id" value="">
        <input type="hidden" id="cart-share-mode" name="share_mode" value="saved">
        <input type="hidden" id="cart-share-mobile-hidden" name="vendor_mobile_number" value="">
        <input type="hidden" id="cart-share-format" name="share_format" value="total">
        <input type="hidden" id="cart-share-show-price" name="show_price" value="0">
        <input type="hidden" id="cart-share-discount-hidden" name="discount_amount" value="0">
    </form>

    <form id="delete-item-form" method="POST" action="" class="hidden">
        @csrf
        @method('DELETE')
        <input type="hidden" name="return_to" value="vendors">
        <input type="hidden" id="delete-item-tab" name="tab" value="">
        <input type="hidden" id="delete-item-focus-cart" name="focus_cart" value="">
    </form>

    <div id="change-vendor-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeChangeVendorModal()">
        <div class="relative flex max-h-[80vh] w-full max-w-xs flex-col rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
            <div class="mb-2 flex items-center justify-between border-b border-slate-100 pb-1.5">
                <h3 class="text-xs font-black text-slate-950">Assign Supplier</h3>
                <button type="button" onclick="closeChangeVendorModal()" class="text-slate-400 transition hover:text-slate-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="relative mb-2">
                <input type="text" id="vendor-search-input" oninput="filterVendors()" placeholder="Search suppliers..." class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>
            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-0.5">
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
                    <button type="button" onclick="selectVendorForCart({{ $supplier->id }})" data-name="{{ $supplier->name }}" class="vendor-list-item flex w-full items-center justify-between rounded-lg border border-transparent px-2.5 py-1.5 text-left text-[10px] font-bold text-slate-700 transition hover:border-slate-100 hover:bg-slate-50 hover:text-slate-900">
                        <span class="truncate">{{ $supplier->name }}</span>
                        <span class="shrink-0 text-[9px] font-semibold text-slate-400">{{ $lastFour }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div id="create-vendor-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeCreateVendorModal()">
        <div class="relative flex w-full max-w-xs flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-xs font-black text-slate-950">Create New Supplier</h3>
                <button type="button" onclick="closeCreateVendorModal()" class="text-slate-400 transition hover:text-slate-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="create-vendor-form" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_to" value="vendors">
                <input type="hidden" id="create-vendor-tab" name="tab" value="">
                <input type="hidden" id="create-vendor-focus-cart" name="focus_cart" value="">
                <input type="hidden" name="supplier_id" value="">
                <div>
                    <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Supplier Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="vendor_name" required placeholder="Supplier Name" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Phone Number <span class="text-rose-500">*</span></label>
                    <input type="tel" name="vendor_mobile_number" required placeholder="Phone number" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Location</label>
                    <input type="text" name="vendor_location" placeholder="Location" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Banking Details / Notes (Optional)</label>
                    <textarea name="vendor_bank_details" placeholder="Bank Name, A/C No, IFSC, UPI ID, etc." rows="2" class="w-full rounded-lg border border-slate-200 bg-slate-50 p-2 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none"></textarea>
                </div>
                <input type="hidden" name="vendor_type" value="Vendor">
                <input type="hidden" name="payment_terms" value="Cash">
                <input type="hidden" name="preferred_payment_method" value="Cash">
                <button type="submit" class="mt-1 flex h-9 w-full items-center justify-center rounded-lg bg-teal-600 text-xs font-black text-white hover:bg-teal-500">
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
                <button type="button" onclick="closeCartShareModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-500">
                        <span>Cart Total</span>
                        <span id="cart-share-total" class="text-slate-900">Rs. 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-500">
                        <span>Net After Discount</span>
                        <span id="cart-share-net-total" class="text-emerald-700">Rs. 0.00</span>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-2">
                    <span class="text-xs font-bold text-slate-700">Show price in message</span>
                    <button type="button" role="switch" aria-checked="false" id="toggle-show-price" class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent bg-slate-200 transition-colors duration-200 ease-in-out focus:outline-none" onclick="togglePriceCheckbox()">
                        <span aria-hidden="true" id="toggle-switch-handle" class="pointer-events-none inline-block h-4 w-4 translate-x-0 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out"></span>
                    </button>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-2">
                    <p class="px-1 text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Message Text</p>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button type="button" id="cart-share-format-total" onclick="setCartShareFormat('total')" class="flex h-9 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white">
                            Total
                        </button>
                        <button type="button" id="cart-share-format-selection" onclick="setCartShareFormat('selection')" class="flex h-9 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-3 text-xs font-black text-emerald-700 hover:bg-emerald-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.998 2.166C6.525 2.166 2.09 6.6 2.09 12.073c0 1.742.455 3.378 1.25 4.793L2 22l5.292-1.387c1.36.74 2.912 1.162 4.566 1.162 5.472 0 9.908-4.433 9.908-9.905 0-5.474-4.436-9.704-9.768-9.704z"/></svg>
                            Selection
                        </button>
                    </div>
                </div>

                <div id="cart-share-discount-wrap" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount in Share</label>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="flex h-10 items-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-600">Rs.</div>
                        <input id="cart-share-discount-input" type="number" min="0" step="0.01" value="" placeholder="Discount" class="h-10 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:outline-none" oninput="updateCartShareDiscount()">
                    </div>
                    <p class="mt-2 text-[10px] font-semibold text-slate-500">If discount is added, the share automatically uses the bill format with price.</p>
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

    <script>
        const initialVendorTab = @json($activeTab);
        const focusCartId = @json($focusCartId);
        const tabUrls = {
            pending: `{{ route('purchaser.vendors.tabs.pending') }}?date={{ $date }}&purchase_grade={{ $purchaseGrade ?? '' }}`,
            completed: `{{ route('purchaser.vendors.tabs.completed') }}?date={{ $date }}&purchase_grade={{ $purchaseGrade ?? '' }}`,
            cancelled: `{{ route('purchaser.vendors.tabs.cancelled') }}?date={{ $date }}&purchase_grade={{ $purchaseGrade ?? '' }}`,
        };
        const tabButtons = {
            draft: document.getElementById('tab-draft-btn'),
            pending: document.getElementById('tab-pending-btn'),
            completed: document.getElementById('tab-completed-btn'),
            cancelled: document.getElementById('tab-cancelled-btn'),
        };
        const tabSections = {
            draft: document.getElementById('section-draft'),
            pending: document.getElementById('section-pending'),
            completed: document.getElementById('section-completed'),
            cancelled: document.getElementById('section-cancelled'),
        };

        function switchVendorTab(tab) {
            Object.entries(tabButtons).forEach(([key, button]) => {
                if (! button) {
                    return;
                }

                button.className = key === tab
                    ? 'rounded-xl bg-white py-2.5 text-center text-xs font-black text-slate-950 shadow-sm'
                    : 'rounded-xl py-2.5 text-center text-xs font-bold text-slate-600 hover:text-slate-900 transition-colors';
            });

            Object.entries(tabSections).forEach(([key, section]) => {
                if (! section) {
                    return;
                }

                section.classList.toggle('hidden', key !== tab);
            });

            if (tab !== 'draft' && tabSections[tab] && tabSections[tab].dataset.loaded !== 'true') {
                const url = tabUrls[tab];
                if (url) {
                    window.showLoader?.();
                    fetch(url)
                        .then(response => response.text())
                        .then(html => {
                            tabSections[tab].innerHTML = html;
                            tabSections[tab].dataset.loaded = 'true';
                        })
                        .catch(err => {
                            console.error('Failed to load tab:', err);
                        })
                        .finally(() => {
                            window.hideLoader?.();
                        });
                }
            }
        }

        function toggleCartCollapse(cartId) {
            const body = document.getElementById(`cart-body-${cartId}`);
            const chevron = document.getElementById(`chevron-${cartId}`);
            if (! body) return;

            const isHidden = body.classList.contains('hidden');
            if (isHidden) {
                body.classList.remove('hidden');
                if (chevron) {
                    chevron.classList.add('rotate-180');
                }
            } else {
                body.classList.add('hidden');
                if (chevron) {
                    chevron.classList.remove('rotate-180');
                }
            }
        }

        function updateCartItemTotal(itemId, cartId = null) {
            const quantityInput = document.getElementById(`quantity-${itemId}`);
            const priceInput = document.getElementById(`price-${itemId}`);
            const totalNode = document.getElementById(`total-${itemId}`);

            if (! quantityInput || ! priceInput || ! totalNode) {
                return;
            }

            const quantity = Number(quantityInput.value || 0);
            const price = Number(priceInput.value || 0);
            totalNode.textContent = (quantity * price).toFixed(2);

            if (cartId) {
                updateCartTotal(cartId);
            }
        }

        function updateCartTotal(cartId) {
            const cartCard = document.getElementById(`cart-card-${cartId}`);
            if (! cartCard) return;

            let sum = 0;
            cartCard.querySelectorAll('[id^="total-"]').forEach(node => {
                sum += Number(node.textContent || 0);
            });
            const formatted = sum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const cartTotalEl = document.getElementById(`cart-total-${cartId}`);
            if (cartTotalEl) {
                cartTotalEl.textContent = formatted;
            }
            const headerTotalEl = document.getElementById(`header-total-${cartId}`);
            if (headerTotalEl) {
                headerTotalEl.textContent = formatted;
            }
        }

        function updateProcessedItemTotal(itemId, cartId = null) {
            const quantityInput = document.getElementById(`processed-qty-${itemId}`);
            const priceInput = document.getElementById(`processed-price-${itemId}`);
            const totalNode = document.getElementById(`processed-total-${itemId}`);

            if (! quantityInput || ! priceInput || ! totalNode) {
                return;
            }

            const quantity = Number(quantityInput.value || 0);
            const price = Number(priceInput.value || 0);
            totalNode.textContent = (quantity * price).toFixed(2);

            if (cartId) {
                const cartCard = document.getElementById(`cart-card-${cartId}`);
                if (cartCard) {
                    let sum = 0;
                    cartCard.querySelectorAll('[id^="processed-total-"]').forEach(node => {
                        sum += Number(node.textContent || 0);
                    });
                    const formatted = sum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const processedCartTotalEl = document.getElementById(`processed-cart-total-${cartId}`);
                    if (processedCartTotalEl) {
                        processedCartTotalEl.textContent = formatted;
                    }
                    const headerTotalEl = document.getElementById(`header-total-${cartId}`);
                    if (headerTotalEl) {
                        headerTotalEl.textContent = formatted;
                    }
                }
            }
        }

        function confirmDeleteItem(itemId, actionUrl, tab = '', cartId = '') {
            if (confirm('Are you sure you want to remove this item?')) {
                const form = document.getElementById('delete-item-form');
                form.action = actionUrl;
                document.getElementById('delete-item-tab').value = tab;
                document.getElementById('delete-item-focus-cart').value = cartId;
                form.submit();
            }
        }

        let currentCartNumber = null;

        function openChangeVendorModal(cartNumber, tab = 'draft', cartId = '') {
            currentCartNumber = cartNumber;
            document.getElementById('change-vendor-form').action = `/purchaser/carts/${cartNumber}/supplier`;
            document.getElementById('change-vendor-tab').value = tab;
            document.getElementById('change-vendor-focus-cart').value = cartId;
            document.getElementById('change-vendor-modal').classList.remove('hidden');
            document.getElementById('change-vendor-modal').classList.add('flex');
            const searchInput = document.getElementById('vendor-search-input');
            searchInput.value = '';
            filterVendors();
            setTimeout(() => searchInput.focus(), 50);
        }

        function closeChangeVendorModal() {
            document.getElementById('change-vendor-modal').classList.add('hidden');
            document.getElementById('change-vendor-modal').classList.remove('flex');
        }

        function filterVendors() {
            const query = document.getElementById('vendor-search-input').value.toLowerCase();
            document.querySelectorAll('.vendor-list-item').forEach((item) => {
                const name = item.getAttribute('data-name').toLowerCase();
                item.classList.toggle('hidden', ! name.includes(query));
            });
        }

        function selectVendorForCart(supplierId) {
            document.getElementById('change-vendor-supplier-id').value = supplierId;
            document.getElementById('change-vendor-form').submit();
        }

        function openCreateVendorModal(cartNumber, tab = 'draft', cartId = '') {
            currentCartNumber = cartNumber;
            document.getElementById('create-vendor-form').action = `/purchaser/carts/${cartNumber}/supplier`;
            document.getElementById('create-vendor-tab').value = tab;
            document.getElementById('create-vendor-focus-cart').value = cartId;
            document.getElementById('create-vendor-modal').classList.remove('hidden');
            document.getElementById('create-vendor-modal').classList.add('flex');
        }

        function closeCreateVendorModal() {
            document.getElementById('create-vendor-modal').classList.add('hidden');
            document.getElementById('create-vendor-modal').classList.remove('flex');
        }

        let cartShareState = {
            actionUrl: '',
            supplierId: '',
            supplierMobile: '',
            title: '',
            totalAmount: 0,
        };

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
            setPriceToggle(document.getElementById('cart-share-show-price').value !== '1');
        }

        function setCartShareFormat(format) {
            const selected = format === 'selection' ? 'selection' : 'total';
            const input = document.getElementById('cart-share-format');
            const totalButton = document.getElementById('cart-share-format-total');
            const selectionButton = document.getElementById('cart-share-format-selection');

            input.value = selected;
            totalButton.classList.toggle('bg-slate-950', selected === 'total');
            totalButton.classList.toggle('text-white', selected === 'total');
            totalButton.classList.toggle('border', selected !== 'total');
            totalButton.classList.toggle('border-slate-200', selected !== 'total');
            totalButton.classList.toggle('bg-white', selected !== 'total');
            totalButton.classList.toggle('text-slate-700', selected !== 'total');
            selectionButton.classList.toggle('bg-emerald-600', selected === 'selection');
            selectionButton.classList.toggle('text-white', selected === 'selection');
            selectionButton.classList.toggle('border-emerald-600', selected === 'selection');
            selectionButton.classList.toggle('bg-white', selected !== 'selection');
            selectionButton.classList.toggle('text-emerald-700', selected !== 'selection');
            selectionButton.classList.toggle('border-emerald-200', selected !== 'selection');
        }

        function updateCartShareDiscount() {
            const discountValue = Math.max(0, Number(document.getElementById('cart-share-discount-input').value || 0));
            const netTotal = Math.max(0, cartShareState.totalAmount - discountValue);

            if (discountValue > 0) {
                setPriceToggle(true);
            }

            document.getElementById('cart-share-total').textContent = `Rs. ${cartShareState.totalAmount.toFixed(2)}`;
            document.getElementById('cart-share-net-total').textContent = `Rs. ${netTotal.toFixed(2)}`;
        }

        function openCartShareModal(actionUrl, supplierId, supplierMobile, title, totalAmount) {
            cartShareState = {
                actionUrl,
                supplierId,
                supplierMobile: supplierMobile || '',
                title: title || 'Cart',
                totalAmount: Number(totalAmount || 0),
            };

            document.getElementById('cart-share-title').textContent = cartShareState.title;
            document.getElementById('cart-share-mobile-input').value = '';
            document.getElementById('cart-share-discount-input').value = '0';
            document.getElementById('cart-share-mobile-error').classList.add('hidden');
            document.getElementById('cart-share-saved-button').disabled = ! cartShareState.supplierMobile;
            document.getElementById('cart-share-saved-button').classList.toggle('opacity-50', ! cartShareState.supplierMobile);
            document.getElementById('cart-share-modal').classList.remove('hidden');
            document.getElementById('cart-share-modal').classList.add('flex');
            setPriceToggle(false);
            setCartShareFormat('total');
            updateCartShareDiscount();
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
            const discountInput = document.getElementById('cart-share-discount-input');

            form.action = cartShareState.actionUrl;
            document.getElementById('cart-share-supplier-id').value = cartShareState.supplierId || '';
            document.getElementById('cart-share-mode').value = mode;
            document.getElementById('cart-share-discount-hidden').value = discountInput.value || '0';
            mobileHidden.value = '';
            mobileError.classList.add('hidden');

            if (mode === 'saved') {
                if (! cartShareState.supplierMobile) {
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

            form.submit();
        }

        document.addEventListener('DOMContentLoaded', () => {
            switchVendorTab(initialVendorTab);

            if (focusCartId) {
                const focusedCard = document.getElementById(`cart-card-${focusCartId}`);
                if (focusedCard) {
                    focusedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
</x-layouts.app>
