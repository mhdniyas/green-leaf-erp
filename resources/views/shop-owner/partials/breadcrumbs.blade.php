@php($breadcrumbs = $breadcrumbs ?? [])

@if (! empty($breadcrumbs))
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
        @foreach ($breadcrumbs as $breadcrumb)
            @if (! $loop->first)
                <span>/</span>
            @endif

            @if (isset($breadcrumb['url']))
                <a href="{{ $breadcrumb['url'] }}" class="font-semibold text-slate-600 hover:text-slate-900">{{ $breadcrumb['label'] }}</a>
            @else
                <span class="font-semibold text-slate-900">{{ $breadcrumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
