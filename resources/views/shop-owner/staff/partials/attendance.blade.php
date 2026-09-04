<!-- TAB 2: ATTENDANCE (FAST ONE-TAP CHECK-IN & REASON MODAL) -->
@include('shop-owner.staff.partials.quick-check-in')

<!-- PENDING HR APPROVAL COMPACT LIST -->
<section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-2">
    <div class="flex items-center justify-between">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Pending & Rejected Submissions</h2>
        @if(isset($pendingEmployees) && $pendingEmployees->count() > 0)
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-900">
                {{ $pendingEmployees->count() }}
            </span>
        @endif
    </div>

    <div class="divide-y divide-slate-100">
        @forelse($pendingEmployees ?? [] as $pendingEmp)
            <div class="py-2.5 space-y-1.5" x-data="{ expanded: false }">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        @if($pendingEmp->photo_url)
                            <img src="{{ $pendingEmp->photo_url }}" class="h-9 w-9 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $pendingEmp->name }}">
                        @else
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs shrink-0">
                                {{ Illuminate\Support\Str::upper(substr($pendingEmp->name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="text-xs font-black text-slate-950 truncate">{{ $pendingEmp->name }}</p>
                            <p class="text-[10px] font-semibold text-slate-400 truncate">{{ $pendingEmp->employee_code }} · {{ $pendingEmp->category?->name ?? 'Staff' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border {{ $pendingEmp->verification_status === 'pending' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                            {{ $pendingEmp->verification_status === 'pending' ? 'Pending HR Approval' : 'Rejected' }}
                        </span>
                        <a href="{{ route('shop-owner.staff.employees.edit-submission', $pendingEmp) }}" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-extrabold text-slate-700 hover:bg-slate-100">
                            Edit
                        </a>
                    </div>
                </div>

                @if($pendingEmp->verification_status === 'rejected' && $pendingEmp->rejection_reason)
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-[11px] text-rose-800 font-medium">
                        <span class="font-bold">Reason:</span> {{ $pendingEmp->rejection_reason }}
                    </div>
                @endif
            </div>
        @empty
            <div class="py-4 text-center text-xs font-semibold text-slate-400">
                No pending or rejected staff submissions.
            </div>
        @endforelse
    </div>
</section>
