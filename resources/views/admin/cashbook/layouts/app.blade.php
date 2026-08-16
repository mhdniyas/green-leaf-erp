<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cashbook — Green Leaf')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js & Lucide Icons -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        [x-cloak] { display: none !important; }
        .white-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }
        .white-card-hover:hover {
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.08), 0 8px 10px -6px rgba(79, 70, 229, 0.05);
            border-color: #c7d2fe;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .sidebar-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.625rem 0.875rem; border-radius: 0.75rem;
            font-size: 0.8125rem; font-weight: 600; color: #64748b;
            transition: all 0.15s ease-in-out;
        }
        .sidebar-link:hover { color: #0f172a; background-color: #f8fafc; }
        .sidebar-link.active-sidebar {
            color: #0f172a; background-color: #f1f5f9; font-weight: 800;
            border-left: 3px solid #0f172a;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
        }
    </style>
    @stack('styles')
</head>
<body class="h-full w-full overflow-x-hidden font-sans text-slate-800 antialiased selection:bg-brand-500 selection:text-white bg-slate-50 custom-scrollbar">

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-md w-full pointer-events-none"></div>

    <!-- Pure Transparent 3-Dot Bouncing Loader (Uiverse.io by mahendrameghwal) -->
    <div id="global-page-loader" class="hidden fixed inset-0 z-[100] flex items-center justify-center pointer-events-none transition-all duration-200">
        <div class="w-full gap-x-2 flex justify-center items-center">
            <div class="w-5 h-5 bg-[#d991c2] animate-pulse rounded-full animate-bounce"></div>
            <div class="w-5 h-5 bg-[#9869b8] animate-pulse rounded-full animate-bounce [animation-delay:0.2s]"></div>
            <div class="w-5 h-5 bg-[#6756cc] animate-pulse rounded-full animate-bounce [animation-delay:0.4s]"></div>
        </div>
    </div>

    <div id="cashbook-layout-shell" class="min-h-screen flex w-full max-w-full overflow-x-hidden" data-sidebar-state="expanded">
        @include('admin.cashbook.layouts.partials.sidebar')

        <div id="cashbook-main" class="w-full max-w-full overflow-x-hidden flex-1 md:pl-64 flex flex-col min-h-screen transition-[padding] duration-300">
            @include('admin.cashbook.layouts.partials.header')

            @php
                $isMobileSection = request()->routeIs('admin.cashbook.*');
            @endphp

            <main class="flex-1 p-3 sm:p-6 md:p-8 space-y-4 sm:space-y-6 {{ $isMobileSection ? 'pb-28 md:pb-8' : '' }}">
                @yield('content')
            </main>

        </div>
    </div>

    <!-- Base Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) { lucide.createIcons(); }

            document.querySelectorAll('a[href]').forEach(a => {
                a.addEventListener('click', (e) => {
                    const href = a.getAttribute('href');
                    if (href && !href.startsWith('#') && !href.startsWith('javascript:') && !a.hasAttribute('target')) {
                        const loader = document.getElementById('global-page-loader');
                        if (loader) loader.classList.remove('hidden');
                    }
                });
            });

            document.querySelectorAll('form').forEach(f => {
                f.addEventListener('submit', () => {
                    const loader = document.getElementById('global-page-loader');
                    if (loader) loader.classList.remove('hidden');
                });
            });
        });

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            if (sidebar && backdrop) {
                const isHidden = sidebar.classList.contains('-translate-x-full');
                if (isHidden) {
                    sidebar.classList.remove('-translate-x-full');
                    backdrop.classList.remove('hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    backdrop.classList.add('hidden');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const shell = document.getElementById('cashbook-layout-shell');
            const sidebar = document.getElementById('main-sidebar');
            const main = document.getElementById('cashbook-main');
            const collapseButton = document.getElementById('cashbook-sidebar-collapse');
            const labels = document.querySelectorAll('[data-cashbook-sidebar-label]');

            if (!shell || !sidebar || !main || !collapseButton) {
                return;
            }

            const syncState = (state) => {
                const isCollapsed = state === 'collapsed';
                shell.dataset.sidebarState = state;

                if (window.innerWidth >= 1024) {
                    sidebar.classList.toggle('lg:w-64', !isCollapsed);
                    sidebar.classList.toggle('lg:w-24', isCollapsed);
                    main.classList.toggle('lg:pl-64', !isCollapsed);
                    main.classList.toggle('lg:pl-24', isCollapsed);
                    labels.forEach((label) => label.classList.toggle('hidden', isCollapsed));
                    collapseButton.innerHTML = isCollapsed
                        ? '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12l-7.5 7.5" /></svg>'
                        : '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>';
                } else {
                    sidebar.classList.remove('lg:w-24');
                    sidebar.classList.add('lg:w-64');
                    main.classList.remove('lg:pl-24');
                    main.classList.add('lg:pl-64');
                    labels.forEach((label) => label.classList.remove('hidden'));
                }
            };

            const currentState = localStorage.getItem('cashbook-sidebar-state') === 'collapsed' ? 'collapsed' : 'expanded';
            syncState(currentState);

            collapseButton.addEventListener('click', () => {
                const nextState = shell.dataset.sidebarState === 'collapsed' ? 'expanded' : 'collapsed';
                localStorage.setItem('cashbook-sidebar-state', nextState);
                syncState(nextState);
            });

            window.addEventListener('resize', () => {
                syncState(localStorage.getItem('cashbook-sidebar-state') === 'collapsed' ? 'collapsed' : 'expanded');
            });
        });

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-2xl shadow-xl text-xs font-bold flex items-center justify-between pointer-events-auto transition-all transform translate-y-2 border ${
                type === 'error' ? 'bg-rose-50 text-rose-900 border-rose-200 shadow-rose-500/10' :
                type === 'success' ? 'bg-emerald-50 text-emerald-900 border-emerald-200 shadow-emerald-500/10' :
                'bg-white text-slate-900 border-slate-200 shadow-slate-500/10'
            }`;
            toast.innerHTML = `
                <div class="flex items-center gap-2">
                    <i data-lucide="${type === 'error' ? 'alert-circle' : 'check-circle'}" class="w-4 h-4 text-${type === 'error' ? 'rose-600' : 'emerald-600'}"></i>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 ml-4 font-bold">✕</button>
            `;
            container.appendChild(toast);
            lucide.createIcons();
            setTimeout(() => {
                toast.classList.add('opacity-0', '-translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    </script>
    @stack('scripts')

    @if($isMobileSection)
        <!-- Sleek Floating Capsule Pill Bottom Navigation Bar (Matching Reference Image) -->
        <div class="fixed bottom-3 left-3 right-3 z-40 block md:hidden max-w-md mx-auto">
            <div class="bg-white/95 backdrop-blur-md rounded-full border border-slate-200/80 shadow-[0_12px_35px_rgba(0,0,0,0.12)] p-1.5 flex items-center justify-around">
                <a href="{{ route('admin.cashbook.reports.hub', request()->query()) }}" data-nav-hub class="flex items-center gap-1.5 transition-all duration-200 {{ request()->routeIs('admin.cashbook.reports.hub') || request()->routeIs('admin.cashbook.reports.shop') || request()->routeIs('admin.cashbook.reports.mobile-ledger') ? 'bg-slate-900 text-white rounded-full px-3.5 py-1.5 text-xs font-black shadow-xs' : 'text-slate-400 hover:text-slate-700 p-2 text-xs font-bold' }}">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    @if(request()->routeIs('admin.cashbook.reports.hub') || request()->routeIs('admin.cashbook.reports.shop') || request()->routeIs('admin.cashbook.reports.mobile-ledger'))
                        <span>Finance</span>
                    @endif
                </a>

                <a href="{{ route('admin.cashbook.reports.products', request()->query()) }}" data-nav-products class="flex items-center gap-1.5 transition-all duration-200 {{ request()->routeIs('admin.cashbook.reports.products') || request()->routeIs('admin.cashbook.products') ? 'bg-slate-900 text-white rounded-full px-3.5 py-1.5 text-xs font-black shadow-xs' : 'text-slate-400 hover:text-slate-700 p-2 text-xs font-bold' }}">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    @if(request()->routeIs('admin.cashbook.reports.products') || request()->routeIs('admin.cashbook.products'))
                        <span>Products</span>
                    @endif
                </a>

                <a href="{{ route('admin.cashbook.reports.charts', request()->query()) }}" data-nav-charts class="flex items-center gap-1.5 transition-all duration-200 {{ request()->routeIs('admin.cashbook.reports.charts') ? 'bg-slate-900 text-white rounded-full px-3.5 py-1.5 text-xs font-black shadow-xs' : 'text-slate-400 hover:text-slate-700 p-2 text-xs font-bold' }}">
                    <i data-lucide="line-chart" class="w-4 h-4"></i>
                    @if(request()->routeIs('admin.cashbook.reports.charts'))
                        <span>Analytics</span>
                    @endif
                </a>

                <a href="{{ route('admin.cashbook.reports.analytics', request()->query()) }}" data-nav-analytics class="flex items-center gap-1.5 transition-all duration-200 {{ request()->routeIs('admin.cashbook.reports.analytics') ? 'bg-slate-900 text-white rounded-full px-3.5 py-1.5 text-xs font-black shadow-xs' : 'text-slate-400 hover:text-slate-700 p-2 text-xs font-bold' }}">
                    <i data-lucide="target" class="w-4 h-4"></i>
                    @if(request()->routeIs('admin.cashbook.reports.analytics'))
                        <span>Target</span>
                    @endif
                </a>

                <a href="{{ route('admin.cashbook.reports.gl-bills', request()->query()) }}" data-nav-glbills class="flex items-center gap-1.5 transition-all duration-200 {{ request()->routeIs('admin.cashbook.reports.gl-bills') ? 'bg-slate-900 text-white rounded-full px-3.5 py-1.5 text-xs font-black shadow-xs' : 'text-slate-400 hover:text-slate-700 p-2 text-xs font-bold' }}">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                    @if(request()->routeIs('admin.cashbook.reports.gl-bills'))
                        <span>GL Bills</span>
                    @endif
                </a>
            </div>
        </div>
        <script>
            if (window.lucide) { lucide.createIcons(); }
        </script>
    @endif
</body>
</html>
