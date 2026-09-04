<x-layouts.staff title="Payroll Settings">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.18em] text-cyan-700">Staff pay setup</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Payroll Settings</h1>
                <p class="mt-2 max-w-3xl text-sm font-semibold leading-6 text-slate-500">Tell the system how each type of staff should be paid. Start with the monthly salary and leave settings. You can adjust the detailed leave rules later if needed.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($allCategories as $category)
                    <a href="#category-{{ $category->code }}" class="rounded-full bg-slate-100 px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-cyan-50 hover:text-cyan-800">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </div>

        <section class="rounded-3xl border border-cyan-100 bg-cyan-50 p-5 sm:p-6">
            <div class="flex gap-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cyan-500 text-sm font-black text-white">i</div>
                <div>
                    <h2 class="font-black text-slate-950">How this works</h2>
                    <p class="mt-1 text-sm font-semibold leading-6 text-slate-600">Create one pay profile for each group of staff, such as Office Staff or Shop Staff. The profile is used when monthly payroll is prepared.</p>
                </div>
            </div>
        </section>

        <section id="check-in-settings" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.18em] text-cyan-700">Attendance control</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Check-in Time Settings</h2>
                    <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">Set the time until shop owners can mark same-day staff attendance. HR/Admin can still correct attendance after this time.</p>
                </div>
                <form method="POST" action="{{ route('admin.staff.settings.check-in-time.update') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    @method('PATCH')
                    <label class="block">
                        <span class="mb-2 block text-sm font-black text-slate-700">Check-in closes at</span>
                        <input type="time" name="shop_attendance_cutoff_time" value="{{ old('shop_attendance_cutoff_time', $checkInTime) }}" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-900 sm:w-48" required>
                    </label>
                    <button type="submit" class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">Save time</button>
                </form>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-black text-slate-950">Add a staff pay profile <span class="sr-only">(Add Payroll Category)</span></h2>
                <p class="text-sm font-semibold text-slate-500">Use this when a new staff group needs its own salary and leave rules.</p>
            </div>

            <form method="POST" action="{{ route('admin.staff.categories.store') }}" class="mt-6 space-y-6">
                @csrf
                <div>
                    <h3 class="text-sm font-black text-slate-950">1. Identify the staff group</h3>
                    <div class="mt-3 grid gap-4 md:grid-cols-3">
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Name</span>
                            <input type="text" name="name" placeholder="For example: Shop Staff" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Short code <span class="font-semibold text-slate-400">(for internal use)</span></span>
                            <input type="text" name="code" placeholder="For example: SHOP" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Where do they work?</span>
                            <select name="staff_area" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                                <option value="office">Office</option>
                                <option value="shop">Shop</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-black text-slate-950">2. Set the usual monthly pay</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500">These percentages tell us how much of a normal day’s pay to give in each situation.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Monthly salary</span>
                            <input type="number" step="0.01" min="0" name="default_monthly_salary" placeholder="0.00" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Paid leave days each month</span>
                            <input type="number" name="monthly_paid_leave_limit" min="0" max="31" placeholder="For example: 1" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Half-day pay</span>
                            <input type="number" min="0" max="100" name="half_day_salary_percent" value="50" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                            <span class="mt-1 block text-xs font-semibold text-slate-400">% of a full day</span>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Absent-day pay</span>
                            <input type="number" min="0" max="100" name="absent_day_salary_percent" value="0" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                            <span class="mt-1 block text-xs font-semibold text-slate-400">Usually 0%</span>
                        </label>
                    </div>
                    <label class="mt-4 block max-w-xs">
                        <span class="mb-2 block text-sm font-black text-slate-700">Extra leave pay</span>
                        <input type="number" min="0" max="100" name="unpaid_leave_salary_percent" value="0" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        <span class="mt-1 block text-xs font-semibold text-slate-400">Pay for leave after the monthly paid limit</span>
                    </label>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-black text-slate-950">3. Carry over unused paid leave</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Choose whether unused paid leave can move into the next period.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 sm:col-span-1">
                            <input type="hidden" name="paid_leave_carry_forward_allowed" value="0">
                            <input type="checkbox" name="paid_leave_carry_forward_allowed" value="1" class="rounded border-slate-300">
                            Allow unused paid leave to carry over
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Maximum days to carry over</span>
                            <input type="number" step="0.01" min="0" name="paid_leave_maximum_carry_forward_days" value="0" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Expire after (months)</span>
                            <input type="number" min="0" max="24" name="paid_leave_carry_forward_expiry_months" placeholder="Optional" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                        </label>
                    </div>
                </div>

                <input type="hidden" name="present_day_weight" value="1">
                <input type="hidden" name="paid_leave_weight" value="1">
                <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 sm:flex-row sm:items-end">
                    <label class="block flex-1">
                        <span class="mb-2 block text-sm font-black text-slate-700">Note <span class="font-semibold text-slate-400">(optional)</span></span>
                        <input type="text" name="notes" placeholder="Anything helpful about this pay profile" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        Use this profile
                    </label>
                    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800">Create pay profile</button>
                </div>
            </form>
        </section>

        @foreach ($allCategories as $category)
            @php
                $isCoreCategory = $category->isCoreCategory();
                $ruleMap = $category->leaveRules->keyBy('leave_type_id');
                $paidLeaveRule = $category->leaveRules->first(fn ($rule) => $rule->leaveType?->code === \App\Models\LeaveType::CODE_PAID);
            @endphp

            <section id="category-{{ $category->code }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-black text-slate-950">{{ $category->name }}</h2>
                            <span class="rounded-full {{ $isCoreCategory ? 'bg-cyan-50 text-cyan-700' : 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-black uppercase tracking-[0.16em]">
                                {{ $isCoreCategory ? 'Built-in profile' : 'Custom profile' }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">Update the everyday pay settings below. Present days and approved paid leave are always paid in full.</p>
                    </div>
                    <a href="#leave-rules-{{ $category->code }}" class="text-sm font-black text-cyan-700 hover:text-cyan-900">Manage detailed leave rules ↓</a>
                </div>

                <form method="POST" action="{{ route('admin.staff.categories.update', $category) }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Profile name</span>
                            <input type="text" name="name" value="{{ $category->name }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm {{ $isCoreCategory ? 'bg-slate-100 text-slate-500' : '' }}" @readonly($isCoreCategory) required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Short code</span>
                            <input type="text" name="code" value="{{ $category->code }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm {{ $isCoreCategory ? 'bg-slate-100 text-slate-500' : '' }}" @readonly($isCoreCategory) required>
                        </label>
                        @if ($isCoreCategory)
                            <input type="hidden" name="staff_area" value="{{ $category->staff_area }}">
                        @endif
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Work area</span>
                            <select name="{{ $isCoreCategory ? '' : 'staff_area' }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm {{ $isCoreCategory ? 'bg-slate-100 text-slate-500' : '' }}" @disabled($isCoreCategory) required>
                                <option value="office" @selected($category->staff_area === 'office')>Office</option>
                                <option value="shop" @selected($category->staff_area === 'shop')>Shop</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Monthly salary</span>
                            <input type="number" step="0.01" min="0" name="default_monthly_salary" value="{{ (float) $category->default_monthly_salary }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Paid leave days each month</span>
                            <input type="number" name="monthly_paid_leave_limit" min="0" max="31" value="{{ $category->monthly_paid_leave_limit }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Half-day pay (%)</span>
                            <input type="number" min="0" max="100" name="half_day_salary_percent" value="{{ (int) round((float) $category->half_day_weight * 100) }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Extra leave pay (%)</span>
                            <input type="number" min="0" max="100" name="unpaid_leave_salary_percent" value="{{ (int) round((float) $category->excess_leave_weight * 100) }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-black text-slate-700">Absent-day pay (%)</span>
                            <input type="number" min="0" max="100" name="absent_day_salary_percent" value="{{ (int) round((float) $category->absent_day_weight * 100) }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                    </div>
                    <input type="hidden" name="present_day_weight" value="1">
                    <input type="hidden" name="paid_leave_weight" value="1">
                    <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 sm:flex-row sm:items-end">
                        <label class="block flex-1">
                            <span class="mb-2 block text-sm font-black text-slate-700">Note <span class="font-semibold text-slate-400">(optional)</span></span>
                            <input type="text" name="notes" value="{{ $category->notes }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-slate-300">
                            Use this profile
                        </label>
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-black text-slate-950">Carry over unused paid leave</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Unused paid leave can move into the next period if you allow it.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-3">
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">
                                <input type="hidden" name="paid_leave_carry_forward_allowed" value="0">
                                <input type="checkbox" name="paid_leave_carry_forward_allowed" value="1" @checked($paidLeaveRule?->carry_forward_allowed) class="rounded border-slate-300">
                                Allow unused paid leave to carry over
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-black text-slate-700">Maximum days to carry over</span>
                                <input type="number" step="0.01" min="0" name="paid_leave_maximum_carry_forward_days" value="{{ $paidLeaveRule?->maximum_carry_forward_days ?? 0 }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-black text-slate-700">Expire after (months)</span>
                                <input type="number" min="0" max="24" name="paid_leave_carry_forward_expiry_months" value="{{ $paidLeaveRule?->carry_forward_expiry_months }}" placeholder="Optional" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800">Save pay settings</button>
                    </div>
                </form>

                @if (false)
                <details id="leave-rules-{{ $category->code }}" class="mt-8 border-t border-slate-100 pt-6">
                    <summary class="cursor-pointer list-none text-lg font-black text-slate-950">Leave Rules <span class="ml-2 text-sm font-semibold text-slate-400">(optional detailed setup)</span></summary>
                    <p class="mt-2 max-w-4xl text-sm font-semibold leading-6 text-slate-500">Use this section to decide how many days of each leave type staff receive, whether unused days move to the next period, and when a rule starts. If you only need basic payroll, you can leave these settings as they are. <span class="sr-only">Carry Forward and future effective dates are managed here.</span></p>

                    <form method="POST" action="{{ route('admin.staff.categories.leave-rules.update', $category) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="overflow-x-auto rounded-3xl border border-slate-200">
                            <table class="min-w-[1500px] w-full text-left text-sm">
                                <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Leave type</th>
                                        <th class="px-4 py-3">Days each year</th>
                                        <th class="px-4 py-3">Days added each month</th>
                                        <th class="px-4 py-3">How often to add</th>
                                        <th class="px-4 py-3">Pay (%)</th>
                                        <th class="px-4 py-3">Move unused days forward?</th>
                                        <th class="px-4 py-3">Maximum days to move</th>
                                        <th class="px-4 py-3">Expire after (months)</th>
                                        <th class="px-4 py-3">Expiry date</th>
                                        <th class="px-4 py-3">Starts on</th>
                                        <th class="px-4 py-3">Ends on</th>
                                        <th class="px-4 py-3">Allow a negative balance?</th>
                                        <th class="px-4 py-3">Note</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($leaveTypes as $index => $leaveType)
                                        @php($rule = $ruleMap->get($leaveType->id))
                                        <tr class="align-top">
                                            <td class="px-4 py-3">
                                                <input type="hidden" name="rules[{{ $index }}][leave_type_id]" value="{{ $leaveType->id }}">
                                                <p class="font-black text-slate-950">{{ $leaveType->name }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $leaveType->is_paid ? 'Paid leave' : 'Unpaid leave' }}</p>
                                            </td>
                                            <td class="px-4 py-3"><input type="number" step="0.01" min="0" name="rules[{{ $index }}][annual_entitlement]" value="{{ old("rules.$index.annual_entitlement", $rule?->annual_entitlement ?? 0) }}" class="w-24 rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><input type="number" step="0.01" min="0" name="rules[{{ $index }}][monthly_accrual_amount]" value="{{ old("rules.$index.monthly_accrual_amount", $rule?->monthly_accrual_amount) }}" class="w-24 rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><select name="rules[{{ $index }}][allocation_frequency]" class="w-36 rounded-xl border border-slate-200 px-3 py-2">@foreach (['monthly' => 'Monthly', 'annual_opening' => 'At year start', 'manual' => 'Manual'] as $value => $label)<option value="{{ $value }}" @selected(old("rules.$index.allocation_frequency", $rule?->allocation_frequency ?? 'monthly') === $value)>{{ $label }}</option>@endforeach</select></td>
                                            <td class="px-4 py-3"><input type="number" step="0.01" min="0" max="1" name="rules[{{ $index }}][payroll_weight]" value="{{ old("rules.$index.payroll_weight", $rule?->payroll_weight ?? ($leaveType->is_paid ? 1 : 0)) }}" class="w-24 rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><input type="hidden" name="rules[{{ $index }}][carry_forward_allowed]" value="0"><input type="checkbox" name="rules[{{ $index }}][carry_forward_allowed]" value="1" @checked((bool) old("rules.$index.carry_forward_allowed", $rule?->carry_forward_allowed ?? false)) class="rounded border-slate-300"></td>
                                            <td class="px-4 py-3"><input type="number" step="0.01" min="0" name="rules[{{ $index }}][maximum_carry_forward_days]" value="{{ old("rules.$index.maximum_carry_forward_days", $rule?->maximum_carry_forward_days ?? 0) }}" class="w-24 rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><input type="number" min="0" max="24" name="rules[{{ $index }}][carry_forward_expiry_months]" value="{{ old("rules.$index.carry_forward_expiry_months", $rule?->carry_forward_expiry_months) }}" class="w-24 rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><input type="date" name="rules[{{ $index }}][carry_forward_expiry_date]" value="{{ old("rules.$index.carry_forward_expiry_date", $rule?->carry_forward_expiry_date?->format('Y-m-d')) }}" class="rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><input type="date" name="rules[{{ $index }}][effective_from]" value="{{ old("rules.$index.effective_from", $rule?->effective_from?->format('Y-m-d')) }}" class="rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><input type="date" name="rules[{{ $index }}][effective_to]" value="{{ old("rules.$index.effective_to", $rule?->effective_to?->format('Y-m-d')) }}" class="rounded-xl border border-slate-200 px-3 py-2"></td>
                                            <td class="px-4 py-3"><input type="hidden" name="rules[{{ $index }}][negative_balance_allowed]" value="0"><input type="checkbox" name="rules[{{ $index }}][negative_balance_allowed]" value="1" @checked((bool) old("rules.$index.negative_balance_allowed", $rule?->negative_balance_allowed ?? false)) class="rounded border-slate-300"></td>
                                            <td class="px-4 py-3"><input type="text" name="rules[{{ $index }}][notes]" value="{{ old("rules.$index.notes", $rule?->notes) }}" class="w-56 rounded-xl border border-slate-200 px-3 py-2"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="rounded-xl bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950 transition hover:bg-cyan-400">Save detailed leave rules</button>
                    </form>
                </details>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.staff>
