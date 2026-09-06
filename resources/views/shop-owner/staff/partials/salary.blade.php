<!-- UNIFIED SALARY & ADVANCE WINDOW -->
@php
    $defaultMode = old('mode', request('mode', request('tab') === 'advance' ? 'advance' : 'salary'));
    if ($errors->has('requested_on') || $errors->has('request_note')) {
        $defaultMode = 'advance';
    }
@endphp
<section class="grid gap-4 lg:grid-cols-12" id="salary-advance-container">
    {{-- Form Column --}}
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-4 lg:col-span-7">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-800" id="form-title">Salary & Advance Window</h2>
                    <span class="rounded-md bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-extrabold text-emerald-800">
                        {{ $calendarMonth->format('M Y') }}
                    </span>
                </div>
                <p class="text-xs font-medium text-slate-500 mt-0.5" id="form-desc">Disburse mid-month advance or record month-end salary.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @php
                    $selectedMonthVal = $calendarMonth->format('Y-m');
                    $monthOptions = collect(range(-11, 1))->map(function ($offset) {
                        $m = now()->addMonths($offset);
                        return [
                            'value' => $m->format('Y-m'),
                            'label' => $m->format('F Y'),
                            'is_current' => $m->isCurrentMonth(),
                        ];
                    })->reverse()->values();

                    if (! $monthOptions->contains('value', $selectedMonthVal)) {
                        $monthOptions->prepend([
                            'value' => $selectedMonthVal,
                            'label' => $calendarMonth->format('F Y'),
                            'is_current' => $calendarMonth->isCurrentMonth(),
                        ]);
                    }
                @endphp

                <!-- Payroll Month Selector Dropdown -->
                <div class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1 shadow-2xs hover:border-slate-300 transition">
                    <label for="sal_filter_month" class="text-[10px] font-black uppercase tracking-wider text-slate-500 whitespace-nowrap">Month:</label>
                    <div class="relative">
                        <select id="sal_filter_month" name="month"
                                onchange="window.location.href = '{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'salary']) }}&month=' + this.value"
                                class="h-6 bg-transparent border-none py-0 pl-1 pr-5 text-xs font-black text-slate-900 focus:ring-0 cursor-pointer appearance-none">
                            @foreach($monthOptions as $mo)
                                <option value="{{ $mo['value'] }}" {{ $selectedMonthVal === $mo['value'] ? 'selected' : '' }}>
                                    {{ $mo['label'] }}{{ $mo['is_current'] ? ' (Current)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center text-slate-500">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Mode Selector Switch -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 self-start sm:self-auto" role="tablist">
                    <button type="button" id="tab-btn-salary" onclick="switchSalaryMode('salary')"
                            class="px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $defaultMode === 'salary' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Pay Salary
                    </button>
                    <button type="button" id="tab-btn-advance" onclick="switchSalaryMode('advance')"
                            class="px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $defaultMode === 'advance' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Give Advance
                    </button>
                </div>
            </div>
        </div>

        {{-- SALARY PAYMENT FORM --}}
        <form method="POST" action="{{ route('shop-owner.staff.salary-payments.store') }}" id="salary-payment-form" class="space-y-4 {{ $defaultMode === 'advance' ? 'hidden' : '' }}">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
            <input type="hidden" name="request_uuid" id="sal_request_uuid" value="{{ old('request_uuid', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="sal_paid_on" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Payment Date</label>
                    <input type="date" name="paid_on" id="sal_paid_on" value="{{ old('paid_on', $selectedDate->format('Y-m-d')) }}"
                           class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                    @error('paid_on')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sal_employee_id" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Select Employee</label>
                    <div class="relative">
                        <select name="employee_id" id="sal_employee_id"
                                class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer"
                                data-salary-employee required>
                            <option value="">Select staff member</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->name }} · {{ $employee->employee_code }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                    @error('employee_id')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Employee Summary Card --}}
            <div id="salary-summary-card" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-2.5" data-salary-summary>
                <div class="flex items-center justify-between text-xs font-bold text-slate-600">
                    <span>Select an employee to see current salary remaining balance.</span>
                </div>
            </div>

            {{-- Amount & Helper --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="sal_amount" class="block text-xs font-black uppercase tracking-wider text-slate-700">Salary Amount (₹)</label>
                    <span id="sal_amount_helper" class="text-[11px] font-bold text-slate-500"></span>
                </div>
                <input type="number" step="0.01" min="0.01" name="amount" id="sal_amount"
                       value="{{ old('amount') }}" placeholder="0.00"
                       class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600"
                       data-salary-amount required>
                @error('amount')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Visible Funding Source Selection --}}
            <fieldset class="rounded-xl border border-slate-200 bg-slate-50/50 p-3">
                <legend class="text-xs font-black uppercase tracking-wider text-slate-700 px-1">Funding Source</legend>
                <div class="grid grid-cols-2 gap-3 mt-1">
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2.5 cursor-pointer hover:border-emerald-400 transition">
                        <input type="radio" name="fund_source" value="sales_income" id="sal_fund_sales"
                                class="text-emerald-600 focus:ring-emerald-500"
                                {{ old('fund_source', 'sales_income') === 'sales_income' ? 'checked' : '' }}>
                        <div>
                            <span class="block text-xs font-black text-slate-900">Sales Cash</span>
                            <span class="block text-[10px] font-medium text-slate-500">From shop daily sales till</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2.5 cursor-pointer hover:border-emerald-400 transition">
                        <input type="radio" name="fund_source" value="petty_cash" id="sal_fund_petty"
                                class="text-emerald-600 focus:ring-emerald-500"
                                {{ old('fund_source') === 'petty_cash' ? 'checked' : '' }}>
                        <div>
                            <span class="block text-xs font-black text-slate-900">Petty Cash</span>
                            <span class="block text-[10px] font-medium text-slate-500">From shop petty float</span>
                        </div>
                    </label>
                </div>
                @error('fund_source')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- Optional Note --}}
            <div>
                <label for="sal_notes" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Note / Description (Optional)</label>
                <input type="text" name="notes" id="sal_notes" value="{{ old('notes') }}"
                       placeholder="e.g. September salary payout"
                       class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                @error('notes')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" id="salary-submit-btn"
                    class="h-11 w-full rounded-xl bg-emerald-600 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition cursor-pointer">
                <span>Record Salary Paid</span>
            </button>
        </form>

        {{-- ADVANCE REQUEST / PAYMENT FORM --}}
        <form method="POST" action="{{ route('shop-owner.staff.advance-requests.store') }}" id="advance-request-form" class="space-y-4 {{ $defaultMode === 'salary' ? 'hidden' : '' }}">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
            <input type="hidden" name="request_uuid" id="adv_request_uuid" value="{{ old('request_uuid', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="adv_requested_on" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Payment Date</label>
                    <input type="date" name="requested_on" id="adv_requested_on" value="{{ old('requested_on', $selectedDate->format('Y-m-d')) }}"
                           class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                    @error('requested_on')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="adv_employee_id" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Select Employee</label>
                    <div class="relative">
                        <select name="employee_id" id="adv_employee_id"
                                class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer"
                                data-advance-employee required>
                            <option value="">Select staff member</option>
                            @foreach($advanceEmployees as $employee)
                                <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->name }} · {{ $employee->employee_code }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                    @error('employee_id')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Employee Summary Card --}}
            <div id="advance-summary-card" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-2.5" data-advance-summary>
                <div class="flex items-center justify-between text-xs font-bold text-slate-600">
                    <span>Select an employee to see availability & attendance breakdown.</span>
                </div>
            </div>

            {{-- Amount & Helper --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="adv_amount" class="block text-xs font-black uppercase tracking-wider text-slate-700">Requested Amount (₹)</label>
                    <span id="adv_amount_helper" class="text-[11px] font-bold text-slate-500"></span>
                </div>
                <input type="number" step="0.01" min="0.01" name="amount" id="adv_amount"
                       value="{{ old('amount') }}" placeholder="0.00"
                       class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600"
                       data-advance-amount required>
                @error('amount')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Dynamic Decision Feedback --}}
            <div id="adv_decision_box" class="hidden rounded-xl border p-2.5 text-xs font-bold flex items-center gap-2" data-advance-decision></div>

            {{-- Visible Funding Source Selection --}}
            <fieldset class="rounded-xl border border-slate-200 bg-slate-50/50 p-3">
                <legend class="text-xs font-black uppercase tracking-wider text-slate-700 px-1">Funding Source</legend>
                <div class="grid grid-cols-2 gap-3 mt-1">
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2.5 cursor-pointer hover:border-emerald-400 transition">
                        <input type="radio" name="fund_source" value="sales_income" id="adv_fund_sales"
                               class="text-emerald-600 focus:ring-emerald-500"
                               {{ old('fund_source', 'sales_income') === 'sales_income' ? 'checked' : '' }}>
                        <div>
                            <span class="block text-xs font-black text-slate-900">Sales Cash</span>
                            <span class="block text-[10px] font-medium text-slate-500">From shop daily sales till</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2.5 cursor-pointer hover:border-emerald-400 transition">
                        <input type="radio" name="fund_source" value="petty_cash" id="adv_fund_petty"
                               class="text-emerald-600 focus:ring-emerald-500"
                               {{ old('fund_source') === 'petty_cash' ? 'checked' : '' }}>
                        <div>
                            <span class="block text-xs font-black text-slate-900">Petty Cash</span>
                            <span class="block text-[10px] font-medium text-slate-500">From shop petty float</span>
                        </div>
                    </label>
                </div>
                @error('fund_source')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- Request Note --}}
            <div>
                <label for="adv_request_note" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Reason / Note (Required if exception)</label>
                <input type="text" name="request_note" id="adv_request_note" value="{{ old('request_note') }}"
                       placeholder="e.g. Festival advance request"
                       class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                @error('request_note')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" id="advance-submit-btn"
                    class="h-11 w-full rounded-xl bg-slate-950 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 transition cursor-pointer">
                <span>Give Advance / Submit Request</span>
            </button>
        </form>
    </article>

    {{-- Recent Payments & Payouts Column --}}
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 lg:col-span-5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Recent Payouts</h2>
                <p class="text-xs font-medium text-slate-500 mt-0.5">Staff payments recorded for this shop.</p>
            </div>
            <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history']) }}"
               class="text-[11px] font-bold text-emerald-600 hover:text-emerald-800 underline">
                View All →
            </a>
        </div>

        <div class="divide-y divide-slate-100 max-h-[480px] overflow-y-auto">
            @forelse($recentPayrollPayments as $payment)
                <div class="py-2.5 flex items-center justify-between text-xs gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="font-black text-slate-950 truncate">{{ $payment->employee?->name }}</p>
                        <p class="text-[10px] font-semibold text-slate-400 truncate">
                            {{ $payment->paid_on->format('d M Y') }} · {{ str_replace('_', ' ', (string) $payment->fund_source) }}
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-black text-slate-950">₹{{ number_format((float) $payment->amount, 2) }}</p>
                        <span class="inline-block rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $payment->payment_type === 'advance' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                            {{ $payment->payment_type ?? 'salary' }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-xs font-semibold text-slate-400">
                    <p>No salary or advance payments recorded yet.</p>
                </div>
            @endforelse
        </div>
    </article>
