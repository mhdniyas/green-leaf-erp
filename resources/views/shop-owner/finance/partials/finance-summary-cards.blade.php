<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-[2rem] border border-slate-200 bg-white px-5 py-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Paid Amount</p>
        <p class="mt-3 text-3xl font-black text-emerald-700">Rs. {{ number_format((float) $paidAmount, 2) }}</p>
    </div>
    <div class="rounded-[2rem] border border-slate-200 bg-white px-5 py-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Outstanding Balance</p>
        <p class="mt-3 text-3xl font-black text-red-600">Rs. {{ number_format((float) $outstandingBalance, 2) }}</p>
    </div>
    <div class="rounded-[2rem] border border-slate-200 bg-white px-5 py-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Delivery Deductions</p>
        <p class="mt-3 text-3xl font-black text-amber-600">Rs. {{ number_format((float) $shortageValue, 2) }}</p>
    </div>
</div>
