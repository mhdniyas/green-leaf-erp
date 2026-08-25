@extends('admin.cashbook.layouts.app')

@section('title', 'Credit Purchase Report - Cashbook')

@section('header_title')
    <i data-lucide="truck" class="h-5 w-5 text-emerald-600"></i> Credit Purchase Report
@endsection

@section('header_subtitle')
    Credit purchases, vendor position, paid and outstanding.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance.vendor-credit.settlements') }}" aria-label="Vendor Settlement History" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-sm hover:bg-slate-50">
            <i data-lucide="history" class="h-4 w-4 text-emerald-600"></i>
            <span>Settlement History</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.journal', ['tab' => 'vendor_payment']) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-sm hover:bg-slate-50">
            <i data-lucide="book-open-check" class="h-4 w-4 text-emerald-600"></i>
            <span class="hidden sm:inline">Payment Journal</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
            <i data-lucide="git-compare-arrows" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Reconciliation</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.cashbook.finance.purchase.reports._header', ['reportName' => 'Credit Purchase Report', 'reportDescription' => 'Credit purchases, vendor position, paid and outstanding.'])

        @php($selectedProductFilter = $filters['product_filter'] ?? null)
        @php($usesCustomDates = in_array($filters['period'], ['custom', 'between', 'range'], true))
        <form method="GET" action="{{ route('admin.cashbook.finance.purchase.reports.credit-purchases') }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <input type="hidden" name="period" value="{{ $filters['period'] }}">

            <div class="flex flex-col gap-3 border-b border-slate-100 pb-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="block text-[10px] font-black uppercase text-slate-500">Period</span>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach(['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $value => $label)
                            <button type="submit" name="period" value="{{ $value }}" class="min-h-9 rounded-lg border px-3 text-xs font-black {{ ($filters['period'] === $value || ($value === 'custom' && $usesCustomDates)) ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <label class="w-full text-[10px] font-black uppercase text-slate-500 sm:w-48">Product Filter
                    <select name="product_filter" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="">All Products</option>
                        @foreach($productFilters as $filter)
                            <option value="{{ $filter->uuid }}" @selected($selectedProductFilter === $filter->uuid)>{{ $filter->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="mt-3 grid gap-2 sm:grid-cols-2 {{ $usesCustomDates ? 'xl:grid-cols-[10rem_10rem_10rem_minmax(14rem,1fr)_auto_auto]' : 'lg:grid-cols-[12rem_minmax(16rem,1fr)_auto_auto]' }} lg:items-end">
                <label class="text-[10px] font-black uppercase text-slate-500">Status
                    <select name="status" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach(['all' => 'All Vendors', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially Paid', 'paid' => 'Fully Settled'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                @if($usesCustomDates)
                    <label class="text-[10px] font-black uppercase text-slate-500">From
                        <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    </label>
                    <label class="text-[10px] font-black uppercase text-slate-500">To
                        <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    </label>
                @endif

                <label class="min-w-0 text-[10px] font-black uppercase text-slate-500">Search
                    <span class="mt-1 flex">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Vendor name or phone..." class="min-h-10 min-w-0 flex-1 rounded-l-lg border border-r-0 border-slate-300 bg-white px-3 text-xs font-semibold normal-case">
                        <button type="submit" class="inline-flex min-h-10 w-10 shrink-0 items-center justify-center rounded-r-lg border border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800" aria-label="Search" title="Search">
                            <i data-lucide="search" class="h-4 w-4"></i>
                        </button>
                    </span>
                </label>

                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800">
                    <i data-lucide="filter" class="h-4 w-4"></i> Apply
                </button>
                <a href="{{ route('admin.cashbook.finance.purchase.reports.credit-purchases') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-slate-600 hover:bg-slate-50" aria-label="Reset filters" title="Reset filters">
                    <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                </a>
            </div>
        </form>

        <!-- KPI METRICS -->
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Credit Purchases</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($kpi['total_invoiced'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-slate-500">{{ number_format($kpi['invoice_count']) }} bills total</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Settled / Paid</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($kpi['total_paid'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-emerald-600">Company payments cleared</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Outstanding Payables</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-rose-700">₹{{ number_format($kpi['total_outstanding'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-rose-600">Pending settlement</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Active Vendors</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-900">{{ number_format($kpi['vendor_count']) }}</div>
                <span class="mt-1 block text-xs font-bold text-slate-500">Suppliers with credit ledger</span>
            </div>
        </section>

        <!-- VENDOR CREDIT SUMMARY TABLE -->
        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Supplier Credit Payables</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Credit purchases grouped by supplier with payment progress and outstanding liabilities.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $vendors->total() }} suppliers</span>
            </div>

            <!-- MOBILE CARDS -->
            <div class="space-y-3 lg:hidden">
                @forelse($vendors as $vendor)
                    <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $vendor['supplier_public_uuid']) }}" class="block rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:bg-slate-100">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="break-words text-sm font-black text-slate-950">{{ $vendor['supplier_name'] }}</div>
                                <div class="mt-0.5 text-xs font-semibold text-slate-500">{{ $vendor['invoice_count'] }} bills / {{ $vendor['supplier_contact'] }}</div>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase {{ $vendor['total_outstanding'] > 0 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $vendor['total_outstanding'] > 0 ? 'Attention Needed' : 'Settled' }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-xl bg-white p-2 border border-slate-200">
                                <span class="block text-[9px] font-black uppercase text-slate-400">Total</span>
                                <strong class="font-mono text-slate-900">₹{{ number_format($vendor['total_net'], 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white p-2 border border-slate-200">
                                <span class="block text-[9px] font-black uppercase text-slate-400">Paid</span>
                                <strong class="font-mono text-emerald-700">₹{{ number_format($vendor['total_paid'], 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white p-2 border border-slate-200">
                                <span class="block text-[9px] font-black uppercase text-slate-400">Due</span>
                                <strong class="font-mono text-rose-700">₹{{ number_format($vendor['total_outstanding'], 2) }}</strong>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-2 text-[11px] font-bold text-slate-500">
                            <span>Oldest: {{ $vendor['oldest_date']?->format('d M Y') ?? '—' }}</span>
                            <span class="text-emerald-700 hover:underline">View Splits →</span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">No vendor credit records found for the selected criteria.</div>
                @endforelse
            </div>

            <!-- DESKTOP TABLE -->
            <div class="hidden overflow-x-auto custom-scrollbar lg:block">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Vendor / Supplier</th>
                            <th class="px-3 py-3 text-center">Bills</th>
                            <th class="px-3 py-3 text-right">Total Purchases</th>
                            <th class="px-3 py-3 text-right">Paid Amount</th>
                            <th class="px-3 py-3 text-right">Outstanding</th>
                            <th class="px-3 py-3 text-center">Oldest Bill</th>
                            <th class="px-3 py-3 text-center">Last Bill</th>
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($vendors as $vendor)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3.5">
                                    <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $vendor['supplier_public_uuid']) }}" class="inline-flex items-center gap-1 text-sm font-extrabold text-emerald-700 hover:underline" aria-label="View vendor credit for {{ $vendor['supplier_name'] }}">
                                        {{ $vendor['supplier_name'] }}
                                        <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                                    </a>
                                    <div class="text-[11px] font-semibold text-slate-400">{{ $vendor['supplier_contact'] }}</div>
                                </td>
                                <td class="px-3 py-3.5 text-center font-mono font-bold text-slate-700">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $vendor['invoice_count'] }}</span>
                                </td>
                                <td class="px-3 py-3.5 text-right font-mono font-bold text-slate-900">₹{{ number_format($vendor['total_net'], 2) }}</td>
                                <td class="px-3 py-3.5 text-right font-mono font-bold text-emerald-700">₹{{ number_format($vendor['total_paid'], 2) }}</td>
                                <td class="px-3 py-3.5 text-right font-mono font-extrabold {{ $vendor['total_outstanding'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                    ₹{{ number_format($vendor['total_outstanding'], 2) }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-mono text-[11px] font-semibold text-slate-600">
                                    {{ $vendor['oldest_date']?->format('Y-m-d') ?? '—' }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-mono text-[11px] font-semibold text-slate-600">
                                    {{ $vendor['last_date']?->format('Y-m-d') ?? '—' }}
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    @if($vendor['total_outstanding'] > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-black text-rose-700">
                                            Attention Needed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-black text-emerald-700">
                                            Settled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $vendor['supplier_public_uuid']) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800">
                                        <span>View Details</span>
                                        <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-sm font-bold text-slate-400">No vendor credit records found for the selected criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $vendors->links() }}
            </div>
        </section>
    </div>
@endsection
