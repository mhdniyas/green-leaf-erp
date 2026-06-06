@if (($as ?? 'link') === 'button')
    <button type="{{ $type ?? 'button' }}" {!! $attributes ?? '' !!} class="inline-flex rounded-xl px-4 py-2.5 text-sm font-bold {{ $classes ?? 'bg-slate-900 text-white' }}">
        {{ $label }}
    </button>
@else
    <a href="{{ $href }}" {!! $attributes ?? '' !!} class="inline-flex rounded-xl px-4 py-2.5 text-sm font-bold {{ $classes ?? 'bg-slate-900 text-white' }}">
        {{ $label }}
    </a>
@endif
