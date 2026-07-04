<x-layouts.admin title="Users & Roles">

    <x-slot:actions>
        @can('create', \App\Models\User::class)
        <a href="{{ route('admin.users.create') }}"
           id="add-user-btn"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add User
        </a>
        @endcan
    </x-slot:actions>

    {{-- Search Filter --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="hidden" name="scope" value="{{ $scope }}">
        @if($role)
            <input type="hidden" name="role" value="{{ $role }}">
        @endif
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search by name or email…"
               class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 w-64">
        <button type="submit" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition-colors">Filter</button>
        @if(request('search') || $scope !== 'all')
            <a href="{{ route('admin.users.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.users.index', array_filter(['role' => $role])) }}"
           class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-black uppercase tracking-[0.18em] transition-colors {{ $scope === 'all' ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-500 hover:border-brand-200 hover:text-brand-700' }}">
            All Users
            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px]">{{ $allUsersCount }}</span>
        </a>

        <a href="{{ route('admin.users.index', array_filter(['scope' => 'pending', 'role' => $role])) }}"
           class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-black uppercase tracking-[0.18em] transition-colors {{ $scope === 'pending' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-gray-200 bg-white text-gray-500 hover:border-amber-200 hover:text-amber-700' }}">
            New Registrations
            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px]">{{ $pendingRegistrationsCount }}</span>
        </a>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.users.index', array_filter(['scope' => $scope !== 'all' ? $scope : null, 'search' => request('search') ?: null])) }}"
           class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-black uppercase tracking-[0.18em] transition-colors {{ $role === null ? 'border-slate-200 bg-slate-900 text-white' : 'border-gray-200 bg-white text-gray-500 hover:border-slate-300 hover:text-slate-900' }}">
            All Roles
            <span class="rounded-full bg-white/90 px-2 py-0.5 text-[10px] text-slate-700">{{ $allUsersCount }}</span>
        </a>

        @foreach($availableRoles as $roleMeta)
            <a href="{{ route('admin.users.index', array_filter(['scope' => $scope !== 'all' ? $scope : null, 'role' => $roleMeta['name'], 'search' => request('search') ?: null])) }}"
               class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-black uppercase tracking-[0.18em] transition-colors {{ $role === $roleMeta['name'] ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-500 hover:border-brand-200 hover:text-brand-700' }}">
                {{ $roleMeta['name'] }}
                <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px]">{{ $roleMeta['count'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Users Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">
                {{ $scope === 'pending' ? 'Pending Registrations' : 'User Accounts' }}
                @if($role)
                    <span class="text-gray-400">· {{ $role }}</span>
                @endif
            </h2>
            <span class="text-xs text-gray-500">{{ $users->total() }} users</span>
        </div>

        @if($users->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No users found</p>
            <p class="text-xs text-gray-500 mt-1">Try refining your search query.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Roles</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Direct Permissions</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($users as $u)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-brand-100 flex items-center justify-center shrink-0">
                                    <span class="text-brand-700 text-xs font-bold">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $u->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $u->email }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px] font-bold">
                                        <span class="rounded-full px-2 py-0.5 {{ $u->isOnline() ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                            {{ $u->isOnline() ? 'Online' : 'Offline' }}
                                        </span>
                                        @if($u->registration_status === 'pending')
                                            <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">
                                                Pending Approval
                                            </span>
                                        @endif
                                        @if($u->shop)
                                            <span class="rounded-full border border-cyan-200 bg-cyan-50 px-2 py-0.5 text-cyan-700">
                                                {{ $u->shop->name }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($u->roles->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach($u->roles as $role)
                                        <span class="inline-flex items-center bg-blue-50 border border-blue-200 text-blue-700 text-[10px] font-medium px-2 py-0.5 rounded-full">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-gray-400">No roles assigned</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($u->permissions->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach($u->permissions as $perm)
                                        <span class="inline-flex items-center bg-green-50 border border-green-200 text-green-700 text-[10px] font-medium px-2 py-0.5 rounded-full">
                                            {{ $perm->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($u->registration_status === 'pending')
                                @can('update', $u)
                                <form method="POST" action="{{ route('admin.users.approve', $u) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-emerald-700" title="Approve Registration">
                                        Approve
                                    </button>
                                </form>
                                @endcan
                                @endif

                                @can('update', $u)
                                <a href="{{ route('admin.users.edit', $u) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                   title="Edit User">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </a>
                                @endcan

                                @can('delete', $u)
                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                      onsubmit="return confirm('Are you sure you want to delete user {{ $u->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete User">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $users->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</x-layouts.app>
