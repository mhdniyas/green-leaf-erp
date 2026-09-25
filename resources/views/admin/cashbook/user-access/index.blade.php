<x-layouts.admin title="Cashbook User Access">
    <div class="mx-auto max-w-7xl space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 shadow-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left Panel: User Search & Selection -->
            <div class="lg:col-span-4 space-y-4">
                <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 p-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Cashbook Security</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Select User</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Pick any user to configure Cashbook page permissions.</p>

                        <form method="GET" action="{{ route('admin.cashbook.user-access.index') }}" class="mt-4">
                            <div class="relative">
                                <input
                                    type="search"
                                    name="search"
                                    value="{{ $search }}"
                                    placeholder="Search name or email..."
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100"
                                >
                            </div>
                        </form>
                    </div>

                    <div class="divide-y divide-slate-100 max-h-[600px] overflow-y-auto">
                        @forelse ($users as $user)
                            @php
                                $isSelected = $selectedUser && $selectedUser->id === $user->id;
                                $userMode = \App\Support\CashbookAccess::userAccessMode($user);
                            @endphp
                            <a
                                href="{{ route('admin.cashbook.user-access.index', ['user_id' => $user->id, 'search' => $search]) }}"
                                class="flex items-center justify-between p-4 transition hover:bg-slate-50 {{ $isSelected ? 'bg-emerald-50/70 border-l-4 border-emerald-600' : '' }}"
                            >
                                <div class="min-w-0 pr-3">
                                    <p class="truncate text-sm font-black {{ $isSelected ? 'text-emerald-950' : 'text-slate-900' }}">{{ $user->name }}</p>
                                    <p class="truncate text-xs font-semibold text-slate-500">{{ $user->email }}</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @foreach ($user->roles as $role)
                                            <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                                {{ $role->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    @if ($userMode === 'full_access')
                                        <span class="inline-flex rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800">Full</span>
                                    @elseif ($userMode === 'read_only')
                                        <span class="inline-flex rounded-lg bg-blue-100 px-2 py-0.5 text-[10px] font-black uppercase text-blue-800">Read</span>
                                    @elseif ($userMode === 'custom')
                                        <span class="inline-flex rounded-lg bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-800">Custom</span>
                                    @else
                                        <span class="inline-flex rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">None</span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="p-6 text-center text-xs font-semibold text-slate-500">
                                No users found matching your search.
                            </div>
                        @endforelse
                    </div>

                    @if ($users->hasPages())
                        <div class="border-t border-slate-100 p-3">
                            {{ $users->links() }}
                        </div>
                    @endif
                </section>
            </div>

            <!-- Right Panel: Permissions & Matrix Configuration -->
            <div class="lg:col-span-8 space-y-6">
                @if ($selectedUser)
                    <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                        <!-- User Header -->
                        <div class="border-b border-slate-100 p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Managing Permissions</p>
                                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $selectedUser->name }}</h1>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $selectedUser->email }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-right">
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">System Role</p>
                                        <p class="text-sm font-black text-slate-900">
                                            {{ $selectedUser->roles->pluck('name')->join(', ') ?: 'No Role' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Access Form -->
                        <form method="POST" action="{{ route('admin.cashbook.user-access.update', $selectedUser) }}" id="cashbook-access-form" class="p-6 space-y-6">
                            @csrf
                            <input type="hidden" name="search" value="{{ $search }}">

                            <!-- Mode Selector -->
                            <div>
                                <label class="block text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-3">
                                    Access Preset Mode
                                </label>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <label class="mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition {{ $accessMode === 'no_access' ? 'border-rose-500 bg-rose-50/50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black text-slate-900">No Access</span>
                                            <input type="radio" name="mode" value="no_access" {{ $accessMode === 'no_access' ? 'checked' : '' }} class="text-rose-600 focus:ring-rose-500">
                                        </div>
                                        <span class="mt-2 text-[11px] font-semibold text-slate-500">Blocked from Cashbook</span>
                                    </label>

                                    <label class="mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition {{ $accessMode === 'read_only' ? 'border-blue-500 bg-blue-50/50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black text-slate-900">Read Only</span>
                                            <input type="radio" name="mode" value="read_only" {{ $accessMode === 'read_only' ? 'checked' : '' }} class="text-blue-600 focus:ring-blue-500">
                                        </div>
                                        <span class="mt-2 text-[11px] font-semibold text-slate-500">View & export only (no edits)</span>
                                    </label>

                                    <label class="mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition {{ $accessMode === 'full_access' ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black text-slate-900">Full Access</span>
                                            <input type="radio" name="mode" value="full_access" {{ $accessMode === 'full_access' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                                        </div>
                                        <span class="mt-2 text-[11px] font-semibold text-slate-500">All modules View + Manage</span>
                                    </label>

                                    <label class="mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition {{ $accessMode === 'custom' ? 'border-amber-500 bg-amber-50/50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black text-slate-900">Custom</span>
                                            <input type="radio" name="mode" value="custom" {{ $accessMode === 'custom' ? 'checked' : '' }} class="text-amber-600 focus:ring-amber-500">
                                        </div>
                                        <span class="mt-2 text-[11px] font-semibold text-slate-500">Select page by page</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Permission Matrix Table -->
                            <div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-3">
                                    <label class="block text-xs font-black uppercase tracking-[0.18em] text-slate-400">
                                        Page / Module Permission Matrix
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" id="btn-select-all-view" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-700 transition hover:bg-slate-100">
                                            Select All View
                                        </button>
                                        <button type="button" id="btn-select-all-manage" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-700 transition hover:bg-slate-100">
                                            Select All Manage
                                        </button>
                                        <button type="button" id="btn-clear-all" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-rose-600 transition hover:bg-rose-50">
                                            Clear All
                                        </button>
                                    </div>
                                </div>

                                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                                                <th class="py-3.5 px-5">Page / Section</th>
                                                <th class="py-3.5 px-5 text-center w-28">View</th>
                                                <th class="py-3.5 px-5 text-center w-28">Manage</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-sm">
                                            @foreach ($sections as $key => $section)
                                                @php
                                                    $hasView = $section['view_permission'] && in_array($section['view_permission'], $userCashbookPermissions, true);
                                                    $hasManage = $section['manage_permission'] && in_array($section['manage_permission'], $userCashbookPermissions, true);
                                                @endphp
                                                <tr class="hover:bg-slate-50/80 transition">
                                                    <td class="py-3 px-5">
                                                        <p class="font-black text-slate-900">{{ $section['label'] }}</p>
                                                        <p class="text-xs font-semibold text-slate-500">{{ $section['description'] }}</p>
                                                    </td>
                                                    <td class="py-3 px-5 text-center">
                                                        @if ($section['view_permission'])
                                                            <input
                                                                type="checkbox"
                                                                name="permissions[]"
                                                                value="{{ $section['view_permission'] }}"
                                                                data-perm-type="view"
                                                                data-section="{{ $key }}"
                                                                {{ $hasView ? 'checked' : '' }}
                                                                class="perm-checkbox perm-view h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                            >
                                                        @else
                                                            <span class="text-slate-300 font-bold">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-3 px-5 text-center">
                                                        @if ($section['manage_permission'])
                                                            <input
                                                                type="checkbox"
                                                                name="permissions[]"
                                                                value="{{ $section['manage_permission'] }}"
                                                                data-perm-type="manage"
                                                                data-section="{{ $key }}"
                                                                {{ $hasManage ? 'checked' : '' }}
                                                                class="perm-checkbox perm-manage h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                            >
                                                        @else
                                                            <span class="text-slate-300 font-bold">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="flex items-center justify-between border-t border-slate-100 pt-5">
                                <p class="text-xs font-semibold text-slate-500">
                                    <span class="text-slate-900 font-bold">Safety Note:</span> Only Cashbook permissions are modified. Unrelated ERP permissions remain unchanged.
                                </p>
                                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-6 py-3 text-xs font-black uppercase tracking-[0.16em] text-white shadow-sm transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                    Save Access
                                </button>
                            </div>
                        </form>
                    </section>
                @else
                    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-3xl border border-slate-200 bg-slate-50 text-slate-400">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-black text-slate-900">No User Selected</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Select a user from the list on the left to configure their Cashbook access.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modeRadios = document.querySelectorAll('input[name="mode"]');
                const viewCheckboxes = document.querySelectorAll('.perm-view');
                const manageCheckboxes = document.querySelectorAll('.perm-manage');
                const allCheckboxes = document.querySelectorAll('.perm-checkbox');
                const modeCards = document.querySelectorAll('.mode-card');

                function getSelectedMode() {
                    const checked = document.querySelector('input[name="mode"]:checked');
                    return checked ? checked.value : 'custom';
                }

                function updateModeStyles() {
                    const currentMode = getSelectedMode();
                    modeCards.forEach(card => {
                        const radio = card.querySelector('input[type="radio"]');
                        if (radio.value === currentMode) {
                            if (currentMode === 'no_access') {
                                card.className = 'mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition border-rose-500 bg-rose-50/50';
                            } else if (currentMode === 'read_only') {
                                card.className = 'mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition border-blue-500 bg-blue-50/50';
                            } else if (currentMode === 'full_access') {
                                card.className = 'mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition border-emerald-500 bg-emerald-50/50';
                            } else {
                                card.className = 'mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition border-amber-500 bg-amber-50/50';
                            }
                        } else {
                            card.className = 'mode-card flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition border-slate-200 bg-white hover:border-slate-300';
                        }
                    });

                    if (currentMode === 'read_only') {
                        manageCheckboxes.forEach(cb => {
                            cb.checked = false;
                            cb.disabled = true;
                        });
                        viewCheckboxes.forEach(cb => cb.disabled = false);
                    } else if (currentMode === 'no_access') {
                        allCheckboxes.forEach(cb => {
                            cb.checked = false;
                            cb.disabled = false;
                        });
                    } else {
                        allCheckboxes.forEach(cb => cb.disabled = false);
                    }
                }

                modeRadios.forEach(radio => {
                    radio.addEventListener('change', () => {
                        const mode = radio.value;
                        if (mode === 'no_access') {
                            allCheckboxes.forEach(cb => cb.checked = false);
                        } else if (mode === 'read_only') {
                            // Keep all views checked, uncheck manages
                            viewCheckboxes.forEach(cb => cb.checked = true);
                            manageCheckboxes.forEach(cb => cb.checked = false);
                        } else if (mode === 'full_access') {
                            viewCheckboxes.forEach(cb => cb.checked = true);
                            manageCheckboxes.forEach(cb => {
                                if (cb.dataset.section !== 'user-access') {
                                    cb.checked = true;
                                }
                            });
                        }
                        updateModeStyles();
                    });
                });

                // Manage implies View
                manageCheckboxes.forEach(manageCb => {
                    manageCb.addEventListener('change', () => {
                        if (manageCb.checked) {
                            const section = manageCb.dataset.section;
                            const viewCb = document.querySelector(`.perm-view[data-section="${section}"]`);
                            if (viewCb) {
                                viewCb.checked = true;
                            }
                        }
                        setCustomMode();
                    });
                });

                viewCheckboxes.forEach(viewCb => {
                    viewCb.addEventListener('change', () => {
                        if (!viewCb.checked) {
                            const section = viewCb.dataset.section;
                            const manageCb = document.querySelector(`.perm-manage[data-section="${section}"]`);
                            if (manageCb) {
                                manageCb.checked = false;
                            }
                        }
                        setCustomMode();
                    });
                });

                function setCustomMode() {
                    const customRadio = document.querySelector('input[name="mode"][value="custom"]');
                    if (customRadio && getSelectedMode() !== 'custom') {
                        customRadio.checked = true;
                        updateModeStyles();
                    }
                }

                document.getElementById('btn-select-all-view')?.addEventListener('click', () => {
                    viewCheckboxes.forEach(cb => cb.checked = true);
                    setCustomMode();
                });

                document.getElementById('btn-select-all-manage')?.addEventListener('click', () => {
                    manageCheckboxes.forEach(cb => {
                        if (cb.dataset.section !== 'user-access') {
                            cb.checked = true;
                            const section = cb.dataset.section;
                            const viewCb = document.querySelector(`.perm-view[data-section="${section}"]`);
                            if (viewCb) viewCb.checked = true;
                        }
                    });
                    setCustomMode();
                });

                document.getElementById('btn-clear-all')?.addEventListener('click', () => {
                    allCheckboxes.forEach(cb => cb.checked = false);
                    const noAccessRadio = document.querySelector('input[name="mode"][value="no_access"]');
                    if (noAccessRadio) {
                        noAccessRadio.checked = true;
                        updateModeStyles();
                    }
                });

                updateModeStyles();
            });
        </script>
    @endpush
</x-layouts.admin>
