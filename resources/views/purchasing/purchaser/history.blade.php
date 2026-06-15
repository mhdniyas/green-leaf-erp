<x-layouts.app title="Purchaser History">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 5</p>
                    <h1 class="mt-2 text-2xl font-black text-slate-950">Purchase history</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Track today&apos;s vendor carts, see submission state, and mark operational completion.</p>
                </div>
                <form action="{{ route('purchaser.history') }}" method="GET">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-12 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                </form>
            </div>
        </section>

        <div class="space-y-5">
            @foreach ([
                'draft' => ['label' => 'Draft', 'tone' => 'bg-slate-100 text-slate-700'],
                'whatsapp_sent' => ['label' => 'WhatsApp Sent', 'tone' => 'bg-blue-100 text-blue-700'],
                'submitted' => ['label' => 'Submitted', 'tone' => 'bg-teal-100 text-teal-700'],
                'approved' => ['label' => 'Approved', 'tone' => 'bg-emerald-100 text-emerald-700'],
                'rejected' => ['label' => 'Rejected', 'tone' => 'bg-rose-100 text-rose-700'],
            ] as $status => $meta)
                <section class="space-y-3">
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-black text-slate-950">{{ $meta['label'] }}</h2>
                        <span class="rounded-full {{ $meta['tone'] }} px-2.5 py-0.5 text-[10px] font-black uppercase tracking-[0.14em]">{{ $groupedCarts[$status]->count() }}</span>
                        @if ($groupedCarts[$status]->isNotEmpty())
                            <span class="text-xs font-bold text-slate-500">• ₹{{ number_format($groupedCarts[$status]->sum(fn($c) => $c->items->sum('line_total') - $c->discount_amount), 2) }}</span>
                        @endif
                    </div>

                    @forelse ($groupedCarts[$status] as $cart)
                        <article class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                            {{-- Cart header --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-black text-slate-950">{{ $cart->cart_number }}</p>
                                <span class="rounded-full {{ $meta['tone'] }} px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em]">{{ $cart->workflow_label }}</span>
                            </div>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ $cart->supplier?->name ?: 'Vendor pending' }}</p>

                            {{-- Stats: always 3-col --}}
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div class="min-w-0 rounded-2xl bg-slate-50 px-3 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Bill</p>
                                    <p class="mt-1 truncate text-sm font-black text-slate-900">{{ $cart->bill_number ?: 'Missing' }}</p>
                                </div>
                                <div class="min-w-0 rounded-2xl bg-slate-50 px-3 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Payment</p>
                                    <p class="mt-1 truncate text-sm font-black text-slate-900">{{ $cart->payment_method ?: 'Pending' }}</p>
                                </div>
                                <div class="min-w-0 rounded-2xl bg-slate-50 px-3 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Amount</p>
                                    <p class="mt-1 truncate text-sm font-black text-slate-900">₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</p>
                                </div>
                            </div>

                            {{-- Detail accordion --}}
                            <details class="mt-2 rounded-lg bg-slate-50 border border-slate-100 p-1.5">
                                <summary class="cursor-pointer text-[10px] font-black text-slate-700 py-1 px-1.5 select-none">View details</summary>
                                <div class="mt-1.5 space-y-1 border-t border-slate-200/60 pt-1.5">
                                    @foreach ($cart->items as $item)
                                        <div class="flex min-w-0 items-center justify-between gap-2 px-1.5 py-1 text-[10px] font-bold text-slate-600 bg-white rounded-md border border-slate-100/50">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="truncate font-black text-slate-900">{{ $item->product->name }}</span>
                                                    @if ($item->is_extra_purchase)
                                                        <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-[0.12em] text-amber-700">Extra</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="shrink-0 text-slate-500 text-[9px]">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} @ ₹{{ number_format((float) $item->unit_price, 2) }}</span>
                                            <span class="shrink-0 font-black text-slate-900 ml-1">₹{{ number_format((float) $item->line_total, 2) }}</span>
                                        </div>
                                    @endforeach

                                    <div class="grid grid-cols-3 gap-1.5 pt-1">
                                        <div class="min-w-0 rounded-md bg-white p-1.5 border border-slate-100">
                                            <p class="text-[8px] font-black uppercase tracking-[0.14em] text-slate-400">P.O.</p>
                                            <p class="mt-0.5 truncate text-[10px] font-black text-slate-900">{{ $cart->purchaseOrder?->po_number ?: 'Pending' }}</p>
                                        </div>
                                        <div class="min-w-0 rounded-md bg-white p-1.5 border border-slate-100">
                                            <p class="text-[8px] font-black uppercase tracking-[0.14em] text-slate-400">GRN</p>
                                            <p class="mt-0.5 truncate text-[10px] font-black text-slate-900">{{ $cart->goodsReceived?->grn_number ?: 'Pending' }}</p>
                                        </div>
                                        <div class="min-w-0 rounded-md bg-white p-1.5 border border-slate-100">
                                            <p class="text-[8px] font-black uppercase tracking-[0.14em] text-slate-400">Invoice</p>
                                            <p class="mt-0.5 truncate text-[10px] font-black text-slate-900">{{ $cart->purchaseInvoice?->invoice_number ?: 'Pending' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </details>

                            {{-- Actions --}}
                            <div class="mt-3 flex flex-wrap gap-3">
                                @if ($cart->status === 'draft')
                                    <a href="{{ $cart->whatsapp_sent_at ? route('purchaser.bill', ['cart' => $cart, 'date' => $date]) : route('purchaser.vendors', ['date' => $date]) }}" class="flex h-11 items-center justify-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700">
                                        {{ $cart->whatsapp_sent_at ? 'Process Bill' : 'Continue Cart' }}
                                    </a>
                                @endif

                                @if ($cart->status === 'submitted')
                                    @foreach ([
                                        'goods_received' => ['label' => 'Goods Received', 'active' => $cart->goods_received_at],
                                        'bill_received' => ['label' => 'Bill Received', 'active' => $cart->bill_received_at],
                                        'payment_made' => ['label' => 'Payment Made', 'active' => $cart->payment_made_at],
                                    ] as $flag => $flagMeta)
                                        <form action="{{ route('purchaser.carts.status', $cart) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="flag" value="{{ $flag }}">
                                            <button type="submit" class="{{ $flagMeta['active'] ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-white text-slate-700 border-slate-200' }} flex h-11 items-center justify-center rounded-2xl border px-4 text-sm font-black">
                                                {{ $flagMeta['label'] }}{{ $flagMeta['active'] ? ' ✓' : '' }}
                                            </button>
                                        </form>
                                    @endforeach
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4">
                            @if ($status === 'draft')
                                No draft carts yet.
                            @elseif ($status === 'whatsapp_sent')
                                No WhatsApp-sent carts for this date.
                            @elseif ($status === 'submitted')
                                No submitted purchases yet for this date.
                            @elseif ($status === 'approved')
                                No approved purchases yet for this date.
                            @else
                                No rejected purchases for this date.
                            @endif
                        </div>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
