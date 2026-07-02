<x-layouts.staff title="Leave Queue">
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-slate-950">Leave Requests</h1>
            <p class="text-sm font-semibold text-slate-500">Admin reviews leave requests submitted by owners or linked users.</p>
        </div>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="space-y-4">
            @foreach($leaveRequests as $leaveRequest)
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-lg font-black text-slate-950">{{ $leaveRequest->employee->name }}</p>
                            <p class="text-sm font-semibold text-slate-500">{{ $leaveRequest->start_date->format('d M Y') }} to {{ $leaveRequest->end_date->format('d M Y') }}</p>
                            <p class="mt-2 text-sm text-slate-700">{{ $leaveRequest->reason }}</p>
                        </div>
                        <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-600">{{ $leaveRequest->status }}</span>
                    </div>

                    <div class="mt-4 text-xs font-semibold text-slate-500">
                        Submitted by {{ $leaveRequest->submittedBy?->name ?? 'System' }}
                        @if($leaveRequest->submittedForShop)
                            • {{ $leaveRequest->submittedForShop->name }}
                        @endif
                    </div>

                    @if($leaveRequest->status === 'pending')
                        <form method="POST" action="{{ route('admin.staff.leaves.review', $leaveRequest) }}" class="mt-4 flex flex-wrap gap-3">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="review_note" placeholder="Review note" class="min-w-[16rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <button type="submit" name="status" value="approved" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-black text-white">Approve</button>
                            <button type="submit" name="status" value="rejected" class="rounded-xl bg-rose-500 px-4 py-2 text-sm font-black text-white">Reject</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        <div>{{ $leaveRequests->links() }}</div>
    </div>
</x-layouts.staff>
