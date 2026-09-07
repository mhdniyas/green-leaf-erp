<!-- HISTORY TAB: FULL COMPREHENSIVE STAFF HISTORY -->
@php
    $prevMonth = $calendarMonth->copy()->subMonth();
    $nextMonth = $calendarMonth->copy()->addMonth();
    $daysInMonth = $calendarMonth->daysInMonth;
    $firstDayOfWeek = $calendarMonth->copy()->startOfMonth()->dayOfWeekIso; // 1 = Monday, 7 = Sunday
    $todayDate = today()->format('Y-m-d');
    $currentSelectedDate = $selectedDate->format('Y-m-d');
@endphp

<div class="space-y-4">
    {{-- Top Row: Attendance Calendar & Day Details --}}
    <section class="grid gap-3 lg:grid-cols-12">
        <!-- COMPACT CALENDAR CARD -->
        <article class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs space-y-3 lg:col-span-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $prevMonth->format('Y-m'), 'date' => $prevMonth->copy()->startOfMonth()->format('Y-m-d')]) }}" 
                   class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
                   title="Previous month">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </a>
                <div class="text-center">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">{{ $calendarMonth->format('F Y') }}</h2>
                    <p class="text-[10px] font-semibold text-slate-400">Attendance Calendar</p>
                </div>
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $nextMonth->format('Y-m'), 'date' => $nextMonth->copy()->startOfMonth()->format('Y-m-d')]) }}" 
                   class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
                   title="Next month">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5-7.5" />
                    </svg>
                </a>
            </div>

            <!-- 7-COLUMN CALENDAR (Mon-Sun) -->
            <div>
                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-black uppercase text-slate-400 pb-1.5">
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                    <div>Sun</div>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center">
                    @for($i = 1; $i < $firstDayOfWeek; $i++)
                        <div class="h-8"></div>
                    @endfor
                    @for($day = 1; $day <= $daysInMonth; $day++)
                        @php
                            $dayDateStr = $calendarMonth->copy()->day($day)->format('Y-m-d');
                            $isSelected = $dayDateStr === $currentSelectedDate;
                            $isToday = $dayDateStr === $todayDate;
                            $hasAttendance = $historyDatesWithAttendance->contains($dayDateStr);
                        @endphp
                        <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $calendarMonth->format('Y-m'), 'date' => $dayDateStr]) }}"
                           class="relative flex h-8 flex-col items-center justify-center rounded-lg text-xs font-bold transition
                                  {{ $isSelected ? 'bg-slate-950 text-white shadow-xs' : ($isToday ? 'border border-emerald-500 font-black text-emerald-950 bg-emerald-50/50' : 'text-slate-700 hover:bg-slate-100') }}">
                            <span>{{ $day }}</span>
                            @if($hasAttendance)
                                <span class="absolute bottom-0.5 h-1 w-1 rounded-full {{ $isSelected ? 'bg-emerald-400' : 'bg-emerald-600' }}"></span>
                            @endif
                        </a>
                    @endfor
                </div>
            </div>

            <div class="flex items-center justify-center gap-3 border-t border-slate-100 pt-2 text-[10px] font-semibold text-slate-400">
                <span class="flex items-center gap-1">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span> Has Records
                </span>
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-sm border border-emerald-500 bg-emerald-50"></span> Today
                </span>
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-sm bg-slate-950"></span> Selected
                </span>
            </div>
        </article>

        <!-- SELECTED DAY ATTENDANCE DETAILS -->
        <article class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs space-y-3 lg:col-span-7">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Selected Date Attendance</h3>
                    <p class="text-sm font-black text-slate-950">{{ $selectedDate->format('d M Y') }}</p>
                </div>
                @if($historyDayAttendance->isNotEmpty())
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black text-slate-700">
                        {{ $historyDayAttendance->count() }} marked
                    </span>
                @endif
            </div>

            <div class="divide-y divide-slate-100 max-h-[300px] overflow-y-auto">
                @forelse($historyDayAttendance as $att)
                    @php
                        $status = $att->status;
                    @endphp
                    <div class="py-2.5 space-y-1">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-xs font-black text-slate-950 truncate">{{ $att->employee?->name }}</p>
                                    <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border shrink-0 {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">
                                        {{ $status === 'present' ? '✓ Present' : str_replace('_', ' ', ucfirst((string) $status)) }}
                                    </span>
                                </div>
                                <p class="text-[10px] font-semibold text-slate-400">
                                    {{ $att->employee?->employee_code }}
                                    @if($att->marked_at)
                                        · Time: {{ $att->marked_at->timezone('Asia/Kolkata')->format('g:i A') }}
                                    @endif
                                    @if($att->markedBy)
                                        · By: {{ $att->markedBy->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if($att->notes)
                            <p class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-600">
                                {{ $att->notes }}
                            </p>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center text-xs font-semibold text-slate-400">
                        No attendance records for {{ $selectedDate->format('d M Y') }}.
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    @if(session('sync_results'))
        @php $syncRes = session('sync_results'); @endphp
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white font-black text-xs">✓</span>
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-emerald-950">Cashbook Sync Results</h3>
                        <p class="text-[11px] font-semibold text-emerald-800">Reconciled Staff History with Shop Cashbook entries.</p>
                    </div>
                </div>
                <span class="rounded-lg bg-emerald-200/70 px-2 py-1 text-[10px] font-black text-emerald-900">
                    {{ count($syncRes['created']) }} Created · {{ count($syncRes['updated']) }} Updated · {{ count($syncRes['matching']) }} Matching · {{ count($syncRes['orphans']) }} Orphans
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs font-bold">
                <div class="rounded-xl border border-emerald-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Created</p>
                    <p class="text-sm font-black text-emerald-700">{{ count($syncRes['created']) }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Updated</p>
                    <p class="text-sm font-black text-blue-700">{{ count($syncRes['updated']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Matching</p>
                    <p class="text-sm font-black text-slate-700">{{ count($syncRes['matching']) }}</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Orphans</p>
                    <p class="text-sm font-black text-rose-700">{{ count($syncRes['orphans']) }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Mismatched</p>
                    <p class="text-sm font-black text-amber-700">{{ count($syncRes['mismatched']) }}</p>
                </div>
            </div>

            @if(!empty($syncRes['orphans']))
                <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-black uppercase text-rose-900 tracking-wider">Orphan Cashbook Entries Detected</h4>
                        <span class="text-[10px] font-semibold text-rose-700">Staff payment no longer exists for these records</span>
                    </div>
                    <div class="divide-y divide-rose-100 max-h-48 overflow-y-auto">
                        @foreach($syncRes['orphans'] as $orphan)
                            <div class="py-2 flex items-center justify-between text-xs gap-2">
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 truncate">{{ $orphan['category'] }} · ₹{{ number_format((float) $orphan['amount'], 2) }}</p>
                                    <p class="text-[10px] text-slate-500 font-medium truncate">Date: {{ $orphan['business_date'] }} · Note: {{ $orphan['notes'] }}</p>
                                </div>
                                <form action="{{ route('shop-owner.staff.delete-cashbook-orphan') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this orphan Cashbook entry?');">
                                    @csrf
                                    <input type="hidden" name="transaction_id" value="{{ $orphan['transaction_id'] }}">
                                    <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
                                    <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                                    <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
                                    <button type="submit" class="rounded-lg bg-rose-600 px-2.5 py-1 text-[11px] font-black text-white hover:bg-rose-700 active:scale-95 transition cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    @endif

    {{-- Bottom Row: Full Salary & Advance Payments and Advance Requests --}}
    <section class="grid gap-4 lg:grid-cols-12">
        <!-- SALARY & ADVANCE PAYMENTS HISTORY TABLE -->
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 lg:col-span-7">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Salary & Advance Payments</h2>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">Recorded staff payouts with funding source & cashbook status.</p>
                </div>
                <div class="flex items-center gap-2">
                    <form action="{{ route('shop-owner.staff.sync-cashbook') }}" method="POST">
                        @csrf
                        <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
                        <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                        <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-800 shadow-2xs hover:bg-emerald-100 active:scale-95 transition cursor-pointer">
                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span>Sync with Cashbook</span>
                        </button>
                    </form>
                    <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black text-emerald-800">
                        {{ $recentPayrollPayments->total() }} Payouts
                    </span>
                </div>
            </div>

            <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto">
                @forelse($recentPayrollPayments as $payment)
                    <div class="py-2.5 flex items-center justify-between text-xs gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <p class="font-black text-slate-950 truncate">{{ $payment->employee?->name }}</p>
                                <span class="rounded px-1.5 py-0.2 text-[9px] font-black uppercase border {{ $payment->payment_type === 'advance' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                                    {{ $payment->payment_type ?? 'salary' }}
                                </span>
                            </div>
                            <p class="text-[10px] font-semibold text-slate-400 mt-0.5 truncate">
                                Paid: {{ $payment->paid_on?->format('d M Y') }} · Source: {{ str_replace('_', ' ', (string) $payment->fund_source) }}
                                @if($payment->notes) · Note: {{ $payment->notes }} @endif
                            </p>
                        </div>
                        <div class="text-right shrink-0 space-y-1">
                            <p class="font-black text-slate-950">₹{{ number_format((float) $payment->amount, 2) }}</p>
                            <span class="inline-block text-[9px] font-bold text-emerald-600">
                                ✓ Cashbook Synced
                            </span>
                            <div class="flex items-center justify-end gap-1.5 pt-0.5">
                                <button type="button" 
                                        onclick="openEditPaymentModal({{ json_encode([
                                            'id' => $payment->id,
                                            'employee_name' => $payment->employee?->name ?? 'Staff',
                                            'amount' => (float) $payment->amount,
                                            'paid_on' => $payment->paid_on?->format('Y-m-d') ?? today()->format('Y-m-d'),
                                            'payment_type' => $payment->payment_type ?? 'salary',
                                            'fund_source' => $payment->fund_source ?? 'sales',
                                            'notes' => $payment->notes ?? '',
                                            'update_url' => route('shop-owner.staff.payments.update', $payment),
                                        ]) }})"
                                        class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition cursor-pointer">
                                    Edit
                                </button>
                                <form action="{{ route('shop-owner.staff.payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this payment record? Any linked Cashbook entry will be detected during sync.');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-900 transition cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs font-semibold text-slate-400">
                        No payments recorded for this shop.
                    </div>
                @endforelse
            </div>

            @if($recentPayrollPayments->hasPages())
                <div class="border-t border-slate-100 pt-2">
                    {{ $recentPayrollPayments->links() }}
                </div>
            @endif
        </article>

        <!-- HR ADVANCE REQUESTS & APPROVALS -->
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 lg:col-span-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">HR Advance Requests</h2>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">Advance exceptions submitted for HR review.</p>
                </div>
            </div>

            <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto">
                @forelse($advanceRequests as $request)
                    <div class="py-2.5 flex items-center justify-between text-xs gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-black text-slate-950 truncate">{{ $request->employee?->name }}</p>
                            <p class="text-[10px] font-semibold text-slate-400 truncate">
                                {{ $request->requested_on?->format('d M Y') }} · Req: ₹{{ number_format((float) $request->requested_amount, 2) }}
                            </p>
                            @if($request->request_note)
                                <p class="text-[10px] font-medium text-slate-500 truncate mt-0.5">"{{ $request->request_note }}"</p>
                            @endif
                        </div>
                        <div class="text-right shrink-0">
                            @if($request->approval_status === 'approved')
                                <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border border-emerald-200 bg-emerald-50 text-emerald-800">Approved</span>
                            @elseif($request->approval_status === 'rejected')
                                <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border border-rose-200 bg-rose-50 text-rose-800">Rejected</span>
                            @else
                                <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border border-amber-200 bg-amber-50 text-amber-800">Pending HR</span>
                            @endif
                            @if($request->approved_amount)
                                <p class="text-[10px] font-black text-slate-800 mt-0.5">₹{{ number_format((float) $request->approved_amount, 2) }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs font-semibold text-slate-400">
                        No HR advance requests found.
                    </div>
                @endforelse
            </div>

            @if($advanceRequests->hasPages())
                <div class="border-t border-slate-100 pt-2">
                    {{ $advanceRequests->links() }}
                </div>
            @endif
        </article>
    </section>

    {{-- Edit Staff Payment Modal --}}
    <div id="edit-staff-payment-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-xl transition-all border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">Edit Staff Payment</h3>
                    <p id="edit-payment-employee-name" class="text-xs font-semibold text-slate-500 mt-0.5"></p>
                </div>
                <button type="button" onclick="closeEditPaymentModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="edit-staff-payment-form" method="POST" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">

                <div>
                    <label for="edit-payment-amount" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="edit-payment-amount" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="edit-payment-date" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Date</label>
                        <input type="date" name="paid_on" id="edit-payment-date" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label for="edit-payment-type" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Category</label>
                        <select name="payment_type" id="edit-payment-type" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="salary">Salary</option>
                            <option value="advance">Staff Advance</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="edit-payment-fund-source" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Fund Source</label>
                    <select name="fund_source" id="edit-payment-fund-source" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="sales">Shop Cash / Daily Sales</option>
                        <option value="petty_cash">Petty Cash</option>
                        <option value="company">Company Account</option>
                    </select>
                </div>

                <div>
                    <label for="edit-payment-notes" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Notes / Remarks</label>
                    <textarea name="notes" id="edit-payment-notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500" placeholder="Optional payment note"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" onclick="closeEditPaymentModal()" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-700 active:scale-95 transition cursor-pointer shadow-xs">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditPaymentModal(data) {
            const modal = document.getElementById('edit-staff-payment-modal');
            const form = document.getElementById('edit-staff-payment-form');
            if (!modal || !form) return;

            document.getElementById('edit-payment-employee-name').textContent = data.employee_name;
            document.getElementById('edit-payment-amount').value = data.amount;
            document.getElementById('edit-payment-date').value = data.paid_on;
            document.getElementById('edit-payment-type').value = data.payment_type;
            document.getElementById('edit-payment-fund-source').value = (data.fund_source === 'petty' ? 'petty_cash' : data.fund_source);
            document.getElementById('edit-payment-notes').value = data.notes || '';
            form.action = data.update_url;

            modal.classList.remove('hidden');
        }

        function closeEditPaymentModal() {
            const modal = document.getElementById('edit-staff-payment-modal');
            if (modal) modal.classList.add('hidden');
        }
    </script>
</div>
