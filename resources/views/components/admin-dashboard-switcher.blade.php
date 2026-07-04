@php
    $currentUser = auth()->user();
    $navDate = request('date', today()->toDateString());
    $canAccessAdminWorkspace = $currentUser &&
        ($currentUser->hasRole('admin') ||
            $currentUser->can('admin.user.view') ||
            $currentUser->can('admin.daily-progress.view') ||
            $currentUser->can('admin.activity-log.view'));

    $dashboardLinks = [
        [
            'label' => 'Admin',
            'href' => route('admin.overview'),
            'active' => request()->routeIs('admin.overview'),
        ],
        [
            'label' => 'Accounting',
            'href' => route('admin.accounting.index', ['date' => $navDate]),
            'active' => request()->routeIs('admin.accounting.*'),
        ],
        [
            'label' => 'Purchasing',
            'href' => route('purchasing.dashboard'),
            'active' => request()->routeIs('purchasing.*') || request()->routeIs('requisitions.board') || request()->routeIs('requisitions.approved_board'),
        ],
        [
            'label' => 'Inventory',
            'href' => route('inventory.dashboard', ['date' => $navDate]),
            'active' => request()->routeIs('inventory.*'),
        ],
        [
            'label' => 'Staff',
            'href' => route('admin.staff.index', ['date' => $navDate]),
            'active' => request()->routeIs('admin.staff.*'),
        ],
    ];
@endphp

@if ($canAccessAdminWorkspace)
    <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50/80 p-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)]" data-admin-dashboard-switcher>
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="hidden min-w-0 px-2 xl:block">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Admin Workspace</p>
                <p class="mt-1 text-sm font-bold text-slate-600">Switch between operational dashboards</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @foreach ($dashboardLinks as $link)
                    <a
                        href="{{ $link['href'] }}"
                        @class([
                            'inline-flex min-h-11 items-center rounded-[1.2rem] border px-5 py-2.5 text-[11px] font-black uppercase tracking-[0.22em] transition sm:px-6',
                            'border-cyan-200 bg-cyan-50 text-cyan-900 shadow-sm shadow-cyan-100/70' => $link['active'],
                            'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900' => ! $link['active'],
                        ])
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
