@php
    $flashClasses = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];
@endphp

@foreach ($flashClasses as $key => $classes)
    @if (session($key))
        <div class="mb-4 rounded-3xl border px-4 py-3 text-sm font-semibold {{ $classes }}">
            {{ session($key) }}
        </div>
    @endif
@endforeach
