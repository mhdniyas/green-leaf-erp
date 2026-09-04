<!-- LEAVE TAB -->
<section class="grid gap-3 sm:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Request Leave</h2>
        <form method="POST" action="{{ route('shop-owner.staff.leave-requests.store') }}" class="space-y-2.5">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
            
            <div class="relative">
                <select name="employee_id" class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" required>
                    <option value="">Select employee</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="start_date" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs font-bold" required>
                <input type="date" name="end_date" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs font-bold" required>
            </div>
            <textarea name="reason" rows="2" class="w-full rounded-lg border border-slate-200 p-2 text-xs font-semibold" placeholder="Reason for leave" required>{{ old('reason') }}</textarea>
            <button type="submit" class="h-10 w-full rounded-xl bg-slate-950 text-xs font-black text-white hover:bg-slate-800 transition">Submit Leave Request</button>
        </form>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Recent Leave Updates</h2>
        <div class="divide-y divide-slate-100">
            @forelse($leaveRequests as $leaveRequest)
                <div class="py-2 flex items-center justify-between text-xs">
                    <div>
                        <p class="font-black text-slate-950">{{ $leaveRequest->employee->name }}</p>
                        <p class="text-[10px] font-semibold text-slate-400">{{ $leaveRequest->start_date->format('d M') }} to {{ $leaveRequest->end_date->format('d M') }}</p>
                    </div>
                    <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $leaveRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($leaveRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $leaveRequest->status }}</span>
                </div>
            @empty
                <p class="py-4 text-center text-xs font-semibold text-slate-400">No leave requests yet.</p>
            @endforelse
        </div>
    </article>
</section>
