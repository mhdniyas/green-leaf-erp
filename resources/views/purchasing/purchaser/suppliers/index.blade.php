<x-layouts.app title="Purchaser Vendor Hub">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        {{-- ═══════════════════════════════════════════════════════════
             ROW 1 · Page Header Card  (matches history.blade.php)
        ════════════════════════════════════════════════════════════════ --}}
        <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs lg:rounded-2xl">
            <!-- Title & Description -->
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">Stage 5</span>
                    <h1 class="text-base font-black text-slate-950">Vendor Hub</h1>
                </div>
                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">Track payment totals, banking notes, and open complete vendor history.</p>
            </div>

            <!-- Controls Row: Report link, Date Picker, Tab shortcuts -->
            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                <a href="{{ route('purchaser.history', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 hover:bg-white shrink-0">
                    <span>Purchase Report</span>
                </a>
                <a href="{{ route('purchaser.vendors', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 hover:bg-white shrink-0">
                    <span>Open Daily Carts</span>
                </a>
                <form action="{{ route('purchaser.suppliers') }}" method="GET" class="shrink-0">
                    <input type="hidden" name="tab" value="{{ $selectedTab }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                </form>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════
             ROW 2 · Dark Vendor Totals Summary Bar
        ════════════════════════════════════════════════════════════════ --}}
        <section class="rounded-xl border border-slate-900 bg-slate-950 px-3 py-2.5 text-white shadow-xs">
            <div class="flex items-center justify-between gap-2 border-b border-slate-800 pb-1.5 text-[10px]">
                <div class="flex items-center gap-1.5 truncate">
                    <span class="rounded bg-teal-500/20 px-1.5 py-0.5 font-black uppercase text-teal-300">Vendors</span>
                    <span class="truncate font-bold text-slate-300"><span id="top-bar-total-vendors">{{ $totalVendorsCount ?? $supplierRows->count() }}</span> {{ Str::plural('vendor', $totalVendorsCount ?? $supplierRows->count()) }}</span>
                </div>
                <span class="shrink-0 font-bold text-slate-400">{{ $selectedTab === 'all' || !$selectedTab ? 'All' : ucfirst($selectedTab) }} Tab</span>
            </div>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                <div class="rounded-lg bg-slate-900 px-1 py-1.5">
                    <p class="text-[9px] font-black uppercase tracking-tight text-teal-400 truncate">Total Buy</p>
                    <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-white truncate" id="top-bar-total-buy">₹{{ number_format($globalTotals['total_amount'] ?? $supplierRows->sum('total_amount'), 2) }}</p>
                </div>
                <div class="rounded-lg bg-slate-900 px-1 py-1.5">
                    <p class="text-[9px] font-black uppercase tracking-tight text-emerald-400 truncate">Paid</p>
                    <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-emerald-300 truncate" id="top-bar-paid">₹{{ number_format($globalTotals['paid_amount'] ?? $supplierRows->sum('paid_amount'), 2) }}</p>
                </div>
                <div class="rounded-lg bg-slate-900 px-1 py-1.5">
                    <p class="text-[9px] font-black uppercase tracking-tight text-amber-400 truncate">Balance Due</p>
                    <p class="mt-0.5 font-mono text-xs sm:text-sm font-black text-amber-300 truncate" id="top-bar-balance">₹{{ number_format($globalTotals['balance_amount'] ?? $supplierRows->sum('balance_amount'), 2) }}</p>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════
             ROW 3 · Unified Tab Switcher + Search + View Mode
        ════════════════════════════════════════════════════════════════ --}}
        <section class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <!-- Left: Tab Switcher Buttons -->
                <div class="inline-flex shrink-0 rounded-lg bg-slate-100 p-0.5 text-xs font-bold text-slate-700 flex-wrap gap-0.5">
                    <a href="{{ route('purchaser.suppliers', ['date' => $date, 'tab' => 'pending', 'search' => $search]) }}"
                       class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-all {{ $selectedTab === 'pending' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-800' }}">
                        <span>PENDING</span>
                        <span id="tab-count-pending" class="rounded-full bg-slate-200/80 px-1.5 py-0.2 text-[9px] font-black text-slate-700">{{ $tabCounts['pending'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('purchaser.suppliers', ['date' => $date, 'tab' => 'credit', 'search' => $search]) }}"
                       class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-all {{ $selectedTab === 'credit' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-800' }}">
                        <span>CREDIT</span>
                        <span id="tab-count-credit" class="rounded-full bg-slate-200/80 px-1.5 py-0.2 text-[9px] font-black text-slate-700">{{ $tabCounts['credit'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('purchaser.suppliers', ['date' => $date, 'tab' => 'all', 'search' => $search]) }}"
                       class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-all {{ $selectedTab === 'all' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-800' }}">
                        <span>ALL VENDORS</span>
                        <span id="tab-count-all" class="rounded-full bg-slate-200/80 px-1.5 py-0.2 text-[9px] font-black text-slate-700">{{ $tabCounts['all'] ?? 0 }}</span>
                    </a>
                </div>

                <!-- Middle: Instant Search -->
                <div class="flex-1 max-w-sm min-w-0">
                    <div class="relative">
                        <input type="search" id="vendor-search-input" placeholder="Search vendor, mobile, location..." oninput="handleVendorSearch(this.value)" value="{{ $search }}" class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-8 text-xs font-semibold text-slate-900 focus:bg-white focus:border-teal-500 focus:outline-none">
                        <svg class="absolute left-2.5 top-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <span id="vendor-search-spinner" class="absolute right-2.5 top-2 hidden">
                            <svg class="h-4 w-4 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </span>
                    </div>
                </div>

                <!-- Right: Tab Total + View Switcher -->
                <div class="flex items-center justify-between md:justify-end gap-3 text-xs shrink-0">
                    <div class="text-right">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Total Balance</span>
                        <span class="font-mono font-black text-amber-700 text-xs sm:text-sm" id="tab-total-balance">₹{{ number_format($globalTotals['balance_amount'] ?? $supplierRows->sum('balance_amount'), 2) }}</span>
                    </div>
                    <!-- Desktop View Switcher -->
                    <div class="hidden md:flex items-center rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                        <button type="button" id="vendor-view-mode-table-btn" onclick="setVendorViewMode('table')" class="rounded-md bg-slate-950 px-2.5 py-1 text-[10px] font-black text-white shadow-2xs">Table</button>
                        <button type="button" id="vendor-view-mode-cards-btn" onclick="setVendorViewMode('cards')" class="rounded-md px-2.5 py-1 text-[10px] font-black text-slate-500 hover:text-slate-800">Cards</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════
             ROW 4 · Vendor Table (desktop) + Mobile Cards
        ════════════════════════════════════════════════════════════════ --}}

        <!-- Desktop Table View -->
        <div id="vendor-table-container" class="hidden md:block overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm lg:rounded-[2rem]">
            <table class="w-full text-left text-xs min-w-[900px]">
                <thead class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="py-3 px-3.5">Vendor</th>
                        <th scope="col" class="py-3 px-3">Contact & Location</th>
                        <th scope="col" class="py-3 px-3">Recent Date</th>
                        <th scope="col" class="py-3 px-3 text-right">Total Buy</th>
                        <th scope="col" class="py-3 px-3 text-right">Paid</th>
                        <th scope="col" class="py-3 px-3 text-right">Discount</th>
                        <th scope="col" class="py-3 px-3 text-right">Balance</th>
                        <th scope="col" class="py-3 px-3.5 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="vendor-table-body" class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($supplierRows as $row)
                        @php($supplier = $row['supplier'])
                        <tr class="transition-colors hover:bg-slate-50/80" data-supplier-id="{{ $supplier->id }}" data-total="{{ $row['total_amount'] }}" data-paid="{{ $row['paid_amount'] }}" data-discount="{{ $row['discount_amount'] }}" data-balance="{{ $row['balance_amount'] }}">
                            <!-- Vendor Name & Badges -->
                            <td class="py-3 px-3.5 align-top">
                                <p class="font-bold text-slate-900">{{ $supplier->name }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] {{ $supplier->credit_approved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $supplier->credit_approved ? 'Credit' : 'Cash' }}
                                    </span>
                                    @if ($row['pending_count'] > 0)
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] {{ $row['pending_issue_tone'] }}">
                                            {{ $row['pending_issue_label'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <!-- Contact & Location -->
                            <td class="py-3 px-3 align-top">
                                <p class="text-[10px] font-medium text-slate-500">{{ $supplier->mobile_number ?: 'Mobile pending' }}</p>
                                @if ($supplier->location)
                                    <p class="text-[10px] font-medium text-slate-500">{{ $supplier->location }}</p>
                                @endif
                                @if (filled($supplier->bank_details))
                                    <span class="mt-1 inline-flex items-center gap-1 rounded bg-teal-50 px-1.5 py-0.5 text-[9px] font-bold text-teal-700" title="{{ $supplier->bank_details }}">🏦 Bank</span>
                                @endif
                            </td>
                            <!-- Recent Date -->
                            <td class="py-3 px-3 align-top font-mono text-[10px] font-bold text-slate-700">
                                {{ $row['recent_business_date'] }}
                            </td>
                            <!-- Total -->
                            <td class="py-3 px-3 text-right align-top font-mono font-black text-slate-950 whitespace-nowrap text-xs">
                                ₹{{ number_format($row['total_amount'], 2) }}
                            </td>
                            <!-- Paid -->
                            <td class="py-3 px-3 text-right align-top font-mono font-black text-emerald-700 whitespace-nowrap text-xs">
                                ₹{{ number_format($row['paid_amount'], 2) }}
                            </td>
                            <!-- Discount -->
                            <td class="py-3 px-3 text-right align-top font-mono font-bold text-slate-600 whitespace-nowrap text-xs">
                                ₹{{ number_format($row['discount_amount'], 2) }}
                            </td>
                            <!-- Balance -->
                            <td class="py-3 px-3 text-right align-top font-mono font-black {{ $row['balance_amount'] > 0 ? 'text-amber-700' : 'text-slate-900' }} whitespace-nowrap text-xs">
                                ₹{{ number_format($row['balance_amount'], 2) }}
                            </td>
                            <!-- Action -->
                            <td class="py-3 px-3.5 text-center align-top whitespace-nowrap">
                                <a href="{{ $row['history_route'] }}" class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-slate-950 px-2.5 text-[10px] font-black text-white shadow-2xs transition-all hover:bg-slate-800 active:scale-95">
                                    <span>View History</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr id="vendor-table-empty-row">
                            <td colspan="8" class="py-8 text-center text-xs font-bold text-slate-500">
                                @if (auth()->user()?->showsRelatedVendorsOnly())
                                    No suppliers are assigned to this purchaser.
                                @else
                                    No vendors found for this tab yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot id="vendor-table-footer" class="border-t-2 border-slate-900 bg-slate-950 text-white font-mono text-xs {{ $supplierRows->isEmpty() ? 'hidden' : '' }}">
                    <tr>
                        <td colspan="3" class="py-3 px-3.5 font-sans font-black uppercase text-[10px] tracking-wider text-slate-300">
                            Table Total (<span id="table-footer-count">{{ $supplierRows->count() }}</span> of <span id="table-footer-total">{{ $totalRowsCount }}</span> {{ \Illuminate\Support\Str::plural('vendor', $totalRowsCount) }})
                        </td>
                        <td id="table-footer-total-buy" class="py-3 px-3 text-right font-black text-white whitespace-nowrap">₹{{ number_format($supplierRows->sum('total_amount'), 2) }}</td>
                        <td id="table-footer-paid" class="py-3 px-3 text-right font-black text-emerald-400 whitespace-nowrap">₹{{ number_format($supplierRows->sum('paid_amount'), 2) }}</td>
                        <td id="table-footer-discount" class="py-3 px-3 text-right font-bold text-slate-300 whitespace-nowrap">₹{{ number_format($supplierRows->sum('discount_amount'), 2) }}</td>
                        <td id="table-footer-balance" class="py-3 px-3 text-right font-black text-amber-400 whitespace-nowrap">₹{{ number_format($supplierRows->sum('balance_amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Mobile Compact Cards View (Default on mobile) -->
        <div id="vendor-cards-container" class="space-y-2 md:hidden">
            @forelse ($supplierRows as $row)
                @php($supplier = $row['supplier'])
                <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs space-y-2" data-supplier-id="{{ $supplier->id }}" data-total="{{ $row['total_amount'] }}" data-paid="{{ $row['paid_amount'] }}" data-discount="{{ $row['discount_amount'] }}" data-balance="{{ $row['balance_amount'] }}">
                    <!-- Row 1: Vendor Name & Badges -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-xs font-black text-slate-950 truncate">{{ $supplier->name }}</h3>
                            <p class="mt-0.5 text-[10px] font-medium text-slate-500">
                                {{ $supplier->mobile_number ?: 'Mobile pending' }}{{ $supplier->location ? ' • ' . $supplier->location : '' }}
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] {{ $supplier->credit_approved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $supplier->credit_approved ? 'Credit' : 'Cash' }}
                            </span>
                            @if ($row['pending_count'] > 0)
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] {{ $row['pending_issue_tone'] }}">
                                    {{ $row['pending_issue_label'] }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Row 2: Amounts + Action -->
                    <div class="flex items-center justify-between gap-2 border-t border-slate-100/80 pt-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-baseline gap-1.5 flex-wrap">
                                <span class="font-mono text-xs font-black text-slate-950">₹{{ number_format($row['total_amount'], 2) }}</span>
                                @if ($row['balance_amount'] > 0)
                                    <span class="text-[10px] font-bold text-amber-700">Due ₹{{ number_format($row['balance_amount'], 2) }}</span>
                                @else
                                    <span class="text-[10px] font-bold text-emerald-700">Cleared</span>
                                @endif
                            </div>
                            <p class="text-[10px] font-medium text-slate-500 mt-0.5">{{ $row['recent_business_date'] }}</p>
                        </div>

                        <a href="{{ $row['history_route'] }}" class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-slate-950 px-2.5 text-[10px] font-black text-white shadow-2xs transition-all hover:bg-slate-800 active:scale-95 shrink-0">
                            History
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </article>
            @empty
                <div id="vendor-cards-empty-card" class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-xs font-bold text-slate-500">
                    @if (auth()->user()?->showsRelatedVendorsOnly())
                        No suppliers are assigned to this purchaser.
                    @else
                        No vendors found for this tab yet.
                    @endif
                </div>
            @endforelse
        </div>

        <!-- Load More Section -->
        <div id="vendor-load-more-container" class="mt-2 flex flex-col items-center justify-center gap-2 {{ $hasMorePages ? '' : 'hidden' }}">
            <button type="button"
                    id="vendor-load-more-btn"
                    onclick="loadMoreVendors()"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 active:scale-98 transition-all">
                <span id="load-more-spinner" class="hidden">
                    <svg class="h-4 w-4 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </span>
                <span id="load-more-text">Load More Vendors (<span id="load-more-count">{{ $supplierRows->count() }}</span> of <span id="load-more-total">{{ $totalRowsCount }}</span>)</span>
            </button>
            <p id="load-more-error" class="hidden text-xs font-semibold text-rose-600"></p>
        </div>


    {{-- ═══════════════════════════════════════════════════════════
         Payment Update Modal (Same as suppliers page had)
    ════════════════════════════════════════════════════════════════ --}}
    <div id="direct-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeDirectPaymentModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Credit Settlement</h3>
                    <p id="direct-payment-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeDirectPaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="direct-payment-form" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_to" value="suppliers">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="payment_paid_by" value="purchaser">

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Total Bill</span>
                        <span id="direct-payment-total" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Discount</span>
                        <span id="direct-payment-discount-val" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Net Payable</span>
                        <span id="direct-payment-net" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Already Paid</span>
                        <span id="direct-payment-paid-val" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Remaining</span>
                        <span id="direct-payment-balance" class="text-amber-700">₹ 0.00</span>
                    </div>
                    <p id="direct-payment-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
                </div>

                <div>
                    <label for="direct_payment_discount_amount" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount</label>
                    <input id="direct_payment_discount_amount" type="number" step="0.01" min="0" name="discount_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="direct_payment_method" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                    <select id="direct_payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="GPay">GPay</option>
                        <option value="Credit">Credit</option>
                    </select>
                </div>

                <div>
                    <label for="direct_additional_paid_amount" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Add Payment Now</label>
                    <input id="direct_additional_paid_amount" type="number" step="0.01" min="0" name="additional_paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    <p class="mt-1 text-[10px] font-semibold text-slate-500">Enter only the extra amount received now.</p>
                </div>

                <div>
                    <label for="direct_payment_note" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Notes</label>
                    <input id="direct_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500">Save Payment Update</button>
            </form>
        </div>
    </div>

    <script>
        let directPaymentCurrentPaidAmount = 0;

        function openDirectPaymentModal(btn) {
            const amount = parseFloat(btn.dataset.amount || '0');
            const initialDiscount = parseFloat(btn.dataset.discountAmount || '0');
            const initialPaid = parseFloat(btn.dataset.paidAmount || '0');
            const paymentMethod = btn.dataset.paymentMethod;
            const paymentNote = btn.dataset.paymentNote || '';
            const paymentDetails = btn.dataset.paymentDetails || '';

            document.getElementById('direct-payment-title').textContent = `${btn.dataset.invoiceNumber} • ${btn.dataset.supplierName}`;
            document.getElementById('direct-payment-form').action = btn.dataset.paymentRoute;
            document.getElementById('direct-payment-total').textContent = `₹ ${amount.toFixed(2)}`;
            directPaymentCurrentPaidAmount = initialPaid;

            const discountInput = document.getElementById('direct_payment_discount_amount');
            const methodInput = document.getElementById('direct_payment_method');
            const paidInput = document.getElementById('direct_additional_paid_amount');
            const noteInput = document.getElementById('direct_payment_note');
            const discountLabel = document.getElementById('direct-payment-discount-val');
            const netLabel = document.getElementById('direct-payment-net');
            const paidLabel = document.getElementById('direct-payment-paid-val');
            const balanceLabel = document.getElementById('direct-payment-balance');
            const warningLabel = document.getElementById('direct-payment-warning');

            const refreshSummary = () => {
                const discount = parseFloat(discountInput.value || '0');
                const additionalPaid = Math.max(0, parseFloat(paidInput.value || '0'));
                const paid = directPaymentCurrentPaidAmount + additionalPaid;
                const net = Math.max(0, amount - discount);
                const remaining = Math.max(0, net - paid);

                discountLabel.textContent = `₹ ${discount.toFixed(2)}`;
                netLabel.textContent = `₹ ${net.toFixed(2)}`;
                paidLabel.textContent = `₹ ${directPaymentCurrentPaidAmount.toFixed(2)}`;
                balanceLabel.textContent = `₹ ${remaining.toFixed(2)}`;

                warningLabel.textContent = remaining > 0 || methodInput.value === 'Credit'
                    ? 'This supplier credit remains pending until the balance is cleared.'
                    : 'This purchaser payment will settle the invoice and reduce purchaser balance.';
            };

            discountInput.value = initialDiscount.toFixed(2);
            methodInput.value = paymentMethod;
            paidInput.value = '';
            noteInput.value = paymentNote;

            [discountInput, methodInput, paidInput].forEach((node) => {
                node.oninput = refreshSummary;
                node.onchange = refreshSummary;
            });

            document.getElementById('direct-payment-modal').classList.remove('hidden');
            document.getElementById('direct-payment-modal').classList.add('flex');
            refreshSummary();
        }

        function closeDirectPaymentModal() {
            document.getElementById('direct-payment-modal').classList.add('hidden');
            document.getElementById('direct-payment-modal').classList.remove('flex');
        }

        function setVendorViewMode(mode) {
            const tableContainer = document.getElementById('vendor-table-container');
            const cardsContainer = document.getElementById('vendor-cards-container');
            const tableBtn = document.getElementById('vendor-view-mode-table-btn');
            const cardsBtn = document.getElementById('vendor-view-mode-cards-btn');

            if (!tableContainer || !cardsContainer || !tableBtn || !cardsBtn) return;

            if (mode === 'table') {
                tableContainer.classList.remove('hidden');
                tableContainer.classList.add('block');
                cardsContainer.classList.add('md:hidden');
                tableBtn.className = 'rounded-md bg-slate-950 px-2.5 py-1 text-[10px] font-black text-white shadow-2xs';
                cardsBtn.className = 'rounded-md px-2.5 py-1 text-[10px] font-black text-slate-500 hover:text-slate-800';
            } else {
                tableContainer.classList.add('hidden');
                tableContainer.classList.remove('block');
                cardsContainer.classList.remove('md:hidden');
                tableBtn.className = 'rounded-md px-2.5 py-1 text-[10px] font-black text-slate-500 hover:text-slate-800';
                cardsBtn.className = 'rounded-md bg-slate-950 px-2.5 py-1 text-[10px] font-black text-white shadow-2xs';
            }
        }

        let currentPage = {{ $currentPage }};
        let hasMore = {{ $hasMorePages ? 'true' : 'false' }};
        let isLoadingMore = false;
        let currentSearch = @json($search);
        let searchDebounceTimer = null;
        const currentTab = @json($selectedTab);
        const currentDate = @json($date);
        const suppliersUrl = @json(route('purchaser.suppliers'));

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatMoney(amount) {
            return Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function renderTableRowHtml(row) {
            const totalFormatted = formatMoney(row.total_amount);
            const paidFormatted = formatMoney(row.paid_amount);
            const discountFormatted = formatMoney(row.discount_amount);
            const balanceFormatted = formatMoney(row.balance_amount);
            const isCredit = Boolean(row.credit_approved);
            const creditBadge = `<span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] ${isCredit ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${isCredit ? 'Credit' : 'Cash'}</span>`;
            const pendingBadge = row.pending_count > 0
                ? `<span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] ${escapeHtml(row.pending_issue_tone)}">${escapeHtml(row.pending_issue_label)}</span>`
                : '';
            const bankBadge = row.has_bank_details && row.bank_details
                ? `<span class="mt-1 inline-flex items-center gap-1 rounded bg-teal-50 px-1.5 py-0.5 text-[9px] font-bold text-teal-700" title="${escapeHtml(row.bank_details)}">🏦 Bank</span>`
                : '';
            const locationText = row.location ? `<p class="text-[10px] font-medium text-slate-500">${escapeHtml(row.location)}</p>` : '';
            const balanceClass = Number(row.balance_amount) > 0 ? 'text-amber-700' : 'text-slate-900';

            return `
                <tr class="transition-colors hover:bg-slate-50/80" data-supplier-id="${row.id}" data-total="${row.total_amount}" data-paid="${row.paid_amount}" data-discount="${row.discount_amount}" data-balance="${row.balance_amount}">
                    <td class="py-3 px-3.5 align-top">
                        <p class="font-bold text-slate-900">${escapeHtml(row.name)}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            ${creditBadge}
                            ${pendingBadge}
                        </div>
                    </td>
                    <td class="py-3 px-3 align-top">
                        <p class="text-[10px] font-medium text-slate-500">${escapeHtml(row.mobile_number || 'Mobile pending')}</p>
                        ${locationText}
                        ${bankBadge}
                    </td>
                    <td class="py-3 px-3 align-top font-mono text-[10px] font-bold text-slate-700">
                        ${escapeHtml(row.recent_business_date)}
                    </td>
                    <td class="py-3 px-3 text-right align-top font-mono font-black text-slate-950 whitespace-nowrap text-xs">
                        ₹${totalFormatted}
                    </td>
                    <td class="py-3 px-3 text-right align-top font-mono font-black text-emerald-700 whitespace-nowrap text-xs">
                        ₹${paidFormatted}
                    </td>
                    <td class="py-3 px-3 text-right align-top font-mono font-bold text-slate-600 whitespace-nowrap text-xs">
                        ₹${discountFormatted}
                    </td>
                    <td class="py-3 px-3 text-right align-top font-mono font-black ${balanceClass} whitespace-nowrap text-xs">
                        ₹${balanceFormatted}
                    </td>
                    <td class="py-3 px-3.5 text-center align-top whitespace-nowrap">
                        <a href="${escapeHtml(row.history_route)}" class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-slate-950 px-2.5 text-[10px] font-black text-white shadow-2xs transition-all hover:bg-slate-800 active:scale-95">
                            <span>View History</span>
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </td>
                </tr>
            `;
        }

        function renderCardHtml(row) {
            const totalFormatted = formatMoney(row.total_amount);
            const balanceFormatted = formatMoney(row.balance_amount);
            const isCredit = Boolean(row.credit_approved);
            const creditBadge = `<span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] ${isCredit ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${isCredit ? 'Credit' : 'Cash'}</span>`;
            const pendingBadge = row.pending_count > 0
                ? `<span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] ${escapeHtml(row.pending_issue_tone)}">${escapeHtml(row.pending_issue_label)}</span>`
                : '';
            const locText = row.location ? ` • ${escapeHtml(row.location)}` : '';
            const balanceText = Number(row.balance_amount) > 0
                ? `<span class="text-[10px] font-bold text-amber-700">Due ₹${balanceFormatted}</span>`
                : `<span class="text-[10px] font-bold text-emerald-700">Cleared</span>`;

            return `
                <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs space-y-2" data-supplier-id="${row.id}" data-total="${row.total_amount}" data-paid="${row.paid_amount}" data-discount="${row.discount_amount}" data-balance="${row.balance_amount}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-xs font-black text-slate-950 truncate">${escapeHtml(row.name)}</h3>
                            <p class="mt-0.5 text-[10px] font-medium text-slate-500">
                                ${escapeHtml(row.mobile_number || 'Mobile pending')}${locText}
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            ${creditBadge}
                            ${pendingBadge}
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t border-slate-100/80 pt-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-baseline gap-1.5 flex-wrap">
                                <span class="font-mono text-xs font-black text-slate-950">₹${totalFormatted}</span>
                                ${balanceText}
                            </div>
                            <p class="text-[10px] font-medium text-slate-500 mt-0.5">${escapeHtml(row.recent_business_date)}</p>
                        </div>
                        <a href="${escapeHtml(row.history_route)}" class="inline-flex h-7 items-center justify-center gap-1 rounded-lg bg-slate-950 px-2.5 text-[10px] font-black text-white shadow-2xs transition-all hover:bg-slate-800 active:scale-95 shrink-0">
                            History
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </article>
            `;
        }

        function updateTableFooter(totalRecords) {
            const tableFooter = document.getElementById('vendor-table-footer');
            const tableBody = document.getElementById('vendor-table-body');
            if (!tableFooter || !tableBody) return;

            const rows = tableBody.querySelectorAll('tr[data-supplier-id]');
            const count = rows.length;

            if (count === 0) {
                tableFooter.classList.add('hidden');
                return;
            }

            tableFooter.classList.remove('hidden');

            let totalBuy = 0;
            let totalPaid = 0;
            let totalDiscount = 0;
            let totalBalance = 0;

            rows.forEach(r => {
                totalBuy += parseFloat(r.dataset.total || '0');
                totalPaid += parseFloat(r.dataset.paid || '0');
                totalDiscount += parseFloat(r.dataset.discount || '0');
                totalBalance += parseFloat(r.dataset.balance || '0');
            });

            const footerCount = document.getElementById('table-footer-count');
            const footerTotal = document.getElementById('table-footer-total');
            const footerBuy = document.getElementById('table-footer-total-buy');
            const footerPaid = document.getElementById('table-footer-paid');
            const footerDiscount = document.getElementById('table-footer-discount');
            const footerBalance = document.getElementById('table-footer-balance');

            if (footerCount) footerCount.textContent = count;
            if (footerTotal && totalRecords !== undefined) footerTotal.textContent = totalRecords;
            if (footerBuy) footerBuy.textContent = '₹' + formatMoney(totalBuy);
            if (footerPaid) footerPaid.textContent = '₹' + formatMoney(totalPaid);
            if (footerDiscount) footerDiscount.textContent = '₹' + formatMoney(totalDiscount);
            if (footerBalance) footerBalance.textContent = '₹' + formatMoney(totalBalance);
        }

        async function loadMoreVendors() {
            if (isLoadingMore || !hasMore) return;

            isLoadingMore = true;
            const btn = document.getElementById('vendor-load-more-btn');
            const spinner = document.getElementById('load-more-spinner');
            const errorEl = document.getElementById('load-more-error');

            if (btn) btn.classList.add('pointer-events-none', 'opacity-60');
            if (spinner) spinner.classList.remove('hidden');
            if (errorEl) errorEl.classList.add('hidden');

            const nextPage = currentPage + 1;
            const url = new URL(suppliersUrl, window.location.origin);
            url.searchParams.set('page', nextPage);
            url.searchParams.set('tab', currentTab);
            url.searchParams.set('date', currentDate);
            if (currentSearch) {
                url.searchParams.set('search', currentSearch);
            }

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}`);
                }

                const data = await response.json();
                const rows = data.data || [];
                const tableBody = document.getElementById('vendor-table-body');
                const cardsContainer = document.getElementById('vendor-cards-container');

                rows.forEach(row => {
                    if (document.querySelector(`[data-supplier-id="${row.id}"]`)) {
                        return;
                    }
                    if (tableBody) {
                        const tr = document.createElement('tbody');
                        tr.innerHTML = renderTableRowHtml(row).trim();
                        tableBody.appendChild(tr.firstElementChild);
                    }
                    if (cardsContainer) {
                        const card = document.createElement('div');
                        card.innerHTML = renderCardHtml(row).trim();
                        cardsContainer.appendChild(card.firstElementChild);
                    }
                });

                currentPage = data.pagination.current_page;
                hasMore = data.pagination.has_more;

                const loadMoreCount = document.getElementById('load-more-count');
                const loadMoreTotal = document.getElementById('load-more-total');
                const loadMoreContainer = document.getElementById('vendor-load-more-container');

                const currentDisplayedCount = document.querySelectorAll('#vendor-table-body tr[data-supplier-id]').length;
                if (loadMoreCount) loadMoreCount.textContent = currentDisplayedCount;
                if (loadMoreTotal) loadMoreTotal.textContent = data.pagination.total_records;

                if (!hasMore && loadMoreContainer) {
                    loadMoreContainer.classList.add('hidden');
                }

                updateTableFooter(data.pagination.total_records);
            } catch (err) {
                console.error('Failed to load more vendors:', err);
                if (errorEl) {
                    errorEl.textContent = 'Failed to load more vendors. Click button to retry.';
                    errorEl.classList.remove('hidden');
                }
            } finally {
                isLoadingMore = false;
                if (btn) btn.classList.remove('pointer-events-none', 'opacity-60');
                if (spinner) spinner.classList.add('hidden');
            }
        }

        function handleVendorSearch(query) {
            clearTimeout(searchDebounceTimer);
            const spinner = document.getElementById('vendor-search-spinner');
            if (spinner) spinner.classList.remove('hidden');

            searchDebounceTimer = setTimeout(() => {
                executeVendorSearch(query);
            }, 300);
        }

        async function executeVendorSearch(query) {
            currentSearch = query.trim();
            currentPage = 1;

            const url = new URL(suppliersUrl, window.location.origin);
            url.searchParams.set('page', 1);
            url.searchParams.set('tab', currentTab);
            url.searchParams.set('date', currentDate);
            if (currentSearch) {
                url.searchParams.set('search', currentSearch);
            }

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}`);
                }

                const data = await response.json();
                const rows = data.data || [];
                const tableBody = document.getElementById('vendor-table-body');
                const cardsContainer = document.getElementById('vendor-cards-container');
                const loadMoreContainer = document.getElementById('vendor-load-more-container');

                if (tableBody) tableBody.innerHTML = '';
                if (cardsContainer) cardsContainer.innerHTML = '';

                if (rows.length === 0) {
                    const emptyText = currentSearch ? 'No vendors match your search.' : 'No vendors found for this tab yet.';
                    if (tableBody) {
                        tableBody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-xs font-bold text-slate-500">${escapeHtml(emptyText)}</td></tr>`;
                    }
                    if (cardsContainer) {
                        cardsContainer.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-xs font-bold text-slate-500">${escapeHtml(emptyText)}</div>`;
                    }
                    if (loadMoreContainer) {
                        loadMoreContainer.classList.add('hidden');
                    }
                } else {
                    rows.forEach(row => {
                        if (tableBody) {
                            const tr = document.createElement('tbody');
                            tr.innerHTML = renderTableRowHtml(row).trim();
                            tableBody.appendChild(tr.firstElementChild);
                        }
                        if (cardsContainer) {
                            const card = document.createElement('div');
                            card.innerHTML = renderCardHtml(row).trim();
                            cardsContainer.appendChild(card.firstElementChild);
                        }
                    });

                    hasMore = data.pagination.has_more;
                    if (loadMoreContainer) {
                        loadMoreContainer.classList.toggle('hidden', !hasMore);
                        const loadMoreCount = document.getElementById('load-more-count');
                        const loadMoreTotal = document.getElementById('load-more-total');
                        if (loadMoreCount) loadMoreCount.textContent = rows.length;
                        if (loadMoreTotal) loadMoreTotal.textContent = data.pagination.total_records;
                    }
                }

                updateTableFooter(data.pagination.total_records);

                const stateUrl = new URL(window.location.href);
                if (currentSearch) {
                    stateUrl.searchParams.set('search', currentSearch);
                } else {
                    stateUrl.searchParams.delete('search');
                }
                window.history.replaceState(null, '', stateUrl.toString());
            } catch (err) {
                console.error('Vendor search error:', err);
            } finally {
                const spinner = document.getElementById('vendor-search-spinner');
                if (spinner) spinner.classList.add('hidden');
            }
        }
    </script>
</x-layouts.app>
