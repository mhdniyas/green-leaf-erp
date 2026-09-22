@forelse ($cancelledInvoices as $invoice)
    <article id="invoice-card-{{ $invoice->id }}" class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-sm transition-all duration-200 hover:shadow-md">
        {{-- Accordion Trigger Header --}}
        <div
            onclick="toggleCartCollapse('inv-{{ $invoice->id }}')"
            class="flex cursor-pointer items-center justify-between gap-3 bg-white p-4 transition hover:bg-slate-50/80 sm:px-5 select-none"
        >
            <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="truncate text-sm font-black text-slate-900">{{ $invoice->supplier?->name ?: 'Supplier pending' }}</h3>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-800">
                            Cancelled
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-500">
                        Bill: {{ $invoice->invoice_number }} @if ($invoice->purchaserCart) • Cart: {{ $invoice->purchaserCart->cart_number }} @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <div class="text-right">
                    <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                    <p class="font-mono text-sm font-black text-slate-900 sm:text-base">
                        ₹{{ number_format((float) $invoice->amount, 2) }}
                    </p>
                    <p class="text-[10px] font-bold text-rose-600">Cancelled</p>
                </div>
                <div id="chevron-inv-{{ $invoice->id }}" class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-transform duration-200">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                </div>
            </div>
        </div>

        {{-- Collapsible Body --}}
        <div id="cart-body-inv-{{ $invoice->id }}" class="hidden border-t border-dashed border-slate-200">
            {{-- Bill Header Info --}}
            <div class="border-b border-dashed border-slate-300 bg-slate-50/40 px-4 pt-4 pb-3 sm:px-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">BILL NO</p>
                        <p class="text-sm font-black text-slate-900">{{ $invoice->invoice_number }}</p>
                        <div class="mt-1 space-y-0.5 text-[11px] font-semibold text-slate-500">
                            @if ($invoice->purchaserCart)
                                <p>Cart: {{ $invoice->purchaserCart->cart_number }}</p>
                            @endif
                            <p>Date: {{ $invoice->business_date?->format('d M Y') ?: ($invoice->created_at?->format('d M Y') ?: date('d M Y')) }}</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-rose-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">
                        Bill Cancelled
                    </span>
                </div>
            </div>

            {{-- Vendor Info Section --}}
            <div class="border-b border-dashed border-slate-300 bg-rose-50/30 px-4 py-3 sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">VENDOR</p>
                <p class="mt-0.5 text-sm font-black text-slate-900">{{ $invoice->supplier?->name ?: 'Supplier pending' }}</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">
                    Cancelled {{ $invoice->cancelled_at?->format('d M Y, h:i A') ?: ($invoice->deleted_at?->format('d M Y, h:i A') ?: '') }}
                    @if ($invoice->cancelledBy)
                        by {{ $invoice->cancelledBy->name }}
                    @endif
                </p>
                @if ($invoice->cancellation_note || $invoice->cancellation_reason)
                    <p class="mt-1 text-xs font-bold text-rose-600">
                        Reason: {{ $invoice->cancellation_note ?: $invoice->cancellation_reason }}
                    </p>
                @endif
            </div>

            {{-- Footer / Total --}}
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-6">
                <span class="font-mono text-xs font-black text-slate-900">Total: ₹{{ number_format((float) $invoice->amount, 2) }}</span>
                <div class="flex items-center gap-2">
                    <a href="{{ route('purchaser.invoices.show', $invoice) }}" class="inline-flex h-9 items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        View Cancelled Bill
                    </a>
                </div>
            </div>
        </div>
    </article>
@empty
@endforelse

@forelse ($cancelledCarts as $cart)
    <article id="cart-card-{{ $cart->id }}" class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-sm transition-all duration-200 hover:shadow-md">
        {{-- Accordion Trigger Header --}}
        <div
            onclick="toggleCartCollapse({{ $cart->id }})"
            class="flex cursor-pointer items-center justify-between gap-3 bg-white p-4 transition hover:bg-slate-50/80 sm:px-5 select-none"
        >
            <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="truncate text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-800">
                            Draft Cancelled
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-500">
                        Cart: {{ $cart->cart_number }} • Grade {{ $cart->purchase_grade ?? 'A' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <div class="text-right">
                    <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                    <p class="font-mono text-sm font-black text-slate-900 sm:text-base">
                        ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}
                    </p>
                    <p class="text-[10px] font-bold text-rose-600">Cancelled</p>
                </div>
                <div id="chevron-{{ $cart->id }}" class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-transform duration-200">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                </div>
            </div>
        </div>

        {{-- Collapsible Body --}}
        <div id="cart-body-{{ $cart->id }}" class="hidden border-t border-dashed border-slate-200">
            {{-- Bill Header Info --}}
            <div class="border-b border-dashed border-slate-300 bg-slate-50/40 px-4 pt-4 pb-3 sm:px-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">BILL NO</p>
                        <p class="text-sm font-black text-slate-900">Cancelled Draft</p>
                        <div class="mt-1 space-y-0.5 text-[11px] font-semibold text-slate-500">
                            <p>Cart: {{ $cart->cart_number }}</p>
                            <p>Date: {{ $cart->business_date?->format('d M Y') ?: (isset($selectedDate) ? $selectedDate->format('d M Y') : date('d M Y')) }}</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-rose-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">
                        Draft Cancelled
                    </span>
                </div>
            </div>

            {{-- Vendor Banner --}}
            <div class="border-b border-dashed border-slate-300 bg-rose-50/30 px-4 py-3 sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">VENDOR</p>
                <p class="mt-0.5 text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'Supplier pending' }}</p>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-6 text-xs">
                <span class="font-mono font-black text-slate-900">Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                <span class="font-bold text-rose-600">Order Cancelled</span>
            </div>
        </div>
    </article>
@empty
    @if ($cancelledInvoices->isEmpty())
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">No cancelled carts or bills for this business day.</p>
    @endif
@endforelse

@if ($cancelledInvoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $cancelledInvoices->hasPages())
    <div class="mt-4">
        {{ $cancelledInvoices->links() }}
    </div>
@endif
