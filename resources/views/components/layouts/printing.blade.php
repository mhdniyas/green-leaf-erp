@props(['title' => 'Print Dashboard'])

@php
    $currentUser = auth()->user();
    $navDate = request('date', app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->operationalDate()->toDateString());
    $sidebarSections = [];

    if ($currentUser?->can('sort.sheet.view')) {
        $sidebarSections[] = [
            'label' => 'Dashboard Modules',
            'items' => [
                [
                    'label' => 'Sort Sheet',
                    'href' => route('sort-sheet.index', array_filter(['date' => request('date')])),
                    'active' => request()->routeIs('sort-sheet.index') || request()->routeIs('sort-sheet.generate'),
                    'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>',
                ],
                [
                    'label' => 'Selection',
                    'href' => route('segregation.index', array_filter(['date' => request('date')])),
                    'active' => request()->routeIs('segregation.index') || request()->routeIs('segregation.generate'),
                    'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15M8.25 4.5v15m7.5-15v15" /></svg>',
                ],
                [
                    'label' => 'Custom Presets',
                    'href' => route('sort-sheet.presets.index'),
                    'active' => request()->routeIs('sort-sheet.presets.*'),
                    'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>',
                ],
            ],
        ];

        $sidebarSections[] = [
            'label' => 'Print Formats',
            'items' => [
                [
                    'label' => 'Shop Wise Portrait',
                    'href' => route('segregation.shop-wise-portrait', array_filter(['date' => request('date')])),
                    'active' => request()->routeIs('segregation.shop-wise-portrait*'),
                    'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-1.5 3h1.5m-1.5 3h1.5M6.75 4.5h10.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H6.75a1.5 1.5 0 0 1-1.5-1.5V6a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>',
                ],
                [
                    'label' => 'Shop Wise Wide',
                    'href' => route('segregation.shop-wise-wide', array_filter(['date' => request('date')])),
                    'active' => request()->routeIs('segregation.shop-wise-wide*'),
                    'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5h-15a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>',
                ],
                [
                    'label' => 'Segregate Grid',
                    'href' => route('segregation.grid', array_filter(['date' => request('date')])),
                    'active' => request()->routeIs('segregation.grid*'),
                    'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6a2.25 2.25 0 0 1 2.25-2.25h12A2.25 2.25 0 0 1 20.25 6v12a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18V6ZM3.75 9.75h16.5m-16.5 4.5h16.5M9.75 3.75v16.5m4.5-16.5v16.5" /></svg>',
                ],
            ],
        ];
    }

    $mobileItems = [
        ['label' => 'Sort Sheet', 'href' => route('sort-sheet.index', array_filter(['date' => request('date')])), 'active' => request()->routeIs('sort-sheet.index')],
        ['label' => 'Selection', 'href' => route('segregation.index', array_filter(['date' => request('date')])), 'active' => request()->routeIs('segregation.index')],
        ['label' => 'Custom Presets', 'href' => route('sort-sheet.presets.index'), 'active' => request()->routeIs('sort-sheet.presets.*')],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Green Leaf Traders</title>
    <meta name="description" content="Green Leaf Traders — Printing Dashboard">
    <script>
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-full bg-slate-100 font-sans antialiased text-slate-900">
<div id="printing-layout-shell" class="min-h-screen lg:flex" data-sidebar-state="expanded">
    <aside id="printing-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-slate-100 transition-[width,transform] duration-300 lg:translate-x-0">
        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 9V4.5h10.5V9M6 17.25h12A2.25 2.25 0 0 0 20.25 15v-3A2.25 2.25 0 0 0 18 9.75H6A2.25 2.25 0 0 0 3.75 12v3A2.25 2.25 0 0 0 6 17.25Zm1.5 0v2.25h9V17.25" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p data-printing-sidebar-label class="truncate text-base font-black text-slate-950">Printing</p>
                    <p data-printing-sidebar-label class="mt-1 text-[11px] font-black uppercase tracking-[0.2em] text-indigo-700">Operations Desk</p>
                </div>
                <button id="printing-sidebar-collapse" type="button" class="hidden rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:inline-flex" aria-label="Collapse printing sidebar" title="Collapse sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <button id="printing-sidebar-close" class="ml-auto rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p data-printing-sidebar-label class="mt-2 truncate text-sm font-black text-slate-950">{{ $currentUser?->name }}</p>
                <p data-printing-sidebar-label class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $currentUser?->email }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-5">
            @foreach ($sidebarSections as $section)
                <div>
                    <p data-printing-sidebar-label class="mb-2.5 px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                        {{ $section['label'] }}
                    </p>
                    <div class="space-y-1">
                        @foreach ($section['items'] as $item)
                            <x-sidebar-link :item="$item" label-attribute="data-printing-sidebar-label" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-slate-200 px-4 py-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-100">
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    <div id="printing-sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/45 lg:hidden"></div>

    <div id="printing-main" class="flex min-h-screen flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="flex items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <button id="printing-sidebar-open" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <button id="printing-sidebar-toggle" type="button" class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100 lg:inline-flex" aria-label="Toggle printing sidebar" title="Toggle sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.32em] text-slate-400">Green Leaf Traders</p>
                    <h1 class="mt-1 truncate text-2xl font-black tracking-[-0.04em] text-slate-950 sm:text-[2rem]">{{ $title }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden rounded-[1.35rem] border border-slate-200 bg-slate-50/90 px-5 py-3 text-right shadow-sm sm:block">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Today</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ today()->format('d M Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-4 sm:px-6 lg:px-8">
                @include('components.admin-dashboard-switcher')
            </div>
            @isset($actions)
                <div class="border-t border-slate-100 px-4 py-3 sm:px-6 lg:px-8">
                    <div class="flex flex-wrap items-center gap-2">
                        {{ $actions }}
                    </div>
                </div>
            @endisset
        </header>

        <main class="flex-1 px-4 pb-24 pt-5 sm:px-6 lg:px-8 lg:pb-8 lg:pt-6">
            @if (session('success'))
                <div class="mb-4 rounded-[1.35rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-[1.35rem] border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-[1.35rem] border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    {{ $errors->first() }}
                </div>
            @endif

            <x-impersonation-banner />

            {{ $slot }}
        </main>
    </div>
</div>

<x-global-footer />

<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] pt-2 backdrop-blur lg:hidden">
    <nav class="mx-auto grid max-w-xl grid-cols-2 gap-2">
        @foreach ($mobileItems as $item)
            <a href="{{ $item['href'] }}" class="flex min-h-[52px] items-center justify-center rounded-2xl px-2 text-center text-[11px] font-black uppercase tracking-[0.12em] transition {{ $item['active'] ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</div>

@include('components.app-dialogs')

<x-sidebar-state-script
    storage-key="printing-sidebar-state"
    shell-id="printing-layout-shell"
    sidebar-id="printing-sidebar"
    main-id="printing-main"
    overlay-id="printing-sidebar-overlay"
    open-button-id="printing-sidebar-open"
    close-button-id="printing-sidebar-close"
    collapse-button-id="printing-sidebar-collapse"
    toggle-button-id="printing-sidebar-toggle"
    label-selector="[data-printing-sidebar-label]"
/>
@stack('scripts')
<x-global-loader />
</body>
</html>
