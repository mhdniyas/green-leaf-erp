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

    // Authoritative settlement check:
    $isFullyPaid = ($creditBalance <= 0.009) && ($totalPurchase <= 0.009 || $paidAmount >= ($totalPurchase - 0.009));
    $isPartiallyPaid = ! $isFullyPaid && ($paidAmount > 0.009);
    $isUnpaid = ! $isFullyPaid && ! $isPartiallyPaid;

    $slNo = str_pad((string) ($serialNumber ?? 1), 2, '0', STR_PAD_LEFT);
    $bills = $vendor->bills ?? collect();
    $unpaidBills = $bills->filter(fn ($b) => ((float) ($b->outstanding ?? 0)) > 0.009);
    $unpaidCount = $unpaidBills->count();

    $vendorUrl = route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext);
    $vendorPdfUrl = route('admin.cashbook.finance.purchase.vendors.single.export.pdf', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext);
    $vendorExcelUrl = route('admin.cashbook.finance.purchase.vendors.single.export.excel', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext);
    $tags = $vendor->category_tags ? array_map(fn ($tag) => explode('|', $tag, 2), array_filter(explode(',', $vendor->category_tags))) : [];
@endphp

<article x-data="{ showBills: false }" class="w-full rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 border-l-4 {{ $isFullyPaid ? 'border-l-emerald-600' : ($isPartiallyPaid ? 'border-l-amber-500' : 'border-l-rose-500') }} relative overflow-hidden">
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
        {{-- Left: Vendor Identity & Details & Payment Actions --}}
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

            {{-- Bill Payment Actions: Single Bill Payment / Expand Bills --}}
            @if($bills->count() === 1)
                @php
                    $singleBill = $bills->first();
                    $singleDue = (float) ($singleBill->outstanding ?? 0);
                    $singlePaid = (float) ($singleBill->paid_amount ?? 0);
                    $singleDiscount = (float) ($singleBill->discount ?? 0);
                    $singleNet = (float) ($singleBill->total_purchase ?? 0);
                    $singleGross = $singleNet + $singleDiscount;
                @endphp
                @if($singleDue > 0.009)
                    <div class="pt-1">
                        <button type="button"
                                @click="$dispatch('open-payment-modal', {{ json_encode([
                                    'id' => $singleBill->invoice_id,
                                    'invoice_public_uuid' => $singleBill->invoice_public_uuid,
                                    'invoice_number' => $singleBill->invoice_number ?: ('#'.$singleBill->invoice_id),
                                    'supplier_name' => $vendor->supplier_name,
                                    'purchaser_name' => $singleBill->purchaser_name,
                                    'business_date' => $singleBill->business_date ? \Illuminate\Support\Carbon::parse($singleBill->business_date)->format('d M Y') : '—',
                                    'gross_amount' => $singleGross,
                                    'discount_amount' => $singleDiscount,
                                    'net_payable' => $singleNet,
                                    'paid_amount' => $singlePaid,
                                    'outstanding' => $singleDue,
                                    'pay_url' => route('admin.cashbook.finance.vendor-credit.pay', $singleBill->invoice_public_uuid),
                                ]) }})"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1 text-xs font-black shadow-xs transition cursor-pointer">
                            <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                            <span>Update Payment</span>
                        </button>
                    </div>
                @endif
            @elseif($bills->count() > 1)
                <div class="pt-1">
                    <button type="button"
                            @click="showBills = !showBills"
                            class="inline-flex items-center gap-1.5 rounded-lg border {{ $unpaidCount > 0 ? 'border-amber-300 bg-amber-50/70 hover:bg-amber-100 text-amber-900' : 'border-slate-300 bg-white hover:bg-slate-50 text-slate-700' }} px-3 py-1 text-xs font-bold transition cursor-pointer">
                        <i data-lucide="receipt" class="w-3.5 h-3.5 {{ $unpaidCount > 0 ? 'text-amber-600' : 'text-slate-500' }}"></i>
                        <span>Bills &amp; Payments ({{ $bills->count() }})</span>
                        @if($unpaidCount > 0)
                            <span class="rounded-full bg-amber-200/90 px-1.5 py-0.2 text-[9px] font-black text-amber-950">{{ $unpaidCount }} Due</span>
                        @endif
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform duration-200" :class="showBills ? 'rotate-180' : ''"></i>
                    </button>
                </div>
            @endif
        </div>

        {{-- Right: Right-aligned Financial Totals Block as in Invoice Bills --}}
        <div class="w-full lg:w-80 rounded-2xl bg-slate-50/90 border border-slate-200/80 p-3.5 space-y-2 shrink-0">
            <div class="flex items-center justify-between text-xs text-slate-600">
                <span class="font-bold">Subtotal</span>
                <span class="font-mono font-bold text-slate-900 tabular-nums">Rs. {{ number_format((float) $vendor->total_purchase, 2) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-600">
                <span class="font-bold">Discount</span>
                <span class="font-mono font-bold text-slate-700 tabular-nums">Rs. {{ number_format($discountAmount, 2) }}</span>
            </div>
            <div class="pt-1.5 border-t border-slate-200 flex items-center justify-between text-xs">
                <span class="font-black text-slate-950 uppercase tracking-wider">PURCHASE TOTAL</span>
                <span class="font-mono font-black text-slate-950 text-sm sm:text-base tabular-nums">Rs. {{ number_format((float) $vendor->total_purchase, 2) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs rounded-xl bg-emerald-50 border border-emerald-200/70 px-2.5 py-1.5 text-emerald-900">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-2xs"></span>
                    <span class="font-black">{{ $paidLabel }}</span>
                </div>
                <span class="font-mono font-black text-emerald-700 tabular-nums">Rs. {{ number_format($paidAmount, 2) }}</span>
            </div>
            @if($creditBalance > 0.009)
                <div class="flex items-center justify-between text-xs rounded-xl bg-amber-50 border border-amber-200/80 text-amber-950 px-2.5 py-1.5">
                    <span class="font-black uppercase tracking-wider">CREDIT BALANCE TO PAY</span>
                    <span class="font-mono font-black text-rose-700 tabular-nums">Rs. {{ number_format($creditBalance, 2) }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Expandable Individual Purchase Bills List (when multiple bills exist) --}}
    @if($bills->count() > 1)
        <div x-show="showBills" x-cloak class="mt-4 pt-3.5 border-t border-slate-200/80 space-y-2.5">
            <div class="flex items-center justify-between text-[11px] font-extrabold text-slate-500 uppercase tracking-wider flex-wrap gap-2">
                <span class="text-slate-700 font-black">Purchase Bills for {{ $vendor->supplier_name }} ({{ $bills->count() }})</span>
                <span class="text-[10px] text-slate-400 font-semibold">Select "Update Payment" to record payment for an individual bill</span>
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-200/90 bg-slate-50/50 shadow-2xs">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/90 text-[10px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2">Bill No</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Purchaser</th>
                            <th class="px-3 py-2 text-right">Purchase Total</th>
                            <th class="px-3 py-2 text-right">Discount</th>
                            <th class="px-3 py-2 text-right">Paid</th>
                            <th class="px-3 py-2 text-right">Balance Due</th>
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 bg-white">
                        @foreach($bills as $b)
                            @php
                                $bDue = (float) ($b->outstanding ?? 0);
                                $bPaid = (float) ($b->paid_amount ?? 0);
                                $bDiscount = (float) ($b->discount ?? 0);
                                $bNet = (float) ($b->total_purchase ?? 0);
                                $bGross = $bNet + $bDiscount;
                                $isBillUnpaid = $bDue > 0.009;
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-3 py-2 font-mono font-bold text-slate-900">
                                    {{ $b->invoice_number ?: ('#'.$b->invoice_id) }}
                                </td>
                                <td class="px-3 py-2 font-semibold text-slate-600 whitespace-nowrap">
                                    {{ $b->business_date ? \Illuminate\Support\Carbon::parse($b->business_date)->format('d M Y') : '—' }}
                                </td>
                                <td class="px-3 py-2 text-slate-700 font-medium">
                                    {{ $b->purchaser_name ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-slate-900 tabular-nums">
                                    Rs. {{ number_format($bGross, 2) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-slate-600 tabular-nums">
                                    Rs. {{ number_format($bDiscount, 2) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-emerald-700 tabular-nums">
                                    Rs. {{ number_format($bPaid, 2) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-black {{ $isBillUnpaid ? 'text-rose-700' : 'text-slate-900' }} tabular-nums">
                                    Rs. {{ number_format($bDue, 2) }}
                                </td>
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                    @if($bDue <= 0.009)
                                        <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[9px] font-black uppercase">PAID</span>
                                    @elseif($bPaid > 0.009)
                                        <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[9px] font-black uppercase">PARTIAL</span>
                                    @else
                                        <span class="rounded-full bg-rose-100 text-rose-800 px-2 py-0.5 text-[9px] font-black uppercase">UNPAID</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @if($isBillUnpaid)
                                        <button type="button"
                                                @click="$dispatch('open-payment-modal', {{ json_encode([
                                                    'id' => $b->invoice_id,
                                                    'invoice_public_uuid' => $b->invoice_public_uuid,
                                                    'invoice_number' => $b->invoice_number ?: ('#'.$b->invoice_id),
                                                    'supplier_name' => $vendor->supplier_name,
                                                    'purchaser_name' => $b->purchaser_name,
                                                    'business_date' => $b->business_date ? \Illuminate\Support\Carbon::parse($b->business_date)->format('d M Y') : '—',
                                                    'gross_amount' => $bGross,
                                                    'discount_amount' => $bDiscount,
                                                    'net_payable' => $bNet,
                                                    'paid_amount' => $bPaid,
                                                    'outstanding' => $bDue,
                                                    'pay_url' => route('admin.cashbook.finance.vendor-credit.pay', $b->invoice_public_uuid),
                                                ]) }})"
                                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 py-1 text-xs font-bold shadow-2xs transition cursor-pointer">
                                            <i data-lucide="credit-card" class="w-3 h-3"></i>
                                            <span>Update Payment</span>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-emerald-700 text-xs font-bold">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                            <span>Settled</span>
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</article>

