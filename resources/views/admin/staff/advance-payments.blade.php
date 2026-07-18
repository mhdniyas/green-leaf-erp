<x-layouts.staff title="Advance Payments">
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Advance Payments</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Review shop-owner advance requests separately from salary payments.</p>
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
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">Paid From Shops</p>
                <p class="mt-2 text-2xl font-black text-cyan-900">Rs. {{ number_format($summary['paid_amount'], 2) }}</p>
            </article>
        </section>

        <section class="space-y-3">
            @forelse($advanceRequests as $advanceRequest)
                @php
                    $snapshot = $advanceRequest->rule_snapshot ?? [];
                    $alreadyAdvanced = (float) ($snapshot['already_advanced_amount'] ?? 0);
                    $available = (float) ($snapshot['available_amount'] ?? max(0, (float) $advanceRequest->eligible_amount - $alreadyAdvanced));
                    $statusClass = match ($advanceRequest->status) {
                        'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-amber-200 bg-amber-50 text-amber-700',
                    };
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-black text-slate-950">{{ $advanceRequest->employee?->name }}</h2>
                                <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $statusClass }}">{{ $advanceRequest->status }}</span>
                            </div>
                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ $advanceRequest->shop?->name }} · requested by {{ $advanceRequest->requestedBy?->name ?? 'Shop owner' }} · {{ $advanceRequest->requested_on->format('d M Y') }}</p>
                            @if($advanceRequest->request_note)
                                <p class="mt-2 text-sm font-semibold text-slate-700">{{ $advanceRequest->request_note }}</p>
                            @endif
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 lg:min-w-[28rem]">
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Requested</p>
                                <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) $advanceRequest->requested_amount, 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Eligible / Available</p>
                                <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) $advanceRequest->eligible_amount, 2) }} / Rs. {{ number_format($available, 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Already Taken</p>
                                <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format($alreadyAdvanced, 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Present / Earned</p>
                                <p class="mt-1 text-sm font-black text-slate-950">{{ number_format((float) ($snapshot['present_days'] ?? 0), 1) }} days / Rs. {{ number_format((float) ($snapshot['earned_amount'] ?? 0), 2) }}</p>
                            </div>
                        </div>
                    </div>

                    @if($advanceRequest->status === 'pending')
                        <form method="POST" action="{{ route('admin.staff.advance-requests.review', $advanceRequest) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                            @csrf
                            @method('PATCH')
                            <input type="number" step="0.01" min="0.01" name="approved_amount" value="{{ number_format((float) $advanceRequest->requested_amount, 2, '.', '') }}" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold">
                            <input type="text" name="review_note" placeholder="Review note" class="h-11 rounded-xl border border-slate-200 px-3 text-sm">
                            <div class="flex gap-2">
                                <button type="submit" name="decision" value="approve" class="flex-1 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white sm:flex-none">Approve</button>
                                <button type="submit" name="decision" value="reject" class="flex-1 rounded-xl bg-rose-600 px-4 py-2 text-sm font-black text-white sm:flex-none">Reject</button>
                            </div>
                        </form>
                    @else
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">
                            {{ $advanceRequest->reviewedBy?->name ? 'Reviewed by '.$advanceRequest->reviewedBy->name : 'Reviewed' }}
                            @if($advanceRequest->approved_amount)
                                · approved Rs. {{ number_format((float) $advanceRequest->approved_amount, 2) }}
                            @endif
                            @if($advanceRequest->shopStaffPayment)
                                · paid from {{ str($advanceRequest->shopStaffPayment->fund_source)->replace('_', ' ')->headline() }}
                            @endif
                            @if($advanceRequest->review_note)
                                · {{ $advanceRequest->review_note }}
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
