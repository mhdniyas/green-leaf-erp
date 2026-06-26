<x-layouts.app title="Purchaser Report">
    @php
        $reportTabs = [
            'today' => [
                'label' => 'Today',
                'tone' => 'bg-teal-100 text-teal-700',
                'carts' => $groupedCarts['today'],
                'description' => 'Purchases and active orders for the selected operational date.',
                'empty' => 'No purchases for this date.',
            ],
            'history' => [
                'label' => 'History',
                'tone' => 'bg-slate-100 text-slate-700',
                'carts' => $groupedCarts['history'],
                'description' => 'All historical and overdue purchases from previous business days.',
                'empty' => 'No historical purchases found.',
            ],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 5</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Purchase report</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Track overdue action, warehouse progress, payment pending, and completed purchases from one mobile report.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <a href="{{ route('purchaser.suppliers', ['date' => $date]) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-white">
                        <span>Vendor Hub</span>
                        @if (($deadlineAlert['pending_total_count'] ?? 0) > 0)
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-black text-rose-700">
                                {{ $deadlineAlert['pending_total_count'] }}
                            </span>
                        @endif
                    </a>
                    <form action="{{ route('purchaser.history') }}" method="GET">
                        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                    </form>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1 shadow-sm">
            @foreach ($reportTabs as $tabKey => $tab)
                <button type="button" id="report-tab-btn-{{ $tabKey }}" onclick="switchReportTab('{{ $tabKey }}')" class="{{ $loop->first ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-700' }} rounded-lg px-2 py-2 text-center transition-all duration-150">
                    <span class="block text-[10px] font-black uppercase tracking-[0.14em]">{{ $tab['label'] }}</span>
                    <span class="mt-0.5 block text-[9px] font-bold">{{ $tab['carts']->count() }} carts</span>
                </button>
            @endforeach
        </div>

        @foreach ($reportTabs as $tabKey => $tab)
            <section id="report-section-{{ $tabKey }}" class="{{ $loop->first ? '' : 'hidden' }} space-y-3">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm lg:rounded-[2rem] lg:px-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-black text-slate-950">{{ $tab['label'] }}</h2>
                            <span class="rounded-full {{ $tab['tone'] }} px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em]">{{ $tab['carts']->count() }}</span>
                        </div>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $tab['description'] }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">Total</p>
                        <p class="mt-1 text-sm font-black text-slate-950">
                            ₹{{ number_format($tab['carts']->sum(function ($cart) use ($relatedBatchState) {
                                if ($cart->status === 'draft') {
                                    return (float) $cart->items->sum('line_total') - (float) $cart->discount_amount;
                                }
                                $batchState = $relatedBatchState[$cart->id] ?? [];
                                if (! ($batchState['warehouse_confirmed'] ?? false)) {
                                    return 0;
                                }
                                if ($cart->purchaseInvoice) {
                                    return max(0.0, (float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount);
                                }
                                return max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
                            }), 2) }}
                        </p>
                    </div>
                </div>

                @forelse ($tab['carts'] as $cart)
                    @php
                        $badge = $statusBadges[$cart->id] ?? ['label' => 'Pending', 'tone' => 'bg-slate-100 text-slate-700'];
                        $receiptNotes = $relatedReceiptNotes[$cart->id] ?? null;
                    @endphp
                    <article class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-black text-slate-950">{{ $cart->cart_number }}</p>
                                    <span class="rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ $cart->supplier?->name ?: 'Vendor pending' }} • {{ $cart->business_date->format('d M Y') }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">Amount</p>
                                <p class="mt-1 text-sm font-black text-slate-950">
                                    ₹{{ number_format($cart->purchaseInvoice ? ((float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount) : ((float) $cart->items->sum('line_total') - (float) $cart->discount_amount), 2) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Bill</p>
                                <p class="mt-1 truncate text-[11px] font-black text-slate-900">
                                    {{ $cart->bill_number ?: ($cart->purchaseInvoice && !str_starts_with($cart->purchaseInvoice->invoice_number, 'PENDING-BILL-') ? $cart->purchaseInvoice->invoice_number : ($cart->payment_status === 'paid' ? 'Paid' : 'Pending')) }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Payment</p>
                                <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ str($cart->payment_status ?: 'unpaid')->replace('_', ' ')->title() }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">GRN</p>
                                <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $cart->goodsReceived?->grn_number ?: 'Pending' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Invoice</p>
                                <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $cart->purchaseInvoice?->invoice_number ?: 'Pending' }}</p>
                            </div>
                        </div>

                        @if (filled($receiptNotes))
                            <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Receipt Notes</p>
                                <p class="mt-1 whitespace-pre-line text-[11px] font-semibold text-slate-700">{{ $receiptNotes }}</p>
                            </div>
                        @endif

                        <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-1.5">
                            <summary class="cursor-pointer px-1.5 py-1 text-[10px] font-black text-slate-700 select-none">View line items</summary>
                            <div class="mt-1.5 space-y-1 border-t border-slate-200/60 pt-1.5">
                                @foreach ($cart->items as $item)
                                    <div class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-slate-100/60 bg-white px-2 py-1.5 text-[10px] font-bold text-slate-600">
                                        <span class="truncate font-black text-slate-900">{{ $item->product->name }}</span>
                                        <span class="shrink-0">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} @ ₹{{ number_format((float) $item->unit_price, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </details>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($cart->status === 'draft')
                                <a href="{{ route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d'), 'tab' => $cart->goods_received_at ? 'delivered' : 'orders', 'focus_cart' => $cart->id]) }}" class="flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-[11px] font-black text-slate-700 hover:bg-slate-50">
                                    Continue Cart
                                </a>
                            @elseif ($cart->purchaseInvoice)
                                @if (in_array($cart->payment_status ?: 'unpaid', ['unpaid', 'partial', 'credit_pending_approval'], true))
                                    <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="flex h-9 items-center justify-center rounded-xl bg-slate-950 px-3 text-[11px] font-black text-white hover:bg-slate-800">
                                        Update Payment
                                    </a>
                                @else
                                    <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="flex h-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-[11px] font-black text-emerald-700 hover:bg-emerald-100">
                                        View Bill
                                    </a>
                                @endif
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4">
                        {{ $tab['empty'] }}
                    </div>
                @endforelse
            </section>
        @endforeach
    </div>

    <script>
        function switchReportTab(tab) {
            const tabs = ['today', 'history'];

            tabs.forEach((tabKey) => {
                const button = document.getElementById(`report-tab-btn-${tabKey}`);
                const section = document.getElementById(`report-section-${tabKey}`);

                if (!button || !section) {
                    return;
                }

                if (tabKey === tab) {
                    button.className = 'bg-white text-slate-950 shadow-sm rounded-lg px-2 py-2 text-center transition-all duration-150';
                    section.classList.remove('hidden');
                } else {
                    button.className = 'text-slate-500 hover:text-slate-700 rounded-lg px-2 py-2 text-center transition-all duration-150';
                    section.classList.add('hidden');
                }
            });
        }
    </script>
</x-layouts.app>
