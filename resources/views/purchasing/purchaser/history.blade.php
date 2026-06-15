<x-layouts.app title="Purchaser Report">
    @php
        $reportTabs = [
            'draft' => [
                'label' => 'Draft',
                'tone' => 'bg-slate-100 text-slate-700',
                'carts' => $groupedCarts['draft'],
                'description' => 'Carts still being prepared before supplier confirmation.',
                'empty' => 'No draft carts yet.',
            ],
            'processing' => [
                'label' => 'Processing',
                'tone' => 'bg-teal-100 text-teal-700',
                'carts' => $groupedCarts['whatsapp_sent']->concat($groupedCarts['submitted'])->sortByDesc(fn ($cart) => $cart->submitted_at ?? $cart->updated_at)->values(),
                'description' => 'Supplier-shared and submitted purchases that are still moving through the daily flow.',
                'empty' => 'No processing carts for this date.',
            ],
            'completed' => [
                'label' => 'Completed',
                'tone' => 'bg-emerald-100 text-emerald-700',
                'carts' => $groupedCarts['approved']->concat($groupedCarts['rejected'])->sortByDesc(fn ($cart) => $cart->submitted_at ?? $cart->updated_at)->values(),
                'description' => 'Approved and closed daily purchases, including anything sent back for recheck.',
                'empty' => 'No completed carts for this date.',
            ],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 5</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Purchase report</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Read the daily purchase flow fast: draft first, processing next, completed last.</p>
                </div>
                <form action="{{ route('purchaser.history') }}" method="GET">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                </form>
            </div>
        </section>

        <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-100 p-1 shadow-sm">
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
                        <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format($tab['carts']->sum(fn ($cart) => $cart->items->sum('line_total') - $cart->discount_amount), 2) }}</p>
                    </div>
                </div>

                @forelse ($tab['carts'] as $cart)
                    @php
                        $statusTone = match ($cart->workflow_status) {
                            'approved' => 'bg-emerald-100 text-emerald-700',
                            'rejected' => 'bg-rose-100 text-rose-700',
                            'submitted' => 'bg-teal-100 text-teal-700',
                            'whatsapp_sent' => 'bg-blue-100 text-blue-700',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    @endphp

                    <article class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-black text-slate-950">{{ $cart->cart_number }}</p>
                                    <span class="rounded-full {{ $statusTone }} px-2.5 py-0.5 text-[9px] font-black uppercase tracking-[0.14em]">{{ $cart->workflow_label }}</span>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ $cart->supplier?->name ?: 'Draft Cart' }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">Amount</p>
                                <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Bill</p>
                                <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $cart->bill_number ?: 'Missing' }}</p>
                            </div>
                            <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Payment</p>
                                <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $cart->payment_method ?: 'Pending' }}</p>
                            </div>
                            <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Items</p>
                                <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $cart->items->count() }} lines</p>
                            </div>
                        </div>

                        <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-1.5">
                            <summary class="cursor-pointer px-1.5 py-1 text-[10px] font-black text-slate-700 select-none">View report details</summary>
                            <div class="mt-1.5 space-y-1 border-t border-slate-200/60 pt-1.5">
                                @foreach ($cart->items as $item)
                                    <div class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-slate-100/60 bg-white px-2 py-1.5 text-[10px] font-bold text-slate-600">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="truncate font-black text-slate-900">{{ $item->product->name }}</span>
                                                @if ($item->is_extra_purchase)
                                                    <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-[0.12em] text-amber-700">Extra</span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="shrink-0 text-[9px] text-slate-500">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} @ ₹{{ number_format((float) $item->unit_price, 2) }}</span>
                                        <span class="shrink-0 font-black text-slate-900">₹{{ number_format((float) $item->line_total, 2) }}</span>
                                    </div>
                                @endforeach

                                <div class="grid grid-cols-3 gap-1.5 pt-1">
                                    <div class="min-w-0 rounded-md border border-slate-100 bg-white p-1.5">
                                        <p class="text-[8px] font-black uppercase tracking-[0.14em] text-slate-400">P.O.</p>
                                        <p class="mt-0.5 truncate text-[10px] font-black text-slate-900">{{ $cart->purchaseOrder?->po_number ?: 'Pending' }}</p>
                                    </div>
                                    <div class="min-w-0 rounded-md border border-slate-100 bg-white p-1.5">
                                        <p class="text-[8px] font-black uppercase tracking-[0.14em] text-slate-400">GRN</p>
                                        <p class="mt-0.5 truncate text-[10px] font-black text-slate-900">{{ $cart->goodsReceived?->grn_number ?: 'Pending' }}</p>
                                    </div>
                                    <div class="min-w-0 rounded-md border border-slate-100 bg-white p-1.5">
                                        <p class="text-[8px] font-black uppercase tracking-[0.14em] text-slate-400">Invoice</p>
                                        <p class="mt-0.5 truncate text-[10px] font-black text-slate-900">{{ $cart->purchaseInvoice?->invoice_number ?: 'Pending' }}</p>
                                    </div>
                                </div>
                            </div>
                        </details>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($cart->status === 'draft')
                                <a href="{{ $cart->whatsapp_sent_at ? route('purchaser.bill', ['cart' => $cart, 'date' => $date]) : route('purchaser.vendors', ['date' => $date]) }}" class="flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-[11px] font-black text-slate-700">
                                    {{ $cart->whatsapp_sent_at ? 'Process Bill' : 'Continue Cart' }}
                                </a>
                            @endif

                            @if ($cart->status === 'submitted')
                                <span class="{{ $cart->bill_received_at ? 'border-emerald-200 bg-emerald-100 text-emerald-700' : 'border-slate-200 bg-white text-slate-700' }} inline-flex h-9 items-center justify-center rounded-xl border px-3 text-[11px] font-black">
                                    Bill Received{{ $cart->bill_received_at ? ' ✓' : '' }}
                                </span>
                                @if ($cart->purchaseInvoice)
                                    <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="flex h-9 items-center justify-center rounded-xl border border-teal-200 bg-teal-50 px-3 text-[11px] font-black text-teal-700">
                                        View Bill
                                    </a>
                                @endif
                                <a href="{{ route('purchaser.finance', ['date' => $date]) }}" class="flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-950 px-3 text-[11px] font-black text-white">
                                    Update Payment
                                </a>
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
            const tabs = ['draft', 'processing', 'completed'];

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
