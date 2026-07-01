<x-layouts.app title="Vendor Finance Report">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-4 py-3 lg:max-w-7xl lg:gap-5 lg:px-6 lg:py-4">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="bg-linear-to-r from-emerald-950 via-teal-900 to-cyan-900 px-5 py-6 text-white lg:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-cyan-100/80">Admin Finance</p>
                        <h1 class="mt-2 text-2xl font-black tracking-tight">Vendor Finance Report</h1>
                        <p class="mt-2 text-sm font-semibold leading-6 text-cyan-50/90">Review daily vendor sections, payment progress, outstanding balances, and current finance status from one professional report.</p>
                    </div>
                    <form action="{{ route('purchasing.invoices.index') }}" method="GET" class="grid gap-3 rounded-[1.5rem] border border-white/15 bg-white/10 p-3 backdrop-blur-sm md:grid-cols-[1.2fr_0.9fr_0.9fr_auto]">
                        <div>
                            <label for="search" class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-50/80">Search Vendor</label>
                            <input id="search" type="text" name="search" value="{{ $search }}" placeholder="Vendor, mobile, invoice, cart" class="mt-2 h-11 w-full rounded-2xl border border-white/15 bg-white/90 px-4 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-cyan-300 focus:bg-white focus:outline-none">
                        </div>
                        <div>
                            <label for="payment_type" class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-50/80">Payment Type</label>
                            <select id="payment_type" name="payment_type" class="mt-2 h-11 w-full rounded-2xl border border-white/15 bg-white/90 px-4 text-sm font-semibold text-slate-900 focus:border-cyan-300 focus:bg-white focus:outline-none">
                                <option value="all" @selected($paymentFilter === 'all')>All Methods</option>
                                <option value="cash" @selected($paymentFilter === 'cash')>Cash</option>
                                <option value="credit" @selected($paymentFilter === 'credit')>Credit</option>
                                <option value="gpay" @selected($paymentFilter === 'gpay')>GPay</option>
                                <option value="online" @selected($paymentFilter === 'online')>Online</option>
                                <option value="both" @selected($paymentFilter === 'both')>Cash / GPay</option>
                            </select>
                        </div>
                        <div>
                            <label for="date" class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-50/80">Report Date</label>
                            <input id="date" type="date" name="date" value="{{ $date }}" class="mt-2 h-11 w-full rounded-2xl border border-white/15 bg-white/90 px-4 text-sm font-semibold text-slate-900 focus:border-cyan-300 focus:bg-white focus:outline-none">
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-white px-5 text-sm font-black text-slate-950 shadow-sm transition hover:bg-cyan-50">
                                Apply
                            </button>
                            <a href="{{ route('purchasing.invoices.index', ['date' => $date]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-white/20 px-5 text-sm font-black text-white/90 transition hover:bg-white/10">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="grid gap-3 border-t border-slate-200 bg-slate-50/80 p-5 sm:grid-cols-2 xl:grid-cols-4 lg:p-6">
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Active Vendors</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($summary['vendor_count']) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Grouped sections for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Bills Logged</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($summary['invoice_count']) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Invoices matched in this report window</p>
                </div>
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Value</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">₹{{ number_format($summary['total_amount'], 2) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Combined purchase finance amount</p>
                </div>
                <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Outstanding</p>
                    <p class="mt-2 text-2xl font-black text-amber-900">₹{{ number_format($summary['outstanding_amount'], 2) }}</p>
                    <p class="mt-1 text-xs font-semibold text-amber-700">Paid ₹{{ number_format($summary['paid_amount'], 2) }}</p>
                </div>
            </div>
        </section>

        @if ($vendorSections->isEmpty())
            <section class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                <p class="text-lg font-black text-slate-900">No vendor finance entries found.</p>
                <p class="mt-2 text-sm font-semibold text-slate-500">Try another date or broaden the payment and search filters.</p>
            </section>
        @else
            <div class="space-y-4">
                @foreach ($vendorSections as $section)
                    @php
                        $vendor = $section['vendor'];
                        $statusTone = $section['current_status'] === 'Settled'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-amber-200 bg-amber-50 text-amber-700';
                    @endphp
                    <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-4 border-b border-slate-200 bg-slate-50/80 px-5 py-5 lg:flex-row lg:items-start lg:justify-between lg:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-black text-slate-950">{{ $vendor?->name ?? 'Vendor Pending Assignment' }}</h2>
                                    <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $statusTone }}">
                                        {{ $section['current_status'] }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-slate-600">
                                    {{ $vendor?->mobile_number ?: $vendor?->contact ?: 'Contact pending' }}
                                    @if ($vendor?->payment_terms)
                                        • {{ $vendor->payment_terms }}
                                    @endif
                                    @if ($vendor?->credit_approved)
                                        • Credit approved
                                    @endif
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">Latest bill: {{ $section['latest_invoice']->invoice_number }} on {{ $section['latest_invoice']->created_at->format('d M Y') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($vendor)
                                    <a href="{{ route('purchasing.invoices.vendor-report', $vendor, false) }}?date={{ $date }}&search={{ urlencode($search) }}&payment_type={{ $paymentFilter }}" class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-100">
                                        Open Vendor Report
                                    </a>
                                    @if (! $vendor->credit_approved && $vendor->credit_approval_requested_at)
                                        <form action="{{ route('purchasing.suppliers.credit-approve', $vendor) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black text-emerald-700 transition hover:bg-emerald-100">
                                                Approve Credit
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                <a href="{{ route('purchasing.invoices.show', $section['latest_invoice']) }}" class="inline-flex h-10 items-center justify-center rounded-2xl bg-slate-950 px-4 text-xs font-black text-white transition hover:bg-slate-800">
                                    Latest Bill
                                </a>
                            </div>
                        </div>

                        <div class="grid gap-3 px-5 py-5 sm:grid-cols-2 xl:grid-cols-4 lg:px-6">
                            <div class="rounded-[1.5rem] bg-slate-50 px-4 py-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Count</p>
                                <p class="mt-2 text-lg font-black text-slate-950">{{ number_format($section['invoice_count']) }}</p>
                            </div>
                            <div class="rounded-[1.5rem] bg-slate-50 px-4 py-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Amount</p>
                                <p class="mt-2 text-lg font-black text-slate-950">₹{{ number_format($section['total_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-[1.5rem] bg-emerald-50 px-4 py-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Paid</p>
                                <p class="mt-2 text-lg font-black text-emerald-800">₹{{ number_format($section['paid_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-[1.5rem] bg-amber-50 px-4 py-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Outstanding</p>
                                <p class="mt-2 text-lg font-black text-amber-900">₹{{ number_format($section['outstanding_amount'], 2) }}</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto border-t border-slate-200">
                            <table class="min-w-full text-left">
                                <thead class="bg-slate-950 text-[11px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-5 py-4 lg:px-6">Invoice</th>
                                        <th class="px-5 py-4">Bill Date</th>
                                        <th class="px-5 py-4">Payment Type</th>
                                        <th class="px-5 py-4 text-right">Amount</th>
                                        <th class="px-5 py-4 text-right">Paid</th>
                                        <th class="px-5 py-4 text-right">Balance</th>
                                        <th class="px-5 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white text-sm">
                                    @foreach ($section['invoices'] as $invoice)
                                        @php
                                            $balance = max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
                                            $methodTone = match ($invoice->payment_method) {
                                                'Cash' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'Credit' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                'GPay' => 'bg-sky-50 text-sky-700 border-sky-200',
                                                'Online' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                                default => 'bg-slate-50 text-slate-600 border-slate-200',
                                            };
                                        @endphp
                                        <tr>
                                            <td class="px-5 py-4 lg:px-6">
                                                <p class="font-mono font-black text-teal-700">{{ $invoice->invoice_number }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->purchaserCart?->cart_number ?: $invoice->goodsReceived?->grn_number ?: 'Manual bill' }}</p>
                                            </td>
                                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $invoice->created_at->format('d M Y') }}</td>
                                            <td class="px-5 py-4">
                                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $methodTone }}">
                                                    {{ $invoice->payment_method ?: 'Pending' }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-right font-black text-slate-950">₹{{ number_format((float) $invoice->amount, 2) }}</td>
                                            <td class="px-5 py-4 text-right font-black text-emerald-700">₹{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                            <td class="px-5 py-4 text-right font-black {{ $balance > 0 ? 'text-amber-700' : 'text-slate-950' }}">₹{{ number_format($balance, 2) }}</td>
                                            <td class="px-5 py-4 text-right">
                                                <a href="{{ route('purchasing.invoices.show', $invoice) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-[11px] font-black text-slate-700 transition hover:bg-slate-50">
                                                    View Bill
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
