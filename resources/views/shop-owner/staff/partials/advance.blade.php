<!-- ADVANCE TAB -->
<section class="grid gap-3 sm:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Request Advance</h2>
        <form method="POST" action="{{ route('shop-owner.staff.advance-requests.store') }}" class="space-y-2.5">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
            <input type="date" name="requested_on" value="{{ $selectedDate->format('Y-m-d') }}" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
            
            <div class="relative">
                <select name="employee_id" class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" data-advance-employee required>
                    <option value="">Select employee</option>
                    @foreach($advanceEmployees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->employee_code }}</option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </div>
            </div>

            <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-2 text-xs font-semibold text-cyan-900" data-advance-summary>
                Select an employee to see available advance.
            </div>
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="Advance amount" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" data-advance-amount required>
            <p class="hidden rounded-lg border px-2.5 py-1.5 text-xs font-black" data-advance-decision></p>
            <input type="hidden" name="fund_source" value="petty_cash">
            <textarea name="request_note" rows="2" placeholder="Reason / note" class="w-full rounded-lg border border-slate-200 p-2 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600"></textarea>
            <button type="submit" class="h-10 w-full rounded-xl bg-cyan-600 text-xs font-black text-white hover:bg-cyan-700 transition">Submit Advance</button>
        </form>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Advance Requests</h2>
        <div class="divide-y divide-slate-100">
            @forelse($advanceRequests as $advanceRequest)
                <div class="py-2 flex items-center justify-between text-xs">
                    <div>
                        <p class="font-black text-slate-950">{{ $advanceRequest->employee?->name }}</p>
                        <p class="text-[10px] font-semibold text-slate-400">{{ $advanceRequest->requested_on->format('d M') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-black text-slate-950">₹{{ number_format((float) $advanceRequest->requested_amount, 2) }}</p>
                        <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $advanceRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($advanceRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $advanceRequest->status }}</span>
                    </div>
                </div>
            @empty
                <p class="py-4 text-center text-xs font-semibold text-slate-400">No advance requests yet.</p>
            @endforelse
        </div>
    </article>
</section>
