<x-layouts.app title="Purchaser Carts">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 4</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Purchaser Carts</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Only the active business-day carts live here. Old payment follow-up stays in Vendor Hub.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <a href="{{ route('purchaser.suppliers', ['date' => $date, 'tab' => 'pending']) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-white">
                        <span>Vendor Hub</span>
                        @if (($deadlineAlert['pending_total_count'] ?? 0) > 0)
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-black text-rose-700">
                                {{ $deadlineAlert['pending_total_count'] }}
                            </span>
                        @endif
                    </a>
                    <form action="{{ route('purchaser.vendors') }}" method="GET">
                        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                    </form>
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

        <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1 shadow-sm sm:grid-cols-4">
            <button type="button" id="tab-draft-btn" onclick="switchVendorTab('draft')" class="rounded-xl py-2 text-center text-[10px] font-black sm:text-xs">
                Draft ({{ $draftCarts->count() }})
            </button>
            <button type="button" id="tab-pending-btn" onclick="switchVendorTab('pending')" class="rounded-xl py-2 text-center text-[10px] font-black sm:text-xs">
                Pending ({{ $pendingCarts->count() }})
            </button>
            <button type="button" id="tab-completed-btn" onclick="switchVendorTab('completed')" class="rounded-xl py-2 text-center text-[10px] font-black sm:text-xs">
                Completed ({{ $completedCarts->count() }})
            </button>
            <button type="button" id="tab-cancelled-btn" onclick="switchVendorTab('cancelled')" class="rounded-xl py-2 text-center text-[10px] font-black sm:text-xs">
                Cancelled ({{ $cancelledCarts->count() }})
            </button>
        </div>

        <div id="section-draft" class="space-y-3">
            @forelse ($draftCarts as $cart)
                <article id="cart-card-{{ $cart->id }}" class="rounded-2xl border {{ $focusCartId === $cart->id ? 'border-teal-300 ring-2 ring-teal-100' : 'border-slate-200' }} bg-white p-3 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }} · Grade {{ $cart->purchase_grade ?? 'A' }}</p>
                            <div class="mt-1 flex items-center gap-2">
                                <h3 class="truncate text-sm font-black text-slate-950">{{ $cart->supplier?->name ?: 'Supplier not selected' }}</h3>
                                <button type="button" onclick="openChangeVendorModal(@js($cart->cart_number), 'draft', {{ $cart->id }})" class="text-slate-400 transition hover:text-slate-600" title="Assign Supplier">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                @if ($cart->supplier)
                                    <button
                                        type="button"
                                        onclick="openCartShareModal('{{ route('purchaser.carts.send', $cart) }}', {{ $cart->supplier_id }}, @js($cart->supplier->mobile_number), @js($cart->cart_number), {{ round((float) $cart->items->sum('line_total'), 2) }})"
                                        class="text-emerald-600 transition hover:text-emerald-500"
                                        title="Share Cart"
                                    >
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12.012 2c-5.506 0-9.969 4.471-9.969 9.986 0 1.764.459 3.419 1.258 4.873L2 22l5.304-1.393c1.42.776 3.033 1.213 4.708 1.213 5.506 0 9.969-4.473 9.969-9.987S17.518 2 12.012 2zm6.275 14.286c-.256.721-1.5 1.302-2.073 1.393-.509.079-1.18.149-3.414-.775-2.856-1.181-4.701-4.089-4.843-4.28-.143-.19-1.146-1.524-1.146-2.909 0-1.385.726-2.062.981-2.348.256-.286.561-.357.747-.357.187 0 .375.002.537.009.169.007.394-.063.616.48.226.552.773 1.895.84 2.03.067.137.112.296.022.477-.09.18-.135.295-.27.456-.135.161-.286.357-.406.48-.135.137-.278.286-.12.562.158.277.702 1.159 1.503 1.875.803.717 1.48.94 1.691 1.045.21.106.333.09.456-.053.123-.143.528-.616.67-.828.141-.21.282-.176.476-.105.195.07.1.24 1.233.805 1.133.565 1.2.94 1.2.94 0 .423-.88 1.163-1.136 1.884z"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (($mergeableDraftCounts[$cart->id] ?? 0) > 0)
                                <form action="{{ route('purchaser.carts.merge-drafts', $cart) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[10px] font-black text-amber-700">
                                        Merge {{ $mergeableDraftCounts[$cart->id] + 1 }}
                                    </button>
                                </form>
                            @endif
                            <button type="button" onclick="openCreateVendorModal(@js($cart->cart_number), 'draft', {{ $cart->id }})" class="rounded-full border border-teal-100 bg-teal-50 px-3 py-1 text-[10px] font-black text-teal-700">
                                + New Supplier
                            </button>
                        </div>
                    </div>

                    @if (! $cart->supplier)
                        <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-3 text-xs font-bold text-rose-800">
                            Supplier details required. Assign or create the vendor before bill processing.
                        </div>
                    @endif

                    <form action="{{ route('purchaser.carts.items.update-all', $cart) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="mt-3 space-y-2">
                            @forelse ($cart->items as $item)
                                @php
                                    $vendorPriceHint = $vendorPriceHintsByCart[$cart->id][$item->product_id] ?? 0;
                                @endphp
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <h4 class="truncate text-[11px] font-black text-slate-900">{{ $item->product->name }}</h4>
                                                @if ($item->is_extra_purchase)
                                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.12em] text-amber-700">Extra</span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="text-xs font-black text-slate-900">₹<span id="total-{{ $item->id }}">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</span></span>
                                    </div>

                                    <div class="mt-2 flex items-end justify-between gap-2">
                                        <div class="flex flex-1 items-end gap-2">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-[8px] font-black uppercase tracking-wider text-slate-400">Qty</span>
                                                <div class="flex h-8 items-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                                                    <button type="button" onclick="this.nextElementSibling.stepDown(); updateCartItemTotal({{ $item->id }})" class="flex h-full w-7 items-center justify-center bg-slate-50 text-xs font-bold text-slate-500">-</button>
                                                    <input type="number" step="any" min="0.01" name="items[{{ $item->id }}][quantity]" id="quantity-{{ $item->id }}" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" oninput="updateCartItemTotal({{ $item->id }})" class="h-full w-12 bg-transparent text-center text-[10px] font-black text-slate-900 focus:outline-none">
                                                    <button type="button" onclick="this.previousElementSibling.stepUp(); updateCartItemTotal({{ $item->id }})" class="flex h-full w-7 items-center justify-center bg-slate-50 text-xs font-bold text-slate-500">+</button>
                                                </div>
                                            </div>
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-[8px] font-black uppercase tracking-wider text-slate-400">Per {{ $item->product->unit }}</span>
                                                <input type="number" step="0.01" min="0.01" name="items[{{ $item->id }}][unit_price]" id="price-{{ $item->id }}" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" oninput="updateCartItemTotal({{ $item->id }})" class="h-8 w-16 rounded-lg border border-slate-200 bg-white text-center text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                                @if ($vendorPriceHint > 0)
                                                    <span class="text-[8px] font-bold text-amber-700">Prev ₹{{ number_format((float) $vendorPriceHint, 2) }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <button type="button" onclick="confirmDeleteItem({{ $item->id }}, '{{ route('purchaser.cart-items.destroy', $item) }}')" class="flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600" title="Delete">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-xs font-bold text-slate-500">No products in this draft cart.</p>
                            @endforelse
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                            <span class="text-[10px] font-bold text-slate-500">Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                            <div class="flex items-center gap-1.5">
                                @if ($cart->items->isNotEmpty())
                                    @if ($cart->supplier)
                                        <button type="submit" name="action" value="process" class="inline-flex h-9 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white hover:bg-teal-500">
                                            Save & Process
                                        </button>
                                    @else
                                        <button type="button" disabled class="inline-flex h-9 items-center justify-center rounded-xl bg-teal-600/50 px-4 text-xs font-black text-white cursor-not-allowed" title="Assign a supplier first">
                                            Save & Process
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </form>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">No draft carts for this business day.</p>
            @endforelse
        </div>

        <div id="section-pending" class="hidden space-y-3">
            @forelse ($pendingCarts as $cart)
                @php
                    $warehouseConfirmed = (bool) ($relatedBatchState[$cart->id]['warehouse_confirmed'] ?? false);
                    $receiptNotes = $relatedReceiptNotes[$cart->id] ?? '';
                @endphp
                <article id="cart-card-{{ $cart->id }}" class="rounded-2xl border {{ $focusCartId === $cart->id ? 'border-teal-300 ring-2 ring-teal-100' : 'border-slate-200' }} bg-white p-3 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }}</p>
                            <div class="mt-1 flex items-center gap-2">
                                <h3 class="truncate text-sm font-black text-slate-950">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                                <button type="button" onclick="openChangeVendorModal(@js($cart->cart_number), 'pending', {{ $cart->id }})" class="text-slate-400 transition hover:text-slate-600" title="Change Supplier">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-600">
                                Bill {{ $cart->bill_number ?: 'Pending' }} • {{ $warehouseConfirmed ? 'Warehouse confirmed' : 'Waiting for warehouse receipt' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" onclick="openCreateVendorModal(@js($cart->cart_number), 'pending', {{ $cart->id }})" class="rounded-full border border-teal-100 bg-teal-50 px-3 py-1 text-[10px] font-black text-teal-700">
                                + New Supplier
                            </button>
                            <span class="rounded-full {{ $warehouseConfirmed ? 'bg-amber-100 text-amber-700' : 'bg-cyan-100 text-cyan-700' }} px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em]">
                                {{ $warehouseConfirmed ? 'Payment Pending' : 'Processing' }}
                            </span>
                        </div>
                    </div>

                    <details class="mt-3 rounded-2xl border border-slate-100 bg-slate-50 p-2">
                        <summary class="cursor-pointer px-2 py-1 text-[10px] font-black text-slate-700">
                            {{ $cart->purchaseInvoice ? 'Edit Qty / Price (Processed Bill)' : 'View Cart Items' }}
                        </summary>
                        <div class="mt-2 border-t border-slate-200/60 pt-2">
                            @if ($cart->purchaseInvoice)
                                <form action="{{ route('purchaser.carts.items.update-all', $cart) }}" method="POST" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    @foreach ($cart->items as $item)
                                        <div class="rounded-xl border border-slate-200 bg-white p-2">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate text-[10px] font-black text-slate-800">{{ $item->product->name }}</span>
                                                <span class="text-[10px] font-black text-slate-900">₹<span id="processed-total-{{ $item->id }}">{{ number_format((float) $item->line_total, 2, '.', '') }}</span></span>
                                            </div>
                                            <div class="mt-2 grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-[9px] font-bold text-slate-500">Qty ({{ $item->product->unit }})</label>
                                                    <input type="number" step="any" min="0.01" name="items[{{ $item->id }}][quantity]" id="processed-qty-{{ $item->id }}" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" oninput="updateProcessedItemTotal({{ $item->id }})" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-center text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                                </div>
                                                <div>
                                                    <label class="text-[9px] font-bold text-slate-500">Price</label>
                                                    <input type="number" step="0.01" min="0.01" name="items[{{ $item->id }}][unit_price]" id="processed-price-{{ $item->id }}" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" oninput="updateProcessedItemTotal({{ $item->id }})" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-center text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    <input type="hidden" name="action" value="processed_update">
                                    <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg bg-teal-600 px-3 text-[10px] font-black text-white hover:bg-teal-500">
                                        Update Qty, Price & Total
                                    </button>
                                </form>
                            @else
                                <div class="space-y-1">
                                    @foreach ($cart->items as $item)
                                        <div class="flex items-center justify-between gap-2 px-2 py-1 text-[10px] font-bold text-slate-600">
                                            <span class="truncate">{{ $item->product->name }}</span>
                                            <span>{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} • ₹{{ number_format((float) $item->line_total, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </details>

                    @if ($receiptNotes !== '')
                        <div class="mt-3 rounded-2xl border border-cyan-100 bg-cyan-50 px-3 py-3 text-xs font-semibold text-cyan-800">
                            Receipt note: {{ $receiptNotes }}
                        </div>
                    @endif

                    <!-- Embedded Bill Receipt (Collapsed as Default) -->
                    @if ($cart->purchaseInvoice)
                        <details class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-900">
                            <summary class="cursor-pointer px-2 py-1 text-[10px] font-black text-cyan-700 hover:text-cyan-600 select-none">
                                View Matched Bill Invoice
                            </summary>
                            <div class="mt-3 border-t border-dashed border-slate-300 pt-3 px-1">
                                @php
                                    $invoice = $cart->purchaseInvoice;
                                    $payableTotal = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
                                    $paidAmount = (float) $invoice->paid_amount;
                                    $balanceAmount = max(0, $payableTotal - $paidAmount);
                                    $paymentMethod = $invoice->payment_method ?: 'Credit';
                                    $supplier = $invoice->supplier;
                                    $businessDate = $cart->business_date;

                                    $statusRibbonText = match(true) {
                                        $invoice->status->value === 'paid' => 'PAID',
                                        $balanceAmount <= 0 => 'PAID',
                                        $paidAmount > 0 => 'PARTIAL',
                                        default => 'UNPAID',
                                    };

                                    $statusRibbonColor = match($statusRibbonText) {
                                        'PAID' => 'text-emerald-700 bg-emerald-100 border border-emerald-200',
                                        'PARTIAL' => 'text-cyan-700 bg-cyan-100 border border-cyan-200',
                                        default => 'text-amber-700 bg-amber-100 border border-amber-200',
                                    };
                                @endphp
                                
                                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                                    <div class="flex items-center justify-between border-b border-dashed border-slate-300 pb-2">
                                        <div>
                                            <p class="text-[9px] font-black text-slate-900 uppercase">BILL INVOICE</p>
                                            <p class="text-[8px] font-bold text-slate-500">GREEN LEAF</p>
                                        </div>
                                        <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-wider {{ $statusRibbonColor }}">
                                            {{ $statusRibbonText }}
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 py-2 text-[9px] text-slate-700 border-b border-dashed border-slate-300">
                                        <div>
                                            <p>Bill: <span class="font-bold text-slate-900">{{ $invoice->invoice_number }}</span></p>
                                            <p>Cart: <span class="font-bold text-slate-900">{{ $cart->cart_number }}</span></p>
                                        </div>
                                        <div class="text-right">
                                            <p>Date: <span class="font-bold text-slate-900">{{ $businessDate->format('d M Y') }}</span></p>
                                        </div>
                                    </div>
                                    
                                    <div class="py-2 text-[9px] text-slate-700 border-b border-dashed border-slate-300">
                                        <p class="font-black text-slate-950">{{ $supplier?->name }}</p>
                                        <p class="mt-0.5 text-slate-600">{{ $supplier?->mobile_number }}</p>
                                    </div>
                                    
                                    <table class="w-full text-left text-[9px] border-b border-dashed border-slate-300 py-1.5">
                                        <thead>
                                            <tr class="border-b border-dashed border-slate-200 font-bold text-slate-800">
                                                <th class="py-1">Item</th>
                                                <th class="py-1 text-right">Qty</th>
                                                <th class="py-1 text-right">Amt</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($cart->items as $item)
                                                <tr>
                                                    <td class="py-1">{{ $item->product->name }}</td>
                                                    <td class="py-1 text-right">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }}</td>
                                                    <td class="py-1 text-right">₹{{ number_format((float) $item->line_total, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    
                                    <div class="pt-2 text-[9px] space-y-1 text-slate-800">
                                        <div class="flex justify-between font-bold text-slate-900">
                                            <span>Total</span>
                                            <span>₹{{ number_format($payableTotal, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-emerald-700 font-bold">
                                            <span>Paid</span>
                                            <span>₹{{ number_format($paidAmount, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-amber-700 font-bold">
                                            <span>Balance</span>
                                            <span>₹{{ number_format($balanceAmount, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                        <div class="text-[10px] font-bold text-slate-500">
                            Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($cart->purchaseInvoice)
                                <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="inline-flex h-8 items-center rounded-lg border border-teal-200 bg-teal-50 px-3 text-[10px] font-black text-teal-700 hover:bg-teal-100">
                                    View Full Bill
                                </a>
                            @endif
                            @if ($warehouseConfirmed && $cart->supplier)
                                <a href="{{ route('purchaser.suppliers.show', ['supplier' => $cart->supplier, 'date' => $date]) }}" class="inline-flex h-8 items-center rounded-lg border border-slate-200 bg-slate-950 px-3 text-[10px] font-black text-white hover:bg-slate-800">
                                    Update in Vendor Hub
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">No pending submitted carts for this business day.</p>
            @endforelse
        </div>

        <div id="section-completed" class="hidden space-y-3">
            @forelse ($completedCarts as $cart)
                @php
                    $receiptNotes = $relatedReceiptNotes[$cart->id] ?? '';
                    $receiptDiscrepancy = $relatedReceiptDiscrepancies[$cart->id] ?? null;
                @endphp
                <article id="cart-card-{{ $cart->id }}" class="rounded-2xl border {{ $focusCartId === $cart->id ? 'border-teal-300 ring-2 ring-teal-100' : 'border-slate-200' }} bg-white p-3 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }}</p>
                            <h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                            <p class="mt-1 text-xs font-semibold text-slate-600">Bill {{ $cart->bill_number ?: 'Pending' }} • Fully settled</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Completed</span>
                    </div>

                    <details class="mt-3 rounded-2xl border border-slate-100 bg-slate-50 p-2">
                        <summary class="cursor-pointer px-2 py-1 text-[10px] font-black text-slate-700">
                            {{ $cart->purchaseInvoice ? 'Edit Qty / Price (Processed Bill)' : 'View Cart Items' }}
                        </summary>
                        <div class="mt-2 border-t border-slate-200/60 pt-2">
                            @if ($cart->purchaseInvoice)
                                <form action="{{ route('purchaser.carts.items.update-all', $cart) }}" method="POST" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    @foreach ($cart->items as $item)
                                        <div class="rounded-xl border border-slate-200 bg-white p-2">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate text-[10px] font-black text-slate-800">{{ $item->product->name }}</span>
                                                <span class="text-[10px] font-black text-slate-900">₹<span id="processed-total-{{ $item->id }}">{{ number_format((float) $item->line_total, 2, '.', '') }}</span></span>
                                            </div>
                                            <div class="mt-2 grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-[9px] font-bold text-slate-500">Qty ({{ $item->product->unit }})</label>
                                                    <input type="number" step="any" min="0.01" name="items[{{ $item->id }}][quantity]" id="processed-qty-{{ $item->id }}" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" oninput="updateProcessedItemTotal({{ $item->id }})" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-center text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                                </div>
                                                <div>
                                                    <label class="text-[9px] font-bold text-slate-500">Price</label>
                                                    <input type="number" step="0.01" min="0.01" name="items[{{ $item->id }}][unit_price]" id="processed-price-{{ $item->id }}" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" oninput="updateProcessedItemTotal({{ $item->id }})" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-center text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    <input type="hidden" name="action" value="processed_update">
                                    <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg bg-teal-600 px-3 text-[10px] font-black text-white hover:bg-teal-500">
                                        Update Qty, Price & Total
                                    </button>
                                </form>
                            @else
                                <div class="space-y-1">
                                    @foreach ($cart->items as $item)
                                        <div class="flex items-center justify-between gap-2 px-2 py-1 text-[10px] font-bold text-slate-600">
                                            <span class="truncate">{{ $item->product->name }}</span>
                                            <span>{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} • ₹{{ number_format((float) $item->line_total, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </details>

                    @if ($receiptNotes !== '')
                        <div class="mt-3 rounded-2xl border border-emerald-100 bg-emerald-50 px-3 py-3 text-xs font-semibold text-emerald-800">
                            Receipt note: {{ $receiptNotes }}
                        </div>
                    @endif

                    @if (filled($receiptDiscrepancy))
                        <div class="mt-3 rounded-2xl border border-blue-200 bg-blue-50 px-3 py-3 text-xs font-semibold text-blue-800">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-blue-700">Delivery Discrepancy</p>
                            <p class="mt-1 whitespace-pre-line">{{ $receiptDiscrepancy }}</p>
                        </div>
                    @endif

                    <!-- Embedded Bill Receipt (Collapsed as Default) -->
                    @if ($cart->purchaseInvoice)
                        <details class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-900">
                            <summary class="cursor-pointer px-2 py-1 text-[10px] font-black text-cyan-700 hover:text-cyan-600 select-none">
                                View Matched Bill Invoice
                            </summary>
                            <div class="mt-3 border-t border-dashed border-slate-300 pt-3 px-1">
                                @php
                                    $invoice = $cart->purchaseInvoice;
                                    $payableTotal = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
                                    $paidAmount = (float) $invoice->paid_amount;
                                    $balanceAmount = max(0, $payableTotal - $paidAmount);
                                    $paymentMethod = $invoice->payment_method ?: 'Credit';
                                    $supplier = $invoice->supplier;
                                    $businessDate = $cart->business_date;

                                    $statusRibbonText = match(true) {
                                        $invoice->status->value === 'paid' => 'PAID',
                                        $balanceAmount <= 0 => 'PAID',
                                        $paidAmount > 0 => 'PARTIAL',
                                        default => 'UNPAID',
                                    };

                                    $statusRibbonColor = match($statusRibbonText) {
                                        'PAID' => 'text-emerald-700 bg-emerald-100 border border-emerald-200',
                                        'PARTIAL' => 'text-cyan-700 bg-cyan-100 border border-cyan-200',
                                        default => 'text-amber-700 bg-amber-100 border border-amber-200',
                                    };
                                @endphp
                                
                                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                                    <div class="flex items-center justify-between border-b border-dashed border-slate-300 pb-2">
                                        <div>
                                            <p class="text-[9px] font-black text-slate-900 uppercase">BILL INVOICE</p>
                                            <p class="text-[8px] font-bold text-slate-500">GREEN LEAF</p>
                                        </div>
                                        <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-wider {{ $statusRibbonColor }}">
                                            {{ $statusRibbonText }}
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 py-2 text-[9px] text-slate-700 border-b border-dashed border-slate-300">
                                        <div>
                                            <p>Bill: <span class="font-bold text-slate-900">{{ $invoice->invoice_number }}</span></p>
                                            <p>Cart: <span class="font-bold text-slate-900">{{ $cart->cart_number }}</span></p>
                                        </div>
                                        <div class="text-right">
                                            <p>Date: <span class="font-bold text-slate-900">{{ $businessDate->format('d M Y') }}</span></p>
                                        </div>
                                    </div>
                                    
                                    <div class="py-2 text-[9px] text-slate-700 border-b border-dashed border-slate-300">
                                        <p class="font-black text-slate-950">{{ $supplier?->name }}</p>
                                        <p class="mt-0.5 text-slate-600">{{ $supplier?->mobile_number }}</p>
                                    </div>
                                    
                                    <table class="w-full text-left text-[9px] border-b border-dashed border-slate-300 py-1.5">
                                        <thead>
                                            <tr class="border-b border-dashed border-slate-200 font-bold text-slate-800">
                                                <th class="py-1">Item</th>
                                                <th class="py-1 text-right">Qty</th>
                                                <th class="py-1 text-right">Amt</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($cart->items as $item)
                                                <tr>
                                                    <td class="py-1">{{ $item->product->name }}</td>
                                                    <td class="py-1 text-right">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }}</td>
                                                    <td class="py-1 text-right">₹{{ number_format((float) $item->line_total, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    
                                    <div class="pt-2 text-[9px] space-y-1 text-slate-800">
                                        <div class="flex justify-between font-bold text-slate-900">
                                            <span>Total</span>
                                            <span>₹{{ number_format($payableTotal, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-emerald-700 font-bold">
                                            <span>Paid</span>
                                            <span>₹{{ number_format($paidAmount, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-amber-700 font-bold">
                                            <span>Balance</span>
                                            <span>₹{{ number_format($balanceAmount, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                        <div class="text-[10px] font-bold text-slate-500">
                            Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}
                        </div>
                        @if ($cart->purchaseInvoice)
                            <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="inline-flex h-8 items-center rounded-lg border border-teal-200 bg-teal-50 px-3 text-[10px] font-black text-teal-700 hover:bg-teal-100">
                                View Full Bill
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">No completed carts for this business day.</p>
            @endforelse
        </div>

        <div id="section-cancelled" class="hidden space-y-3">
            @forelse ($cancelledCarts as $cart)
                <article id="cart-card-{{ $cart->id }}" class="rounded-2xl border {{ $focusCartId === $cart->id ? 'border-rose-300 ring-2 ring-rose-100' : 'border-slate-200' }} bg-white p-3 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }} · Grade {{ $cart->purchase_grade ?? 'A' }}</p>
                            <h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $cart->business_date?->format('d M Y') }}</p>
                        </div>
                        <span class="rounded-full bg-rose-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">Cancelled</span>
                    </div>

                    @if ($cart->items->isNotEmpty())
                        <div class="mt-3 space-y-1 rounded-2xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Items ({{ $cart->items->count() }})</p>
                            <div class="divide-y divide-slate-200/60">
                                @foreach ($cart->items as $item)
                                    <div class="flex items-center justify-between gap-2 py-1.5 text-xs">
                                        <span class="font-bold text-slate-800">{{ $item->product?->name ?? 'Unknown' }}</span>
                                        <span class="font-semibold text-slate-600">
                                            {{ (float) $item->quantity }} {{ $item->product?->unit ?? '' }}
                                            @if ((float) $item->unit_price > 0)
                                                · ₹{{ number_format((float) $item->unit_price, 2) }}
                                            @endif
                                            @if ((float) $item->line_total > 0)
                                                = <span class="font-bold text-slate-900">₹{{ number_format((float) $item->line_total, 2) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="mt-3 text-xs font-semibold text-slate-400">No items recorded in this cart.</p>
                    @endif

                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-[10px] font-bold text-slate-500">
                        <span>Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                        <span class="text-rose-600 font-bold">Order Cancelled</span>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">No cancelled carts for this business day.</p>
            @endforelse
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
                <button type="button" onclick="closeCartShareModal()" class="text-slate-400 hover:text-slate-600">✕</button>
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
                    ? 'rounded-xl bg-white py-2 text-center text-[10px] font-black text-slate-950 shadow-sm sm:text-xs'
                    : 'rounded-xl py-2 text-center text-[10px] font-black text-slate-500 sm:text-xs';
            });

            Object.entries(tabSections).forEach(([key, section]) => {
                if (! section) {
                    return;
                }

                section.classList.toggle('hidden', key !== tab);
            });
        }

        function updateCartItemTotal(itemId) {
            const quantityInput = document.getElementById(`quantity-${itemId}`);
            const priceInput = document.getElementById(`price-${itemId}`);
            const totalNode = document.getElementById(`total-${itemId}`);

            if (! quantityInput || ! priceInput || ! totalNode) {
                return;
            }

            const quantity = Number(quantityInput.value || 0);
            const price = Number(priceInput.value || 0);
            totalNode.textContent = (quantity * price).toFixed(2);
        }

        function updateProcessedItemTotal(itemId) {
            const quantityInput = document.getElementById(`processed-qty-${itemId}`);
            const priceInput = document.getElementById(`processed-price-${itemId}`);
            const totalNode = document.getElementById(`processed-total-${itemId}`);

            if (! quantityInput || ! priceInput || ! totalNode) {
                return;
            }

            const quantity = Number(quantityInput.value || 0);
            const price = Number(priceInput.value || 0);
            totalNode.textContent = (quantity * price).toFixed(2);
        }

        function confirmDeleteItem(itemId, actionUrl) {
            if (confirm('Are you sure you want to remove this item?')) {
                const form = document.getElementById('delete-item-form');
                form.action = actionUrl;
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
