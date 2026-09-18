@php
    $summary = $financialReport['summary'] ?? [
        'sales' => 0.0,
        'expenses' => 0.0,
        'net_balance' => 0.0,
        'net_balance_percentage' => null,
    ];
    $settlement = $financialReport['settlement'] ?? [
        'due' => 0.0,
        'received' => 0.0,
        'allocated' => 0.0,
        'pending' => 0.0,
    ];
    $posDue = (float) ($settlement['due'] ?? 0.0);
    $posAllocated = (float) ($settlement['allocated'] ?? 0.0);
    $posRemaining = round(max(0, $posDue - $posAllocated), 2);
    $totalSales = (float) ($summary['sales'] ?? 0.0);

    $posStatus = match (true) {
        abs($posDue - $posAllocated) < 0.01 => 'fully_settled',
        $posDue > $posAllocated => 'shop_owes_company',
        default => 'company_owes_shop',
    };

    $statusLabel = match ($posStatus) {
        'fully_settled' => 'FULLY SETTLED',
        'shop_owes_company' => 'SHOP OWES COMPANY',
        'company_owes_shop' => 'COMPANY OWES SHOP',
    };

    $netBalance = (float) ($summary['net_balance'] ?? ($summary['sales'] - $summary['expenses']));
    $netBalancePercentage = $summary['net_balance_percentage'] ?? ($summary['sales'] > 0 ? round(($netBalance / $summary['sales']) * 100, 1) : null);
    $isNetPositive = $netBalance >= 0;

    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;
    $settlementDetailsUrl = route('admin.cashbook.shop.settlement-details', array_filter([
        'shop' => $currentShopSlugOrId,
        'month' => $month ?? request('month'),
        'period_mode' => $periodMode ?? request('period_mode') ?? request('view'),
        'date' => request('date') ?: request('day') ?: ($periodMode === 'day' ? ($periodStart ?? null) : null),
        'from' => request('from') ?: request('custom_from') ?: ($periodMode === 'custom' ? ($periodStart ?? null) : null),
        'to' => request('to') ?: request('custom_to') ?: ($periodMode === 'custom' ? ($periodEnd ?? null) : null),
    ]));
@endphp

