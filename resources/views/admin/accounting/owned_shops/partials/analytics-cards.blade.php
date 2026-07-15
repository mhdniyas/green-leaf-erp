@php
    $analyticsCards = [
        ['label' => 'Billed', 'value' => (float) $analytics['cards']['total_billed'], 'class' => 'text-slate-950'],
        ['label' => 'Collected', 'value' => (float) $analytics['cards']['total_paid'], 'class' => 'text-emerald-700'],
        ['label' => 'Balance', 'value' => (float) $analytics['cards']['total_balance'], 'class' => 'text-rose-700'],
        ['label' => 'Shop Cash', 'value' => (float) $analytics['cards']['credit'], 'class' => (float) $analytics['cards']['credit'] >= 0 ? 'text-emerald-700' : 'text-rose-700'],
        ['label' => 'Income', 'value' => (float) $analytics['cards']['income'], 'class' => 'text-slate-950'],
        ['label' => 'Expense', 'value' => (float) $analytics['cards']['expense'], 'class' => 'text-slate-950'],
        ['label' => 'Cash Flow', 'value' => (float) $analytics['cards']['cash_flow'], 'class' => 'text-cyan-700'],
        ['label' => 'Petty Cash', 'value' => (float) $pettyCashBalance, 'class' => $pettyCashBalance >= 0 ? 'text-emerald-700' : 'text-rose-700'],
    ];
@endphp

<section id="owned-shop-summary" class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Summary</p>
            <h2 class="mt-2 text-xl font-black text-slate-950">Owned shop analytics</h2>
        </div>
        <p class="text-xs font-bold text-slate-500">{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</p>
    </div>

    <div class="mt-5 grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-3">
        @foreach ($analyticsCards as $card)
            <div class="min-w-0 rounded-[1.15rem] border border-slate-200 bg-slate-50 p-4">
                <p class="truncate text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-3 whitespace-nowrap text-xl font-black tracking-tight {{ $card['class'] }} tabular-nums xl:text-[1.35rem] 2xl:text-2xl">
                    Rs. {{ number_format($card['value'], 2) }}
                </p>
            </div>
        @endforeach
    </div>
</section>
