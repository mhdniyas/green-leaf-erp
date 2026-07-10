@props(['title' => 'Staff Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Green Leaf Traders</title>
    <meta name="description" content="Green Leaf Traders — Staff Management Dashboard">
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-full bg-slate-100 font-sans antialiased text-slate-900">
@php
    $currentUser = auth()->user();
    $navDate = request('date', today()->toDateString());
    $canViewStaffDashboard = \App\Support\StaffAccess::canViewDashboard($currentUser);
    $canViewStaffEmployees = \App\Support\StaffAccess::canViewEmployees($currentUser);
    $canViewStaffAttendance = \App\Support\StaffAccess::canViewAttendance($currentUser);
    $canViewStaffLeaves = \App\Support\StaffAccess::canViewLeaves($currentUser);
    $canViewStaffPayroll = \App\Support\StaffAccess::canViewPayroll($currentUser);
    $canViewStaffCategories = \App\Support\StaffAccess::canViewCategories($currentUser);
    $canAccessAdminOverview = $currentUser &&
        ($currentUser->hasRole('admin') ||
            $currentUser->can('admin.user.view') ||
            $currentUser->can('admin.daily-progress.view') ||
            $currentUser->can('admin.activity-log.view'));
    $sidebarItems = [];

    if ($canViewStaffDashboard) {
        $sidebarItems[] = [
            'label' => 'Dashboard',
            'href' => route('admin.staff.index', ['date' => $navDate]),
            'active' => request()->routeIs('admin.staff.index'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v7.5h-7.5v-7.5Zm-9 9h7.5v7.5h-7.5v-7.5Zm9 3h7.5v4.5h-7.5v-4.5Z" /></svg>',
        ];
    }

    if ($canViewStaffEmployees) {
        $sidebarItems[] = [
            'label' => 'Employees',
            'href' => route('admin.staff.employees.index', ['date' => $navDate]),
            'active' => request()->routeIs('admin.staff.employees.index') || request()->routeIs('admin.staff.show'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a8.97 8.97 0 0 0 3.74-1.04 4.5 4.5 0 0 0-7.48-2.23m3.74 3.27v.28A10.94 10.94 0 0 1 12 21c-2.33 0-4.5-.73-6.28-1.98v-.29m12.56 0a5.97 5.97 0 0 0-12.56 0M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 2.25a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>',
        ];
    }

    if ($canViewStaffAttendance) {
        $sidebarItems[] = [
            'label' => 'Attendance',
            'href' => route('admin.staff.attendance', ['date' => $navDate]),
            'active' => request()->routeIs('admin.staff.attendance'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 9.75h6M9 14.25h3m-6.75 6h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12A2.25 2.25 0 0 0 5.25 20.25Z" /></svg>',
        ];
    }

    if ($canViewStaffLeaves) {
        $sidebarItems[] = [
            'label' => 'Leave Queue',
            'href' => route('admin.staff.leaves.index'),
            'active' => request()->routeIs('admin.staff.leaves.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-8.25A2.25 2.25 0 0 0 17.25 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12A2.25 2.25 0 0 0 6.75 20.25h5.379a3 3 0 0 1 2.121-.879h3a3 3 0 0 1 2.25 1.014V14.25ZM8.25 7.5h7.5m-7.5 4.5h4.5" /></svg>',
        ];
    }

    if ($canViewStaffPayroll) {
        $sidebarItems[] = [
            'label' => 'Payroll',
            'href' => route('admin.staff.payroll.index'),
            'active' => request()->routeIs('admin.staff.payroll.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3" /></svg>',
        ];
    }

    if ($canViewStaffCategories) {
        $sidebarItems[] = [
            'label' => 'Categories',
            'href' => route('admin.staff.categories.index'),
            'active' => request()->routeIs('admin.staff.categories.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15" /></svg>',
        ];
    }

    $mobileItems = [];

    if ($canViewStaffDashboard) {
        $mobileItems[] = ['label' => 'Home', 'href' => route('admin.staff.index', ['date' => $navDate]), 'active' => request()->routeIs('admin.staff.index')];
    }

    if ($canViewStaffEmployees) {
        $mobileItems[] = ['label' => 'Staff', 'href' => route('admin.staff.employees.index', ['date' => $navDate]), 'active' => request()->routeIs('admin.staff.employees.index') || request()->routeIs('admin.staff.show')];
    }

    if ($canViewStaffAttendance) {
        $mobileItems[] = ['label' => 'Attend', 'href' => route('admin.staff.attendance', ['date' => $navDate]), 'active' => request()->routeIs('admin.staff.attendance')];
    }

    if ($canViewStaffLeaves) {
        $mobileItems[] = ['label' => 'Leave', 'href' => route('admin.staff.leaves.index'), 'active' => request()->routeIs('admin.staff.leaves.*')];
    }

    if ($canViewStaffPayroll) {
        $mobileItems[] = ['label' => 'Payroll', 'href' => route('admin.staff.payroll.index'), 'active' => request()->routeIs('admin.staff.payroll.*')];
    }

    $mobileItems = array_slice($mobileItems, 0, 4);
@endphp

<div class="min-h-screen lg:flex">
    <aside id="staff-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[width,transform] duration-300 lg:translate-x-0 lg:w-72">
        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-cyan-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a8.97 8.97 0 0 0 3.74-1.04 4.5 4.5 0 0 0-7.48-2.23m3.74 3.27v.28A10.94 10.94 0 0 1 12 21c-2.33 0-4.5-.73-6.28-1.98v-.29m12.56 0a5.97 5.97 0 0 0-12.56 0M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 2.25a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </div>
                <div class="min-w-0" data-sidebar-label>
                    <p class="truncate text-base font-black text-slate-950">Staff Management</p>
                    <p class="mt-1 text-[11px] font-black uppercase tracking-[0.2em] text-cyan-700">Admin Desk</p>
                </div>
                <button id="staff-sidebar-close" class="ml-auto rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if ($canAccessAdminOverview)
                <a href="{{ route('admin.overview') }}" class="mt-5 flex items-center justify-between rounded-[1.35rem] bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800" data-sidebar-link>
                    <span data-sidebar-label>Admin Panel</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5H19.5V10.5M10.5 13.5 19.5 4.5M18 13.5V19.5H4.5V6H10.5" />
                    </svg>
                </a>
            @endif

            <div class="mt-4 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3" data-sidebar-full-only>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p class="mt-2 truncate text-sm font-black text-slate-950">{{ $currentUser?->name }}</p>
                <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $currentUser?->email }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-5">
            @foreach($sidebarItems as $item)
                <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-[1.2rem] px-4 py-3 text-sm font-black transition {{ $item['active'] ? 'bg-cyan-50 text-cyan-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}" data-sidebar-link>
                    <span class="{{ $item['active'] ? 'text-cyan-700' : 'text-slate-400' }}">{!! $item['icon'] !!}</span>
                    <span data-sidebar-label>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-t border-slate-200 px-4 py-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-100" data-sidebar-link>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    <span data-sidebar-label>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <div id="staff-sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/45 lg:hidden"></div>

    <div id="staff-content-shell" class="flex min-h-screen flex-1 flex-col transition-[padding] duration-300 lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="flex items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <button id="staff-sidebar-open" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <button id="staff-sidebar-desktop-toggle" type="button" class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100 lg:inline-flex" title="Toggle Sidebar">
                    <svg id="staff-sidebar-desktop-toggle-icon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    <span class="sr-only">Toggle Sidebar</span>
                </button>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.32em] text-slate-400">Green Leaf Traders</p>
                    <h1 class="mt-1 truncate text-2xl font-black tracking-[-0.04em] text-slate-950 sm:text-[2rem]">{{ $title }}</h1>
                </div>

                <div class="hidden rounded-[1.35rem] border border-slate-200 bg-slate-50/90 px-5 py-3 text-right shadow-sm sm:block">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Today</p>
                    <p class="mt-1 text-sm font-black text-slate-950">{{ today()->format('d M Y') }}</p>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-4 sm:px-6 lg:px-8">
                @include('components.admin-dashboard-switcher')
            </div>
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

            {{ $slot }}
        </main>
    </div>
</div>

<x-global-footer />

<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] pt-2 backdrop-blur lg:hidden">
    <nav class="mx-auto grid max-w-xl grid-cols-4 gap-2">
        @foreach($mobileItems as $item)
            <a href="{{ $item['href'] }}" class="flex min-h-[52px] items-center justify-center rounded-2xl px-2 text-center text-[11px] font-black uppercase tracking-[0.12em] transition {{ $item['active'] ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</div>

@include('components.app-dialogs')

<script>
    (() => {
        const sidebar = document.getElementById('staff-sidebar');
        const overlay = document.getElementById('staff-sidebar-overlay');
        const openButton = document.getElementById('staff-sidebar-open');
        const closeButton = document.getElementById('staff-sidebar-close');
        const desktopToggleButton = document.getElementById('staff-sidebar-desktop-toggle');
        const desktopToggleIcon = document.getElementById('staff-sidebar-desktop-toggle-icon');
        const contentShell = document.getElementById('staff-content-shell');
        const labelTargets = document.querySelectorAll('[data-sidebar-label]');
        const fullOnlyTargets = document.querySelectorAll('[data-sidebar-full-only]');
        const linkTargets = document.querySelectorAll('[data-sidebar-link]');
        const desktopQuery = window.matchMedia('(min-width: 1024px)');

        if (!sidebar || !overlay || !openButton || !closeButton || !desktopToggleButton || !desktopToggleIcon || !contentShell) {
            return;
        }

        const collapsedStorageKey = 'staff_sidebar_collapsed';

        const openSidebar = () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        };

        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        };

        const setDesktopSidebarState = (collapsed) => {
            const isDesktop = desktopQuery.matches;
            const shouldCollapse = isDesktop && collapsed;

            sidebar.classList.toggle('lg:w-24', shouldCollapse);
            sidebar.classList.toggle('lg:w-72', !shouldCollapse);
            contentShell.classList.toggle('lg:pl-24', shouldCollapse);
            contentShell.classList.toggle('lg:pl-72', !shouldCollapse);

            labelTargets.forEach((element) => {
                element.classList.toggle('hidden', shouldCollapse);
            });

            fullOnlyTargets.forEach((element) => {
                element.classList.toggle('hidden', shouldCollapse);
            });

            linkTargets.forEach((element) => {
                element.classList.toggle('justify-center', shouldCollapse);
            });

            desktopToggleButton.setAttribute('title', shouldCollapse ? 'Expand Sidebar' : 'Collapse Sidebar');
            desktopToggleIcon.innerHTML = shouldCollapse
                ? '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12l-7.5 7.5" />'
                : '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />';
        };

        const applyDesktopPreference = () => {
            setDesktopSidebarState(localStorage.getItem(collapsedStorageKey) === '1');
        };

        openButton.addEventListener('click', openSidebar);
        closeButton.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
        desktopToggleButton.addEventListener('click', () => {
            const nextState = localStorage.getItem(collapsedStorageKey) !== '1';

            localStorage.setItem(collapsedStorageKey, nextState ? '1' : '0');
            setDesktopSidebarState(nextState);
        });
        desktopQuery.addEventListener('change', applyDesktopPreference);

        applyDesktopPreference();
    })();
</script>
@stack('scripts')
</body>
</html>
