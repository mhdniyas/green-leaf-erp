<x-layouts.accounting title="Daily Sale Report">
    @php
        $previousDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = today()->toDateString();
        $summary = $report['summary'];
        $shopRows = $report['shop_rows'];
        $invoices = $report['invoices'];
        $ownedShopOptions = $ownedShops ?? collect();
        $activeTab = request('tab', 'shops');
        $activeTab = in_array($activeTab, ['shops', 'invoices'], true) ? $activeTab : 'shops';
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[linear-gradient(135deg,_#052e16,_#14532d_45%,_#166534)] text-white shadow-[0_30px_90px_rgba(21,128,61,0.16)]">
            <div class="flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-end lg:justify-between lg:px-7">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-200">Accounting / Daily Sales</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Daily sales report for {{ $date->format('d M Y') }}</h2>
                    <p class="mt-3 max-w-2xl text-sm font-semibold leading-6 text-emerald-50/90">Dedicated accounting report with one clean daily sales page, shop totals, invoice table, and status filtering.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.daily-sales') }}" class="flex flex-wrap items-end gap-2 rounded-[1.5rem] border border-white/15 bg-white/10 p-3 backdrop-blur">
                    <a href="{{ route('admin.accounting.daily-sales', ['date' => $previousDate, 'status' => $statusFilter, 'tab' => $activeTab, 'owned_shop_id' => $selectedOwnedShopId, 'only_owned_shops' => ($onlyOwnedShops ?? false) ? 1 : null]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white transition hover:bg-white/20" title="Previous day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <label class="min-w-[11rem]">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-emerald-100">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="mt-2 h-11 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                    </label>
                    <label class="min-w-[10rem]">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-emerald-100">Status</span>
                        <select name="status" class="mt-2 h-11 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                            <option value="all" @selected($statusFilter === 'all')>All Invoices</option>
                            <option value="pending" @selected($statusFilter === 'pending')>Pending Only</option>
                            <option value="settled" @selected($statusFilter === 'settled')>Settled Only</option>
                        </select>
                    </label>
                    <label class="min-w-[12rem]">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-emerald-100">Owned Shop</span>
                        <select name="owned_shop_id" class="mt-2 h-11 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                            <option value="">All Shops</option>
                            @foreach ($ownedShopOptions as $shop)
                                <option value="{{ $shop->id }}" @selected((int) ($selectedOwnedShopId ?? 0) === (int) $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex h-11 items-center gap-3 rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950">
                        <input type="checkbox" name="only_owned_shops" value="1" @checked($onlyOwnedShops ?? false) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Only owned shops</span>
                    </label>
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-white px-4 text-xs font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-emerald-50">
                        Apply
                    </button>
                    @if ($date->format('Y-m-d') !== $todayDate || $statusFilter !== 'all' || ($onlyOwnedShops ?? false) || $selectedOwnedShopId)
                        <a href="{{ route('admin.accounting.daily-sales', ['date' => $todayDate, 'status' => 'all', 'tab' => $activeTab]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 px-4 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-white/20">
                            Reset
                        </a>
                    @endif
                    <a href="{{ route('admin.accounting.daily-sales', ['date' => $nextDate, 'status' => $statusFilter, 'tab' => $activeTab, 'owned_shop_id' => $selectedOwnedShopId, 'only_owned_shops' => ($onlyOwnedShops ?? false) ? 1 : null]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white transition hover:bg-white/20" title="Next day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </form>
            </div>

            <div class="grid gap-4 border-t border-white/10 px-5 py-5 md:grid-cols-2 xl:grid-cols-4 lg:px-7">
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-100/80">Shops</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">{{ number_format($summary['count']) }}</p>
                    <p class="mt-2 text-sm font-semibold text-emerald-50/90">{{ number_format($summary['invoice_count']) }} invoice(s)</p>
                </article>
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-100/80">Credit</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">Rs. {{ number_format($summary['total_amount'], 2) }}</p>
                    <p class="mt-2 text-sm font-semibold text-emerald-50/90">Total shop sales for the day</p>
                </article>
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-100/80">Debit</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">Rs. {{ number_format($summary['paid_amount'], 2) }}</p>
                    <p class="mt-2 text-sm font-semibold text-emerald-50/90">Collections recorded</p>
                </article>
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-100/80">Balance</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">Rs. {{ number_format($summary['outstanding_amount'], 2) }}</p>
                    <p class="mt-2 text-sm font-semibold text-emerald-50/90">{{ ucfirst($statusFilter) }} view</p>
                </article>
            </div>
        </section>

        <section
            class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm"
            data-daily-sales-export
            data-export-table-id="{{ $activeTab === 'shops' ? 'daily-sales-shops-table' : 'daily-sales-invoices-table' }}"
            data-export-title="{{ $activeTab === 'shops' ? 'Daily Sales Report - Sales by Shop' : 'Daily Sales Report - Invoices' }}"
            data-export-filename="{{ $activeTab === 'shops' ? 'daily-sales-by-shop' : 'daily-sales-invoices' }}"
        >
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Report Tabs</p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Full-page daily sales tables</h3>
                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">Switch between shop summary and invoice detail without splitting the page width.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.daily-sales', ['date' => $date->format('Y-m-d'), 'status' => $statusFilter, 'tab' => 'shops', 'owned_shop_id' => $selectedOwnedShopId, 'only_owned_shops' => ($onlyOwnedShops ?? false) ? 1 : null]) }}" class="inline-flex h-11 items-center rounded-2xl px-4 text-xs font-black uppercase tracking-[0.16em] transition {{ $activeTab === 'shops' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                        Sales by Shop
                    </a>
                    <a href="{{ route('admin.accounting.daily-sales', ['date' => $date->format('Y-m-d'), 'status' => $statusFilter, 'tab' => 'invoices', 'owned_shop_id' => $selectedOwnedShopId, 'only_owned_shops' => ($onlyOwnedShops ?? false) ? 1 : null]) }}" class="inline-flex h-11 items-center rounded-2xl px-4 text-xs font-black uppercase tracking-[0.16em] transition {{ $activeTab === 'invoices' ? 'bg-emerald-600 text-white' : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                        Invoices
                    </a>
                    <button type="button" data-export="excel" class="inline-flex h-11 items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                        Export Excel
                    </button>
                    <button type="button" data-export="pdf" class="inline-flex h-11 items-center rounded-2xl border border-cyan-200 bg-cyan-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-cyan-700 transition hover:bg-cyan-100">
                        Export PDF
                    </button>
                </div>
            </div>

            @if ($activeTab === 'shops')
                <div class="mt-5">
                    <div class="mb-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Shop Sales Table</p>
                        <h4 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Sales by shop</h4>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">Grouped daily shop totals with credit, collection, and pending balance.</p>
                    </div>

                    <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                        <table id="daily-sales-shops-table" class="min-w-full table-auto text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Shop</th>
                                    <th class="px-4 py-3 text-right">Sales</th>
                                    <th class="px-4 py-3 text-right">Collected</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3 text-right">Invoices</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse ($shopRows as $row)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-black text-slate-950">{{ $row['shop']?->name ?? 'Shop pending' }}</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['status'] }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format($row['paid_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ $row['outstanding_amount'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['outstanding_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-600">{{ number_format($row['invoice_count']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No shop sales rows for the selected filter.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="mt-5">
                    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily Invoices</p>
                            <h4 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Invoice list</h4>
                            <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">Exact invoice rows for the day with status and open action.</p>
                        </div>
                        <a href="{{ route('purchasing.shop-invoices.index') }}" class="inline-flex h-10 items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                            Shop Invoices
                        </a>
                    </div>

                    <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                        <table id="daily-sales-invoices-table" class="min-w-full table-auto text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Invoice</th>
                                    <th class="px-4 py-3">Shop</th>
                                    <th class="px-4 py-3 text-right">Sales</th>
                                    <th class="px-4 py-3 text-right">Paid</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Open</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse ($invoices as $invoice)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ \Illuminate\Support\Carbon::parse((string) $invoice->business_date)->format('d M Y') }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-black text-slate-950">{{ $invoice->shop?->name ?? 'Shop pending' }}</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->shop?->code ?? 'No code' }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ (float) $invoice->balance_amount > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ (float) $invoice->balance_amount > 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                                {{ (float) $invoice->balance_amount > 0 ? 'Pending' : 'Settled' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                                                Open
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No invoice rows for the selected filter.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <script src="{{ asset('js/accounting-daily-sales-export.js') }}" defer></script>
</x-layouts.accounting>
