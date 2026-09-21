@props(['title' => 'Purchaser V2', 'date' => null, 'grade' => 'A'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GL Purchaser">
    <title>{{ $title }} — Green Leaf Purchaser</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        /* iOS safe area support */
        .pb-safe {
            padding-bottom: env(safe-area-inset-bottom, 1rem);
        }
        .pt-safe {
            padding-top: env(safe-area-inset-top, 0.5rem);
        }
    </style>
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased selection:bg-emerald-500 selection:text-white">
    <div class="flex flex-1 min-h-screen">
        <!-- Desktop Sidebar (Hidden on mobile) -->
        <aside class="hidden md:flex w-64 flex-col border-r border-slate-200/80 bg-white">
            <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-6">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 font-black text-white shadow-md shadow-emerald-600/20">
                    GL
                </div>
                <div>
                    <span class="text-sm font-black tracking-tight text-slate-900 flex items-center gap-1.5">
                        PURCHASER
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold text-emerald-800">V2</span>
                    </span>
                    <p class="text-[11px] font-medium text-slate-500">Green Leaf ERP</p>
                </div>
            </div>

            <div class="flex flex-1 flex-col justify-between p-4">
                <nav class="space-y-1.5">
                    <!-- Demand -->
                    <a href="{{ route('purchaser-v2.daily', ['date' => $date, 'purchase_grade' => $grade]) }}"
                       class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('purchaser-v2.daily') || request()->routeIs('purchaser-v2.buy') || request()->routeIs('purchaser-v2.dashboard') || request()->routeIs('purchaser-v2.index') ? 'bg-emerald-50 text-emerald-700 font-extrabold shadow-sm border border-emerald-200/60' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        <svg class="h-4 w-4 {{ request()->routeIs('purchaser-v2.daily') || request()->routeIs('purchaser-v2.buy') || request()->routeIs('purchaser-v2.dashboard') ? 'text-emerald-600' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        Daily Demand
                    </a>

                    <!-- Draft Carts -->
                    <a href="{{ route('purchaser-v2.cart.index', ['date' => $date, 'grade' => $grade]) }}"
                       class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('purchaser-v2.cart.*') ? 'bg-emerald-50 text-emerald-700 font-extrabold shadow-sm border border-emerald-200/60' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        <svg class="h-4 w-4 {{ request()->routeIs('purchaser-v2.cart.*') ? 'text-emerald-600' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Draft Carts
                    </a>

                    <!-- Daily Report -->
                    <a href="{{ route('purchaser-v2.report', ['date' => $date, 'grade' => $grade]) }}"
                       class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('purchaser-v2.report') ? 'bg-emerald-50 text-emerald-700 font-extrabold shadow-sm border border-emerald-200/60' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        <svg class="h-4 w-4 {{ request()->routeIs('purchaser-v2.report') ? 'text-emerald-600' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Daily Report
                    </a>
                </nav>

                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('purchaser.dashboard') }}"
                       class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 border border-slate-200/60">
                        <span>Legacy Purchaser Flow</span>
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>

                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Business Date</p>
                        <p class="mt-0.5 text-xs font-extrabold text-emerald-700">{{ $date ?? date('Y-m-d') }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="flex flex-1 flex-col min-w-0 pb-28 md:pb-8">
            <!-- Mobile & Desktop Top App Bar -->
            <header class="sticky top-0 z-30 flex h-14 md:h-16 items-center justify-between border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur-md md:px-8 pt-safe">
                <div class="flex items-center gap-2.5 min-w-0">
                    <!-- Mobile GL Logo -->
                    <div class="flex md:hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600 font-black text-xs text-white shadow-sm shadow-emerald-600/30">
                        GL
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-sm font-extrabold text-slate-900 md:text-base">{{ $title }}</h1>
                        <p class="md:hidden text-[10px] font-medium text-slate-500 truncate">{{ $date ?? date('Y-m-d') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Grade Switcher (iOS style segmented toggle) -->
                    <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold border border-slate-200/60">
                        <a href="{{ request()->fullUrlWithQuery(['grade' => 'A']) }}"
                           class="rounded-lg px-2.5 py-1 transition {{ $grade === 'A' ? 'bg-white text-emerald-700 shadow-sm font-extrabold' : 'text-slate-500 hover:text-slate-900' }}">
                            Grade A
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['grade' => 'B']) }}"
                           class="rounded-lg px-2.5 py-1 transition {{ $grade === 'B' ? 'bg-white text-amber-700 shadow-sm font-extrabold' : 'text-slate-500 hover:text-slate-900' }}">
                            Grade B
                        </a>
                    </div>

                    <!-- User Avatar -->
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-black text-emerald-800">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'P', 0, 1)) }}
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-3.5 sm:p-5 md:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    <!-- Sleek Floating Capsule Pill Bottom Navigation Bar (Mobile) - 40% Opacity Glassmorphism -->
    <div class="md:hidden fixed bottom-3 inset-x-4 sm:inset-x-12 z-40 max-w-sm mx-auto pointer-events-none pb-safe">
        <nav class="pointer-events-auto bg-white/40 hover:bg-white/90 focus-within:bg-white/95 backdrop-blur-md rounded-full border border-white/60 shadow-[0_10px_35px_rgba(0,0,0,0.08)] p-1.5 flex items-center justify-around transition-all duration-300">
            
            <!-- 1. Demand Tab -->
            <a href="{{ route('purchaser-v2.daily', ['date' => $date, 'purchase_grade' => $grade]) }}"
               class="flex flex-col items-center justify-center py-1.5 px-4 rounded-full transition-all active:scale-90 text-center min-w-[72px] {{ request()->routeIs('purchaser-v2.daily') || request()->routeIs('purchaser-v2.buy') || request()->routeIs('purchaser-v2.dashboard') || request()->routeIs('purchaser-v2.index') ? 'bg-white/90 text-emerald-700 shadow-sm border border-emerald-100 font-black' : 'text-slate-600 hover:text-slate-900 font-semibold' }}">
                <svg class="h-4 w-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ request()->routeIs('purchaser-v2.daily') || request()->routeIs('purchaser-v2.buy') || request()->routeIs('purchaser-v2.dashboard') ? '2.5' : '2' }}" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span class="text-[10px] tracking-tight leading-none">Demand</span>
            </a>

            <!-- 2. Cart Tab -->
            <a href="{{ route('purchaser-v2.cart.index', ['date' => $date, 'grade' => $grade]) }}"
               class="flex flex-col items-center justify-center py-1.5 px-4 rounded-full transition-all active:scale-90 text-center min-w-[72px] {{ request()->routeIs('purchaser-v2.cart.*') ? 'bg-white/90 text-emerald-700 shadow-sm border border-emerald-100 font-black' : 'text-slate-600 hover:text-slate-900 font-semibold' }}">
                <svg class="h-4 w-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ request()->routeIs('purchaser-v2.cart.*') ? '2.5' : '2' }}" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-[10px] tracking-tight leading-none">Cart</span>
            </a>

            <!-- 3. Daily Report Tab -->
            <a href="{{ route('purchaser-v2.report', ['date' => $date, 'grade' => $grade]) }}"
               class="flex flex-col items-center justify-center py-1.5 px-4 rounded-full transition-all active:scale-90 text-center min-w-[72px] {{ request()->routeIs('purchaser-v2.report') ? 'bg-white/90 text-emerald-700 shadow-sm border border-emerald-100 font-black' : 'text-slate-600 hover:text-slate-900 font-semibold' }}">
                <svg class="h-4 w-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ request()->routeIs('purchaser-v2.report') ? '2.5' : '2' }}" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span class="text-[10px] tracking-tight leading-none">Report</span>
            </a>

        </nav>
    </div>
</body>
</html>