<section class="grid grid-cols-1 lg:grid-cols-2 gap-4" aria-label="Top Balance After Allocation and Net Operating Balance">
    <!-- CARD 1: BALANCE AFTER ALLOCATION (AUTHORITATIVE POSITION) -->
    <div class="rounded-3xl border {{ $posStatus === 'shop_owes_company' ? 'border-amber-200/90 bg-amber-50/60' : ($posStatus === 'company_owes_shop' ? 'border-sky-200/90 bg-sky-50/60' : 'border-emerald-200/90 bg-emerald-50/60') }} p-4 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <span class="text-xs font-black uppercase tracking-widest {{ $posStatus === 'shop_owes_company' ? 'text-amber-800' : ($posStatus === 'company_owes_shop' ? 'text-sky-800' : 'text-emerald-800') }}">
                Balance After Allocation
            </span>
            <span class="inline-flex items-center rounded-md {{ $posStatus === 'shop_owes_company' ? 'bg-amber-100/90 text-amber-900' : ($posStatus === 'company_owes_shop' ? 'bg-sky-100/90 text-sky-900' : 'bg-emerald-100/90 text-emerald-900') }} px-2 py-0.5 text-[10px] font-mono font-bold">
                Period Due: ₹{{ number_format($posDue, 2) }}
            </span>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-3">
            <div>
                <p class="font-mono text-2xl sm:text-3xl lg:text-4xl font-black {{ $posStatus === 'shop_owes_company' ? 'text-amber-950' : ($posStatus === 'company_owes_shop' ? 'text-sky-950' : 'text-emerald-950') }} tabular-nums tracking-tight leading-tight break-words">
                    ₹{{ number_format($posRemaining, 2) }}
                </p>
                <p class="mt-1 text-xs font-black uppercase tracking-wider {{ $posStatus === 'shop_owes_company' ? 'text-amber-800' : ($posStatus === 'company_owes_shop' ? 'text-sky-800' : 'text-emerald-800') }}">
                    {{ $statusLabel }}
                </p>
            </div>

            <a href="{{ $settlementDetailsUrl }}"
               class="inline-flex items-center justify-center gap-1 text-xs font-black text-slate-800 hover:text-emerald-700 bg-white/80 hover:bg-white border border-slate-200/80 rounded-xl px-3 py-1.5 shadow-2xs transition w-fit">
                <span>View Details</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        <!-- Breakdown Grid -->
        <div class="pt-3 border-t border-slate-200/60 grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-medium {{ $posStatus === 'shop_owes_company' ? 'text-amber-900' : ($posStatus === 'company_owes_shop' ? 'text-sky-900' : 'text-emerald-900') }}">
            <div>
                <span class="block text-[10px] uppercase font-bold text-slate-500">Period Due</span>
                <span class="font-mono font-bold tabular-nums">₹{{ number_format($posDue, 2) }}</span>
            </div>
            <div>
                <span class="block text-[10px] uppercase font-bold text-slate-500">Allocated</span>
                <span class="font-mono font-bold text-emerald-700 tabular-nums">₹{{ number_format($posAllocated, 2) }}</span>
            </div>
            <div>
                <span class="block text-[10px] uppercase font-bold text-slate-500">Remaining</span>
                <span class="font-mono font-bold {{ $posRemaining > 0 ? 'text-amber-800' : 'text-slate-700' }} tabular-nums">₹{{ number_format($posRemaining, 2) }}</span>
            </div>
            <div>
                <span class="block text-[10px] uppercase font-bold text-slate-500">Total Sales</span>
                <span class="font-mono font-bold text-slate-900 tabular-nums">₹{{ number_format($totalSales, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- CARD 2: NET OPERATING BALANCE -->
    <div class="rounded-3xl border {{ $isNetPositive ? 'border-emerald-200/90 bg-emerald-50/60' : 'border-rose-200/90 bg-rose-50/60' }} p-4 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <span class="text-xs font-black uppercase tracking-widest {{ $isNetPositive ? 'text-emerald-800' : 'text-rose-800' }}">
                Net Operating Balance
            </span>
            @if($netBalancePercentage !== null)
                <span class="inline-flex items-center rounded-md {{ $isNetPositive ? 'bg-emerald-100/90 text-emerald-900' : 'bg-rose-100/90 text-rose-900' }} px-2 py-0.5 text-[10px] font-mono font-bold">
                    {{ $netBalancePercentage }}% of Sales
                </span>
            @endif
        </div>

        <div>
            <p class="font-mono text-2xl sm:text-3xl lg:text-4xl font-black {{ $isNetPositive ? 'text-emerald-950' : 'text-rose-950' }} tabular-nums tracking-tight leading-tight break-words">
                {{ $netBalance < 0 ? '-' : '' }}₹{{ number_format(abs($netBalance), 2) }}
            </p>
            <p class="mt-1 text-xs font-black uppercase tracking-wider {{ $isNetPositive ? 'text-emerald-800' : 'text-rose-800' }}">
                {{ $isNetPositive ? 'Operating Surplus' : 'Operating Deficit' }}
            </p>
        </div>

        <div class="pt-3 border-t border-slate-200/60 flex items-center justify-between flex-wrap gap-2 text-[11px] font-medium {{ $isNetPositive ? 'text-emerald-700' : 'text-rose-700' }}">
            <span>Sales: <strong class="font-mono font-bold">₹{{ number_format((float) $summary['sales'], 2) }}</strong></span>
            <span>Expenses: <strong class="font-mono font-bold">₹{{ number_format((float) $summary['expenses'], 2) }}</strong></span>
        </div>
    </div>
</section>

