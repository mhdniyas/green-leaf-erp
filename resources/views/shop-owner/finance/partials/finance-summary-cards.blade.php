@php
    $isOwnedAccountingShop = $isOwnedAccountingShop ?? false;
    $latestClosingBalance = (float) ($latestClosingBalance ?? 0);
    $carryOver = (float) ($carryOver ?? 0);
    $totalBilled = (float) ($totalBilled ?? 0);
    $paidAmount = (float) ($paidAmount ?? 0);
    $outstandingBalance = (float) ($outstandingBalance ?? 0);
    $pendingPaymentAmount = (float) ($pendingPaymentAmount ?? 0);
    $availableCredit = (float) ($availableInvoicePaymentCredit ?? 0);
@endphp

<div class="grid grid-cols-3 gap-2">
    @if ($isOwnedAccountingShop)
        {{-- Row 1, Card 1: Cashbook - Bill --}}
        <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
            <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Cashbook - Bill</p>
            <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-slate-950 sm:text-base">
                Rs. {{ number_format($latestClosingBalance - $outstandingBalance, 2) }}
            </p>
        </div>
    @else
        {{-- Row 1, Card 1: Total Billed --}}
        <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
            <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Total Billed</p>
            <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-slate-950 sm:text-base">
                Rs. {{ number_format($totalBilled, 2) }}
            </p>
        </div>
    @endif

    {{-- Row 1, Card 2: Paid --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Paid</p>
        <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-emerald-700 sm:text-base">
            Rs. {{ number_format($paidAmount, 2) }}
        </p>
    </div>

    {{-- Row 1, Card 3: Balance --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Balance</p>
        <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-red-600 sm:text-base">
            Rs. {{ number_format($outstandingBalance, 2) }}
        </p>
    </div>

    {{-- Row 2, Card 4: Pending Payments --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Pending Payments</p>
        <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-amber-700 sm:text-base">
            Rs. {{ number_format($pendingPaymentAmount, 2) }}
        </p>
    </div>

    {{-- Row 2, Card 5: Carry Over --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Carry Over</p>
        <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-amber-600 sm:text-base">
            Rs. {{ number_format($carryOver, 2) }}
        </p>
    </div>

    {{-- Row 2, Card 6: Total Billed / Credit --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        @if ($isOwnedAccountingShop)
            <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Total Billed</p>
            <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-slate-950 sm:text-base">
                Rs. {{ number_format($totalBilled, 2) }}
            </p>
        @else
            <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Credit</p>
            <p class="mt-1 whitespace-nowrap truncate text-[11px] font-black text-cyan-700 sm:text-base">
                Rs. {{ number_format($availableCredit, 2) }}
            </p>
        @endif
    </div>
</div>
