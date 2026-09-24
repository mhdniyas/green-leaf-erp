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
    $hasChildren = ! empty($item['children']);
@endphp

<div x-data="{ open: {{ $active ? 'true' : 'false' }} }" class="space-y-1">
    <div class="flex items-center justify-between group">
        <a
            href="{{ $item['href'] }}"
            @if (! empty($item['target'])) target="{{ $item['target'] }}" @endif
            title="{{ $item['label'] }}"
            @class([
                'flex-1 flex min-h-[44px] items-center gap-3 rounded-2xl px-3.5 py-2.5 text-xs font-extrabold transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500',
                'bg-white text-slate-950 shadow-[0_4px_18px_rgba(15,23,42,0.06)] ring-1 ring-slate-200/90' => $active,
                'text-slate-600 hover:bg-slate-100/90 hover:text-slate-950' => ! $active,
            ])
        >
            @if (! empty($item['icon']))
                <span @class([
                    'shrink-0 transition-colors [&_svg]:h-4.5 [&_svg]:w-4.5',
                    'text-emerald-600' => $active,
                    'text-slate-400 group-hover:text-slate-700' => ! $active,
                ])>{!! $item['icon'] !!}</span>
            @endif

            <span {{ $labelAttributes->class('min-w-0 flex-1 truncate tracking-tight') }}>{{ $item['label'] }}</span>

            @if (filled($badge) && (int) $badge > 0)
                <span {{ $labelAttributes->class("ml-auto inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-black ring-1 {$badgeClasses}") }}>
                    {{ $badge }}
                </span>
            @endif
        </a>

        @if ($hasChildren)
            <button
                type="button"
                @click.stop="open = !open"
                :aria-expanded="open.toString()"
                class="min-h-[44px] min-w-[36px] flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-800 hover:bg-slate-100/90 transition-all duration-200 shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                :class="{ 'text-emerald-600 bg-emerald-50/80': open }"
                aria-label="Toggle {{ $item['label'] }} submenu"
            >
                <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
            {{ $labelAttributes->class('ml-3.5 mt-1 space-y-1 border-l-2 border-slate-200/90 py-1 pl-3') }}
        >
            @foreach ($item['children'] as $child)
                @php
                    $childActive = (bool) ($child['active'] ?? false);
                    $childBadge = $child['badge'] ?? null;
                    $childBadgeTone = $child['badge_tone'] ?? 'warning';
                    $childBadgeClasses = [
                        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                        'warning' => 'bg-orange-100 text-orange-800 ring-orange-200',
                        'danger' => 'bg-rose-100 text-rose-800 ring-rose-200',
                        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200',
                    ][$childBadgeTone] ?? 'bg-orange-100 text-orange-800 ring-orange-200';
                    $hasSubChildren = ! empty($child['children']);
                @endphp
                <div x-data="{ childOpen: {{ $childActive ? 'true' : 'false' }} }" class="space-y-1">
                    <div class="flex items-center justify-between group/child">
                        <a
                            href="{{ $child['href'] }}"
                            @if (! empty($child['target'])) target="{{ $child['target'] }}" @endif
                            @class([
                                'flex-1 flex min-h-[38px] items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-bold transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500',
                                'bg-emerald-50 text-emerald-950 font-black shadow-2xs ring-1 ring-emerald-200/90' => $childActive,
                                'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900' => ! $childActive,
                            ])
                        >
                            <span class="min-w-0 flex-1 truncate">{{ $child['label'] }}</span>

                            @if (filled($childBadge) && (int) $childBadge > 0)
                                <span class="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded-full px-1.5 text-[9px] font-black ring-1 {{ $childBadgeClasses }}">
                                    {{ $childBadge }}
                                </span>
                            @endif
                        </a>

                        @if ($hasSubChildren)
                            <button
                                type="button"
                                @click.stop="childOpen = !childOpen"
                                :aria-expanded="childOpen.toString()"
                                class="min-h-[38px] min-w-[32px] flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-transform duration-200 shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                                :class="{ 'text-emerald-600 bg-emerald-50/80': childOpen }"
                                aria-label="Toggle {{ $child['label'] }} sub-items"
                            >
                                <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-180': childOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    @if ($hasSubChildren)
                        <div
                            x-show="childOpen"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="ml-3 space-y-1 border-l-2 border-slate-200/80 py-1 pl-2.5"
                        >
                            @foreach ($child['children'] as $subChild)
                                @php
                                    $subChildActive = (bool) ($subChild['active'] ?? false);
                                    $subChildBadge = $subChild['badge'] ?? null;
                                    $subChildBadgeTone = $subChild['badge_tone'] ?? 'warning';
                                    $subChildBadgeClasses = [
                                        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                                        'warning' => 'bg-orange-100 text-orange-800 ring-orange-200',
                                        'danger' => 'bg-rose-100 text-rose-800 ring-rose-200',
                                        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200',
                                    ][$subChildBadgeTone] ?? 'bg-orange-100 text-orange-800 ring-orange-200';
                                @endphp
                                <a
                                    href="{{ $subChild['href'] }}"
                                    @if (! empty($subChild['target'])) target="{{ $subChild['target'] }}" @endif
                                    @class([
                                        'flex min-h-[34px] items-center gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500',
                                        'bg-emerald-100/80 text-emerald-950 font-black ring-1 ring-emerald-200/80' => $subChildActive,
                                        'text-slate-500 hover:bg-slate-100 hover:text-slate-900' => ! $subChildActive,
                                    ])
                                >
                                    <span class="min-w-0 flex-1 truncate">{{ $subChild['label'] }}</span>

                                    @if (filled($subChildBadge) && (int) $subChildBadge > 0)
                                        <span class="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded-full px-1.5 text-[9px] font-black ring-1 {{ $subChildBadgeClasses }}">
                                            {{ $subChildBadge }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>


