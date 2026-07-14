<x-layouts.staff title="Leave Queue">
    @php
        $activeTab = request()->string('tab')->toString();

        if (! in_array($activeTab, ['submit', 'queue'], true)) {
            $activeTab = $errors->any() ? 'submit' : 'queue';
        }
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-slate-950">Leave Requests</h1>
            <p class="text-sm font-semibold text-slate-500">Admin reviews leave requests submitted by owners or linked users.</p>
        </div>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="rounded-3xl border border-slate-200 bg-white p-2 shadow-sm">
            <div class="grid gap-2 md:grid-cols-2">
                <a
                    href="{{ route('admin.staff.leaves.index', ['tab' => 'submit']) }}"
                    class="{{ $activeTab === 'submit' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }} rounded-2xl px-4 py-3 text-center text-sm font-black transition"
                >
                    Submit Leave
                </a>
                <a
                    href="{{ route('admin.staff.leaves.index', ['tab' => 'queue']) }}"
                    class="{{ $activeTab === 'queue' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }} rounded-2xl px-4 py-3 text-center text-sm font-black transition"
                >
                    Leave Queue
                </a>
            </div>
        </div>

        @if($activeTab === 'submit')
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-xl font-black text-slate-950">HR Leave Submission</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Select the employee first. Leave context updates automatically from the selected staff profile.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Leave Context</p>
                        <p id="employee-leave-context" class="mt-1 font-bold text-slate-700">Select employee to load leave context.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.staff.leaves.store') }}" class="mt-5 space-y-5">
                    @csrf
                    <input type="hidden" name="shop_id" value="{{ old('shop_id') }}" data-leave-shop-id>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                        <label class="space-y-2">
                            <span class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Employee</span>
                            <select
                                name="employee_id"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-slate-400 focus:ring-4 focus:ring-slate-200"
                                data-leave-employee-select
                                data-enhanced-select
                                required
                            >
                                <option value="">Select employee</option>
                                @foreach($employees as $employee)
                                    <option
                                        value="{{ $employee->id }}"
                                        data-staff-area="{{ $employee->staff_area }}"
                                        data-category-name="{{ $employee->category->name }}"
                                        data-shop-id="{{ $employee->default_shop_id }}"
                                        data-shop-name="{{ $employee->defaultShop?->name }}"
                                        @selected((int) old('employee_id') === $employee->id)
                                    >
                                        {{ $employee->name }} · {{ $employee->category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="space-y-2">
                            <span class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Start Date</span>
                            <input type="date" name="start_date" value="{{ old('start_date') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-slate-400 focus:ring-4 focus:ring-slate-200" required>
                        </label>

                        <label class="space-y-2">
                            <span class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">End Date</span>
                            <input type="date" name="end_date" value="{{ old('end_date') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-slate-400 focus:ring-4 focus:ring-slate-200" required>
                        </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Staff Area</p>
                            <p id="employee-leave-area" class="mt-2 text-sm font-bold text-slate-700">Waiting for employee selection</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Category</p>
                            <p id="employee-leave-category" class="mt-2 text-sm font-bold text-slate-700">Waiting for employee selection</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Shop Context</p>
                            <p id="employee-leave-shop" class="mt-2 text-sm font-bold text-slate-700">Automatic from employee profile</p>
                        </div>
                    </div>

                    <label class="block space-y-2">
                        <span class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Reason</span>
                        <textarea name="reason" rows="4" placeholder="Reason for leave request" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-slate-400 focus:ring-4 focus:ring-slate-200" required>{{ old('reason') }}</textarea>
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800">Submit Leave</button>
                    </div>
                </form>
            </section>
        @endif

        @if($activeTab === 'queue')
            <div class="space-y-4">
                @foreach($leaveRequests as $leaveRequest)
                    @php($leaveSerial = ($leaveRequests->currentPage() - 1) * $leaveRequests->perPage() + $loop->iteration)
                    <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-2 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">#{{ $leaveSerial }}</span>
                                <div>
                                    <a href="{{ route('admin.staff.show', $leaveRequest->employee) }}" class="text-lg font-black text-slate-950 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $leaveRequest->employee->name }}</a>
                                    <p class="text-xs font-semibold text-slate-500">{{ $leaveRequest->employee->category->name }} · {{ ucfirst($leaveRequest->employee->staff_area) }}</p>
                                    <p class="text-sm font-semibold text-slate-500">{{ $leaveRequest->start_date->format('d M Y') }} to {{ $leaveRequest->end_date->format('d M Y') }}</p>
                                    <p class="mt-2 text-sm text-slate-700">{{ $leaveRequest->reason }}</p>
                                </div>
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
        @endif
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
        <style>
            .staff-select2 + .select2 {
                width: 100% !important;
            }

            .staff-select2 + .select2 .select2-selection--single {
                min-height: 52px;
                border-radius: 1rem;
                border-color: rgb(226 232 240);
                padding: 0.7rem 1rem;
                display: flex;
                align-items: center;
                box-shadow: none;
            }

            .staff-select2 + .select2 .select2-selection__rendered {
                color: rgb(51 65 85);
                font-size: 0.875rem;
                font-weight: 600;
                line-height: 1.25rem;
                padding-left: 0;
                padding-right: 1.75rem;
            }

            .staff-select2 + .select2 .select2-selection__placeholder {
                color: rgb(100 116 139);
            }

            .staff-select2 + .select2 .select2-selection__arrow {
                height: 100%;
                right: 0.85rem;
            }

            .staff-select2 + .select2.select2-container--focus .select2-selection--single {
                border-color: rgb(148 163 184);
                box-shadow: 0 0 0 4px rgb(226 232 240);
            }

            .select2-dropdown {
                border-color: rgb(226 232 240);
                border-radius: 1rem;
                overflow: hidden;
                box-shadow: 0 20px 45px -20px rgb(15 23 42 / 0.35);
            }

            .select2-search--dropdown {
                padding: 0.75rem;
                background: rgb(248 250 252);
            }

            .select2-search--dropdown .select2-search__field {
                border-color: rgb(203 213 225);
                border-radius: 0.75rem;
                padding: 0.65rem 0.85rem;
                font-size: 0.875rem;
                font-weight: 600;
                outline: none;
            }

            .select2-results__option {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
                font-weight: 600;
            }

            .select2-results__option--highlighted.select2-results__option--selectable {
                background: rgb(15 23 42);
                color: white;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const employeeSelect = document.querySelector('[data-leave-employee-select]');

                if (! employeeSelect) {
                    return;
                }

                const shopInput = document.querySelector('[data-leave-shop-id]');
                const contextLabel = document.getElementById('employee-leave-context');
                const areaLabel = document.getElementById('employee-leave-area');
                const categoryLabel = document.getElementById('employee-leave-category');
                const shopLabel = document.getElementById('employee-leave-shop');

                const syncLeaveContext = function () {
                    const selectedOption = employeeSelect.options[employeeSelect.selectedIndex];

                    if (! selectedOption || ! selectedOption.value) {
                        if (shopInput) {
                            shopInput.value = '';
                        }

                        contextLabel.textContent = 'Select employee to load leave context.';
                        areaLabel.textContent = 'Waiting for employee selection';
                        categoryLabel.textContent = 'Waiting for employee selection';
                        shopLabel.textContent = 'Automatic from employee profile';

                        return;
                    }

                    const staffArea = selectedOption.dataset.staffArea || 'office';
                    const categoryName = selectedOption.dataset.categoryName || 'Unassigned';
                    const shopId = selectedOption.dataset.shopId || '';
                    const shopName = selectedOption.dataset.shopName || '';

                    if (shopInput) {
                        shopInput.value = staffArea === 'shop' ? shopId : '';
                    }

                    areaLabel.textContent = staffArea === 'shop' ? 'Shop staff' : 'Office / HR-managed staff';
                    categoryLabel.textContent = categoryName;

                    if (staffArea === 'shop') {
                        shopLabel.textContent = shopName !== '' ? shopName : 'No default owned shop assigned';
                        contextLabel.textContent = shopName !== ''
                            ? `Shop leave request will be linked to ${shopName}.`
                            : 'Shop leave request will use the employee record without a default shop.';
                    } else {
                        shopLabel.textContent = 'No shop needed';
                        contextLabel.textContent = 'Office leave request will be submitted directly under HR.';
                    }
                };

                if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
                    const employeePicker = window.jQuery(employeeSelect);
                    employeePicker.addClass('staff-select2');
                    employeePicker.select2({
                        width: '100%',
                        placeholder: 'Search and select employee',
                        minimumResultsForSearch: 0,
                    });
                    employeePicker.on('change', syncLeaveContext);
                } else {
                    employeeSelect.addEventListener('change', syncLeaveContext);
                }

                syncLeaveContext();
            });
        </script>
    @endpush
</x-layouts.staff>
