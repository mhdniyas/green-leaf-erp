@php
    $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $bdAssets = app()->runningUnitTests()
        ? ['resources/css/app.css', 'resources/js/app.js']
        : ['resources/css/purchase-manager/app.css', 'resources/js/purchase-manager/app.js'];

    $currentUser = auth()->user();
    // Get the currently open or reopened business day (for nav context)
    $activeDay = \App\Models\PurchaseBusinessDay::query()
        ->whereIn('status', [\App\Models\PurchaseBusinessDay::STATUS_OPEN, \App\Models\PurchaseBusinessDay::STATUS_REOPENED])
        ->latest('opened_at')
        ->first();

    // Resolve context from route parameters for nav links
    $routeDay = request()->route('businessDay') ?? request()->route('uuid');
    $navDayUuid = $routeDay instanceof \App\Models\BusinessDay
        ? $routeDay->uuid
        : (is_string($routeDay) ? $routeDay : $activeDay?->uuid);

    // 3-tab navigation — Today / Bills / History
    $bdNav = [
        [
            'key'    => 'today',
            'label'  => 'Today',
            'href'   => $navDayUuid
                ? route('purchasing.business-days.show', $navDayUuid)
                : route('purchasing.business-days.index'),
            'active' => request()->routeIs('purchasing.business-days.show'),
            'icon'   => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>',
        ],
        [
            'key'    => 'bills',
            'label'  => 'Bills',
            'href'   => $navDayUuid
                ? route('purchasing.business-days.bills.create', $navDayUuid)
                : route('purchasing.business-days.index'),
            'active' => request()->routeIs('purchasing.business-days.bills.*')
                || request()->routeIs('purchasing.business-days.close.*'),
            'icon'   => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
        ],
        [
            'key'    => 'history',
            'label'  => 'History',
            'href'   => route('purchasing.business-days.index', ['view' => 'history']),
            'active' => request()->routeIs('purchasing.business-days.index'),
            'icon'   => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-google-tag />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title', 'Business Day')) }} — Green Leaf</title>
    <meta name="description" content="Green Leaf — Purchaser Business Day">
    <script>
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    </script>
    @vite($bdAssets)
    @stack('styles')
</head>
<body class="min-h-full bg-slate-100 font-sans antialiased text-slate-900">

<div class="min-h-screen w-full overflow-x-hidden lg:flex">

    {{-- ── DESKTOP SIDEBAR ─────────────────────────────────────────── --}}
    <aside class="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-44 lg:shrink-0 lg:flex-col lg:border-r lg:border-slate-200 lg:bg-white">

        {{-- Brand header --}}
        <div class="border-b border-slate-200 px-4 py-4">
            <div class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-xs font-black text-slate-950">Business Day</p>
                    <p class="mt-0.5 text-[9px] font-black uppercase tracking-[0.18em] text-teal-700">Green Leaf</p>
                </div>
            </div>

            {{-- Active day context pill --}}
            @if ($activeDay)
                <div class="mt-3 rounded-xl border border-teal-100 bg-teal-50 px-3 py-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.12em] text-teal-600 leading-none">Active Day</p>
                    <p class="mt-1 text-xs font-black text-teal-900">{{ $activeDay->business_date->format('d M Y') }}</p>
                    <span class="mt-0.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $activeDay->isOpen() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $activeDay->status }}
                    </span>
                </div>
            @else
                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-400 leading-none">No Active Day</p>
                    <p class="mt-1 text-[10px] font-semibold text-slate-500">Open a day to start.</p>
                </div>
            @endif
        </div>

        {{-- Nav items --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-2.5 py-3">
            @foreach ($bdNav as $item)
                <a href="{{ $item['href'] }}"
                   class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-xs font-black transition
                          {{ $item['active']
                              ? 'bg-teal-600 text-white shadow-sm'
                              : 'text-slate-700 hover:bg-slate-100' }}">
                    {!! $item['icon'] !!}
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Footer links --}}
        <div class="border-t border-slate-200 px-2.5 py-3 space-y-1.5">
            <a href="{{ route('purchasing.dashboard') }}"
               class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-black text-slate-700 transition hover:bg-slate-100">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Purchasing
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-black text-slate-600 transition hover:bg-slate-50">
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- ── MAIN CONTENT ─────────────────────────────────────────────── --}}
    <div class="flex flex-1 min-w-0 min-h-screen flex-col overflow-x-hidden">

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="flex items-center gap-2.5 px-3 py-2.5 sm:px-5">
                {{-- Mobile brand icon --}}
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white lg:hidden">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-[9px] font-black uppercase tracking-[0.24em] text-slate-400 leading-none">Green Leaf · Business Day</p>
                    <h1 class="mt-0.5 truncate text-base font-black tracking-[-0.02em] text-slate-950">
                        {{ trim($__env->yieldContent('title', 'Business Day')) }}
                    </h1>
                </div>

                @if ($activeDay)
                    <div class="hidden shrink-0 rounded-xl border border-teal-100 bg-teal-50 px-3 py-1.5 text-right sm:block">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-teal-600 leading-none">Active</p>
                        <p class="mt-0.5 text-xs font-black text-teal-900">{{ $activeDay->business_date->format('d M Y') }}</p>
                    </div>
                @endif
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 min-w-0 w-full max-w-full overflow-x-hidden px-3 pb-24 pt-3 sm:px-4 lg:pb-6 lg:pt-4">
            @if (session('success'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if ($viewErrors->any())
                <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    {{ $viewErrors->first() }}
                </div>
            @endif

            <x-impersonation-banner />

            @yield('content')
        </main>
    </div>
</div>

{{-- ── MOBILE BOTTOM NAV ─────────────────────────────────────────────── --}}
<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/80 bg-white/95 px-3 py-2 pb-[max(env(safe-area-inset-bottom),8px)] backdrop-blur-md lg:hidden">
    <nav class="mx-auto grid max-w-sm grid-cols-3 gap-2">
        @foreach ($bdNav as $item)
            <a href="{{ $item['href'] }}"
               class="flex items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-xs font-black transition-all duration-150 {{ $item['active'] ? 'bg-teal-600 text-white shadow-md shadow-teal-600/20' : 'bg-slate-100/80 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900' }}">
                {!! $item['icon'] !!}
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>


@include('components.app-dialogs')
@stack('scripts')
<x-global-loader />
</body>
</html>
