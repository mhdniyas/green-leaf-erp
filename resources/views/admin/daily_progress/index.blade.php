<x-layouts.admin title="Daily Operational Progress">
    @php
        $carbonDate = \Illuminate\Support\Carbon::parse($date);
        $prevDate = $carbonDate->copy()->subDay()->format('Y-m-d');
        $nextDate = $carbonDate->copy()->addDay()->format('Y-m-d');
        $todayDate = \Illuminate\Support\Carbon::today()->format('Y-m-d');
        $toneClasses = [
            'slate' => 'border-slate-200 bg-white text-slate-900',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'sky' => 'border-sky-200 bg-sky-50 text-sky-900',
            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-900',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
            'teal' => 'border-teal-200 bg-teal-50 text-teal-900',
            'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
        ];
        $focusToneClasses = [
            'slate' => 'border-slate-200 bg-slate-50 text-slate-700',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
            'rose' => 'border-rose-200 bg-rose-50 text-rose-800',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Daily Control Room</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Daily Operational Progress</h1>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                        Admin view for purchase intake, warehouse readiness, shop order movement, delivery completion, collections, accounting, and staff attendance.
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.daily-progress') }}" class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.daily-progress', ['date' => $prevDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50" title="Previous Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </a>

                    <input
                        type="date"
                        name="date"
                        value="{{ $date }}"
                        onchange="this.form.submit()"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-black text-slate-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                    >

                    @if($date !== $todayDate)
                        <a href="{{ route('admin.daily-progress', ['date' => $todayDate]) }}" class="inline-flex h-10 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700 transition-colors hover:bg-emerald-100">
                            Today
                        </a>
                    @endif

                    <a href="{{ route('admin.daily-progress', ['date' => $nextDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50" title="Next Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </form>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($decisionCards as $card)
                    <div class="{{ $toneClasses[$card['tone']] ?? $toneClasses['slate'] }} rounded-lg border p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] opacity-60">{{ $card['label'] }}</p>
                        <p class="mt-3 text-2xl font-black">{{ $card['value'] }}</p>
                        <p class="mt-2 text-xs font-semibold leading-5 opacity-80">{{ $card['meta'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Orders</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $totalOrdersCount }}</p>
                <p class="mt-2 text-xs font-semibold text-slate-500">{{ $submittedOrdersCount }} submitted, {{ $approvedOrdersCount }} approved, {{ $deliveredOrdersCount }} delivered</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-teal-500" style="width: {{ max(4, $orderCompletionPercent) }}%;"></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Stock Intake</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ number_format($totalStockKg, 2) }} kg</p>
                <p class="mt-2 text-xs font-semibold text-slate-500">{{ $pendingBatchesCount }} pending sort, {{ $sortedBatchesCount }} sorted, {{ $closedBatchesCount }} closed</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ $totalStockKg > 0 ? max(8, min(100, ($sortedBatchesCount + $closedBatchesCount) / max(1, $pendingBatchesCount + $sortedBatchesCount + $closedBatchesCount) * 100)) : 4 }}%;"></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Collections</p>
                <p class="mt-3 text-3xl font-black text-slate-950">Rs. {{ number_format($invoicePaidTotal, 2) }}</p>
                <p class="mt-2 text-xs font-semibold text-slate-500">Balance Rs. {{ number_format($invoiceBalanceTotal, 2) }} from {{ $generatedInvoicesCount }} invoice(s)</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(4, $collectionProgressPercent) }}%;"></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Exceptions</p>
                <p class="mt-3 text-3xl font-black {{ $discrepancyOrdersCount > 0 || $pendingDeliveryReviewCount > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $discrepancyOrdersCount + $pendingDeliveryReviewCount }}</p>
                <p class="mt-2 text-xs font-semibold text-slate-500">{{ $pendingDeliveryReviewCount }} delivery reviews, {{ $pendingRevisionOrdersCount }} updates, {{ $lateOrdersCount }} late</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $discrepancyOrdersCount > 0 || $pendingDeliveryReviewCount > 0 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $discrepancyOrdersCount > 0 || $pendingDeliveryReviewCount > 0 ? 100 : 8 }}%;"></div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">End-to-End Flow</h2>
                <p class="mt-1 text-xs text-slate-500">Follow the date from purchase intake through shop delivery and cash closure.</p>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($flowStages as $stage)
                    <div class="{{ $toneClasses[$stage['tone']] ?? $toneClasses['slate'] }} rounded-lg border p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] opacity-70">{{ $stage['label'] }}</p>
                        <div class="mt-3 flex items-end justify-between gap-3">
                            <p class="text-3xl font-black">{{ $stage['count'] }}</p>
                            <p class="text-right text-[11px] font-black uppercase tracking-[0.14em] opacity-60">{{ $stage['meta'] }}</p>
                        </div>
                        <p class="mt-3 text-xs font-semibold leading-5 opacity-80">{{ $stage['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1fr_420px]">
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Admin Focus</h2>
                    <p class="mt-1 text-xs text-slate-500">The highest-signal items to check for {{ $carbonDate->format('d M Y') }}.</p>
                </div>
                <div class="grid gap-3 p-5 md:grid-cols-2">
                    @foreach($adminFocusItems as $item)
                        <div class="{{ $focusToneClasses[$item['tone']] ?? $focusToneClasses['slate'] }} rounded-lg border p-4">
                            <p class="text-sm font-black text-slate-950">{{ $item['label'] }}</p>
                            <p class="mt-2 text-xs font-semibold leading-5">{{ $item['detail'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Day Comparison</h2>
                    <p class="mt-1 text-xs text-slate-500">Compares {{ $carbonDate->format('d M') }} with {{ \Illuminate\Support\Carbon::parse($previousDate)->format('d M') }}.</p>
                </div>
                <div class="space-y-3 p-5">
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Purchase Orders</span>
                        <span class="text-sm font-black text-slate-950">{{ $totalPoCount }} today / {{ $previousPoCount }} previous</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Shop Orders</span>
                        <span class="text-sm font-black text-slate-950">{{ $totalOrdersCount }} today / {{ $previousOrdersCount }} previous</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Active Shops</span>
                        <span class="text-sm font-black text-slate-950">{{ $shopsWithOrdersCount }} ordered / {{ $activeShopCount }} active</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</span>
                        <span class="text-sm font-black text-slate-950">Rs. {{ number_format($closingCashTotal, 2) }}</span>
                    </div>
                </div>
            </div>
        </section>

        @if($totalOrdersCount === 0 && $totalPoCount === 0 && $grnCount === 0 && $generatedInvoicesCount === 0)
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <h2 class="text-base font-black text-amber-950">No operational activity found for {{ $carbonDate->format('d M Y') }}</h2>
                <p class="mt-2 text-sm leading-6 text-amber-800">
                    This date has no purchase orders, GRNs, shop requisitions, or shop invoices in the local database. Start by checking whether shops submitted requisitions for the date and whether purchase/warehouse teams recorded receipts.
                </p>
            </section>
        @endif

        <section class="grid gap-5 xl:grid-cols-[1fr_420px]">
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Orders Needing Attention</h2>
                        <p class="mt-1 text-xs text-slate-500">Late, pending review, revision, shortage, or cash variance orders.</p>
                    </div>
                    <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">{{ $atRiskOrders->count() }} shown</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($atRiskOrders as $order)
                        <article class="p-5">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <a href="{{ route('requisitions.show', $order) }}" class="text-sm font-black text-slate-950 hover:text-emerald-700">{{ $order->order_number }}</a>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $order->shop?->name ?? 'Unknown shop' }} · {{ $order->warehouseWorkflowLabel() }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if($order->hasPendingDeliveryReview())
                                        <span class="rounded-full bg-amber-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Delivery Review</span>
                                    @endif
                                    @if($order->has_pending_revision)
                                        <span class="rounded-full bg-sky-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-sky-700">Update Pending</span>
                                    @endif
                                    @if($order->is_late)
                                        <span class="rounded-full bg-rose-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">Late</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Shortage</p>
                                    <p class="mt-1 text-sm font-black {{ (float) $order->total_shortage_value > 0 ? 'text-rose-700' : 'text-slate-500' }}">Rs. {{ number_format((float) $order->total_shortage_value, 2) }}</p>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Cash Variance</p>
                                    <p class="mt-1 text-sm font-black {{ abs((float) $order->cash_discrepancy) > 0.01 ? 'text-amber-700' : 'text-slate-500' }}">Rs. {{ number_format(abs((float) $order->cash_discrepancy), 2) }}</p>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Payment</p>
                                    <p class="mt-1 text-sm font-black text-slate-700">{{ str($order->payment_status ?: 'pending')->replace('_', ' ')->title() }}</p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <p class="text-sm font-black text-emerald-700">No flagged orders for this date.</p>
                            <p class="mt-1 text-xs text-slate-500">Delivery, revision, shortage, and cash variance checks are clear.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Finance Snapshot</h2>
                    <p class="mt-1 text-xs text-slate-500">Shop invoice and accounting close status.</p>
                </div>
                <div class="space-y-3 p-5">
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Invoices</p>
                            <p class="text-sm font-black text-slate-950">{{ $generatedInvoicesCount }}</p>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-emerald-50 px-2 py-2 text-emerald-800">
                                <p class="text-lg font-black">{{ $paidInvoicesCount }}</p>
                                <p class="text-[10px] font-bold uppercase">Paid</p>
                            </div>
                            <div class="rounded-lg bg-amber-50 px-2 py-2 text-amber-800">
                                <p class="text-lg font-black">{{ $partialInvoicesCount }}</p>
                                <p class="text-[10px] font-bold uppercase">Partial</p>
                            </div>
                            <div class="rounded-lg bg-rose-50 px-2 py-2 text-rose-800">
                                <p class="text-lg font-black">{{ $unpaidInvoicesCount }}</p>
                                <p class="text-[10px] font-bold uppercase">Unpaid</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Accounting</p>
                        <p class="mt-3 text-2xl font-black text-slate-950">{{ $accountingProgressPercent }}%</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $accountingSubmittedCount }} submitted, {{ $accountingApprovedCount }} approved, {{ $accountingRecheckCount }} recheck, {{ $accountingMissingCount }} missing</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Staff Attendance</p>
                        <p class="mt-3 text-2xl font-black text-slate-950">{{ $attendancePresentCount }} present</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $attendanceHalfDayCount }} half day, {{ $attendanceAbsentCount }} absent, {{ $attendanceLeaveCount }} leave</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Shop-by-Shop Progress</h2>
                    <p class="mt-1 text-xs text-slate-500">Every active shop is listed, including shops with no order for the selected date.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-[11px] font-bold text-slate-500">
                    <span class="rounded-full bg-slate-100 px-3 py-1">No order: {{ $shopsWithoutOrdersCount }}</span>
                    <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Flagged: {{ $discrepancyOrdersCount }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-left">
                    <thead class="bg-slate-50/70 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Shop</th>
                            <th class="px-4 py-4 text-center">Orders</th>
                            <th class="px-4 py-4 text-center">Dispatch</th>
                            <th class="px-4 py-4 text-center">Delivered</th>
                            <th class="px-4 py-4 text-center">Invoice</th>
                            <th class="px-4 py-4 text-center">Accounting</th>
                            <th class="px-4 py-4 text-center">Staff</th>
                            <th class="px-4 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Exception Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @foreach($shopProgressRows as $row)
                            @php
                                $hasFlag = $row['discrepancy_orders'] > 0 || $row['pending_review_orders'] > 0;
                                $variance = (float) $row['cash_discrepancy_total'];
                                $exceptionValue = (float) $row['shortage_total'] + abs($variance);
                                $progressWidth = max(6, $row['progress_percent']);
                                $accountingEntry = $row['accounting_entry'];
                            @endphp
                            <tr class="align-top transition-colors hover:bg-slate-50/70">
                                <td class="px-5 py-5">
                                    <div class="flex min-w-[220px] flex-col gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-900">{{ $row['shop']->name }}</p>
                                            <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ $row['shop']->code }}</p>
                                        </div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                @class([
                                                    'h-full rounded-full',
                                                    'bg-rose-500' => $hasFlag,
                                                    'bg-teal-500' => ! $hasFlag && $row['delivered_orders'] === $row['total_orders'] && $row['total_orders'] > 0,
                                                    'bg-sky-500' => ! $hasFlag && $row['out_for_delivery_orders'] > 0,
                                                    'bg-emerald-500' => ! $hasFlag && $row['approved_orders'] > 0,
                                                    'bg-slate-300' => $row['total_orders'] === 0,
                                                ])
                                                style="width: {{ $progressWidth }}%;"
                                            ></div>
                                        </div>
                                        @if($row['latest_order_number'])
                                            <a href="{{ route('requisitions.show', $row['latest_order_number']) }}" class="text-[11px] font-black text-emerald-700 underline decoration-emerald-200 underline-offset-4 hover:text-emerald-800">
                                                Latest: {{ $row['latest_order_number'] }}
                                            </a>
                                        @else
                                            <p class="text-[11px] font-semibold text-slate-400">No requisition for this date</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-5 text-center">
                                    <p class="text-lg font-black text-slate-900">{{ $row['total_orders'] }}</p>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $row['approved_orders'] }} approved</p>
                                </td>
                                <td class="px-4 py-5 text-center text-lg font-black text-sky-700">{{ $row['out_for_delivery_orders'] }}</td>
                                <td class="px-4 py-5 text-center text-lg font-black text-teal-700">{{ $row['delivered_orders'] }}</td>
                                <td class="px-4 py-5 text-center">
                                    <p class="text-sm font-black text-slate-900">Rs. {{ number_format($row['invoice_total'], 2) }}</p>
                                    <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] {{ $row['balance_total'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                        {{ $row['invoice_count'] }} invoice(s), Rs. {{ number_format($row['balance_total'], 2) }} due
                                    </p>
                                    @if($row['invoices']->isNotEmpty())
                                        @php($latestInvoice = $row['invoices']->sortByDesc('created_at')->first())
                                        <a href="{{ route('purchasing.shop-invoices.show', $latestInvoice) }}" class="mt-2 inline-flex text-[10px] font-black text-slate-500 underline decoration-slate-200 underline-offset-4 hover:text-emerald-700">
                                            {{ $latestInvoice->invoice_number }}
                                        </a>
                                    @endif
                                </td>
                                <td class="px-4 py-5 text-center">
                                    @if($accountingEntry)
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em]',
                                            'bg-emerald-50 text-emerald-700' => $accountingEntry->status === 'approved',
                                            'bg-amber-50 text-amber-700' => $accountingEntry->status === 'submitted',
                                            'bg-rose-50 text-rose-700' => $accountingEntry->status === 'recheck_required',
                                            'bg-slate-100 text-slate-600' => ! in_array($accountingEntry->status, ['approved', 'submitted', 'recheck_required'], true),
                                        ])>
                                            {{ $accountingEntry->statusLabel() }}
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Missing</span>
                                    @endif
                                </td>
                                <td class="px-4 py-5 text-center">
                                    <p class="text-sm font-black text-slate-900">{{ $row['attendance_present'] }}/{{ $row['attendance_count'] }}</p>
                                    <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">present</p>
                                </td>
                                <td class="px-4 py-5">
                                    <span @class([
                                        'inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em]',
                                        'bg-slate-100 text-slate-500' => $row['total_orders'] === 0,
                                        'bg-rose-50 text-rose-700' => $hasFlag,
                                        'bg-teal-50 text-teal-700' => ! $hasFlag && $row['delivered_orders'] === $row['total_orders'] && $row['total_orders'] > 0,
                                        'bg-sky-50 text-sky-700' => ! $hasFlag && $row['out_for_delivery_orders'] > 0,
                                        'bg-emerald-50 text-emerald-700' => ! $hasFlag && $row['approved_orders'] > 0,
                                        'bg-amber-50 text-amber-700' => ! $hasFlag && $row['approved_orders'] === 0 && $row['total_orders'] > 0,
                                    ])>
                                        {{ $row['status_label'] }}
                                    </span>
                                    @if($row['pending_review_orders'] > 0)
                                        <p class="mt-2 text-[11px] font-bold text-amber-600">{{ $row['pending_review_orders'] }} review pending</p>
                                    @endif
                                </td>
                                <td class="px-5 py-5 text-right">
                                    <p class="font-black {{ $exceptionValue > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                        {{ $exceptionValue > 0 ? 'Rs. '.number_format($exceptionValue, 2) : 'Nil' }}
                                    </p>
                                    @if($row['shortage_total'] > 0 || abs($variance) > 0.01)
                                        <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                            S {{ number_format($row['shortage_total'], 2) }} · V {{ number_format(abs($variance), 2) }}
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.admin>
