<!-- ADVANCE TAB (STAGE 4: ACCESSIBLE SALARY AVAILABILITY & VISIBLE FUNDING) -->
<section class="grid gap-4 lg:grid-cols-12">
    {{-- Form Column --}}
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-4 lg:col-span-7">
        <div class="border-b border-slate-100 pb-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Request Advance / Give Advance</h2>
            <p class="text-xs font-medium text-slate-500 mt-0.5">Disburse salary advance within the 50% ceiling or submit for HR approval.</p>
        </div>

        <form method="POST" action="{{ route('shop-owner.staff.advance-requests.store') }}" id="advance-request-form" class="space-y-4">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
            <input type="hidden" name="request_uuid" id="adv_request_uuid" value="{{ old('request_uuid', (string) \Illuminate\Support\Str::uuid()) }}">

            {{-- Date & Employee in 2 columns on tablet/desktop --}}
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

            {{-- Employee Summary Card (Authoritative Breakdown from SalaryAvailabilityService) --}}
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

            {{-- Visible Funding Source Selection (No Hidden petty_cash) --}}
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

            {{-- Optional Note --}}
            <div>
                <label for="adv_request_note" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Reason / Note (Optional)</label>
                <textarea name="request_note" id="adv_request_note" rows="2"
                          placeholder="State justification or employee remarks"
                          class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">{{ old('request_note') }}</textarea>
                @error('request_note')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" id="advance-submit-btn"
                    class="h-11 w-full rounded-xl bg-cyan-600 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span>Submit Advance</span>
            </button>
        </form>
    </article>

    {{-- History Column --}}
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 lg:col-span-5">
        <div class="border-b border-slate-100 pb-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Recent Advance Requests</h2>
            <p class="text-xs font-medium text-slate-500 mt-0.5">Approved and pending advance requests for this shop.</p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($advanceRequests as $advanceRequest)
                <div class="py-2.5 flex items-center justify-between text-xs">
                    <div>
                        <p class="font-black text-slate-950">{{ $advanceRequest->employee?->name }}</p>
                        <p class="text-[10px] font-semibold text-slate-400">
                            {{ $advanceRequest->requested_on->format('d M Y') }} · {{ str_replace('_', ' ', $advanceRequest->fund_source) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-black text-slate-950">₹{{ number_format((float) ($advanceRequest->approved_amount ?? $advanceRequest->requested_amount), 2) }}</p>
                        <span class="inline-block rounded px-2 py-0.5 text-[9px] font-black uppercase border {{ $advanceRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($advanceRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                            {{ $advanceRequest->status }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-xs font-semibold text-slate-400">
                    <p>No advance requests recorded yet.</p>
                </div>
            @endforelse
        </div>
    </article>
</section>
