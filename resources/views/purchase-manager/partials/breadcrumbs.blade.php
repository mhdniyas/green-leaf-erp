@php
    $segments = collect(request()->segments())->filter(fn (string $segment) => $segment !== 'purchase-manager')->values();
@endphp

@if ($segments->isNotEmpty())
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
        <span>Purchase</span>
        @foreach ($segments as $segment)
            <span>/</span>
            <span>{{ str($segment)->replace('-', ' ')->title() }}</span>
        @endforeach
    </nav>
@endif
