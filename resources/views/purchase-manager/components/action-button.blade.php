@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
])

@php
    $classes = [
        'primary' => 'bg-slate-900 text-white hover:bg-slate-700',
        'secondary' => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        'soft-danger' => 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-bold transition', $classes[$variant] ?? $classes['primary']]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class(['inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-bold transition', $classes[$variant] ?? $classes['primary']]) }}>
        {{ $slot }}
    </button>
@endif
