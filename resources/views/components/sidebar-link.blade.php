@props([
    'item',
    'labelAttribute' => 'data-sidebar-label',
    'activeTone' => 'neutral',
])

@php
    $labelAttributes = new \Illuminate\View\ComponentAttributeBag([$labelAttribute => '']);
    $active = (bool) ($item['active'] ?? false);
    $badge = $item['badge'] ?? null;
    $badgeTone = $item['badge_tone'] ?? 'warning';
    $badgeClasses = [
        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'warning' => 'bg-orange-100 text-orange-800 ring-orange-200',
        'danger' => 'bg-rose-100 text-rose-800 ring-rose-200',
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200',
    ][$badgeTone] ?? 'bg-orange-100 text-orange-800 ring-orange-200';
@endphp

<div>
    <a
        href="{{ $item['href'] }}"
        title="{{ $item['label'] }}"
        @class([
            'group flex min-h-12 items-center gap-3 rounded-2xl px-4 py-3 text-sm font-black transition-all',
            'bg-white text-slate-950 shadow-[0_10px_24px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/80' => $active,
            'text-slate-500 hover:bg-white/70 hover:text-slate-950 hover:shadow-sm' => ! $active,
        ])
    >
        @if (! empty($item['icon']))
            <span @class([
                'shrink-0 transition-colors [&_svg]:h-5 [&_svg]:w-5',
                'text-slate-900' => $active,
                'text-slate-400 group-hover:text-slate-700' => ! $active,
            ])>{!! $item['icon'] !!}</span>
        @endif

        <span {{ $labelAttributes->class('min-w-0 flex-1 truncate') }}>{{ $item['label'] }}</span>

        @if (filled($badge) && (int) $badge > 0)
            <span {{ $labelAttributes->class("ml-auto inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full px-2 text-xs font-black ring-1 {$badgeClasses}") }}>
                {{ $badge }}
            </span>
        @endif
    </a>

    @if (! empty($item['children']) && $active)
        <div {{ $labelAttributes->class('ml-6 mt-1 space-y-1 border-l border-slate-200 py-1 pl-4') }}>
            @foreach ($item['children'] as $child)
                @php
                    $childBadge = $child['badge'] ?? null;
                    $childBadgeTone = $child['badge_tone'] ?? 'warning';
                    $childBadgeClasses = [
                        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                        'warning' => 'bg-orange-100 text-orange-800 ring-orange-200',
                        'danger' => 'bg-rose-100 text-rose-800 ring-rose-200',
                        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200',
                    ][$childBadgeTone] ?? 'bg-orange-100 text-orange-800 ring-orange-200';
                @endphp
                <a
                    href="{{ $child['href'] }}"
                    @class([
                        'flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-black transition-all',
                        'bg-white text-slate-950 shadow-sm ring-1 ring-slate-200/80' => $child['active'] ?? false,
                        'text-slate-500 hover:bg-white/70 hover:text-slate-950' => ! ($child['active'] ?? false),
                    ])
                >
                    <span class="min-w-0 flex-1 truncate">{{ $child['label'] }}</span>

                    @if (filled($childBadge) && (int) $childBadge > 0)
                        <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-black ring-1 {{ $childBadgeClasses }}">
                            {{ $childBadge }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
