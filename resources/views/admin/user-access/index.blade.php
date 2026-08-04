<x-layouts.admin title="Login as User">
    <div class="mx-auto max-w-7xl space-y-5">
        <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">User Access</p>
                        <h1 class="mt-2 text-2xl font-black text-slate-950">Login as User</h1>
                        <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-500">Open the app exactly as an approved non-admin user for support, testing, and troubleshooting. Every impersonation session is logged with login and return details.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Eligible Users</p>
                            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($summary['eligible_total']) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Approved</p>
                            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($summary['approved_total']) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Matching Results</p>
                            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($users->total()) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.user-access.index') }}" class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="min-w-0 flex-1">
                        <label for="user-access-search" class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Search</label>
                        <input
                            id="user-access-search"
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search name, email, role, or shop"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                        >
                    </div>
                    <div class="flex gap-2 lg:self-end">
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:bg-slate-800">
                            Search
                        </button>
                        <a href="{{ route('admin.user-access.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-600 transition hover:bg-slate-50">
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            @if ($users->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-3xl border border-slate-200 bg-slate-50 text-slate-400">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <p class="mt-4 text-base font-black text-slate-900">No users matched your search</p>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Try a different name, email, role, or shop filter.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-6 py-4">User</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4">Shop</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($users as $listedUser)
                                @php
                                    $roleNames = $listedUser->roles->pluck('name')->map(fn (string $roleName): string => str($roleName)->headline()->toString());
                                    $canBeViewed = $listedUser->hasApprovedRegistration();
                                @endphp
                                <tr class="align-top transition hover:bg-slate-50/70">
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-sm font-black text-slate-700">
                                                {{ strtoupper(substr($listedUser->name, 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-black text-slate-950">{{ $listedUser->name }}</p>
                                                <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $listedUser->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($roleNames->isNotEmpty())
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($roleNames as $roleName)
                                                    <span class="rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-indigo-700">{{ $roleName }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">No role assigned</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($listedUser->shop)
                                            <span class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-cyan-700">{{ $listedUser->shop->name }}</span>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">No shop</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] {{ $listedUser->isOnline() ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500' }}">
                                                {{ $listedUser->isOnline() ? 'Online' : 'Offline' }}
                                            </span>
                                            <span class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] {{ $canBeViewed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                {{ $canBeViewed ? 'Approved' : 'Pending Approval' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if ($canBeViewed)
                                            <form method="POST" action="{{ route('admin.user-access.store', $listedUser->public_uuid) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2.5 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:bg-slate-800">
                                                    View as User
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                                                Approval Required
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="border-t border-slate-100 px-6 py-4">
                        {{ $users->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-layouts.admin>
