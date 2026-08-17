@php
    $displayPaid = (float) ($monthlyPaidAmount ?? $paidAmount ?? 0);
    $displayBalanceToPay = (float) ($monthlyBalanceToPay ?? $payableBalance ?? $outstandingBalance ?? 0);
    $currentMonthName = now()->format('F Y');
@endphp

<div class="grid grid-cols-2 gap-2 sm:gap-3">
    {{-- Card 1: Paid (Monthly Base) --}}
    <div class="rounded-xl border border-emerald-200 bg-white p-2.5 sm:p-3 shadow-2xs min-w-0">
        <div class="flex items-center justify-between gap-1">
            <div class="min-w-0">
                <p class="text-[8px] sm:text-[10px] font-black uppercase tracking-tight text-emerald-800 truncate">Paid</p>
                <p class="text-[7px] sm:text-[8px] font-bold text-slate-400 truncate">{{ $currentMonthName }}</p>
            </div>
            <span class="rounded-full bg-emerald-100 p-1 text-emerald-700 shrink-0">
                <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            </span>
        </div>
        <p class="mt-1 whitespace-nowrap truncate text-xs sm:text-sm font-black text-emerald-700 font-mono">
            Rs. {{ number_format($displayPaid, 2) }}
        </p>
    </div>

    {{-- Card 2: Balance to Pay (Monthly Base) --}}
    <div class="rounded-xl border border-rose-200 bg-white p-2.5 sm:p-3 shadow-2xs min-w-0">
        <div class="flex items-center justify-between gap-1">
            <div class="min-w-0">
                <p class="text-[8px] sm:text-[10px] font-black uppercase tracking-tight text-rose-800 truncate">Balance to Pay</p>
                <p class="text-[7px] sm:text-[8px] font-bold text-slate-400 truncate">{{ $currentMonthName }}</p>
            </div>
            <span class="rounded-full bg-rose-100 p-1 text-rose-700 shrink-0">
                <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </span>
        </div>
        <p class="mt-1 whitespace-nowrap truncate text-xs sm:text-sm font-black text-rose-700 font-mono">
            Rs. {{ number_format($displayBalanceToPay, 2) }}
        </p>
    </div>
</div>
