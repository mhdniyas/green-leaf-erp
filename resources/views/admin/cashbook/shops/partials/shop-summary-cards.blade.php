@php
    $summary = $financialReport['summary'] ?? [
        'sales' => 0.0,
        'expenses' => 0.0,
        'gl_bills' => 0.0,
        'salary' => 0.0,
        'petty_used' => 0.0,
        'company_payable' => 0.0,
    ];
    $settlement = $financialReport['settlement'] ?? [
        'due' => 0.0,
        'received' => 0.0,
        'allocated' => 0.0,
        'pending' => 0.0,
    ];
    $position = $financialReport['position'] ?? [
        'direction' => 'settled',
        'amount' => 0.0,
        'due_to_company' => 0.0,
        'less_settled' => 0.0,
        'current_balance' => 0.0,
    ];
    $posDirection = $position['direction'] ?? 'settled';
    $posAmount = (float) ($position['amount'] ?? 0.0);
    $posDue = (float) ($position['due_to_company'] ?? ($settlement['due'] ?? 0.0));
    $posPaid = (float) ($position['less_settled'] ?? ($settlement['received'] ?? 0.0));
@endphp

<section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" aria-label="Shop Financial Summary">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-900"></span>
            Shop Summary
        </h2>
        <span class="text-xs font-semibold text-slate-500">Configured Period Values</span>
    </div>

    <!-- 6 Primary Numbers Grid: 1 col mobile, 2 cols sm/tablet, 3 cols desktop -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- 1. SALES -->
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4 sm:p-5 transition hover:bg-emerald-50/70 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-800">Sales</span>
                </div>
                <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-emerald-950 tabular-nums tracking-tight leading-tight break-words">
                    ₹{{ number_format((float) $summary['sales'], 2) }}
                </p>
                <p class="mt-1 text-xs font-medium text-emerald-700">Total Configured Sales</p>
            </div>
        </div>

        <!-- 2. EXPENSES -->
        <div class="rounded-2xl border border-rose-100 bg-rose-50/40 p-4 sm:p-5 transition hover:bg-rose-50/70 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-rose-800">Expenses</span>
                    @if(isset($summary['expenses_percentage']) && $summary['expenses_percentage'] !== null)
                        <span class="inline-flex items-center rounded-md bg-rose-100/90 px-2 py-0.5 text-[10px] font-mono font-bold text-rose-800">
                            {{ $summary['expenses_percentage'] }}% of Sales
                        </span>
                    @endif
                </div>
                <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-rose-950 tabular-nums tracking-tight leading-tight break-words">
                    ₹{{ number_format((float) $summary['expenses'], 2) }}
                </p>
                <p class="mt-1 text-xs font-medium text-rose-700">Total Configured Expenses</p>
            </div>
        </div>

        <!-- 3. GL BILLS -->
        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4 sm:p-5 transition hover:bg-indigo-50/70 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-indigo-800">GL Bills</span>
                    @if(isset($summary['gl_bills_percentage']) && $summary['gl_bills_percentage'] !== null)
                        <span class="inline-flex items-center rounded-md bg-indigo-100/90 px-2 py-0.5 text-[10px] font-mono font-bold text-indigo-800">
                            {{ $summary['gl_bills_percentage'] }}% of Sales
                        </span>
                    @endif
                </div>
                <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-indigo-950 tabular-nums tracking-tight leading-tight break-words">
                    ₹{{ number_format((float) $summary['gl_bills'], 2) }}
                </p>
                <p class="mt-1 text-xs font-medium text-indigo-700">Green Leaf Purchases</p>
            </div>
        </div>

        <!-- 4. SALARY -->
        <div class="rounded-2xl border border-amber-100 bg-amber-50/40 p-4 sm:p-5 transition hover:bg-amber-50/70 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-amber-800">Salary</span>
                    @if(isset($summary['salary_percentage']) && $summary['salary_percentage'] !== null)
                        <span class="inline-flex items-center rounded-md bg-amber-100/90 px-2 py-0.5 text-[10px] font-mono font-bold text-amber-800">
                            {{ $summary['salary_percentage'] }}% of Sales
                        </span>
                    @endif
                </div>
                <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-amber-950 tabular-nums tracking-tight leading-tight break-words">
                    ₹{{ number_format((float) $summary['salary'], 2) }}
                </p>
                <p class="mt-1 text-xs font-medium text-amber-700">Staff Wages &amp; Salary</p>
            </div>
        </div>

        <!-- 5. PETTY USED (Not % of Sales, signed if non-zero) -->
        <div class="rounded-2xl border border-purple-100 bg-purple-50/40 p-4 sm:p-5 transition hover:bg-purple-50/70 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-purple-800">Petty Used</span>
                </div>
                <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-purple-950 tabular-nums tracking-tight leading-tight break-words">
                    @if(!empty($summary['petty_used_formatted']))
                        {{ $summary['petty_used_formatted'] }}
                    @elseif((float) ($summary['petty_used'] ?? 0) > 0.0001)
                        -₹{{ number_format((float) $summary['petty_used'], 2) }}
                    @elseif((float) ($summary['petty_used'] ?? 0) < -0.0001)
                        +₹{{ number_format(abs((float) $summary['petty_used']), 2) }}
                    @else
                        ₹0.00
                    @endif
                </p>
                <p class="mt-1 text-xs font-medium text-purple-700">Expenses From Petty</p>
            </div>
        </div>

        <!-- 6. PAYABLE -->
        <div class="rounded-2xl border border-blue-100 bg-blue-50/40 p-4 sm:p-5 transition hover:bg-blue-50/70 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-blue-800">Payable</span>
                    @if(isset($summary['payable_percentage']) && $summary['payable_percentage'] !== null)
                        <span class="inline-flex items-center rounded-md bg-blue-100/90 px-2 py-0.5 text-[10px] font-mono font-bold text-blue-800">
                            {{ $summary['payable_percentage'] }}% of Sales
                        </span>
                    @endif
                </div>
                <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-blue-950 tabular-nums tracking-tight leading-tight break-words">
                    ₹{{ number_format((float) $summary['company_payable'], 2) }}
                </p>
                <p class="mt-1 text-xs font-medium text-blue-700">Remaining Settlement Due</p>
            </div>

            <div class="mt-3 pt-2.5 border-t border-blue-200/60 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 text-xs">
                <span class="text-[11px] font-semibold text-blue-900">Total Paid:</span>
                <span class="font-mono font-bold text-emerald-800 text-xs sm:text-sm tabular-nums tracking-tight">₹{{ number_format((float) ($settlement['received'] ?? $settlement['allocated'] ?? 0), 2) }}</span>
            </div>
        </div>
    </div>

    @if(isset($vendorPurchaseSummary) && (($vendorPurchaseSummary['total_purchase'] ?? 0) > 0 || ($vendorPurchaseSummary['invoice_count'] ?? 0) > 0))
        <div class="pt-4 border-t border-slate-100 rounded-2xl bg-amber-50/50 border border-amber-200/80 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-amber-900">Vendor Purchases</span>
                    <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-mono font-bold text-amber-900">
                        {{ $vendorPurchaseSummary['invoice_count'] }} Invoices
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-4 flex-wrap font-mono text-sm">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500">Total:</span>
                        <strong class="text-amber-950 font-bold ml-1">₹{{ number_format((float) $vendorPurchaseSummary['total_purchase'], 2) }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500">Cash:</span>
                        <span class="text-emerald-800 font-semibold ml-1">₹{{ number_format((float) $vendorPurchaseSummary['cash_purchase'], 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500">Credit:</span>
                        <span class="text-rose-800 font-semibold ml-1">₹{{ number_format((float) $vendorPurchaseSummary['credit_purchase'], 2) }}</span>
                    </div>
                </div>
            </div>

            <div>
                <a href="{{ route('admin.cashbook.shop.purchases.vendors', [($currentShop->slug ?: $currentShop->shop_id)]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-amber-300 bg-white hover:bg-amber-100/60 text-amber-900 text-xs font-bold shadow-2xs transition">
                    <span>View Vendor Purchases</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    @endif
</section>
