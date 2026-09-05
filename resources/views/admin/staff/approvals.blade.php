<x-layouts.staff title="Pending Employee Approvals">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Pending Approvals</h1>
                <p class="text-sm font-semibold text-slate-500">Review and complete HR setup for shop-submitted staff registrations.</p>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-black text-slate-950">Pending Verification Requests</h2>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-900">
                    {{ $pendingEmployees->total() }} Pending
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-3 pr-3">Photo</th>
                            <th class="py-3">Employee Name</th>
                            <th class="py-3">Code</th>
                            <th class="py-3">Submitted Shop</th>
                            <th class="py-3">Phone</th>
                            <th class="py-3">ID Type</th>
                            <th class="py-3">Submitted Date</th>
                            <th class="py-3">Submitted By</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($pendingEmployees as $employee)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 pr-3">
                                    @if($employee->photo_url)
                                        <img src="{{ $employee->photo_url }}" class="h-9 w-9 rounded-full object-cover border border-slate-200" alt="{{ $employee->name }}">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs">
                                            {{ Illuminate\Support\Str::upper(substr($employee->name, 0, 2)) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 font-bold text-slate-950">{{ $employee->name }}</td>
                                <td class="py-3 font-mono text-slate-600">{{ $employee->employee_code }}</td>
                                <td class="py-3 font-bold text-slate-900">
                                    <span class="inline-flex rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-black text-amber-900 border border-amber-200">
                                        {{ $employee->defaultShop?->name ?? 'Shop' }}
                                    </span>
                                </td>
                                <td class="py-3 text-slate-600">{{ $employee->phone }}</td>
                                <td class="py-3 uppercase text-slate-500 font-bold">{{ str_replace('_', ' ', $employee->id_type) }}</td>
                                <td class="py-3 text-slate-500">{{ $employee->created_at?->format('d M Y') }}</td>
                                <td class="py-3 text-slate-600">{{ $employee->submittedBy?->name ?? 'Shop Owner' }}</td>
                                <td class="py-3">
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-amber-900">
                                        PENDING
                                    </span>
                                </td>
                                <td class="py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('admin.staff.approvals.show', $employee) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800 transition">
                                            <span>Review</span>
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        </a>
                                        @can('delete', $employee)
                                            <form method="POST" action="{{ route('admin.staff.duplicate.destroy', $employee) }}" onsubmit="return confirm('Permanently delete this pending registration as a duplicate? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-black text-rose-700 hover:bg-rose-100 transition">
                                                    Delete duplicate
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-xs font-semibold text-slate-400">
                                    No staff registrations pending HR approval.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $pendingEmployees->links() }}</div>
        </section>
    </div>
</x-layouts.staff>
