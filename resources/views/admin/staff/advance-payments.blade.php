<x-layouts.staff title="Advance Payments">
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Advance Payments</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Review, edit amount/date, delete, and manage shop-incharge advance requests.</p>
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
                    $approvedAmount = $advanceRequest->approved_amount !== null ? (float) $advanceRequest->approved_amount : null;
                    $currentEffectiveAmount = $advanceRequest->status === 'approved' && $approvedAmount !== null ? $approvedAmount : $requestedAmount;
                    $aboveLimit = max(0, round($requestedAmount - $availableWithoutHr, 2));
                    $statusClass = match ($advanceRequest->status) {
                        'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-amber-200 bg-amber-50 text-amber-700',
                    };
                    $currentDateFormatted = $advanceRequest->requested_on ? $advanceRequest->requested_on->format('Y-m-d') : ($advanceRequest->created_at ? $advanceRequest->created_at->format('Y-m-d') : today()->format('Y-m-d'));
                    $currentNote = $advanceRequest->status === 'approved' ? ($advanceRequest->review_note ?? $advanceRequest->request_note) : ($advanceRequest->request_note ?? $advanceRequest->review_note);
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4" x-data="{ showEditModal: false }">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance Request #{{ $advanceRequest->id }}</span>
                                <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $statusClass }}">{{ $advanceRequest->status }}</span>
                            </div>
                            <h2 class="text-xl font-black text-slate-950 mt-0.5">{{ $advanceRequest->employee?->name }}</h2>
                            <p class="text-xs font-bold text-slate-500">{{ $advanceRequest->shop?->name }} · Date: {{ $advanceRequest->requested_on ? $advanceRequest->requested_on->format('d M Y') : 'N/A' }} · By {{ $advanceRequest->requestedBy?->name ?? 'Shop incharge' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                            <div class="text-right">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                                    {{ $advanceRequest->status === 'approved' ? 'Approved Amount' : 'Requested Amount' }}
                                </span>
                                <p class="text-2xl font-black {{ $advanceRequest->status === 'approved' ? 'text-emerald-600' : 'text-indigo-600' }}">₹{{ number_format($currentEffectiveAmount, 2) }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 border-l border-slate-200 pl-3">
                                <button type="button" @click="showEditModal = true" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition flex items-center gap-1.5 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('admin.staff.advance-requests.destroy', $advanceRequest) }}" onsubmit="return confirm('Are you sure you want to delete advance request #{{ $advanceRequest->id }} for {{ addslashes($advanceRequest->employee?->name ?? 'Staff') }}? Associated cashbook and accounting entries will also be removed.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700 hover:bg-rose-100 hover:text-rose-900 transition flex items-center gap-1.5 shadow-sm">
                                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
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
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Reason / Note</span>
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
                                · {{ $advanceRequest->shopStaffPayment->cashbookLine ? 'Posted to shop cashbook' : 'Cashbook posting synced' }}
                            @endif
                            @if($advanceRequest->review_note)
                                · Note: {{ $advanceRequest->review_note }}
                            @endif
                        </div>
                    @endif

                    <!-- Edit Advance Modal -->
                    <template x-if="showEditModal">
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" @click.self="showEditModal = false" x-cloak>
                            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <div>
                                        <h3 class="text-lg font-black text-slate-950">Edit Advance Payment #{{ $advanceRequest->id }}</h3>
                                        <p class="text-xs font-semibold text-slate-500">{{ $advanceRequest->employee?->name }} · {{ $advanceRequest->shop?->name }}</p>
                                    </div>
                                    <button type="button" @click="showEditModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('admin.staff.advance-requests.update', $advanceRequest) }}" class="space-y-4">
                                    @csrf
                                    @method('PUT')

                                    <div class="space-y-1">
                                        <label class="text-xs font-black uppercase tracking-wider text-slate-600">Advance Amount (₹)</label>
                                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ number_format($currentEffectiveAmount, 2, '.', '') }}" required class="h-11 w-full rounded-xl border border-slate-300 px-3 text-base font-black text-slate-950 focus:border-slate-950 focus:ring-0">
                                        <p class="text-[11px] text-slate-500 font-medium">Updating the amount will also update the linked payment and recalculate cashbook balances.</p>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-xs font-black uppercase tracking-wider text-slate-600">Payment / Request Date</label>
                                        <input type="date" name="requested_on" value="{{ $currentDateFormatted }}" required class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm font-bold text-slate-950 focus:border-slate-950 focus:ring-0">
                                        <p class="text-[11px] text-slate-500 font-medium">Changing date will automatically shift the daily ledger and accounting records to the new date.</p>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-xs font-black uppercase tracking-wider text-slate-600">Notes / Reason (Optional)</label>
                                        <textarea name="note" rows="2" placeholder="Update notes or reason for this advance..." class="w-full rounded-xl border border-slate-300 p-3 text-sm text-slate-950 focus:border-slate-950 focus:ring-0">{{ $currentNote }}</textarea>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                        <button type="button" @click="showEditModal = false" class="h-10 rounded-xl border border-slate-200 bg-slate-100 px-4 text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-200 transition">Cancel</button>
                                        <button type="submit" class="h-10 rounded-xl bg-slate-950 px-5 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800 transition shadow">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
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
