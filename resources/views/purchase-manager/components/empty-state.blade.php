@props([
    'title',
    'description',
    'actionHref' => null,
    'actionLabel' => null,
])

<div class="rounded-[2rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
    <h3 class="text-base font-black text-slate-900">{{ $title }}</h3>
    <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
    @if ($actionHref && $actionLabel)
        <a href="{{ $actionHref }}" class="mt-5 inline-flex rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">
            {{ $actionLabel }}
        </a>
    @endif
</div>
