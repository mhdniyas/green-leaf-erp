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
                    <p class="mt-1 text-xs font-semibold text-slate-600">Track overdue action, warehouse progress, payment pending, and completed purchases from a mobile-friendly report table.</p>
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
                        <input type="hidden" name="include_expenses" value="{{ $includeExpenses ? '1' : '0' }}">
                        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                    </form>
                </div>
            </div>
            <div class="mt-3 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                <a href="{{ route('purchaser.history', ['date' => $date, 'include_expenses' => 0]) }}" class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] {{ $includeExpenses ? 'text-slate-600 hover:text-slate-800' : 'bg-white text-slate-900 shadow-sm' }}">
                    Bills Only
                </a>
                <a href="{{ route('purchaser.history', ['date' => $date, 'include_expenses' => 1]) }}" class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] {{ $includeExpenses ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                    + Expenses
                </a>
            </div>
        </section>

        {{-- Monthly Summary (Compact 3-Column Row) --}}
        @if (isset($monthSummary))
            <section class="rounded-xl border border-slate-900 bg-slate-950 px-3 py-2.5 text-white shadow-xs">
                <div class="flex items-center justify-between gap-2 border-b border-slate-800 pb-1.5 text-[10px]">
                    <div class="flex items-center gap-1.5 truncate">
                        <span class="rounded bg-teal-500/20 px-1.5 py-0.5 font-black uppercase text-teal-300">Monthly</span>
                        <span class="truncate font-bold text-slate-300">{{ $monthSummary['month_name'] }}</span>
                    </div>
                    <span class="shrink-0 font-bold text-slate-400">{{ $monthSummary['total_carts'] }} bills</span>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                    <div class="rounded-lg bg-slate-900 px-1 py-1.5">
                        <p class="text-[9px] font-black uppercase tracking-tight text-teal-400 truncate">{{ $includeExpenses ? 'Total Outflow' : 'Total Buy' }}</p>
                        <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-white truncate">₹{{ number_format($includeExpenses ? $monthSummary['grand_total'] : $monthSummary['total_purchase'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-900 px-1 py-1.5">
                        <p class="text-[9px] font-black uppercase tracking-tight text-emerald-400 truncate">Cash Paid</p>
                        <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-emerald-300 truncate">₹{{ number_format($monthSummary['total_cash'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-900 px-1 py-1.5">
                        <p class="text-[9px] font-black uppercase tracking-tight text-amber-400 truncate">{{ $includeExpenses ? 'Expenses' : 'Credit Due' }}</p>
                        <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-amber-300 truncate">₹{{ number_format($includeExpenses ? $monthSummary['expense_total'] : $monthSummary['total_credit'], 2) }}</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- Daily Summary (Compact 3-Column Row) --}}
        <section class="rounded-xl border border-teal-200 bg-teal-50/40 px-3 py-2.5 shadow-xs">
            <div class="flex items-center justify-between gap-2 border-b border-teal-200/60 pb-1.5 text-[10px]">
                <div class="flex items-center gap-1.5 truncate">
                    <span class="rounded bg-teal-600 px-1.5 py-0.5 font-black uppercase text-white">Daily</span>
                    <span class="truncate font-bold text-teal-950">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
                </div>
                <span class="shrink-0 font-bold text-teal-800">{{ $todaySummary['total_carts'] ?? 0 }} bills</span>
            </div>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                <div class="rounded-lg bg-white px-1 py-1.5 border border-slate-200/80 shadow-2xs">
                    <p class="text-[9px] font-black uppercase tracking-tight text-slate-700 truncate">{{ $includeExpenses ? 'Daily Outflow' : 'Daily Buy' }}</p>
                    <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-slate-950 truncate">₹{{ number_format($includeExpenses ? ($todaySummary['grand_total'] ?? 0) : ($todaySummary['total_purchase'] ?? 0), 2) }}</p>
                </div>
                <div class="rounded-lg bg-white px-1 py-1.5 border border-slate-200/80 shadow-2xs">
                    <p class="text-[9px] font-black uppercase tracking-tight text-emerald-700 truncate">Cash Paid</p>
                    <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-emerald-800 truncate">₹{{ number_format($todaySummary['total_cash'] ?? 0, 2) }}</p>
                </div>
                <div class="rounded-lg bg-white px-1 py-1.5 border border-slate-200/80 shadow-2xs">
                    <p class="text-[9px] font-black uppercase tracking-tight text-amber-700 truncate">{{ $includeExpenses ? 'Expenses' : 'Credit Due' }}</p>
                    <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-amber-800 truncate">₹{{ number_format($includeExpenses ? ($todaySummary['expense_total'] ?? 0) : ($todaySummary['total_credit'] ?? 0), 2) }}</p>
                </div>
            </div>
        </section>

        <!-- View Mode & Tab Controls -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="grid grid-cols-2 gap-2 flex-1 rounded-xl bg-slate-100 p-1 shadow-sm">
                @foreach ($reportTabs as $tabKey => $tab)
                    <button type="button" id="report-tab-btn-{{ $tabKey }}" onclick="switchReportTab('{{ $tabKey }}')" class="{{ $loop->first ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-700' }} rounded-lg px-2 py-2 text-center transition-all duration-150">
                        <span class="block text-[10px] font-black uppercase tracking-[0.14em]">{{ $tab['label'] }}</span>
                        <span class="mt-0.5 block text-[9px] font-bold">{{ $tab['carts']->count() }} carts</span>
                    </button>
                @endforeach
            </div>

            <!-- Table / Cards View Switcher -->
            <div class="flex items-center justify-end rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <button type="button" id="view-mode-table-btn" onclick="setReportViewMode('table')" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-950 px-2.5 py-1.5 text-[10px] font-black text-white shadow-xs transition-all sm:px-3">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18" />
                    </svg>
                    Table View
                </button>
                <button type="button" id="view-mode-cards-btn" onclick="setReportViewMode('cards')" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[10px] font-black text-slate-500 transition-all hover:text-slate-800 sm:px-3">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <rect width="7" height="7" x="3" y="3" rx="1.5" />
                        <rect width="7" height="7" x="14" y="3" rx="1.5" />
                        <rect width="7" height="7" x="3" y="14" rx="1.5" />
                        <rect width="7" height="7" x="14" y="14" rx="1.5" />
                    </svg>
                    Cards View
                </button>
            </div>
        </div>

        @foreach ($reportTabs as $tabKey => $tab)
            <section id="report-section-{{ $tabKey }}" class="{{ $loop->first ? '' : 'hidden' }} space-y-3">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm lg:rounded-[2rem] lg:px-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-black text-slate-950">{{ $tab['label'] }} Report</h2>
                            <span class="rounded-full {{ $tab['tone'] }} px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em]">{{ $tab['carts']->count() }}</span>
                        </div>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $tab['description'] }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">Total Amount</p>
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

                @if ($tab['carts']->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4">
                        {{ $tab['empty'] }}
                    </div>
                @else
                    <!-- Mobile-Friendly Table Container -->
                    <div id="report-table-container-{{ $tabKey }}" class="report-view-table overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm lg:rounded-[2rem]">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th scope="col" class="py-3 px-3.5">Ref / Date</th>
                                    <th scope="col" class="py-3 px-3">Vendor</th>
                                    <th scope="col" class="py-3 px-3 text-right">Amount</th>
                                    <th scope="col" class="py-3 px-3">Bill / Invoice</th>
                                    <th scope="col" class="py-3 px-3">Status</th>
                                    <th scope="col" class="py-3 px-3.5 text-center">Bill Link</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach ($tab['carts'] as $cart)
                                    @php
                                        $badge = $statusBadges[$cart->id] ?? ['label' => 'Pending', 'tone' => 'bg-slate-100 text-slate-700'];
                                        $receiptNotes = $relatedReceiptNotes[$cart->id] ?? null;
                                        $cartAmount = $cart->purchaseInvoice 
                                            ? ((float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount) 
                                            : ((float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
                                        
                                        $billNumber = $cart->bill_number 
                                            ?: ($cart->purchaseInvoice && !str_starts_with($cart->purchaseInvoice->invoice_number, 'PENDING-BILL-') 
                                                ? $cart->purchaseInvoice->invoice_number 
                                                : ($cart->payment_status === 'paid' ? 'Paid' : 'Pending'));
                                                
                                        $billUrl = match (true) {
                                            $cart->purchaseInvoice !== null => route('purchaser.invoices.show', $cart->purchaseInvoice),
                                            $cart->status === 'draft' => route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d'), 'tab' => 'draft', 'focus_cart' => $cart->id]),
                                            default => route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d'), 'tab' => 'submitted', 'focus_cart' => $cart->id]),
                                        };
                                        
                                        $billLinkLabel = match (true) {
                                            $cart->purchaseInvoice !== null => 'View Bill',
                                            $cart->status === 'draft' => 'Draft Bill',
                                            default => 'Bill Details',
                                        };
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50/80">
                                        <!-- Ref / Date -->
                                        <td class="py-3 px-3.5 align-top">
                                            <p class="font-mono text-xs font-black text-slate-950">{{ $cart->cart_number }}</p>
                                            <p class="mt-0.5 text-[10px] font-semibold text-slate-500">{{ $cart->business_date->format('d M Y') }}</p>
                                            <span class="mt-1 inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600">
                                                {{ $cart->items->count() }} {{ \Illuminate\Support\Str::plural('item', $cart->items->count()) }}
                                            </span>
                                        </td>
                                        <!-- Vendor -->
                                        <td class="py-3 px-3 align-top">
                                            <p class="font-black text-slate-950 min-w-[110px] max-w-[180px] truncate">{{ $cart->supplier?->name ?: 'Vendor pending' }}</p>
                                            @if ($cart->supplier?->mobile_number)
                                                <p class="mt-0.5 text-[10px] font-medium text-slate-500">{{ $cart->supplier->mobile_number }}</p>
                                            @endif
                                            @if (filled($cart->supplier?->bank_details))
                                                <span class="mt-1 inline-flex items-center gap-1 rounded bg-teal-50 px-1.5 py-0.5 text-[9px] font-bold text-teal-700" title="{{ $cart->supplier->bank_details }}">
                                                    🏦 Bank Info
                                                </span>
                                            @endif
                                        </td>
                                        <!-- Amount -->
                                        <td class="py-3 px-3 text-right align-top font-mono whitespace-nowrap">
                                            <p class="font-black text-slate-950">₹{{ number_format($cartAmount, 2) }}</p>
                                            @php
                                                $cashAmount = $cart->purchaseInvoice 
                                                    ? (float) $cart->purchaseInvoice->paid_amount 
                                                    : (strcasecmp((string) $cart->payment_method, 'Cash') === 0 ? $cartAmount : 0.0);
                                                $creditAmount = max(0.0, $cartAmount - $cashAmount);
                                            @endphp
                                            <p class="mt-0.5 text-[10px] font-bold text-emerald-700">Cash: ₹{{ number_format($cashAmount, 2) }}</p>
                                            @if ($creditAmount > 0)
                                                <p class="text-[9px] font-bold text-amber-700">Credit: ₹{{ number_format($creditAmount, 2) }}</p>
                                            @endif
                                        </td>
                                        <!-- Bill / Invoice -->
                                        <td class="py-3 px-3 align-top">
                                            @if ($cart->purchaseInvoice)
                                                <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="font-mono text-xs font-black text-teal-700 hover:underline">
                                                    {{ $cart->purchaseInvoice->invoice_number }}
                                                </a>
                                            @else
                                                <span class="font-mono text-xs font-semibold text-slate-600">{{ $billNumber }}</span>
                                            @endif
                                            <p class="mt-0.5 text-[10px] font-medium text-slate-500">
                                                Pay: {{ str($cart->payment_status ?: 'unpaid')->replace('_', ' ')->title() }}
                                            </p>
                                        </td>
                                        <!-- Status -->
                                        <td class="py-3 px-3 align-top">
                                            <span class="inline-block rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $badge['tone'] }}">
                                                {{ $badge['label'] }}
                                            </span>
                                            @if ($cart->goodsReceived?->grn_number)
                                                <p class="mt-1 text-[10px] font-medium text-slate-500">GRN: {{ $cart->goodsReceived->grn_number }}</p>
                                            @endif
                                        </td>
                                        <!-- Bill Link -->
                                        <td class="py-3 px-3.5 text-center align-top whitespace-nowrap">
                                            <a href="{{ $billUrl }}" class="inline-flex h-8 items-center justify-center gap-1 rounded-xl bg-teal-600 px-3 text-[11px] font-black text-white shadow-xs transition-all hover:bg-teal-500 active:scale-95">
                                                <span>{{ $billLinkLabel }}</span>
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($tab['carts']->isNotEmpty())
                                <tfoot class="border-t-2 border-slate-900 bg-slate-950 text-white font-mono text-xs">
                                    <tr>
                                        <td colspan="2" class="py-3 px-3.5 font-sans font-black uppercase text-[10px] tracking-wider text-slate-300">
                                            Table Total ({{ $tab['carts']->count() }} {{ \Illuminate\Support\Str::plural('bill', $tab['carts']->count()) }})
                                        </td>
                                        <td class="py-3 px-3 text-right whitespace-nowrap">
                                            @php
                                                $tableTotalAmount = $tab['carts']->sum(function ($cart) use ($relatedBatchState) {
                                                    if ($cart->status === 'draft') {
                                                        return (float) $cart->items->sum('line_total') - (float) $cart->discount_amount;
                                                    }
                                                    if ($cart->purchaseInvoice) {
                                                        return max(0.0, (float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount);
                                                    }
                                                    return max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
                                                });
                                                $tableTotalCash = $tab['carts']->sum(function ($cart) {
                                                    if ($cart->purchaseInvoice) {
                                                        return (float) $cart->purchaseInvoice->paid_amount;
                                                    }
                                                    return strcasecmp((string) $cart->payment_method, 'Cash') === 0 ? max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount) : 0.0;
                                                });
                                                $tableTotalCredit = max(0.0, $tableTotalAmount - $tableTotalCash);
                                            @endphp
                                            <p class="font-black text-white">₹{{ number_format($tableTotalAmount, 2) }}</p>
                                            <p class="text-[10px] font-bold text-emerald-400">Cash: ₹{{ number_format($tableTotalCash, 2) }}</p>
                                            @if ($tableTotalCredit > 0)
                                                <p class="text-[9px] font-bold text-amber-400">Credit: ₹{{ number_format($tableTotalCredit, 2) }}</p>
                                            @endif
                                        </td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <!-- Cards View Container (Hidden by default or toggled) -->
                    <div id="report-cards-container-{{ $tabKey }}" class="report-view-cards hidden space-y-3">
                        @foreach ($tab['carts'] as $cart)
                            @php
                                $badge = $statusBadges[$cart->id] ?? ['label' => 'Pending', 'tone' => 'bg-slate-100 text-slate-700'];
                                $receiptNotes = $relatedReceiptNotes[$cart->id] ?? null;
                                $cartAmount = $cart->purchaseInvoice 
                                    ? ((float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount) 
                                    : ((float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
                                    
                                $billUrl = match (true) {
                                    $cart->purchaseInvoice !== null => route('purchaser.invoices.show', $cart->purchaseInvoice),
                                    $cart->status === 'draft' => route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d'), 'tab' => 'draft', 'focus_cart' => $cart->id]),
                                    default => route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d'), 'tab' => 'submitted', 'focus_cart' => $cart->id]),
                                };
                                
                                $billLinkLabel = match (true) {
                                    $cart->purchaseInvoice !== null => 'View Bill',
                                    $cart->status === 'draft' => 'Draft Bill',
                                    default => 'Bill Details',
                                };
                            @endphp
                            <article class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-base font-black text-slate-950">{{ $cart->cart_number }}</p>
                                            <span class="rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                                        </div>
                                        <p class="mt-1 text-xs font-semibold text-slate-600">{{ $cart->supplier?->name ?: 'Vendor pending' }} • {{ $cart->business_date->format('d M Y') }}</p>
                                        @if (filled($cart->supplier?->bank_details))
                                            <p class="mt-1 text-[10px] font-bold text-teal-700">🏦 {{ $cart->supplier->bank_details }}</p>
                                        @endif
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">Total Amount</p>
                                        <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format($cartAmount, 2) }}</p>
                                        @php
                                            $cardCash = $cart->purchaseInvoice 
                                                ? (float) $cart->purchaseInvoice->paid_amount 
                                                : (strcasecmp((string) $cart->payment_method, 'Cash') === 0 ? $cartAmount : 0.0);
                                            $cardCredit = max(0.0, $cartAmount - $cardCash);
                                        @endphp
                                        <p class="mt-0.5 text-[10px] font-bold text-emerald-700">Cash: ₹{{ number_format($cardCash, 2) }}</p>
                                        @if ($cardCredit > 0)
                                            <p class="text-[9px] font-bold text-amber-700">Credit: ₹{{ number_format($cardCredit, 2) }}</p>
                                        @endif
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
                                    <summary class="cursor-pointer px-1.5 py-1 text-[10px] font-black text-slate-700 select-none">View line items ({{ $cart->items->count() }})</summary>
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
                                    <a href="{{ $billUrl }}" class="flex h-9 items-center justify-center gap-1 rounded-xl bg-teal-600 px-4 text-[11px] font-black text-white hover:bg-teal-500 shadow-xs">
                                        <span>{{ $billLinkLabel }}</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <script>
        let currentViewMode = 'table';

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

        function setReportViewMode(mode) {
            currentViewMode = mode;
            const tableBtn = document.getElementById('view-mode-table-btn');
            const cardsBtn = document.getElementById('view-mode-cards-btn');

            if (mode === 'table') {
                tableBtn.className = 'rounded-lg bg-slate-950 px-3 py-1.5 text-[10px] font-black text-white shadow-xs transition-all';
                cardsBtn.className = 'rounded-lg px-3 py-1.5 text-[10px] font-black text-slate-500 hover:text-slate-800 transition-all';

                document.querySelectorAll('.report-view-table').forEach(el => el.classList.remove('hidden'));
                document.querySelectorAll('.report-view-cards').forEach(el => el.classList.add('hidden'));
            } else {
                cardsBtn.className = 'rounded-lg bg-slate-950 px-3 py-1.5 text-[10px] font-black text-white shadow-xs transition-all';
                tableBtn.className = 'rounded-lg px-3 py-1.5 text-[10px] font-black text-slate-500 hover:text-slate-800 transition-all';

                document.querySelectorAll('.report-view-cards').forEach(el => el.classList.remove('hidden'));
                document.querySelectorAll('.report-view-table').forEach(el => el.classList.add('hidden'));
            }
        }
    </script>
</x-layouts.app>
