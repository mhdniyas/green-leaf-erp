@php
    $origBilled = (float) ($analytics['cards']['original_invoice_total'] ?? $analytics['cards']['total_billed'] ?? 0);
    $finalBilled = (float) ($analytics['cards']['final_bill_total'] ?? $analytics['cards']['total_billed'] ?? 0);
    $creditsAdj = (float) ($analytics['cards']['approved_credit_adjustments'] ?? 0);
    $debitsAdj = (float) ($analytics['cards']['approved_debit_adjustments'] ?? 0);
    $hasAdj = ($creditsAdj > 0.0001 || $debitsAdj > 0.0001);
    $overpayment = (float) ($analytics['cards']['overpayment_credit'] ?? 0);

    $kpiCards = [
        [
            'label' => $hasAdj ? 'Original Billed' : 'Billed',
            'value' => $origBilled,
            'tone' => 'text-slate-950',
            'caption' => $hasAdj ? 'Original baseline' : 'Period invoices',
        ],
    ];

    if ($hasAdj) {
        $netAdj = round($debitsAdj - $creditsAdj, 2);
        $kpiCards[] = [
            'label' => 'Adjustments',
            'value' => $netAdj,
            'tone' => $netAdj < 0 ? 'text-emerald-700' : 'text-amber-700',
            'caption' => 'Credits -' . number_format($creditsAdj, 2) . ' / Debits +' . number_format($debitsAdj, 2),
            'formatted' => ($netAdj < 0 ? '-' : '+') . 'Rs. ' . number_format(abs($netAdj), 2),
        ];
        $kpiCards[] = [
            'label' => 'Final Bill',
            'value' => $finalBilled,
            'tone' => 'text-indigo-950',
            'caption' => 'Net payable baseline',
        ];
    }

    $kpiCards[] = [
        'label' => 'Collected',
        'value' => (float) ($analytics['cards']['total_paid'] ?? 0),
        'tone' => 'text-emerald-700',
        'caption' => 'Paid against bills',
    ];

    $kpiCards[] = [
        'label' => 'Pending',
        'value' => (float) ($analytics['cards']['total_balance'] ?? 0),
        'tone' => 'text-rose-700',
        'caption' => 'Still due',
    ];

    $kpiCards[] = [
        'label' => 'Closing',
        'value' => (float) ($analytics['cards']['closing_balance'] ?? $pettyCashBalance ?? 0),
        'tone' => ((float) ($analytics['cards']['closing_balance'] ?? $pettyCashBalance ?? 0) >= 0) ? 'text-emerald-700' : 'text-rose-700',
        'caption' => 'Latest receipt balance',
    ];
@endphp

<section class="space-y-3">
    <div class="grid gap-3 sm:grid-cols-2 {{ count($kpiCards) > 4 ? 'lg:grid-cols-3 xl:grid-cols-6' : 'xl:grid-cols-4' }}">
        @foreach ($kpiCards as $card)
            <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-black tracking-tight {{ $card['tone'] }}">
                    {{ $card['formatted'] ?? ('Rs. ' . number_format($card['value'], 2)) }}
                </p>
                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $card['caption'] }}</p>
            </article>
        @endforeach
    </div>

    @if ($overpayment > 0.0001)
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 px-4 py-2.5 flex items-center justify-between text-xs text-indigo-950 font-bold">
            <div class="flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-indigo-600"></i>
                <span>Overpayment Credit Available:</span>
            </div>
            <span class="font-black text-indigo-700 text-sm">Rs. {{ number_format($overpayment, 2) }}</span>
        </div>
    @endif
</section>
