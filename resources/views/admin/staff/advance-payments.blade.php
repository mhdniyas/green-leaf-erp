<x-layouts.staff title="Advance Payments">
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Advance Payments</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Review shop-incharge advance requests separately from salary payments.</p>
            </div>

            <form method="GET" action="{{ route('admin.staff.advance-payments.index') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <input type="month" name="payroll_month" value="{{ $selectedPayrollMonth->format('Y-m') }}" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold">
                <select name="status" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold">
                    <option value="all" @selected($status === 'all')>All status</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="approved" @selected($status === 'approved')>Approved</option>
                    <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                </select>
                <select name="shop_id" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold">
                    <option value="0">All shops</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}" @selected($selectedShopId === $shop->id)>{{ $shop->name }}</option>
                    @endforeach
                </select>
                <select name="employee_id" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold">
                    <option value="0">All employees</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($selectedEmployeeId === $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="h-11 rounded-xl bg-slate-950 px-4 text-sm font-black text-white">Apply</button>
            </form>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Pending</p>
                <p class="mt-2 text-2xl font-black text-amber-900">{{ number_format($summary['pending_count']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Requested</p>
                <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($summary['requested_amount'], 2) }}</p>
            </article>
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Approved</p>
                <p class="mt-2 text-2xl font-black text-emerald-900">Rs. {{ number_format($summary['approved_amount'], 2) }}</p>
            </article>
            <article class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">Posted To Cashbook</p>
                <p class="mt-2 text-2xl font-black text-cyan-900">Rs. {{ number_format($summary['paid_amount'], 2) }}</p>
            </article>
        </section>

        <section class="space-y-3">
            @forelse($advanceRequests as $advanceRequest)
                @php
                    $snapshot = $advanceRequest->rule_snapshot ?? [];
                    $earnedAmount = (float) ($snapshot['earned_amount'] ?? 0);
                    $managerLimit = (float) ($snapshot['eligible_amount'] ?? round($earnedAmount * 0.5, 2));
                    $alreadyAdvanced = (float) ($snapshot['already_advanced_amount'] ?? 0);
                    $availableWithoutHr = (float) ($snapshot['available_amount'] ?? max(0, $managerLimit - $alreadyAdvanced));
                    $requestedAmount = (float) $advanceRequest->requested_amount;
                    $aboveLimit = max(0, round($requestedAmount - $availableWithoutHr, 2));
                    $statusClass = match ($advanceRequest->status) {
                        'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-amber-200 bg-amber-50 text-amber-700',
                    };
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance Request</span>
                                <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $statusClass }}">{{ $advanceRequest->status }}</span>
                            </div>
                            <h2 class="text-xl font-black text-slate-950 mt-0.5">{{ $advanceRequest->employee?->name }}</h2>
                            <p class="text-xs font-bold text-slate-500">{{ $advanceRequest->shop?->name }} · Requested on {{ $advanceRequest->requested_on->format('d M Y') }} by {{ $advanceRequest->requestedBy?->name ?? 'Shop incharge' }}</p>
                        </div>
                        <div class="sm:text-right">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Requested Amount</span>
                            <p class="text-2xl font-black text-indigo-600">₹{{ number_format($requestedAmount, 2) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Accrued Salary</p>
                            <p class="text-sm font-black text-slate-900 mt-1">₹{{ number_format($earnedAmount, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">50% Manager Limit</p>
                            <p class="text-sm font-black text-slate-900 mt-1">₹{{ number_format($managerLimit, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Already Advanced</p>
                            <p class="text-sm font-black text-slate-900 mt-1">₹{{ number_format($alreadyAdvanced, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Available Without HR</p>
                            <p class="text-sm font-black text-slate-900 mt-1">₹{{ number_format($availableWithoutHr, 2) }}</p>
                        </div>
                        <div class="col-span-2 sm:col-span-1 rounded-lg bg-amber-100/60 p-2.5 border border-amber-200/80">
                            <p class="text-[10px] font-black uppercase tracking-wider text-amber-800">Above Manager Limit</p>
                            <p class="text-sm font-black text-amber-950 mt-0.5">₹{{ number_format($aboveLimit, 2) }}</p>
                        </div>
                    </div>

                    @if($advanceRequest->request_note)
                        <div class="rounded-xl bg-slate-100/70 p-3 text-sm">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Reason</span>
                            <p class="font-medium text-slate-800">{{ $advanceRequest->request_note }}</p>
                        </div>
                    @endif

                    @if($advanceRequest->status === 'pending')
                        <form method="POST" action="{{ route('admin.staff.advance-requests.review', $advanceRequest) }}" class="pt-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="fund_source" value="{{ $advanceRequest->fund_source }}">
                            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                                <div class="w-full sm:w-48">
                                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Approved Amount (₹)</label>
                                    <input type="number" step="0.01" min="0.01" name="approved_amount" value="{{ number_format((float) $advanceRequest->requested_amount, 2, '.', '') }}" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-900 focus:border-slate-950 focus:ring-0">
                                </div>
                                <div class="flex-1">
                                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Review Note (Required if Rejecting)</label>
                                    <input type="text" name="review_note" placeholder="Add a note or reason for rejection/approval..." class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900 focus:border-slate-950 focus:ring-0">
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <button type="submit" name="decision" value="reject" class="h-10 rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-black uppercase tracking-wider text-rose-700 hover:bg-rose-100 transition">Reject</button>
                                    <button type="submit" name="decision" value="approve" class="h-10 rounded-xl bg-emerald-600 px-5 text-xs font-black uppercase tracking-wider text-white shadow hover:bg-emerald-700 transition">Approve</button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-600">
                            {{ $advanceRequest->reviewedBy?->name ? 'Reviewed by '.$advanceRequest->reviewedBy->name : 'Reviewed' }}
                            @if($advanceRequest->approved_amount)
                                · Approved ₹{{ number_format((float) $advanceRequest->approved_amount, 2) }}
                            @endif
                            @if($advanceRequest->shopStaffPayment)
                                · {{ $advanceRequest->shopStaffPayment->cashbookLine ? 'Posted to shop cashbook' : 'Cashbook posting pending' }}
                            @endif
                            @if($advanceRequest->review_note)
                                · Note: {{ $advanceRequest->review_note }}
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <p class="text-lg font-black text-slate-900">No advance requests found.</p>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Change the filters or payroll month to review another period.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.staff>
