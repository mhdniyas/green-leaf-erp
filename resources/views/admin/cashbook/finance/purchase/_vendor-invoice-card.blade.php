@php
    $totalPurchase = (float) ($vendor->total_purchase ?? 0);
    $cash = (float) ($vendor->cash_purchase ?? 0);
    $credit = (float) ($vendor->credit_purchase ?? 0);
    if ($credit <= 0.009) {
        $paidLabel = 'Cash Paid';
        $paidAmount = (float) ($vendor->paid_amount ?? $vendor->cash_purchase);
    } elseif ($cash <= 0.009) {
        $paidLabel = 'Paid';
        $paidAmount = (float) ($vendor->paid_amount ?? $vendor->credit_paid);
    } else {
        $paidLabel = 'Paid';
        $paidAmount = (float) ($vendor->paid_amount ?? ($cash + (float) $vendor->credit_paid));
    }
    $discountAmount = (float) ($vendor->discount ?? 0);
    $creditBalance = (float) ($vendor->outstanding ?? 0);
    $billCount = (int) ($vendor->invoice_count ?? 0);
    $outstandingBillsCount = (int) ($vendor->outstanding_bills_count ?? ($creditBalance > 0.009 ? 1 : 0));

    // Authoritative settlement check:
    $isFullyPaid = ($creditBalance <= 0.009) && ($totalPurchase <= 0.009 || $paidAmount >= ($totalPurchase - 0.009));
    $isPartiallyPaid = ! $isFullyPaid && ($paidAmount > 0.009);
    $isUnpaid = ! $isFullyPaid && ! $isPartiallyPaid;

    $slNo = str_pad((string) ($serialNumber ?? 1), 2, '0', STR_PAD_LEFT);

    $vendorUrl = route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext);
    $vendorPdfUrl = route('admin.cashbook.finance.purchase.vendors.single.export.pdf', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext);
    $vendorExcelUrl = route('admin.cashbook.finance.purchase.vendors.single.export.excel', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext);
    $tags = $vendor->category_tags ? array_map(fn ($tag) => explode('|', $tag, 2), array_filter(explode(',', $vendor->category_tags))) : [];
@endphp

