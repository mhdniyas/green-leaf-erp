<!-- TAB 3: SALARY TAB (COMPACT CASHBOOK STYLE) -->
<section class="grid gap-3 sm:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Pay Staff Salary</h2>
        <form method="POST" action="{{ route('shop-owner.staff.salary-payments.store') }}" class="space-y-2.5">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
            
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Date</label>
                <input type="date" name="paid_on" value="{{ $selectedDate->format('Y-m-d') }}" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900" required>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Employee</label>
                <div class="relative">
                    <select name="employee_id" class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" data-salary-employee required>
                        <option value="">Select employee</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->employee_code }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-xs font-semibold text-emerald-900" data-salary-summary>
                Select an employee to see salary balance.
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Amount (₹)</label>
                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
            </div>

            <input type="hidden" name="fund_source" value="petty_cash">

            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Note</label>
                <input type="text" name="notes" placeholder="Note / description" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
            </div>

            <button type="submit" class="h-10 w-full rounded-xl bg-emerald-600 text-xs font-black text-white hover:bg-emerald-700 transition">
                Pay Salary
            </button>
        </form>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Recent Salary Payments</h2>
        <div class="divide-y divide-slate-100">
            @forelse($recentPayrollPayments as $payment)
                <div class="py-2 flex items-center justify-between text-xs">
                    <div>
                        <p class="font-black text-slate-950">{{ $payment->employee?->name }}</p>
                        <p class="text-[10px] font-semibold text-slate-400">{{ $payment->paid_on->format('d M') }} · {{ str($payment->payment_type)->headline() }}</p>
                    </div>
                    <p class="font-black text-slate-950">₹{{ number_format((float) $payment->amount, 2) }}</p>
                </div>
            @empty
                <p class="py-4 text-center text-xs font-semibold text-slate-400">No recent salary payments.</p>
            @endforelse
        </div>
    </article>
</section>
