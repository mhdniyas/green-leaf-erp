<x-layouts.app title="Purchaser Report">
    @php
        $allCartsList = $groupedCarts['today']->merge($groupedCarts['history'])->unique('id')->values();

        $creditCarts = $allCartsList->filter(function ($cart) {
            $cartAmount = $cart->purchaseInvoice 
                ? ((float) $cart->purchaseInvoice->amount - (float) $cart->discount_amount) 
                : ((float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
            $isPaid = ($cart->purchaseInvoice && $cart->purchaseInvoice->payment_status === 'paid') 
                || $cart->payment_status === 'paid' 
                || ($cart->purchaseInvoice && (float) $cart->purchaseInvoice->paid_amount >= $cartAmount);
            if ($isPaid) return false;

            $method = (string) ($cart->purchaseInvoice?->payment_method ?? $cart->payment_method);
            return strcasecmp($method, 'Credit') === 0;
        })->values();

        $dueCarts = $allCartsList->filter(function ($cart) {
            $cartAmount = $cart->purchaseInvoice 
                ? ((float) $cart->purchaseInvoice->amount - (float) $cart->discount_amount) 
                : ((float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
            $isPaid = ($cart->purchaseInvoice && $cart->purchaseInvoice->payment_status === 'paid') 
                || $cart->payment_status === 'paid' 
                || ($cart->purchaseInvoice && (float) $cart->purchaseInvoice->paid_amount >= $cartAmount);
            if ($isPaid) return false;

            $method = (string) ($cart->purchaseInvoice?->payment_method ?? $cart->payment_method);
            return strcasecmp($method, 'Credit') !== 0;
        })->values();

        $reportTabs = [
            'today' => [
                'label' => 'Today',
                'tone' => 'bg-teal-100 text-teal-700',
                'carts' => $groupedCarts['today'],
                'description' => 'Purchases and active orders for the selected operational date.',
                'empty' => 'No purchases for this date.',
            ],
            'credit' => [
                'label' => 'Credit',
                'tone' => 'bg-amber-100 text-amber-700',
                'carts' => $creditCarts,
                'description' => 'Pending bills with Credit payment method selected.',
                'empty' => 'No pending credit bills found.',
            ],
            'due' => [
                'label' => 'Due',
                'tone' => 'bg-slate-100 text-slate-700',
                'carts' => $dueCarts,
                'description' => 'Unpaid and partially paid non-credit bills (To Be Paid).',
                'empty' => 'No pending due bills found.',
            ],
            'history' => [
                'label' => 'History',
                'tone' => 'bg-slate-100 text-slate-700',
                'carts' => $groupedCarts['history'],
                'description' => 'All historical and overdue purchases from previous business days.',
                'empty' => 'No historical purchases found.',
            ],
        ];

        $defaultTab = request('tab', 'today');
        if (!array_key_exists($defaultTab, $reportTabs)) {
            $defaultTab = 'today';
        }
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs lg:rounded-2xl">
            <!-- Row 1: Title & Description -->
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">Stage 5</span>
                    <h1 class="text-base font-black text-slate-950">Purchase report</h1>
                </div>
                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">Track overdue action, warehouse progress, and completed purchases.</p>
            </div>

            <!-- Row 2: Controls (Vendor Hub, Date Picker, Expenses Toggle) -->
            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                <a href="{{ route('purchaser.suppliers', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 hover:bg-white shrink-0">
                    <span>Vendor Hub</span>
                    @if (($deadlineAlert['pending_total_count'] ?? 0) > 0)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-100 px-1 text-[9px] font-black text-rose-700">
                            {{ $deadlineAlert['pending_total_count'] }}
                        </span>
                    @endif
                </a>

                <form action="{{ route('purchaser.history') }}" method="GET" class="shrink-0">
                    <input type="hidden" name="include_expenses" value="{{ $includeExpenses ? '1' : '0' }}">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                </form>

                <div class="inline-flex h-8 shrink-0 rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-[9px]">
                    <a href="{{ route('purchaser.history', ['date' => $date, 'include_expenses' => 0]) }}" class="flex items-center rounded-md px-2.5 py-1 font-black uppercase tracking-wider {{ $includeExpenses ? 'text-slate-600 hover:text-slate-800' : 'bg-white text-slate-900 shadow-2xs' }}">
                        Bills Only
                    </a>
                    <a href="{{ route('purchaser.history', ['date' => $date, 'include_expenses' => 1]) }}" class="flex items-center rounded-md px-2.5 py-1 font-black uppercase tracking-wider {{ $includeExpenses ? 'bg-white text-teal-700 shadow-2xs' : 'text-slate-600 hover:text-slate-800' }}">
                        + Expenses
                    </a>
                </div>
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
                        @if($includeExpenses)
                            <p class="text-[9px] font-black uppercase tracking-tight text-amber-400 truncate">Total</p>
                            <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-amber-300 truncate">₹{{ number_format($monthSummary['total_cash'] + ($monthSummary['expense_total'] ?? 0), 2) }}</p>
                            <p class="mt-1 text-[8px] font-semibold text-amber-200">incl. ₹{{ number_format($monthSummary['expense_total'] ?? 0, 2) }} expense</p>
                        @else
                            <p class="text-[9px] font-black uppercase tracking-tight text-amber-400 truncate">Credit Due</p>
                            <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-amber-300 truncate">₹{{ number_format($monthSummary['total_credit'], 2) }}</p>
                        @endif
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
                    @if($includeExpenses)
                        <p class="text-[9px] font-black uppercase tracking-tight text-amber-700 truncate">Total</p>
                        <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-amber-800 truncate">₹{{ number_format(($todaySummary['total_cash'] ?? 0) + ($todaySummary['expense_total'] ?? 0), 2) }}</p>
                        <p class="mt-1 text-[8px] font-semibold text-amber-600">incl. ₹{{ number_format($todaySummary['expense_total'] ?? 0, 2) }} expense</p>
                    @else
                        <p class="text-[9px] font-black uppercase tracking-tight text-amber-700 truncate">Credit Due</p>
                        <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-amber-800 truncate">₹{{ number_format($todaySummary['total_credit'] ?? 0, 2) }}</p>
                    @endif
                </div>
            </div>
        </section>

        <!-- Unified Tab Switcher, Vendor Search & Report Summary -->
        <section class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <!-- Left: Tab Switcher Buttons (Today / Credit / Due / History) -->
                <div class="inline-flex shrink-0 rounded-lg bg-slate-100 p-0.5 text-xs font-bold text-slate-700 flex-wrap gap-0.5">
                    @foreach ($reportTabs as $tabKey => $tab)
                        <button type="button" id="report-tab-btn-{{ $tabKey }}" onclick="switchReportTab('{{ $tabKey }}')" class="{{ $tabKey === $defaultTab ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-800' }} inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-all">
                            <span>{{ strtoupper($tab['label']) }}</span>
                            <span class="rounded-full bg-slate-200/80 px-1.5 py-0.2 text-[9px] font-black text-slate-700">{{ $tab['carts']->count() }}</span>
                        </button>
                    @endforeach
                </div>

                <!-- Middle: Vendor / Cart Instant Search -->
                <div class="flex-1 max-w-sm min-w-0">
                    <div class="relative">
                        <input type="search" id="vendor-search-input" placeholder="Search vendor name, cart ref..." oninput="filterVendorCards(this.value)" class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-teal-500 focus:outline-none">
                        <svg class="absolute left-2.5 top-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>
                </div>

                <!-- Right: Active Tab Total Amount Summary & Desktop View Switcher -->
                <div class="flex items-center justify-between md:justify-end gap-3 text-xs shrink-0">
                    @foreach ($reportTabs as $tabKey => $tab)
                        @php
                            $tabTotalAmount = $tab['carts']->sum(function ($cart) use ($relatedBatchState) {
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
                            });
                        @endphp
                        <div id="tab-summary-total-{{ $tabKey }}" class="{{ $tabKey === $defaultTab ? '' : 'hidden' }} text-right">
                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Total Amount</span>
                            <span class="font-mono font-black text-slate-950 text-xs sm:text-sm">₹{{ number_format($tabTotalAmount, 2) }}</span>
                        </div>
                    @endforeach

                    <!-- Desktop View Switcher -->
                    <div class="hidden md:flex items-center rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                        <button type="button" id="view-mode-table-btn" onclick="setReportViewMode('table')" class="rounded-md bg-slate-950 px-2.5 py-1 text-[10px] font-black text-white shadow-2xs">Table</button>
                        <button type="button" id="view-mode-cards-btn" onclick="setReportViewMode('cards')" class="rounded-md px-2.5 py-1 text-[10px] font-black text-slate-500 hover:text-slate-800">Cards</button>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($reportTabs as $tabKey => $tab)
            <section id="report-section-{{ $tabKey }}" class="{{ $tabKey === $defaultTab ? '' : 'hidden' }} space-y-3">

                @if ($tab['carts']->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4">
                        {{ $tab['empty'] }}
                    </div>
                @else
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
                            $cartAmount = $cart->purchaseInvoice 
                                ? ((float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount) 
                                : ((float) $cart->items->sum('line_total') - (float) $cart->discount_amount);

                            if ($cart->purchaseInvoice) {
                                return (float) $cart->purchaseInvoice->paid_amount;
                            }
                            $isPaid = $cart->payment_status === 'paid' || in_array($cart->payment_method, ['Cash', 'Online', 'GPay']);
                            return $isPaid ? $cartAmount : (float) ($cart->paid_amount ?? 0);
                        });
                        $tableTotalCredit = max(0.0, $tableTotalAmount - $tableTotalCash);
                    @endphp

                    <!-- Desktop Table View (Hidden on mobile) -->
                    <div id="report-table-container-{{ $tabKey }}" class="report-view-table hidden md:block overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm lg:rounded-[2rem]">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th scope="col" class="py-3 px-3.5">Ref / Date</th>
                                    <th scope="col" class="py-3 px-3">Vendor</th>
                                    <th scope="col" class="py-3 px-3 text-right">Amount</th>
                                    <th scope="col" class="py-3 px-3">Bill / Invoice</th>
                                    <th scope="col" class="py-3 px-3">Status</th>
                                    <th scope="col" class="py-3 px-3.5 text-center">Action</th>
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
                                        
                                        $isPaid = ($cart->purchaseInvoice && $cart->purchaseInvoice->payment_status === 'paid') 
                                            || $cart->payment_status === 'paid' 
                                            || ($cart->purchaseInvoice && (float) $cart->purchaseInvoice->paid_amount >= $cartAmount);

                                        $isCreditMethod = strcasecmp((string) ($cart->purchaseInvoice?->payment_method ?? $cart->payment_method), 'Credit') === 0;

                                        $cashAmount = $cart->purchaseInvoice 
                                            ? (float) $cart->purchaseInvoice->paid_amount 
                                            : ($isPaid || in_array($cart->payment_method, ['Cash', 'Online', 'GPay']) ? $cartAmount : (float) ($cart->paid_amount ?? 0));
                                        $creditAmount = max(0.0, $cartAmount - $cashAmount);

                                        $billNumber = $cart->bill_number 
                                            ?: ($cart->purchaseInvoice && !str_starts_with($cart->purchaseInvoice->invoice_number, 'PENDING-BILL-') 
                                                ? $cart->purchaseInvoice->invoice_number 
                                                : ($isPaid ? 'Paid' : 'Pending'));
                                                
                                        $pdfUrl = $cart->purchaseInvoice ? route('purchaser.invoices.pdf', $cart->purchaseInvoice) : null;
                                        
                                        $paymentStatusLabel = match(true) {
                                            $cart->purchaseInvoice?->payment_status === 'credit_pending_approval' || $cart->payment_status === 'credit_pending_approval' => 'Credit Pending Approval',
                                            $isPaid => 'Paid',
                                            $cashAmount > 0 && $creditAmount > 0 => 'Partially Paid',
                                            default => str($cart->payment_status ?: 'unpaid')->replace('_', ' ')->title()->toString(),
                                        };

                                        $hasInvoice = $cart->purchaseInvoice !== null;

                                        $invoiceData = [
                                            'id' => $hasInvoice ? $cart->purchaseInvoice->id : $cart->id,
                                            'number' => $hasInvoice ? $cart->purchaseInvoice->invoice_number : $cart->cart_number,
                                            'billNumber' => $cart->bill_number ?: ($hasInvoice ? $cart->purchaseInvoice->invoice_number : ''),
                                            'supplier' => $cart->supplier?->name ?: 'Vendor pending',
                                            'amount' => round((float) $cartAmount, 2),
                                            'discountAmount' => round((float) ($hasInvoice ? $cart->purchaseInvoice->discount_amount : ($cart->discount_amount ?? 0)), 2),
                                            'paidAmount' => round((float) $cashAmount, 2),
                                            'balance' => max(0, round((float) $creditAmount, 2)),
                                            'paymentMethod' => $hasInvoice ? ($cart->purchaseInvoice->payment_method ?: 'Cash') : ($cart->payment_method ?: 'Cash'),
                                            'paymentNote' => $hasInvoice ? $cart->purchaseInvoice->payment_note : $cart->payment_note,
                                            'paymentDetails' => $hasInvoice ? $cart->purchaseInvoice->payment_details : $cart->payment_details,
                                            'creditApproved' => (bool) ($cart->supplier?->credit_approved),
                                            'isSubmitMode' => ! $hasInvoice,
                                            'cartId' => $cart->id,
                                            'supplierId' => $cart->supplier_id,
                                            'businessDate' => $cart->business_date->format('Y-m-d'),
                                            'cartItems' => $cart->items->mapWithKeys(fn($item) => [
                                                $item->id => ['unit_price' => (float) $item->unit_price]
                                            ])->all(),
                                        ];

                                        $paymentActionUrl = $hasInvoice 
                                            ? route('purchaser.invoices.payment', $cart->purchaseInvoice) 
                                            : route('purchaser.carts.submit');

                                        $modalPayload = [
                                            'supplierName' => $cart->supplier?->name ?: 'Vendor pending',
                                            'supplierMobile' => $cart->supplier?->mobile_number ?: '',
                                            'billRef' => $cart->cart_number,
                                            'invoiceNumber' => $cart->purchaseInvoice?->invoice_number ?: ($cart->bill_number ?: 'PENDING-BILL-' . $cart->cart_number),
                                            'date' => $cart->business_date->format('d M Y'),
                                            'paymentStatus' => $paymentStatusLabel,
                                            'totalAmount' => '₹' . number_format($cartAmount, 2),
                                            'cashAmount' => '₹' . number_format($cashAmount, 2),
                                            'creditAmount' => '₹' . number_format($creditAmount, 2),
                                            'grnNumber' => $cart->goodsReceived?->grn_number ?: 'Pending',
                                            'pdfUrl' => $pdfUrl ?: '#',
                                            'items' => $cart->items->map(fn($item) => [
                                                'name' => $item->product?->name ?: 'Item',
                                                'quantity' => (float) $item->quantity,
                                                'unit' => $item->product?->unit ?: '',
                                                'price' => number_format((float) $item->unit_price, 2),
                                                'total' => number_format((float) $item->line_total, 2),
                                            ])->values()->all(),
                                        ];
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
                                            <p class="font-bold text-slate-900">{{ $cart->supplier?->name ?: 'Vendor pending' }}</p>
                                            <p class="text-[10px] font-medium text-slate-500">{{ $cart->supplier?->mobile_number ?: 'Mobile pending' }}</p>
                                        </td>
                                        <!-- Amount -->
                                        <td class="py-3 px-3 align-top text-right whitespace-nowrap">
                                            <p class="font-mono text-xs font-black text-slate-950">₹{{ number_format($cartAmount, 2) }}</p>
                                            <div class="mt-0.5 text-[10px] font-semibold space-y-0.5">
                                                @if ($isPaid)
                                                    <p class="text-emerald-700 font-bold">Paid: ₹{{ number_format($cashAmount, 2) }}</p>
                                                @elseif ($cashAmount > 0 && $creditAmount > 0)
                                                    <p class="text-emerald-700 font-bold">Paid: ₹{{ number_format($cashAmount, 2) }}</p>
                                                    <p class="{{ $isCreditMethod ? 'text-amber-700' : 'text-slate-600' }} font-bold">
                                                        {{ $isCreditMethod ? 'Credit' : 'To Be Paid' }}: ₹{{ number_format($creditAmount, 2) }}
                                                    </p>
                                                @elseif ($isCreditMethod)
                                                    <p class="text-amber-700 font-bold">Credit: ₹{{ number_format($creditAmount, 2) }}</p>
                                                @else
                                                    <p class="text-slate-600 font-bold">To Be Paid: ₹{{ number_format($creditAmount, 2) }}</p>
                                                @endif
                                            </div>
                                        </td>
                                        <!-- Bill / Invoice -->
                                        <td class="py-3 px-3 align-top">
                                            @if ($pdfUrl)
                                                <a href="{{ $pdfUrl }}" target="_blank" class="font-mono text-xs font-bold text-teal-700 hover:underline">
                                                    {{ $billNumber }}
                                                </a>
                                            @else
                                                <span class="font-mono text-xs font-semibold text-slate-600">{{ $billNumber }}</span>
                                            @endif
                                            <p class="mt-0.5 text-[10px] font-bold {{ $isPaid ? 'text-emerald-700' : 'text-slate-500' }}">
                                                Pay: {{ $paymentStatusLabel }}
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
                                        <!-- Actions -->
                                        <td class="py-3 px-3.5 text-center align-top whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button type="button" onclick='openPaymentModal(@json($invoiceData), "{{ $paymentActionUrl }}")' class="inline-flex h-7 items-center justify-center rounded-lg {{ $isPaid ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-slate-950 hover:bg-slate-800' }} px-2.5 text-[10px] font-black text-white shadow-2xs transition-all active:scale-95">
                                                    {{ $isPaid ? 'Paid ✓' : 'Payment' }}
                                                </button>
                                                <button type="button" onclick='openMobileBillModal(@json($modalPayload))' class="inline-flex h-7 items-center justify-center rounded-lg bg-teal-600 px-2.5 text-[10px] font-black text-white shadow-2xs transition-all hover:bg-teal-500 active:scale-95">
                                                    View Bill
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-slate-900 bg-slate-950 text-white font-mono text-xs">
                                <tr>
                                    <td colspan="2" class="py-3 px-3.5 font-sans font-black uppercase text-[10px] tracking-wider text-slate-300">
                                        Table Total ({{ $tab['carts']->count() }} {{ \Illuminate\Support\Str::plural('bill', $tab['carts']->count()) }})
                                    </td>
                                    <td class="py-3 px-3 text-right whitespace-nowrap">
                                        <p class="font-black text-white">₹{{ number_format($tableTotalAmount, 2) }}</p>
                                        @if ($tableTotalCash > 0)
                                            <p class="text-[10px] font-bold text-emerald-400">Cash: ₹{{ number_format($tableTotalCash, 2) }}</p>
                                        @endif
                                        @if ($tableTotalCredit > 0)
                                            <p class="text-[9px] font-bold text-amber-400">Credit: ₹{{ number_format($tableTotalCredit, 2) }}</p>
                                        @endif
                                    </td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Mobile Compact Cards View (Default on mobile) -->
                    <div id="report-cards-container-{{ $tabKey }}" class="report-view-cards space-y-2 md:hidden">
                        @foreach ($tab['carts'] as $cart)
                            @php
                                $badge = $statusBadges[$cart->id] ?? ['label' => 'Pending', 'tone' => 'bg-slate-100 text-slate-700'];
                                $cartAmount = $cart->purchaseInvoice 
                                    ? ((float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount) 
                                    : ((float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
                                    
                                $isPaid = ($cart->purchaseInvoice && $cart->purchaseInvoice->payment_status === 'paid') 
                                    || $cart->payment_status === 'paid' 
                                    || ($cart->purchaseInvoice && (float) $cart->purchaseInvoice->paid_amount >= $cartAmount);

                                $isCreditMethod = strcasecmp((string) ($cart->purchaseInvoice?->payment_method ?? $cart->payment_method), 'Credit') === 0;

                                $cashAmount = $cart->purchaseInvoice 
                                    ? (float) $cart->purchaseInvoice->paid_amount 
                                    : ($isPaid || in_array($cart->payment_method, ['Cash', 'Online', 'GPay']) ? $cartAmount : (float) ($cart->paid_amount ?? 0));
                                $creditAmount = max(0.0, $cartAmount - $cashAmount);
                                
                                $paymentStatusLabel = match(true) {
                                    $cart->purchaseInvoice?->payment_status === 'credit_pending_approval' || $cart->payment_status === 'credit_pending_approval' => 'Credit Pending Approval',
                                    $isPaid => 'Paid',
                                    $cashAmount > 0 && $creditAmount > 0 => 'Partially Paid',
                                    default => str($cart->payment_status ?: 'unpaid')->replace('_', ' ')->title()->toString(),
                                };

                                $hasInvoice = $cart->purchaseInvoice !== null;

                                $invoiceData = [
                                    'id' => $hasInvoice ? $cart->purchaseInvoice->id : $cart->id,
                                    'number' => $hasInvoice ? $cart->purchaseInvoice->invoice_number : $cart->cart_number,
                                    'billNumber' => $cart->bill_number ?: ($hasInvoice ? $cart->purchaseInvoice->invoice_number : ''),
                                    'supplier' => $cart->supplier?->name ?: 'Vendor pending',
                                    'amount' => round((float) $cartAmount, 2),
                                    'discountAmount' => round((float) ($hasInvoice ? $cart->purchaseInvoice->discount_amount : ($cart->discount_amount ?? 0)), 2),
                                    'paidAmount' => round((float) $cashAmount, 2),
                                    'balance' => max(0, round((float) $creditAmount, 2)),
                                    'paymentMethod' => $hasInvoice ? ($cart->purchaseInvoice->payment_method ?: 'Cash') : ($cart->payment_method ?: 'Cash'),
                                    'paymentNote' => $hasInvoice ? $cart->purchaseInvoice->payment_note : $cart->payment_note,
                                    'paymentDetails' => $hasInvoice ? $cart->purchaseInvoice->payment_details : $cart->payment_details,
                                    'creditApproved' => (bool) ($cart->supplier?->credit_approved),
                                    'isSubmitMode' => ! $hasInvoice,
                                    'cartId' => $cart->id,
                                    'supplierId' => $cart->supplier_id,
                                    'businessDate' => $cart->business_date->format('Y-m-d'),
                                    'cartItems' => $cart->items->mapWithKeys(fn($item) => [
                                        $item->id => ['unit_price' => (float) $item->unit_price]
                                    ])->all(),
                                ];

                                $paymentActionUrl = $hasInvoice 
                                    ? route('purchaser.invoices.payment', $cart->purchaseInvoice) 
                                    : route('purchaser.carts.submit');

                                $modalPayload = [
                                    'supplierName' => $cart->supplier?->name ?: 'Vendor pending',
                                    'supplierMobile' => $cart->supplier?->mobile_number ?: '',
                                    'billRef' => $cart->cart_number,
                                    'invoiceNumber' => $cart->purchaseInvoice?->invoice_number ?: ($cart->bill_number ?: 'PENDING-BILL-' . $cart->cart_number),
                                    'date' => $cart->business_date->format('d M Y'),
                                    'paymentStatus' => $paymentStatusLabel,
                                    'totalAmount' => '₹' . number_format($cartAmount, 2),
                                    'cashAmount' => '₹' . number_format($cashAmount, 2),
                                    'creditAmount' => '₹' . number_format($creditAmount, 2),
                                    'grnNumber' => $cart->goodsReceived?->grn_number ?: 'Pending',
                                    'items' => $cart->items->map(fn($item) => [
                                        'name' => $item->product?->name ?: 'Item',
                                        'quantity' => (float) $item->quantity,
                                        'unit' => $item->product?->unit ?: '',
                                        'price' => number_format((float) $item->unit_price, 2),
                                        'total' => number_format((float) $item->line_total, 2),
                                    ])->values()->all(),
                                ];
                            @endphp
                            <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs space-y-2">
                                <!-- Row 1: Vendor Name & Cart Ref (Left) | Badge (Right) -->
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex items-center gap-1.5 truncate">
                                        <h3 class="text-xs font-black text-slate-950 truncate">{{ $cart->supplier?->name ?: 'Vendor pending' }}</h3>
                                        <span class="font-mono text-[10px] font-bold text-slate-500 shrink-0">{{ $cart->cart_number }}</span>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $badge['tone'] }}">
                                        {{ $badge['label'] }}
                                    </span>
                                </div>

                                <!-- Row 2: Amount, Status & Date/Items (Left) | Action Buttons (Right) -->
                                <div class="flex items-center justify-between gap-2 border-t border-slate-100/80 pt-2 text-xs">
                                    <div class="min-w-0">
                                        <div class="flex items-baseline gap-1.5 flex-wrap">
                                            <span class="font-mono text-xs font-black text-slate-950">₹{{ number_format($cartAmount, 2) }}</span>
                                            <span class="text-[10px] font-bold {{ $isPaid ? 'text-emerald-700' : ($isCreditMethod ? 'text-amber-700' : 'text-slate-600') }}">
                                                @if ($cart->purchaseInvoice?->payment_status === 'credit_pending_approval' || $cart->payment_status === 'credit_pending_approval')
                                                    Credit Pending
                                                @elseif ($isPaid)
                                                    Paid
                                                @elseif ($cashAmount > 0 && $creditAmount > 0)
                                                    Paid ₹{{ number_format($cashAmount, 2) }} · {{ $isCreditMethod ? 'Credit' : 'Due' }} ₹{{ number_format($creditAmount, 2) }}
                                                @elseif ($isCreditMethod)
                                                    Credit ₹{{ number_format($creditAmount, 2) }}
                                                @else
                                                    To Be Paid ₹{{ number_format($creditAmount, 2) }}
                                                @endif
                                            </span>
                                        </div>
                                        <p class="text-[10px] font-medium text-slate-500 mt-0.5">{{ $cart->business_date->format('d M') }} · {{ $cart->items->count() }} {{ \Illuminate\Support\Str::plural('item', $cart->items->count()) }}</p>
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <button type="button" onclick='openPaymentModal(@json($invoiceData), "{{ $paymentActionUrl }}")' class="inline-flex h-7 items-center justify-center rounded-lg {{ $isPaid ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-slate-950 hover:bg-slate-800' }} px-2 text-[10px] font-black text-white shadow-2xs">
                                            {{ $isPaid ? 'Paid ✓' : 'Payment' }}
                                        </button>
                                        <button type="button" onclick='openMobileBillModal(@json($modalPayload))' class="inline-flex h-7 items-center justify-center rounded-lg bg-teal-600 px-2 text-[10px] font-black text-white hover:bg-teal-500 shadow-2xs">
                                            View Bill
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <!-- Simplified Mobile Total Footer Section -->
                    <div class="rounded-2xl border border-slate-900 bg-slate-950 p-4 text-white shadow-sm md:hidden">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-400">{{ $tab['carts']->count() }} {{ \Illuminate\Support\Str::plural('Bill', $tab['carts']->count()) }}</p>
                                <p class="mt-0.5 text-xs font-black uppercase tracking-wider text-slate-200">Total Credit Due</p>
                            </div>
                            <div class="text-right">
                                <p class="font-mono text-lg font-black text-amber-400">₹{{ number_format($tableTotalCredit, 2) }}</p>
                                @if ($tableTotalCash > 0)
                                    <p class="mt-0.5 text-[10px] font-bold text-emerald-400">Cash: ₹{{ number_format($tableTotalCash, 2) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <!-- Mobile Bottom-Sheet Bill Modal -->
    <div id="mobile-bill-modal" class="fixed inset-0 z-[100] hidden flex-col justify-end bg-slate-900/60 backdrop-blur-xs overscroll-none touch-none transition-opacity duration-200" onclick="if (event.target === this) closeMobileBillModal()">
        <div class="relative flex max-h-[85vh] w-full max-w-lg mx-auto flex-col rounded-t-3xl bg-white shadow-2xl transition-transform duration-300 touch-pan-y">
            <!-- Sticky Header -->
            <div class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-100 bg-white px-4 py-3 rounded-t-3xl">
                <h3 class="text-sm font-black text-slate-950">Bill Details</h3>
                <button type="button" onclick="closeMobileBillModal()" class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 text-xs font-bold">
                    ✕
                </button>
            </div>

            <!-- Scrollable Body Content -->
            <div class="flex-1 overflow-y-auto overscroll-contain [-webkit-overflow-scrolling:touch] p-4 space-y-3 text-xs font-semibold text-slate-700">
                <!-- Vendor Info -->
                <div class="border-b border-slate-100 pb-2">
                    <h4 id="mb-supplier-name" class="text-sm font-black text-slate-950"></h4>
                    <p id="mb-supplier-mobile" class="mt-0.5 text-[11px] text-slate-500 font-medium"></p>
                </div>

                <!-- Reference & Details Grid -->
                <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-2.5 text-xs border border-slate-100">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Bill Ref</p>
                        <p id="mb-bill-ref" class="font-mono font-bold text-slate-900 mt-0.5 text-[11px]"></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Date</p>
                        <p id="mb-date" class="font-bold text-slate-900 mt-0.5 text-[11px]"></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Invoice</p>
                        <p id="mb-invoice" class="font-mono font-bold text-teal-700 mt-0.5 text-[11px] break-all"></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Payment Status</p>
                        <p id="mb-payment-status" class="font-black text-amber-700 mt-0.5 text-[11px]"></p>
                    </div>
                </div>

                <!-- Amount Summary -->
                <div class="rounded-xl border border-slate-900 bg-slate-950 p-3 text-white shadow-xs">
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Amount Summary</p>
                    <div class="mt-1.5 space-y-1 text-xs">
                        <div class="flex justify-between font-black text-xs text-white">
                            <span>Total</span>
                            <span id="mb-total"></span>
                        </div>
                        <div id="mb-cash-row" class="flex justify-between text-emerald-400 font-bold text-[11px]">
                            <span>Cash</span>
                            <span id="mb-cash"></span>
                        </div>
                        <div id="mb-credit-row" class="flex justify-between text-amber-400 font-bold text-[11px]">
                            <span>Credit</span>
                            <span id="mb-credit"></span>
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                        <h4 class="font-black text-slate-950 text-xs">Items — <span id="mb-items-count">0</span></h4>
                    </div>
                    <div id="mb-items-list" class="mt-1 divide-y divide-slate-100 text-xs max-h-72 overflow-y-auto overscroll-contain [-webkit-overflow-scrolling:touch] pr-1">
                        <!-- Items rendered dynamically -->
                    </div>
                </div>

                <!-- GRN Number -->
                <div class="border-t border-slate-200 pt-2">
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">GRN</p>
                    <p id="mb-grn" class="font-mono font-bold text-slate-800 mt-0.5 text-[11px]"></p>
                </div>
            </div>

            <!-- Sticky Footer Action -->
            <div class="sticky bottom-0 z-20 border-t border-slate-100 bg-white p-3">
                <button type="button" onclick="closeMobileBillModal()" class="w-full inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-xs font-black text-slate-700 hover:bg-slate-200">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Simplified Payment Update Modal -->
    <div id="payment-update-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overscroll-none touch-none" onclick="if (event.target === this) closePaymentModal()">
        <div class="w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-4 shadow-xl text-xs font-semibold text-slate-800 touch-pan-y">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Update Payment</h3>
                    <p id="payment-modal-invoice" class="text-[11px] font-semibold text-slate-500 truncate mt-0.5"></p>
                </div>
                <button type="button" onclick="closePaymentModal()" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
            </div>

            <form id="payment-update-form" method="POST" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" id="payment-form-method-override" name="_method" value="PATCH" disabled>
                <input type="hidden" id="payment-form-cart-id" name="cart_id" value="">
                <input type="hidden" id="payment-form-supplier-id" name="supplier_id" value="">
                <input type="hidden" id="payment-form-business-date" name="business_date" value="">
                <input type="hidden" id="payment-form-paid-amount" name="paid_amount" value="">
                <input type="hidden" name="return_to" value="history">
                <input type="hidden" name="date" value="{{ request('date', $date ?? now()->format('Y-m-d')) }}">
                <input type="hidden" id="payment-form-tab" name="tab" value="today">
                <input type="hidden" id="discount_amount" name="discount_amount" value="0.00">

                <!-- Total Amount & Balance Card -->
                <div class="rounded-xl border border-slate-900 bg-slate-950 p-3 text-white shadow-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Bill</span>
                        <span id="payment-modal-total" class="font-mono text-base font-black text-white">₹0.00</span>
                    </div>
                    <div id="payment-modal-already-paid-row" class="hidden flex items-center justify-between text-[11px] font-bold text-slate-400">
                        <span>Already Paid</span>
                        <span id="payment-modal-already-paid" class="font-mono font-bold text-emerald-400">₹0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-300">
                        <span>Remaining Balance</span>
                        <span id="payment-modal-balance" class="font-mono font-black text-amber-400">₹0.00</span>
                    </div>
                </div>

                <!-- Paid Amount Input -->
                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Paid Amount (₹)</label>
                    <input id="additional_paid_amount" type="number" step="0.01" min="0" name="additional_paid_amount" placeholder="Enter amount paid" class="mt-1 h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 font-mono text-sm font-black text-slate-950 focus:bg-white focus:border-teal-600 focus:outline-none">
                </div>

                <!-- Auto Difference Action (Discount vs Balance) -->
                <div id="diff-action-container" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-2.5 space-y-2">
                    <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <span>Unpaid Difference</span>
                        <span id="diff-amount-label" class="font-mono text-slate-900 font-bold">₹0.00</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                        <button type="button" id="diff-btn-discount" onclick="setDiffMode('discount')" class="h-8 rounded-lg border text-[10px] font-black transition-all">
                            Discount
                        </button>
                        <button type="button" id="diff-btn-balance" onclick="setDiffMode('balance')" class="h-8 rounded-lg border text-[10px] font-black transition-all">
                            Balance
                        </button>
                    </div>
                </div>

                <!-- Payment Method -->
                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Payment Method</label>
                    <input type="hidden" id="payment_method" name="payment_method" value="Cash">
                    <div class="mt-1 grid grid-cols-4 gap-1.5">
                        <button type="button" onclick="selectPaymentMethod('Cash')" id="pm-btn-Cash" class="h-8 rounded-lg border border-teal-600 bg-teal-600 text-[11px] font-black text-white shadow-2xs transition-all">Cash</button>
                        <button type="button" onclick="selectPaymentMethod('Online')" id="pm-btn-Online" class="h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all">Online</button>
                        <button type="button" onclick="selectPaymentMethod('GPay')" id="pm-btn-GPay" class="h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all">GPay</button>
                        <button type="button" onclick="selectPaymentMethod('Credit')" id="pm-btn-Credit" class="h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all">Credit</button>
                    </div>
                </div>

                <!-- Bill Ref No -->
                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Bill Ref No (Optional)</label>
                    <input id="bill_number" type="text" name="bill_number" placeholder="Bill number" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 active:scale-95 transition-all shadow-xs">
                    Submit Payment
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentViewMode = 'table';
        let currentInvoiceAmount = 0;
        let currentCreditApproved = false;
        let currentInvoicePaidAmount = 0;
        let currentDiffMode = 'discount';
        let userToggledDiffMode = false;
        let currentDifference = 0;

        function togglePageJumpControls(show) {
            document.querySelectorAll('.fixed.z-\\[60\\]').forEach(el => {
                el.style.display = show ? '' : 'none';
            });
        }

        function lockBackgroundScroll() {
            document.documentElement.classList.add('overflow-hidden', 'touch-none');
            document.body.classList.add('overflow-hidden', 'touch-none');
        }

        function unlockBackgroundScroll() {
            document.documentElement.classList.remove('overflow-hidden', 'touch-none');
            document.body.classList.remove('overflow-hidden', 'touch-none');
        }

        function setDiffMode(mode) {
            userToggledDiffMode = true;
            currentDiffMode = mode;
            updatePaymentModalStatus();
        }

        function selectPaymentMethod(method) {
            const methods = ['Cash', 'Online', 'GPay', 'Credit'];
            const input = document.getElementById('payment_method');
            if (input) input.value = method;

            methods.forEach(m => {
                const btn = document.getElementById(`pm-btn-${m}`);
                if (!btn) return;
                if (m === method) {
                    btn.className = 'h-8 rounded-lg border border-teal-600 bg-teal-600 text-[11px] font-black text-white shadow-2xs transition-all';
                } else {
                    btn.className = 'h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all';
                }
            });

            updatePaymentModalStatus();
        }

        function switchReportTab(tab) {
            const tabs = ['today', 'credit', 'due', 'history'];

            tabs.forEach((tabKey) => {
                const button = document.getElementById(`report-tab-btn-${tabKey}`);
                const section = document.getElementById(`report-section-${tabKey}`);
                const totalSummary = document.getElementById(`tab-summary-total-${tabKey}`);

                if (!button || !section) {
                    return;
                }

                if (tabKey === tab) {
                    button.className = 'bg-white text-slate-950 shadow-2xs font-black inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-all';
                    section.classList.remove('hidden');
                    if (totalSummary) totalSummary.classList.remove('hidden');
                } else {
                    button.className = 'text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-all';
                    section.classList.add('hidden');
                    if (totalSummary) totalSummary.classList.add('hidden');
                }
            });

            const tabFormInput = document.getElementById('payment-form-tab');
            if (tabFormInput) tabFormInput.value = tab;
        }

        function filterVendorCards(query) {
            const q = query.toLowerCase().trim();
            document.querySelectorAll('.report-view-cards article, .report-view-table tbody tr').forEach(el => {
                if (!q) {
                    el.style.display = '';
                    return;
                }
                const text = el.textContent.toLowerCase();
                if (text.includes(q)) {
                    el.style.display = '';
                } else {
                    el.style.display = 'none';
                }
            });
        }

        function setReportViewMode(mode) {
            currentViewMode = mode;
            const tableBtn = document.getElementById('view-mode-table-btn');
            const cardsBtn = document.getElementById('view-mode-cards-btn');

            if (!tableBtn || !cardsBtn) return;

            if (mode === 'table') {
                tableBtn.className = 'rounded-lg bg-slate-950 px-3 py-1.5 text-[10px] font-black text-white shadow-xs transition-all';
                cardsBtn.className = 'rounded-lg px-3 py-1.5 text-[10px] font-black text-slate-500 hover:text-slate-800 transition-all';

                document.querySelectorAll('.report-view-table').forEach(el => {
                    el.classList.remove('hidden');
                    el.classList.add('md:block');
                });
                document.querySelectorAll('.report-view-cards').forEach(el => {
                    el.classList.add('hidden');
                    el.classList.remove('md:block');
                });
            } else {
                cardsBtn.className = 'rounded-lg bg-slate-950 px-3 py-1.5 text-[10px] font-black text-white shadow-xs transition-all';
                tableBtn.className = 'rounded-lg px-3 py-1.5 text-[10px] font-black text-slate-500 hover:text-slate-800 transition-all';

                document.querySelectorAll('.report-view-cards').forEach(el => {
                    el.classList.remove('hidden');
                    el.classList.add('md:block');
                });
                document.querySelectorAll('.report-view-table').forEach(el => {
                    el.classList.add('hidden');
                    el.classList.remove('md:block');
                });
            }
        }

        function openMobileBillModal(data) {
            togglePageJumpControls(false);
            document.getElementById('mb-supplier-name').textContent = data.supplierName;
            document.getElementById('mb-supplier-mobile').textContent = data.supplierMobile ? data.supplierMobile : 'Mobile pending';
            document.getElementById('mb-bill-ref').textContent = data.billRef;
            document.getElementById('mb-date').textContent = data.date;
            document.getElementById('mb-invoice').textContent = data.invoiceNumber;
            document.getElementById('mb-payment-status').textContent = data.paymentStatus;
            document.getElementById('mb-total').textContent = data.totalAmount;
            document.getElementById('mb-cash').textContent = data.cashAmount;
            document.getElementById('mb-credit').textContent = data.creditAmount;
            document.getElementById('mb-grn').textContent = data.grnNumber;

            const countNode = document.getElementById('mb-items-count');
            if (countNode) {
                countNode.textContent = data.items.length;
            }

            const itemsList = document.getElementById('mb-items-list');
            if (itemsList) {
                if (data.items.length > 0) {
                    itemsList.innerHTML = data.items.map(item => `
                        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-0 text-xs">
                            <div class="min-w-0 pr-2">
                                <p class="font-black text-slate-950 truncate">${item.name}</p>
                                <p class="text-[10px] font-semibold text-slate-500">${item.quantity} ${item.unit} × ₹${item.price}</p>
                            </div>
                            <span class="font-mono font-black text-slate-950 shrink-0">₹${item.total}</span>
                        </div>
                    `).join('');
                } else {
                    itemsList.innerHTML = '<p class="py-1.5 text-slate-500 font-semibold italic text-xs">No items listed</p>';
                }
            }

            const modal = document.getElementById('mobile-bill-modal');
            if (modal) {
                lockBackgroundScroll();
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeMobileBillModal() {
            togglePageJumpControls(true);
            unlockBackgroundScroll();
            const modal = document.getElementById('mobile-bill-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function openPaymentModal(invoice, actionUrl) {
            if (!invoice || !actionUrl) return;
            togglePageJumpControls(false);
            userToggledDiffMode = false;
            currentInvoiceAmount = Number(invoice.amount || 0);
            currentCreditApproved = Boolean(invoice.creditApproved);
            currentInvoicePaidAmount = Number(invoice.paidAmount || 0);

            const form = document.getElementById('payment-update-form');
            const methodOverride = document.getElementById('payment-form-method-override');
            const cartIdInput = document.getElementById('payment-form-cart-id');
            const supplierIdInput = document.getElementById('payment-form-supplier-id');
            const bDateInput = document.getElementById('payment-form-business-date');

            if (form) form.action = actionUrl;

            let itemsContainer = document.getElementById('payment-form-items-inputs');
            if (!itemsContainer) {
                itemsContainer = document.createElement('div');
                itemsContainer.id = 'payment-form-items-inputs';
                form.appendChild(itemsContainer);
            }
            itemsContainer.innerHTML = '';

            if (invoice.isSubmitMode && invoice.cartItems) {
                if (methodOverride) methodOverride.disabled = true;
                if (cartIdInput) cartIdInput.value = invoice.cartId || '';
                if (supplierIdInput) supplierIdInput.value = invoice.supplierId || '';
                if (bDateInput) bDateInput.value = invoice.businessDate || '';

                Object.entries(invoice.cartItems).forEach(([itemId, itemData]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `items[${itemId}][unit_price]`;
                    input.value = itemData.unit_price || 0;
                    itemsContainer.appendChild(input);
                });
            } else {
                if (methodOverride) {
                    methodOverride.disabled = false;
                    methodOverride.value = 'PATCH';
                }
                if (cartIdInput) cartIdInput.value = '';
                if (supplierIdInput) supplierIdInput.value = '';
                if (bDateInput) bDateInput.value = '';
            }

            const invNode = document.getElementById('payment-modal-invoice');
            if (invNode) invNode.textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;

            const totalNode = document.getElementById('payment-modal-total');
            if (totalNode) totalNode.textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;

            const alreadyPaidRow = document.getElementById('payment-modal-already-paid-row');
            const alreadyPaidNode = document.getElementById('payment-modal-already-paid');
            if (alreadyPaidRow && alreadyPaidNode) {
                if (currentInvoicePaidAmount > 0) {
                    alreadyPaidRow.classList.remove('hidden');
                    alreadyPaidNode.textContent = `₹${currentInvoicePaidAmount.toFixed(2)}`;
                } else {
                    alreadyPaidRow.classList.add('hidden');
                }
            }

            const billNumInput = document.getElementById('bill_number');
            if (billNumInput) billNumInput.value = invoice.billNumber || '';

            selectPaymentMethod(invoice.paymentMethod || 'Cash');

            const addPaidInput = document.getElementById('additional_paid_amount');
            if (addPaidInput) addPaidInput.value = '';

            updatePaymentModalStatus();
            const modal = document.getElementById('payment-update-modal');
            if (modal) {
                lockBackgroundScroll();
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closePaymentModal() {
            togglePageJumpControls(true);
            unlockBackgroundScroll();
            const modal = document.getElementById('payment-update-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function updatePaymentModalStatus() {
            const addPaidInput = document.getElementById('additional_paid_amount');
            const hiddenPaidInput = document.getElementById('payment-form-paid-amount');
            const discInput = document.getElementById('discount_amount');
            const diffContainer = document.getElementById('diff-action-container');
            const diffAmtNode = document.getElementById('diff-amount-label');
            const btnDiscount = document.getElementById('diff-btn-discount');
            const btnBalance = document.getElementById('diff-btn-balance');

            const additionalPaidAmount = Math.max(0, Number(addPaidInput?.value || 0));
            if (hiddenPaidInput) hiddenPaidInput.value = additionalPaidAmount;

            const totalBill = currentInvoiceAmount;
            const alreadyPaid = currentInvoicePaidAmount;
            const remainingToPay = Math.max(0, totalBill - alreadyPaid);

            const rawDiff = Math.max(0, remainingToPay - additionalPaidAmount);
            currentDifference = rawDiff;
            const diffPercent = remainingToPay > 0 ? (rawDiff / remainingToPay) * 100 : 0;

            if (rawDiff > 0.01 && additionalPaidAmount > 0) {
                if (diffContainer) diffContainer.classList.remove('hidden');
                if (diffAmtNode) diffAmtNode.textContent = `₹${rawDiff.toFixed(2)}`;

                if (btnDiscount) btnDiscount.textContent = `Discount (₹${rawDiff.toFixed(2)})`;
                if (btnBalance) btnBalance.textContent = `Balance (₹${rawDiff.toFixed(2)})`;

                if (!userToggledDiffMode) {
                    currentDiffMode = diffPercent <= 5 ? 'discount' : 'balance';
                }

                if (currentDiffMode === 'discount') {
                    if (discInput) discInput.value = rawDiff.toFixed(2);
                    if (btnDiscount) btnDiscount.className = 'h-8 rounded-lg border border-teal-600 bg-teal-600 text-[10px] font-black text-white shadow-2xs transition-all';
                    if (btnBalance) btnBalance.className = 'h-8 rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-black text-slate-700 hover:bg-slate-100 transition-all';
                } else {
                    if (discInput) discInput.value = '0.00';
                    if (btnBalance) btnBalance.className = 'h-8 rounded-lg border border-amber-600 bg-amber-600 text-[10px] font-black text-white shadow-2xs transition-all';
                    if (btnDiscount) btnDiscount.className = 'h-8 rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-black text-slate-700 hover:bg-slate-100 transition-all';
                }
            } else {
                if (diffContainer) diffContainer.classList.add('hidden');
                if (discInput) discInput.value = '0.00';
            }

            const discountVal = Math.max(0, Number(discInput?.value || 0));
            const netDue = Math.max(0, remainingToPay - discountVal);
            const balance = Math.max(0, netDue - additionalPaidAmount);

            const balanceNode = document.getElementById('payment-modal-balance');
            if (balanceNode) {
                balanceNode.textContent = `₹${balance.toFixed(2)}`;
                balanceNode.className = balance > 0 ? 'font-mono font-black text-amber-400' : 'font-mono font-black text-emerald-400';
            }
        }

        document.getElementById('additional_paid_amount')?.addEventListener('input', updatePaymentModalStatus);
    </script>
</x-layouts.app>