<article x-data="{
    showOutstanding: false,
    billsLoaded: false,
    isLoading: false,
    isLoadingMore: false,
    page: 1,
    hasMore: false,
    remainingCount: 0,
    outstandingCount: {{ $outstandingBillsCount }},
    bills: [],
    errorMessage: null,
    async toggleOutstandingBills() {
        this.showOutstanding = !this.showOutstanding;
        if (this.showOutstanding && !this.billsLoaded && !this.isLoading) {
            await this.fetchBills(1);
        }
    },
    async fetchBills(pageToFetch = 1) {
        if (pageToFetch === 1) {
            this.isLoading = true;
            this.errorMessage = null;
        } else {
            this.isLoadingMore = true;
        }
        try {
            const url = new URL('{{ route('admin.cashbook.finance.purchase.vendors.outstanding-bills', $vendor->supplier_public_uuid) }}', window.location.origin);
            url.searchParams.set('page', pageToFetch);
            @if(!empty($purchaseContext['start_date'])) url.searchParams.set('start_date', '{{ $purchaseContext['start_date'] }}'); @endif
            @if(!empty($purchaseContext['end_date'])) url.searchParams.set('end_date', '{{ $purchaseContext['end_date'] }}'); @endif
            @if(!empty($purchaseContext['purchaser_id'])) url.searchParams.set('purchaser_id', '{{ $purchaseContext['purchaser_id'] }}'); @endif
            @if(!empty($purchaseContext['product_filter'])) url.searchParams.set('product_filter', '{{ $purchaseContext['product_filter'] }}'); @endif
            @if(!empty($purchaseContext['payment'])) url.searchParams.set('payment', '{{ $purchaseContext['payment'] }}'); @endif

            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (res.ok && data.success) {
                if (pageToFetch === 1) {
                    this.bills = data.bills;
                } else {
                    this.bills = [...this.bills, ...data.bills];
                }
                this.page = data.page;
                this.hasMore = data.has_more;
                this.remainingCount = data.remaining_count;
                this.outstandingCount = data.total_count;
                this.billsLoaded = true;
            } else {
                this.errorMessage = data.message || 'Failed to load outstanding bills.';
            }
        } catch (e) {
            this.errorMessage = 'Network error while loading bills. Please retry.';
        } finally {
            this.isLoading = false;
            this.isLoadingMore = false;
            this.$nextTick(() => {
                if (window.lucide) { window.lucide.createIcons(); }
            });
        }
    },
    async loadMore() {
        if (this.isLoadingMore || !this.hasMore) return;
        await this.fetchBills(this.page + 1);
    }
}" class="w-full rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 border-l-4 {{ $isFullyPaid ? 'border-l-emerald-600' : ($isPartiallyPaid ? 'border-l-amber-500' : 'border-l-rose-500') }} relative overflow-hidden">
    {{-- Top Bar: Serial Number & Action Buttons on Same Line (Left), Status Badge (Right) --}}
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2.5 mb-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="vendor-serial-badge inline-flex items-center text-xs font-black tracking-wider px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-800 border border-slate-200/80 font-mono"
                  data-initial-sl="{{ $slNo }}">
                <span class="serial-num">SL {{ $slNo }}</span>
            </span>

            <a href="{{ $vendorPdfUrl }}"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 hover:text-slate-950 shadow-2xs transition"
               title="Export {{ $vendor->supplier_name }} Bill Report to PDF">
                <i data-lucide="file-text" class="w-3.5 h-3.5 text-rose-600"></i>
                <span>PDF</span>
            </a>
            <a href="{{ $vendorExcelUrl }}"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 hover:text-slate-950 shadow-2xs transition"
               title="Export {{ $vendor->supplier_name }} Report to Excel (.xlsx)">
                <i data-lucide="sheet" class="w-3.5 h-3.5 text-emerald-600"></i>
                <span>Excel</span>
            </a>
            <a href="{{ $vendorUrl }}"
               class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/80 px-3 py-1 text-xs font-extrabold transition">
                <span>View Vendor Details</span>
                <i data-lucide="arrow-right" class="w-3 h-3 text-emerald-700"></i>
            </a>
        </div>
        <div>
            @if($isFullyPaid)
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100/90 text-emerald-900 border border-emerald-300 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                    <span>PAID</span>
                </span>
            @elseif($isPartiallyPaid)
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100/90 text-amber-900 border border-amber-300 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                    <span>PARTIAL</span>
                </span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-100/90 text-rose-900 border border-rose-300 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                    <span>UNPAID</span>
                </span>
            @endif
        </div>
    </div>

    {{-- Main Body: Vendor Info on Left, Bill Totals Table on Right --}}
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        {{-- Left: Vendor Identity & Details --}}
        <div class="min-w-0 flex-1 space-y-2">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">VENDOR</span>
                <div class="mt-0.5 flex items-center gap-2.5 flex-wrap">
                    <h3 class="text-base sm:text-lg font-black text-slate-950 uppercase tracking-tight">
                        {{ $vendor->supplier_name ?? 'Unassigned Vendor' }}
                    </h3>

                    @if(!empty($tags))
                        <div class="inline-flex items-center gap-1 flex-wrap">
                            @foreach(array_slice($tags, 0, 3) as [$categoryId, $categoryName])
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-700 border border-slate-200/60">
                                    {{ $categoryName ?: 'Uncategorised' }}
                                </span>
                            @endforeach
                            @if(count($tags) > 3)
                                <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[9px] font-black text-white">
                                    +{{ count($tags) - 3 }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3 text-xs font-semibold text-slate-500 flex-wrap">
                <span class="flex items-center gap-1">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>Period: <strong class="text-slate-800 font-bold">{{ $formattedRange }}</strong></span>
                </span>
                <span class="text-slate-300">&bull;</span>
                <span class="flex items-center gap-1 text-slate-700 font-bold">
                    <i data-lucide="receipt" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>{{ number_format($billCount) }} {{ \Illuminate\Support\Str::plural('Purchase Bill', $billCount) }}</span>
                </span>
                <span class="text-slate-300">&bull;</span>
                <span class="text-slate-500">
                    Terms: <strong class="text-slate-800 font-bold">{{ $credit > 0.009 ? 'Credit' : 'Cash' }}</strong>
                </span>
            </div>
        </div>

        {{-- Right: Right-aligned Financial Totals Block as in Invoice Bills --}}
        <div class="w-full lg:w-96 rounded-2xl bg-slate-50/90 border border-slate-200/80 p-3.5 sm:p-4 space-y-2 shrink-0">
            <div class="flex items-center justify-between text-xs text-slate-600">
                <span class="font-bold">Subtotal</span>
                <span class="font-mono font-bold text-slate-900 tabular-nums whitespace-nowrap">Rs. {{ number_format((float) $vendor->total_purchase, 2) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-600">
                <span class="font-bold">Discount</span>
                <span class="font-mono font-bold text-slate-700 tabular-nums whitespace-nowrap">Rs. {{ number_format($discountAmount, 2) }}</span>
            </div>
            <div class="pt-1.5 border-t border-slate-200 flex items-center justify-between text-xs">
                <span class="font-black text-slate-950 uppercase tracking-wider">PURCHASE TOTAL</span>
                <span class="font-mono font-black text-slate-950 text-sm sm:text-base tabular-nums whitespace-nowrap">Rs. {{ number_format((float) $vendor->total_purchase, 2) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs rounded-xl bg-emerald-50 border border-emerald-200/70 px-2.5 py-1.5 text-emerald-900">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-2xs"></span>
                    <span class="font-black">{{ $paidLabel }}</span>
                </div>
                <span class="font-mono font-black text-emerald-700 tabular-nums whitespace-nowrap">Rs. {{ number_format($paidAmount, 2) }}</span>
            </div>

            {{-- Interactive Clickable Credit Balance to Pay (Expands only outstanding bills via L2 lazy-loading) --}}
            @if($creditBalance > 0.009)
                <div @click="toggleOutstandingBills()"
                     :class="showOutstanding ? 'ring-2 ring-amber-400 bg-amber-100/95' : 'hover:bg-amber-100/75 cursor-pointer'"
                     class="flex items-center justify-between gap-2 text-xs rounded-xl bg-amber-50 border border-amber-200/80 text-amber-950 px-3 py-2 transition-all select-none cursor-pointer group"
                     title="Click to view and update outstanding purchase bills">
                    <div class="flex items-center gap-2 min-w-0">
                        <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 text-amber-700 transition-transform duration-200" :class="showOutstanding ? 'rotate-90' : ''"></i>
                        <div class="flex flex-col min-w-0">
                            <span class="font-black uppercase tracking-wider text-[11px] leading-tight text-amber-950 group-hover:underline">CREDIT BALANCE TO PAY</span>
                            <span class="text-[10px] text-amber-800 font-bold"
                                  x-text="billsLoaded ? `${outstandingCount} Due ${outstandingCount === 1 ? 'Bill' : 'Bills'}` : '{{ $outstandingBillsCount }} Due {{ \Illuminate\Support\Str::plural('Bill', $outstandingBillsCount) }}'">
                                {{ $outstandingBillsCount }} Due {{ \Illuminate\Support\Str::plural('Bill', $outstandingBillsCount) }}
                            </span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="font-mono font-black text-rose-700 text-sm sm:text-base tabular-nums whitespace-nowrap block leading-tight">Rs. {{ number_format($creditBalance, 2) }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- L2: Expandable Outstanding Purchase Bills List (Only shown when Credit Balance is clicked) --}}
    @if($creditBalance > 0.009)
        <div x-show="showOutstanding" x-cloak class="mt-4 pt-3.5 border-t border-slate-200/80 space-y-2.5">
            <div class="flex items-center justify-between text-[11px] font-extrabold text-slate-500 uppercase tracking-wider flex-wrap gap-2">
                <span class="text-slate-800 font-black flex items-center gap-1.5">
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i>
                    <span>Outstanding Purchase Bills for {{ $vendor->supplier_name }} (<span x-text="outstandingCount"></span>)</span>
                </span>
                <span class="text-[10px] text-slate-400 font-semibold">
                    Showing <span x-text="bills.length"></span> of <span x-text="outstandingCount"></span> due &bull; Click "Update Payment" to record bill payment
                </span>
            </div>

            {{-- Loading State --}}
            <div x-show="isLoading" class="py-6 text-center text-xs font-bold text-slate-500 flex items-center justify-center gap-2">
                <svg class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>Loading outstanding purchase bills...</span>
            </div>

            {{-- Error State with Retry --}}
            <div x-show="errorMessage" x-cloak class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                    <span x-text="errorMessage"></span>
                </div>
                <button type="button"
                        @click="fetchBills(1)"
                        class="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-[10px] uppercase cursor-pointer">
                    Retry
                </button>
            </div>

            {{-- Desktop Loaded Bills Table (md and up) --}}
            <div x-show="!isLoading && bills.length > 0" class="hidden md:block overflow-x-auto rounded-xl border border-slate-200/90 bg-slate-50/50 shadow-2xs">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/90 text-[10px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2">Bill No</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Purchaser</th>
                            <th class="px-3 py-2 text-right">Purchase Total</th>
                            <th class="px-3 py-2 text-right">Discount</th>
                            <th class="px-3 py-2 text-right">Already Paid</th>
                            <th class="px-3 py-2 text-right">Balance Due</th>
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 bg-white">
                        <template x-for="b in bills" :key="b.id">
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-3 py-2 font-mono font-bold text-slate-900 whitespace-nowrap" x-text="b.invoice_number"></td>
                                <td class="px-3 py-2 font-semibold text-slate-600 whitespace-nowrap" x-text="b.business_date"></td>
                                <td class="px-3 py-2 text-slate-700 font-medium whitespace-nowrap" x-text="b.purchaser_name"></td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-slate-900 tabular-nums whitespace-nowrap"
                                    x-text="'Rs. ' + Number(b.gross_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                <td class="px-3 py-2 text-right font-mono text-slate-600 tabular-nums whitespace-nowrap"
                                    x-text="'Rs. ' + Number(b.discount_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-emerald-700 tabular-nums whitespace-nowrap"
                                    x-text="'Rs. ' + Number(b.paid_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                <td class="px-3 py-2 text-right font-mono font-black text-rose-700 tabular-nums whitespace-nowrap"
                                    x-text="'Rs. ' + Number(b.outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                    <template x-if="b.status === 'PARTIAL'">
                                        <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[9px] font-black uppercase">PARTIAL</span>
                                    </template>
                                    <template x-if="b.status === 'UNPAID'">
                                        <span class="rounded-full bg-rose-100 text-rose-800 px-2 py-0.5 text-[9px] font-black uppercase">UNPAID</span>
                                    </template>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button type="button"
                                            @click="$dispatch('open-payment-modal', b)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 py-1 text-xs font-bold shadow-2xs transition cursor-pointer">
                                        <i data-lucide="credit-card" class="w-3 h-3"></i>
                                        <span>Update Payment</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Mobile Loaded Bills Stack (below md) --}}
            <div x-show="!isLoading && bills.length > 0" class="block md:hidden space-y-2.5">
                <template x-for="b in bills" :key="'mob-' + b.id">
                    <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2 shadow-2xs">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                            <div class="min-w-0">
                                <span class="font-mono font-bold text-xs text-slate-900 block truncate" x-text="b.invoice_number"></span>
                                <span class="text-[10px] font-semibold text-slate-500" x-text="b.business_date + ' • ' + (b.purchaser_name || 'Direct')"></span>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider"
                                  :class="b.status === 'PARTIAL' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="b.status"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total</span>
                                <span class="font-mono font-bold text-slate-800 tabular-nums whitespace-nowrap"
                                      x-text="'Rs. ' + Number(b.gross_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Balance Due</span>
                                <span class="font-mono font-black text-rose-700 tabular-nums text-sm whitespace-nowrap"
                                      x-text="'Rs. ' + Number(b.outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                            <span class="text-[10px] text-slate-500 font-semibold tabular-nums"
                                  x-text="'Paid: Rs. ' + Number(b.paid_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            <button type="button"
                                    @click="$dispatch('open-payment-modal', b)"
                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 text-xs font-bold shadow-2xs transition cursor-pointer">
                                <i data-lucide="credit-card" class="w-3 h-3"></i>
                                <span>Update Payment</span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Load More (batches of 5) --}}
            <div x-show="hasMore" class="pt-1 text-center">
                <button type="button"
                        @click="loadMore()"
                        :disabled="isLoadingMore"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 px-4 py-1.5 text-xs font-black uppercase tracking-wider text-slate-700 shadow-2xs transition cursor-pointer active:scale-98">
                    <template x-if="isLoadingMore">
                        <span class="inline-flex items-center gap-1">
                            <svg class="animate-spin h-3.5 w-3.5 text-slate-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Loading more...</span>
                        </span>
                    </template>
                    <template x-if="!isLoadingMore">
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-amber-600"></i>
                            <span>Load More (<span x-text="remainingCount"></span> remaining)</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    @endif
</article>

