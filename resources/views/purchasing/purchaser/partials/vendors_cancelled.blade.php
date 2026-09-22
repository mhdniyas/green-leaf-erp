@forelse ($cancelledInvoices as $invoice)
    <article id="invoice-card-{{ $invoice->id }}" class="rounded-2xl border border-rose-200 bg-white p-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $invoice->invoice_number }}</p>
                    @if ($invoice->purchaserCart)
                        <span class="text-[9px] font-semibold text-slate-400">· Cart {{ $invoice->purchaserCart->cart_number }}</span>
                    @endif
                </div>
                <h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ $invoice->supplier?->name ?: 'Supplier pending' }}</h3>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Cancelled {{ $invoice->cancelled_at?->format('d M Y, h:i A') ?: ($invoice->deleted_at?->format('d M Y, h:i A') ?: '') }}
                    @if ($invoice->cancelledBy)
                        by {{ $invoice->cancelledBy->name }}
                    @endif
                </p>
                @if ($invoice->cancellation_note || $invoice->cancellation_reason)
                    <p class="mt-0.5 text-[11px] font-medium text-rose-600">
                        Reason: {{ $invoice->cancellation_note ?: $invoice->cancellation_reason }}
                    </p>
                @endif
            </div>
            <span class="rounded-full bg-rose-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">Bill Cancelled</span>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-[10px] font-bold text-slate-500">
            <span>Total: ₹{{ number_format((float) $invoice->amount, 2) }}</span>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchaser.invoices.show', $invoice) }}" class="inline-flex h-7 items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[10px] font-bold text-slate-700 hover:bg-slate-100">
                    <svg class="h-3 w-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    View Cancelled Bill
                </a>
                <span class="font-bold text-rose-600">Cancelled</span>
            </div>
        </div>
    </article>
@empty
@endforelse

@forelse ($cancelledCarts as $cart)
    <article id="cart-card-{{ $cart->id }}" class="rounded-2xl border {{ ($focusCartId ?? null) === $cart->id ? 'border-rose-300 ring-2 ring-rose-100' : 'border-slate-200' }} bg-white p-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }} · Grade {{ $cart->purchase_grade ?? 'A' }}</p>
                <h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $cart->business_date?->format('d M Y') }}</p>
            </div>
            <span class="rounded-full bg-rose-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">Cancelled</span>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-[10px] font-bold text-slate-500">
            <span>Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
            <span class="text-rose-600 font-bold">Order Cancelled</span>
        </div>
    </article>
@empty
    @if ($cancelledInvoices->isEmpty())
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">No cancelled carts or bills for this business day.</p>
    @endif
@endforelse

@if ($cancelledInvoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $cancelledInvoices->hasPages())
    <div class="mt-4">
        {{ $cancelledInvoices->links() }}
    </div>
@endif
