@php
    $showHeader ??= true;
    $vendorSummary = $finance['vendor']['summary'];
    $salesSummary = $finance['sales']['summary'];
    $vendorRows = $finance['vendor']['rows'];
    $salesRows = $finance['sales']['rows'];
    $vendorChart = $finance['vendor']['chart'];
    $salesChart = $finance['sales']['chart'];
    $vendorDailyRows = $finance['vendor']['daily_rows'];
    $salesDailyRows = $finance['sales']['daily_rows'];
    $pendingCreditRequests = $finance['pending_credit_requests'];
@endphp

<section class="space-y-5">
    @if ($showHeader)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Finance Reports</p>
                <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Vendor Reports and Sales Reports</h2>
            </div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-teal-700">Section 01</p>
                        <h3 class="mt-2 text-xl font-black text-slate-950">Vendor Reports</h3>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Daily purchasing credits, daily debit payments, and vendor-level due tracking.</p>
                    </div>
                    <a href="{{ route('purchasing.invoices.index', ['date' => $endDate->format('Y-m-d')]) }}" class="inline-flex h-10 items-center rounded-2xl border border-teal-200 bg-white px-4 text-xs font-black uppercase tracking-[0.18em] text-teal-700 transition hover:bg-teal-50">
                        Open Vendor Ledger
                    </a>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Vendors</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($vendorSummary['count']) }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Credit</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($vendorSummary['total_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Debit</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($vendorSummary['paid_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Balance</p>
                        <p class="mt-2 text-2xl font-black text-amber-900">Rs. {{ number_format($vendorSummary['outstanding_amount'], 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-5">
                <div class="grid gap-5 lg:grid-cols-[0.95fr_1.3fr]">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Top Vendor Movement</p>
                        <div class="mt-4 space-y-3">
                            @forelse ($vendorChart as $bar)
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-xs font-bold text-slate-600">
                                        <span class="truncate">{{ $bar['label'] }}</span>
                                        <span>Rs. {{ number_format($bar['amount'], 2) }}</span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-teal-600" style="width: {{ $bar['width'] }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">No vendor movement in this period.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Vendor</th>
                                    <th class="px-4 py-3 text-right">Credit</th>
                                    <th class="px-4 py-3 text-right">Debit</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3 text-right">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @forelse ($vendorRows as $row)
                                    @php
                                        $vendor = $row['vendor'];
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-black text-slate-950">{{ $vendor?->name ?? 'Vendor pending' }}</p>
                                            <p class="mt-1 font-semibold text-slate-500">{{ $row['invoice_count'] }} bill(s) • {{ $row['status'] }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['paid_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ $row['outstanding_amount'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['outstanding_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            @if ($vendor)
                                                <a href="{{ route('purchasing.invoices.vendor-report', $vendor, false) }}?date={{ $endDate->format('Y-m-d') }}" class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 font-black text-slate-700 transition hover:bg-slate-50">Open</a>
                                            @else
                                                <span class="font-bold text-slate-400">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No vendor report rows.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Daily View</p>
                            <h4 class="mt-1 text-sm font-black text-slate-950">Daily credit and debit table</h4>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">{{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Lead Vendor</th>
                                    <th class="px-4 py-3 text-right">Credit</th>
                                    <th class="px-4 py-3 text-right">Debit</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3 text-right">View</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse ($vendorDailyRows as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-600">{{ $row['lead_label'] }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['credit_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['debit_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ $row['balance_amount'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['balance_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('finance.vendor-daily', ['date' => $row['date']]) }}" class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-50">Daily View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No vendor daily rows in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </article>

        <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-sky-700">Section 02</p>
                        <h3 class="mt-2 text-xl font-black text-slate-950">Sales Reports</h3>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Daily sales credits, debit collections, and shop-owner recovery status.</p>
                    </div>
                    <a href="{{ route('purchasing.shop-invoices.index') }}" class="inline-flex h-10 items-center rounded-2xl border border-sky-200 bg-white px-4 text-xs font-black uppercase tracking-[0.18em] text-sky-700 transition hover:bg-sky-50">
                        Open Sales Ledger
                    </a>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Shops</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($salesSummary['count']) }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Credit</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($salesSummary['total_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Debit</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($salesSummary['paid_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Balance</p>
                        <p class="mt-2 text-2xl font-black text-rose-900">Rs. {{ number_format($salesSummary['outstanding_amount'], 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-5">
                <div class="grid gap-5 lg:grid-cols-[0.95fr_1.3fr]">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Top Sales Movement</p>
                        <div class="mt-4 space-y-3">
                            @forelse ($salesChart as $bar)
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-xs font-bold text-slate-600">
                                        <span class="truncate">{{ $bar['label'] }}</span>
                                        <span>Rs. {{ number_format($bar['amount'], 2) }}</span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-sky-600" style="width: {{ $bar['width'] }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">No sales movement in this period.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Shop Owner</th>
                                    <th class="px-4 py-3 text-right">Credit</th>
                                    <th class="px-4 py-3 text-right">Debit</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3 text-right">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @forelse ($salesRows as $row)
                                    @php
                                        $shop = $row['shop'];
                                        $latestInvoice = $row['latest_invoice'];
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-black text-slate-950">{{ $shop?->name ?? 'Shop pending' }}</p>
                                            <p class="mt-1 font-semibold text-slate-500">{{ $row['invoice_count'] }} invoice(s) • {{ $row['status'] }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['paid_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ $row['outstanding_amount'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['outstanding_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('purchasing.shop-invoices.show', $latestInvoice) }}" class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 font-black text-slate-700 transition hover:bg-slate-50">Open</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No sales report rows.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Daily View</p>
                            <h4 class="mt-1 text-sm font-black text-slate-950">Daily credit and debit table</h4>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">{{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Lead Shop</th>
                                    <th class="px-4 py-3 text-right">Credit</th>
                                    <th class="px-4 py-3 text-right">Debit</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3 text-right">View</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse ($salesDailyRows as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-600">{{ $row['lead_label'] }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['credit_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['debit_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ $row['balance_amount'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['balance_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('finance.sales-daily', ['date' => $row['date']]) }}" class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-50">Daily View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No sales daily rows in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </article>
    </div>

    <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-amber-700">Approve Credit Requests</p>
                <h3 class="mt-2 text-lg font-black text-slate-950">Vendor credit waiting for admin decision</h3>
            </div>
            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">{{ $pendingCreditRequests->count() }} pending</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="bg-white text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Vendor</th>
                        <th class="px-5 py-3">Requested By</th>
                        <th class="px-5 py-3">Note</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse ($pendingCreditRequests as $supplier)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-black text-slate-950">{{ $supplier->name }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $supplier->mobile_number ?: $supplier->contact ?: 'Contact pending' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-700">{{ $supplier->creditApprovalRequestedBy?->name ?? 'System' }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $supplier->credit_approval_requested_at?->format('d M Y h:i A') }}</p>
                            </td>
                            <td class="max-w-md px-5 py-4 text-sm font-semibold text-slate-600">{{ $supplier->credit_approval_note ?: 'No note added.' }}</td>
                            <td class="px-5 py-4 text-right">
                                <form action="{{ route('purchasing.suppliers.credit-approve', $supplier) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 items-center rounded-xl bg-emerald-600 px-4 text-xs font-black text-white transition hover:bg-emerald-700">
                                        Approve Credit
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-sm font-bold text-slate-500">No vendor credit requests are waiting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</section>
