@props([
    'label',
    'tone' => 'slate',
])

@php
    $tones = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-700',
        'blue' => 'border-blue-200 bg-blue-50 text-blue-700',
        'cyan' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];
@endphp

<span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $tones[$tone] ?? $tones['slate'] }}">
    {{ $label }}
</span>