</section>

<script>
    function switchSalaryMode(mode) {
        const salaryForm = document.getElementById('salary-payment-form');
        const advanceForm = document.getElementById('advance-request-form');
        const salaryBtn = document.getElementById('tab-btn-salary');
        const advanceBtn = document.getElementById('tab-btn-advance');
        const formTitle = document.getElementById('form-title');
        const formDesc = document.getElementById('form-desc');

        if (mode === 'salary') {
            salaryForm?.classList.remove('hidden');
            advanceForm?.classList.add('hidden');
            salaryBtn?.classList.remove('text-slate-600', 'hover:text-slate-900');
            salaryBtn?.classList.add('bg-slate-950', 'text-white', 'shadow-xs');
            advanceBtn?.classList.remove('bg-slate-950', 'text-white', 'shadow-xs');
            advanceBtn?.classList.add('text-slate-600', 'hover:text-slate-900');
            if (formTitle) formTitle.textContent = 'Pay Staff Salary / Record Salary Paid';
            if (formDesc) formDesc.textContent = 'Pay earned monthly salary up to the current remaining balance.';
        } else {
            salaryForm?.classList.add('hidden');
            advanceForm?.classList.remove('hidden');
            advanceBtn?.classList.remove('text-slate-600', 'hover:text-slate-900');
            advanceBtn?.classList.add('bg-slate-950', 'text-white', 'shadow-xs');
            salaryBtn?.classList.remove('bg-slate-950', 'text-white', 'shadow-xs');
            salaryBtn?.classList.add('text-slate-600', 'hover:text-slate-900');
            if (formTitle) formTitle.textContent = 'Request Advance / Give Advance';
            if (formDesc) formDesc.textContent = 'Disburse salary advance within 50% ceiling or submit for HR approval.';
        }
    }
</script>
