<x-layouts.app title="Purchaser Vendor Hub">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Vendor Hub</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Purchaser vendors</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Old payment follow-up lives here. Current business-day cart work stays in Daily Carts.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <a href="{{ route('purchaser.vendors', ['date' => $date]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-white">
                        Open Daily Carts
                    </a>
                    <form action="{{ route('purchaser.suppliers') }}" method="GET">
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="tab" value="{{ $selectedTab }}">
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search vendor, mobile, location..." class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none sm:w-72">
                    </form>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1 shadow-sm">
            <a href="{{ route('purchaser.suppliers', ['date' => $date, 'tab' => 'pending', 'search' => $search]) }}" class="rounded-xl px-4 py-2 text-center text-xs font-black {{ $selectedTab === 'pending' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500' }}">
                Pending ({{ $tabCounts['pending'] ?? 0 }})
            </a>
            <a href="{{ route('purchaser.suppliers', ['date' => $date, 'tab' => 'credit', 'search' => $search]) }}" class="rounded-xl px-4 py-2 text-center text-xs font-black {{ $selectedTab === 'credit' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500' }}">
                Credit ({{ $tabCounts['credit'] ?? 0 }})
            </a>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4 space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Vendor Summary</p>
                    <h2 class="mt-1 text-sm font-black text-slate-950">All purchaser vendors ({{ $supplierRows->count() }})</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Track payment totals, banking notes, and open complete vendor history.</p>
                </div>
                <!-- Table / Cards View Switcher -->
                <div class="flex items-center justify-end rounded-xl border border-slate-200 bg-slate-50 p-1 shadow-xs">
                    <button type="button" id="vendor-view-mode-table-btn" onclick="setVendorViewMode('table')" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-950 px-2.5 py-1.5 text-[10px] font-black text-white shadow-xs transition-all sm:px-3">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18" />
                        </svg>
                        Table View
                    </button>
                    <button type="button" id="vendor-view-mode-cards-btn" onclick="setVendorViewMode('cards')" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[10px] font-black text-slate-500 transition-all hover:text-slate-800 sm:px-3">
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

            <!-- Mobile Touch-Scrollable Table View -->
            <div id="vendor-table-container" class="vendor-view-table overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-xs">
                <table class="w-full text-left text-xs min-w-[950px]">
                    <thead class="border-b border-slate-100 bg-slate-950 text-[10px] font-black uppercase tracking-wider text-slate-300">
                        <tr>
                            <th scope="col" class="py-3 px-3.5">Vendor Name</th>
                            <th scope="col" class="py-3 px-3">Contact & Location</th>
                            <th scope="col" class="py-3 px-3">Recent Date</th>
                            <th scope="col" class="py-3 px-3 text-right">Total</th>
                            <th scope="col" class="py-3 px-3 text-right">Paid</th>
                            <th scope="col" class="py-3 px-3 text-right">Discount</th>
                            <th scope="col" class="py-3 px-3 text-right">Balance</th>
                            <th scope="col" class="py-3 px-3.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($supplierRows as $row)
                            @php($supplier = $row['supplier'])
                            <tr class="transition-colors hover:bg-slate-50/80">
                                <!-- Vendor Name -->
                                <td class="py-3 px-3.5 align-top">
                                    <div class="flex items-center gap-2">
                                        <p class="font-black text-slate-950 text-sm">{{ $supplier->name }}</p>
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $supplier->credit_approved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ $supplier->credit_approved ? 'Credit' : 'Cash' }}
                                        </span>
                                        @if ($row['pending_count'] > 0)
                                            <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $row['pending_issue_tone'] }}">
                                                {{ $row['pending_issue_label'] }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <!-- Contact & Banking Notes -->
                                <td class="py-3 px-3 align-top">
                                    <p class="text-xs font-semibold text-slate-900">{{ $supplier->mobile_number ?: 'Mobile pending' }}</p>
                                    @if ($supplier->location)
                                        <p class="text-[10px] font-medium text-slate-500">{{ $supplier->location }}</p>
                                    @endif
                                    @if (filled($supplier->bank_details))
                                        <span class="mt-1 inline-flex items-center gap-1 rounded bg-teal-50 px-1.5 py-0.5 text-[9px] font-bold text-teal-700" title="{{ $supplier->bank_details }}">
                                            🏦 Bank Info
                                        </span>
                                    @endif
                                </td>
                                <!-- Recent Date -->
                                <td class="py-3 px-3 align-top font-mono text-xs font-bold text-slate-700">
                                    {{ $row['recent_business_date'] }}
                                </td>
                                <!-- Total -->
                                <td class="py-3 px-3 text-right align-top font-mono font-black text-slate-950 whitespace-nowrap">
                                    ₹{{ number_format($row['total_amount'], 2) }}
                                </td>
                                <!-- Paid -->
                                <td class="py-3 px-3 text-right align-top font-mono font-black text-emerald-700 whitespace-nowrap">
                                    ₹{{ number_format($row['paid_amount'], 2) }}
                                </td>
                                <!-- Discount -->
                                <td class="py-3 px-3 text-right align-top font-mono font-bold text-slate-600 whitespace-nowrap">
                                    ₹{{ number_format($row['discount_amount'], 2) }}
                                </td>
                                <!-- Balance -->
                                <td class="py-3 px-3 text-right align-top font-mono font-black {{ $row['balance_amount'] > 0 ? 'text-amber-700' : 'text-slate-900' }} whitespace-nowrap">
                                    ₹{{ number_format($row['balance_amount'], 2) }}
                                </td>
                                <!-- Action Link -->
                                <td class="py-3 px-3.5 text-center align-top whitespace-nowrap">
                                    <a href="{{ $row['history_route'] }}" class="inline-flex h-8 items-center justify-center gap-1 rounded-xl bg-slate-950 px-3 text-[11px] font-black text-white shadow-xs transition-all hover:bg-slate-800 active:scale-95">
                                        <span>View Details</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-xs font-bold text-slate-500">
                                    No vendors found for this tab yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($supplierRows->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-900 bg-slate-950 text-white font-mono text-xs">
                            <tr>
                                <td colspan="3" class="py-3 px-3.5 font-sans font-black uppercase text-[10px] tracking-wider text-slate-300">
                                    Total Summary ({{ $supplierRows->count() }} {{ \Illuminate\Support\Str::plural('vendor', $supplierRows->count()) }})
                                </td>
                                <td class="py-3 px-3 text-right font-black text-white whitespace-nowrap">
                                    ₹{{ number_format($supplierRows->sum('total_amount'), 2) }}
                                </td>
                                <td class="py-3 px-3 text-right font-black text-emerald-400 whitespace-nowrap">
                                    ₹{{ number_format($supplierRows->sum('paid_amount'), 2) }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-slate-300 whitespace-nowrap">
                                    ₹{{ number_format($supplierRows->sum('discount_amount'), 2) }}
                                </td>
                                <td class="py-3 px-3 text-right font-black text-amber-400 whitespace-nowrap">
                                    ₹{{ number_format($supplierRows->sum('balance_amount'), 2) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <!-- Mobile Cards View Container (Hidden by default or toggled) -->
            <div id="vendor-cards-container" class="vendor-view-cards hidden space-y-3">
                @forelse ($supplierRows as $row)
                    @php($supplier = $row['supplier'])
                    <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 shadow-xs">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-sm font-black text-slate-950">{{ $supplier->name }}</h3>
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $supplier->credit_approved ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $supplier->credit_approved ? 'Credit Approved' : 'Cash Terms' }}
                                    </span>
                                    @if ($row['pending_count'] > 0)
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $row['pending_issue_tone'] }}">
                                            {{ $row['pending_issue_label'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ $supplier->mobile_number ?: 'Mobile pending' }}{{ $supplier->location ? ' • '.$supplier->location : '' }}</p>
                                @if (filled($supplier->bank_details))
                                    <p class="mt-1 text-[10px] font-bold text-teal-700">🏦 {{ $supplier->bank_details }}</p>
                                @endif
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black text-slate-700 shrink-0">{{ $row['pending_count'] }} {{ \Illuminate\Support\Str::plural('issue', (int) $row['pending_count']) }}</span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-white px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Recent Date</p>
                                <p class="mt-0.5 font-mono text-[11px] font-black text-slate-900">{{ $row['recent_business_date'] }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Total Buy</p>
                                <p class="mt-0.5 font-mono text-[11px] font-black text-slate-900">₹{{ number_format($row['total_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Paid Cash</p>
                                <p class="mt-0.5 font-mono text-[11px] font-black text-emerald-700">₹{{ number_format($row['paid_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Balance</p>
                                <p class="mt-0.5 font-mono text-[11px] font-black {{ $row['balance_amount'] > 0 ? 'text-amber-700' : 'text-slate-900' }}">₹{{ number_format($row['balance_amount'], 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <a href="{{ $row['history_route'] }}" class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">
                                <span>View Details (Full History)</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-xs font-bold text-slate-500">
                        No vendors found for this tab yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border {{ $issueSections->sum('count') > 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-col gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] {{ $issueSections->sum('count') > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Action Board</p>
                    <h2 class="mt-1 text-lg font-black {{ $issueSections->sum('count') > 0 ? 'text-rose-900' : 'text-emerald-900' }}">
                        {{ $selectedTab === 'credit' ? 'Credit follow-up' : 'Pending vendor follow-up' }}
                    </h2>
                    <p class="mt-1 text-xs font-semibold {{ $issueSections->sum('count') > 0 ? 'text-rose-800' : 'text-emerald-800' }}">
                        @if ($issueSections->sum('count') > 0)
                            Open each vendor issue below and finish it from the correct page or popup.
                        @else
                            Nothing is pending in this tab right now.
                        @endif
                    </p>
                </div>

                <div class="grid gap-2">
                    @foreach ($issueSections as $section)
                        <details class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm" @if($section['count'] > 0) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="truncate text-sm font-black text-slate-950">{{ $section['label'] }}</h3>
                                        <span class="rounded-full {{ $section['count'] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }} px-2.5 py-1 text-[10px] font-black">
                                            {{ $section['count'] }} {{ \Illuminate\Support\Str::plural('issue', (int) $section['count']) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $section['description'] }}</p>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Expand</span>
                            </summary>

                            <div class="mt-3 grid gap-2">
                                @forelse ($section['rows'] as $row)
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate text-sm font-black text-slate-950">{{ $row['supplier']->name }}</p>
                                                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-600">
                                                        {{ $row['cart']->cart_number }}
                                                    </span>
                                                    @if ($section['key'] === 'receipt_pending')
                                                        <span class="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-teal-700">
                                                            Warehouse Issue
                                                        </span>
                                                        @php($paymentStatus = (string) ($row['cart']->purchaseInvoice?->payment_status ?: $row['cart']->payment_status ?: 'unpaid'))
                                                        @if ($paymentStatus === 'paid')
                                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-emerald-700">
                                                                Paid
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                                    Business date {{ $row['cart']->business_date->format('d M Y') }}
                                                    @if ($row['supplier']->mobile_number)
                                                        • {{ $row['supplier']->mobile_number }}
                                                    @endif
                                                </p>
                                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $row['popup_message'] }}</p>
                                            </div>

                                            @if (($row['action_type'] ?? '') === 'update_payment')
                                                <button
                                                    type="button"
                                                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white transition hover:bg-slate-800"
                                                    onclick="openDirectPaymentModal(this)"
                                                    data-invoice-id="{{ $row['invoice']['id'] }}"
                                                    data-invoice-number="{{ $row['invoice']['number'] }}"
                                                    data-supplier-name="{{ $row['invoice']['supplier'] }}"
                                                    data-amount="{{ $row['invoice']['amount'] }}"
                                                    data-discount-amount="{{ $row['invoice']['discountAmount'] }}"
                                                    data-paid-amount="{{ $row['invoice']['paidAmount'] }}"
                                                    data-payment-method="{{ $row['invoice']['paymentMethod'] }}"
                                                    data-payment-note="{{ $row['invoice']['paymentNote'] }}"
                                                    data-payment-details="{{ $row['invoice']['paymentDetails'] }}"
                                                    data-credit-approved="{{ $row['invoice']['creditApproved'] ? 'true' : 'false' }}"
                                                    data-payment-route="{{ $row['payment_route'] }}"
                                                >
                                                    {{ $row['button'] }}
                                                </button>
                                            @else
                                                <a href="{{ $row['route'] }}" class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 px-4 text-xs font-black text-white transition hover:bg-rose-700">
                                                    {{ $row['button'] }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 px-3 py-4 text-center text-xs font-bold text-emerald-700">
                                        {{ $section['empty'] }}
                                    </div>
                                @endforelse
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

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
                    <label for="direct_payment_note" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                    <input id="direct_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="direct_payment_details" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Details</label>
                    <textarea id="direct_payment_details" name="payment_details" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
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
            const creditApproved = btn.dataset.creditApproved === 'true';

            document.getElementById('direct-payment-title').textContent = `${btn.dataset.invoiceNumber} • ${btn.dataset.supplierName}`;
            document.getElementById('direct-payment-form').action = btn.dataset.paymentRoute;
            document.getElementById('direct-payment-total').textContent = `₹ ${amount.toFixed(2)}`;
            directPaymentCurrentPaidAmount = initialPaid;

            const discountInput = document.getElementById('direct_payment_discount_amount');
            const methodInput = document.getElementById('direct_payment_method');
            const paidInput = document.getElementById('direct_additional_paid_amount');
            const noteInput = document.getElementById('direct_payment_note');
            const detailsInput = document.getElementById('direct_payment_details');
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
            detailsInput.value = paymentDetails;

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
                cardsContainer.classList.add('hidden');
                tableBtn.className = 'rounded-lg bg-slate-950 px-3 py-1.5 text-[10px] font-black text-white shadow-xs transition-all';
                cardsBtn.className = 'rounded-lg px-3 py-1.5 text-[10px] font-black text-slate-500 hover:text-slate-800 transition-all';
            } else {
                tableContainer.classList.add('hidden');
                cardsContainer.classList.remove('hidden');
                tableBtn.className = 'rounded-lg px-3 py-1.5 text-[10px] font-black text-slate-500 hover:text-slate-800 transition-all';
                cardsBtn.className = 'rounded-lg bg-slate-950 px-3 py-1.5 text-[10px] font-black text-white shadow-xs transition-all';
            }
        }
    </script>
</x-layouts.app>
