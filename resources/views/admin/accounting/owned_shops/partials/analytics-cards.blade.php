@php
    $kpiCards = [
        [
            'label' => 'Billed',
            'value' => (float) ($analytics['cards']['total_billed'] ?? 0),
            'tone' => 'text-slate-950',
            'caption' => 'Period invoices',
        ],
        [
            'label' => 'Collected',
            'value' => (float) ($analytics['cards']['total_paid'] ?? 0),
            'tone' => 'text-emerald-700',
            'caption' => 'Paid against bills',
        ],
        [
            'label' => 'Pending',
            'value' => (float) ($analytics['cards']['total_balance'] ?? 0),
            'tone' => 'text-rose-700',
            'caption' => 'Still due',
        ],
        [
            'label' => 'Closing',
            'value' => (float) ($analytics['cards']['closing_balance'] ?? $pettyCashBalance ?? 0),
            'tone' => ((float) ($analytics['cards']['closing_balance'] ?? $pettyCashBalance ?? 0) >= 0) ? 'text-emerald-700' : 'text-rose-700',
            'caption' => 'Latest receipt balance',
        ],
    ];
@endphp

<section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($kpiCards as $card)
        <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">{{ $card['label'] }}</p>
            <p class="mt-2 text-2xl font-black tracking-tight {{ $card['tone'] }}">Rs. {{ number_format($card['value'], 2) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $card['caption'] }}</p>
        </article>
    @endforeach
</section>
