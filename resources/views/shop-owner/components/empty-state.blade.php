<div class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
    <h3 class="text-lg font-black text-slate-900">{{ $title }}</h3>
    <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
    @isset($actionLabel)
        <a href="{{ $actionUrl }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">{{ $actionLabel }}</a>
    @endisset
</div>
