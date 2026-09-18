@php
    $position = $financialReport['position'] ?? [
        'direction' => 'settled',
        'amount' => 0.0,
        'due_to_company' => 0.0,
        'less_settled' => 0.0,
        'company_reimbursement' => 0.0,
        'current_balance' => 0.0,
    ];
    $direction = $position['direction'] ?? 'settled';
    $amount = (float) ($position['amount'] ?? 0.0);
@endphp

<section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-5" aria-label="Shop Current Position">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full {{ $direction === 'shop_owes_company' ? 'bg-amber-600' : ($direction === 'company_owes_shop' ? 'bg-sky-600' : 'bg-emerald-600') }}"></span>
            Current Position
        </h2>
        <span class="text-xs font-semibold text-slate-500">Authoritative Balance Status</span>
    </div>

    <!-- Main Position Banner -->
    <div class="rounded-2xl p-4 sm:p-6 text-center border {{ $direction === 'shop_owes_company' ? 'border-amber-200 bg-amber-50/70 text-amber-950' : ($direction === 'company_owes_shop' ? 'border-sky-200 bg-sky-50/70 text-sky-950' : 'border-emerald-200 bg-emerald-50/70 text-emerald-950') }}">
        <span class="text-xs font-black uppercase tracking-widest {{ $direction === 'shop_owes_company' ? 'text-amber-800' : ($direction === 'company_owes_shop' ? 'text-sky-800' : 'text-emerald-800') }}">
            @if($direction === 'shop_owes_company')
                Shop Owes Company
            @elseif($direction === 'company_owes_shop')
                Company Owes Shop
            @else
                Settled
            @endif
        </span>

        <p class="mt-2 font-mono text-2xl sm:text-3xl lg:text-4xl font-black tabular-nums tracking-tight leading-tight break-words">
            ₹{{ number_format($amount, 2) }}
        </p>
    </div>

    <!-- Position Breakdown -->
    <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 divide-y divide-slate-200/60 text-xs font-semibold">
        <div class="flex items-center justify-between py-2">
            <span class="text-slate-600">Due to Company</span>
            <span class="font-mono font-bold text-slate-900 tabular-nums">₹{{ number_format((float) ($position['due_to_company'] ?? 0), 2) }}</span>
        </div>

        <div class="flex items-center justify-between py-2">
            <span class="text-slate-600">Less Settled</span>
            <span class="font-mono font-bold text-emerald-700 tabular-nums">− ₹{{ number_format((float) ($position['less_settled'] ?? 0), 2) }}</span>
        </div>

        <div class="flex items-center justify-between py-2">
            <span class="text-slate-600">Company Reimbursement</span>
            <span class="font-mono font-bold text-slate-700 tabular-nums">₹{{ number_format((float) ($position['company_reimbursement'] ?? 0), 2) }}</span>
        </div>

        <div class="flex items-center justify-between pt-2.5">
            <span class="text-xs font-black uppercase text-slate-950">Current Balance</span>
            <span class="font-mono text-xs font-black text-slate-950 tabular-nums">₹{{ number_format((float) ($position['current_balance'] ?? $amount), 2) }}</span>
        </div>
    </div>
</section>
