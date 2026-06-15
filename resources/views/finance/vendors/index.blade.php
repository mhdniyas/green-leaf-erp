@php
    $vendorSummary = $finance['vendor']['summary'];
    $vendorRows = $finance['vendor']['rows'];
    $vendorChart = $finance['vendor']['chart'];
    $vendorDailyRows = $finance['vendor']['daily_rows'];
    $pendingCreditRequests = $finance['pending_credit_requests'];
@endphp

<x-layouts.app title="Vendor Reports">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="bg-linear-to-r from-slate-950 via-teal-950 to-emerald-900 px-6 py-7 text-white">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.28em] text-teal-100/80">Finance / Vendor</p>
                        <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Vendor Reports</h1>
                        <p class="mt-3 text-sm font-semibold leading-6 text-slate-200">Daily vendor credit and debit movement, due balances, and period reporting.</p>
                    </div>
                    <form method="GET" action="{{ route('finance.vendors.index') }}" class="flex flex-wrap items-end gap-2 rounded-[1.5rem] border border-white/10 bg-white/10 p-3 backdrop-blur">
                        <label class="min-w-[12rem]">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">Start Date</span>
                            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="mt-2 h-10 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                        </label>
                        <label class="min-w-[12rem]">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">End Date</span>
                            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="mt-2 h-10 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                        </label>
                        <button type="submit" class="inline-flex h-10 items-center rounded-2xl bg-white px-5 text-xs font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-teal-50">Apply</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="flex flex-wrap gap-3">
            <a href="{{ route('finance.vendors.excel', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="inline-flex h-10 items-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-50">Excel Export</a>
            <a href="{{ route('finance.vendors.pdf', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="inline-flex h-10 items-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-50">PDF View</a>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Vendors</p>
                <p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($vendorSummary['count']) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Credit</p>
                <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($vendorSummary['total_amount'], 2) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Debit</p>
                <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($vendorSummary['paid_amount'], 2) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Balance</p>
                <p class="mt-2 text-3xl font-black text-amber-900">Rs. {{ number_format($vendorSummary['outstanding_amount'], 2) }}</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[0.95fr_1.3fr]">
            <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Top Vendor Movement</h2>
                </div>
                <div class="space-y-3 p-5">
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
            </article>

            <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Vendor Credit / Debit</h2>
                </div>
                <div class="overflow-x-auto">
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
                                @php($vendor = $row['vendor'])
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
            </article>
        </section>

        <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Daily Table</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Daily credit and debit</h2>
                </div>
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">{{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</span>
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
                            <th class="px-4 py-3 text-right">Daily View</th>
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
                                    <a href="{{ route('finance.vendor-daily', ['date' => $row['date']]) }}" class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-50">Open</a>
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
        </article>

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
                                        <button type="submit" class="inline-flex h-9 items-center rounded-xl bg-emerald-600 px-4 text-xs font-black text-white transition hover:bg-emerald-700">Approve Credit</button>
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
    </div>
</x-layouts.app>
