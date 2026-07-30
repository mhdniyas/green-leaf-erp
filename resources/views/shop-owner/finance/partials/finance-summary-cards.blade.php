@php
    $isOwnedAccountingShop = $isOwnedAccountingShop ?? false;
    $latestClosingBalance = (float) ($latestClosingBalance ?? 0);
    $carryOver = (float) ($carryOver ?? 0);
@endphp

<div class="grid grid-cols-2 gap-3 {{ $isOwnedAccountingShop ? 'lg:grid-cols-5' : 'lg:grid-cols-3' }}">
    @if ($isOwnedAccountingShop)
        <div class="rounded-[1.35rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Cashbook - Bill</p>
            <p class="mt-2 whitespace-nowrap text-lg font-black text-slate-950 sm:text-xl">Rs. {{ number_format((float) ($latestClosingBalance - $outstandingBalance), 2) }}</p>
        </div>
    @endif
    <div class="rounded-[1.35rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid</p>
        <p class="mt-2 whitespace-nowrap text-lg font-black text-emerald-700 sm:text-xl">Rs. {{ number_format((float) $paidAmount, 2) }}</p>
    </div>
    <div class="rounded-[1.35rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
        <p class="mt-2 whitespace-nowrap text-lg font-black text-red-600 sm:text-xl">Rs. {{ number_format((float) $outstandingBalance, 2) }}</p>
    </div>
    @if ($isOwnedAccountingShop)
        <div class="rounded-[1.35rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Pending Payments</p>
            <p class="mt-2 whitespace-nowrap text-lg font-black text-amber-700 sm:text-xl">Rs. {{ number_format((float) $pendingPaymentAmount, 2) }}</p>
        </div>
    @endif
    <div class="rounded-[1.35rem] border border-slate-200 bg-white px-4 py-4 shadow-sm {{ $isOwnedAccountingShop ? 'col-span-2 lg:col-span-1' : '' }}">
        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Carry Over</p>
        <p class="mt-2 whitespace-nowrap text-lg font-black text-amber-600 sm:text-xl">Rs. {{ number_format((float) $carryOver, 2) }}</p>
    </div>
</div>
