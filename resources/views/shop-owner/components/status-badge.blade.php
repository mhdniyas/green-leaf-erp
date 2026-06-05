@php
    $toneClasses = [
        'neutral' => 'bg-slate-100 text-slate-700 border-slate-200',
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
        'danger' => 'bg-red-50 text-red-700 border-red-200',
        'info' => 'bg-blue-50 text-blue-700 border-blue-200',
    ];
@endphp

<span class="inline-flex rounded-full border px-3 py-1 text-xs font-black uppercase tracking-[0.16em] {{ $toneClasses[$tone ?? 'neutral'] }}">
    {{ $label }}
</span>
