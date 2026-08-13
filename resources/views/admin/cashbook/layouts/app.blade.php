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
<body class="h-full font-sans text-slate-800 antialiased selection:bg-brand-500 selection:text-white bg-slate-50 custom-scrollbar">

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-md w-full pointer-events-none"></div>

    <div class="min-h-screen flex">
        @include('admin.cashbook.layouts.partials.sidebar')

        <div class="flex-1 md:pl-64 flex flex-col min-h-screen">
            @include('admin.cashbook.layouts.partials.header')

            <main class="flex-1 p-3 sm:p-6 md:p-8 space-y-6 sm:space-y-8">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Base Scripts -->
    <script>
        lucide.createIcons();

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
</body>
</html>
