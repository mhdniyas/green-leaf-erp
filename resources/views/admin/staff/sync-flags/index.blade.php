<x-layouts.staff title="Staff Sync Flags">
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Page Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.staff.index') }}" class="text-xs font-bold text-cyan-700 hover:underline">← Back to Staff Dashboard</a>
                <div class="mt-1 flex items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-950">Staff Sync Flags</h1>
                    @if($openFlagsCount > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-3 py-1 text-xs font-black text-rose-700 animate-pulse">
                            <span>🚩</span>
                            <span>{{ $openFlagsCount }} Active {{ Str::plural('Issue', $openFlagsCount) }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-black text-emerald-700">
                            <span>✓</span>
                            <span>All Records Synced</span>
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm font-semibold text-slate-500">Detect & repair discrepancies between Shop Staff History, Admin HR History, Cashbook, and Salary/Advance state.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <form action="{{ route('admin.staff.sync-flags.scan') }}" method="POST">
                    @csrf
                    @if($selectedShopId)<input type="hidden" name="shop_id" value="{{ $selectedShopId }}">@endif
                    @if($selectedEmployeeId)<input type="hidden" name="employee_id" value="{{ $selectedEmployeeId }}">@endif
                    @if($selectedMonth)<input type="hidden" name="month" value="{{ $selectedMonth }}">@endif
                    @if($selectedPaymentType)<input type="hidden" name="payment_type" value="{{ $selectedPaymentType }}">@endif
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-slate-800 active:scale-95 transition cursor-pointer">
                        <svg class="h-4 w-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Scan & Verify All</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- KPI Overview Cards --}}
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <article class="rounded-2xl border {{ $openFlagsCount > 0 ? 'border-rose-200 bg-rose-50/70' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider {{ $openFlagsCount > 0 ? 'text-rose-700' : 'text-slate-400' }}">Active Open Flags</p>
                <p class="mt-2 text-2xl font-black {{ $openFlagsCount > 0 ? 'text-rose-950' : 'text-slate-950' }}">{{ $openFlagsCount }}</p>
                <p class="mt-1 text-[11px] font-semibold text-slate-500">Unresolved discrepancies</p>
            </article>

            <article class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider text-amber-800">Cashbook Mismatches</p>
                <p class="mt-2 text-2xl font-black text-amber-950">{{ $cashbookMismatchesCount }}</p>
                <p class="mt-1 text-[11px] font-semibold text-amber-700">Amount / date / shop issues</p>
            </article>

            <article class="rounded-2xl border border-red-200 bg-red-50/60 p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider text-red-800">Orphan Cashbooks</p>
                <p class="mt-2 text-2xl font-black text-red-950">{{ $orphanCashbooksCount }}</p>
                <p class="mt-1 text-[11px] font-semibold text-red-700">Missing parent payment</p>
            </article>

            <article class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider text-cyan-800">Salary & Advance Flags</p>
                <p class="mt-2 text-2xl font-black text-cyan-950">{{ $advanceSalaryMismatchesCount }}</p>
                <p class="mt-1 text-[11px] font-semibold text-cyan-700">Balance calculation issues</p>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider text-emerald-800">Resolved Records</p>
                <p class="mt-2 text-2xl font-black text-emerald-950">{{ $resolvedCount }}</p>
                <p class="mt-1 text-[11px] font-semibold text-emerald-700">Verified & clean</p>
            </article>
        </section>

        {{-- Filters & Search Bar --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('admin.staff.sync-flags.index') }}" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    {{-- Status Switcher Tabs --}}
                    <div class="inline-flex rounded-xl bg-slate-100 p-1">
                        <a href="{{ route('admin.staff.sync-flags.index', array_merge(request()->query(), ['status' => 'open'])) }}"
                           class="rounded-lg px-3 py-1.5 text-xs font-black transition {{ $selectedStatus === 'open' ? 'bg-white text-slate-950 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Open Issues ({{ $openFlagsCount }})
                        </a>
                        <a href="{{ route('admin.staff.sync-flags.index', array_merge(request()->query(), ['status' => 'resolved'])) }}"
                           class="rounded-lg px-3 py-1.5 text-xs font-black transition {{ $selectedStatus === 'resolved' ? 'bg-white text-slate-950 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Resolved ({{ $resolvedCount }})
                        </a>
                        <a href="{{ route('admin.staff.sync-flags.index', array_merge(request()->query(), ['status' => 'all'])) }}"
                           class="rounded-lg px-3 py-1.5 text-xs font-black transition {{ $selectedStatus === 'all' ? 'bg-white text-slate-950 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            All Records
                        </a>
                    </div>
                    <input type="hidden" name="status" value="{{ $selectedStatus }}">

                    <div class="text-xs font-semibold text-slate-400">
                        Showing {{ $flags->firstItem() ?? 0 }} - {{ $flags->lastItem() ?? 0 }} of {{ $flags->total() }} flags
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Search Keywords</label>
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search employee, shop, issue..." class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-cyan-600 focus:ring-cyan-600">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Shop</label>
                        <select name="shop_id" class="h-10 w-full rounded-xl border border-slate-200 px-2.5 text-xs font-bold text-slate-900 focus:border-cyan-600 focus:ring-cyan-600">
                            <option value="">All Shops</option>
                            @foreach($shops as $s)
                                <option value="{{ $s->id }}" @selected($selectedShopId == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Employee</label>
                        <select name="employee_id" class="h-10 w-full rounded-xl border border-slate-200 px-2.5 text-xs font-bold text-slate-900 focus:border-cyan-600 focus:ring-cyan-600">
                            <option value="">All Employees</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" @selected($selectedEmployeeId == $emp->id)>{{ $emp->name }} ({{ $emp->employee_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Month</label>
                        <input type="month" name="month" value="{{ $selectedMonth }}" class="h-10 w-full rounded-xl border border-slate-200 px-2.5 text-xs font-bold text-slate-900 focus:border-cyan-600 focus:ring-cyan-600">
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="h-10 flex-1 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800 transition cursor-pointer">
                            Filter
                        </button>
                        @if($selectedShopId || $selectedEmployeeId || $selectedMonth || $selectedPaymentType || $selectedFlagCode || $search)
                            <a href="{{ route('admin.staff.sync-flags.index', ['status' => $selectedStatus]) }}" class="h-10 rounded-xl border border-slate-200 bg-white px-3 flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </section>

        {{-- Flags Table --}}
        <section class="rounded-3xl border border-slate-200 bg-white overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="px-3.5 py-3">Flag / Issue</th>
                            <th class="px-3.5 py-3">Employee</th>
                            <th class="px-3.5 py-3">Shop</th>
                            <th class="px-3.5 py-3">Payment Date</th>
                            <th class="px-3.5 py-3">Type</th>
                            <th class="px-3.5 py-3 text-right">Master Amount</th>
                            <th class="px-3.5 py-3 text-center">Shop Hist.</th>
                            <th class="px-3.5 py-3 text-center">HR Hist.</th>
                            <th class="px-3.5 py-3 text-center">Cashbook</th>
                            <th class="px-3.5 py-3">Status</th>
                            <th class="px-3.5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($flags as $flag)
                            @php
                                $details = $flag->details ?? [];
                                $master = $details['master'] ?? null;
                                $cashbook = $details['cashbook'] ?? null;
                                $shopHist = $details['shop_history'] ?? null;
                                $hrHist = $details['hr_history'] ?? null;
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition {{ $flag->status === 'open' ? 'bg-rose-50/20' : '' }}">
                                <td class="px-3.5 py-3">
                                    <div class="flex items-start gap-2">
                                        <span class="text-sm shrink-0">{{ $flag->status === 'open' ? '🚩' : '✓' }}</span>
                                        <div>
                                            <p class="font-black text-slate-950 leading-tight">{{ $flag->title }}</p>
                                            <p class="text-[10px] text-slate-500 font-semibold mt-0.5 max-w-xs">{{ $flag->description }}</p>
                                            <span class="mt-1 inline-block text-[9px] font-black uppercase tracking-wider rounded px-1.5 py-0.2 {{ $flag->status === 'open' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                                                {{ $flag->flag_code }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3.5 py-3 whitespace-nowrap">
                                    @if($flag->employee)
                                        <a href="{{ route('admin.staff.show', $flag->employee->employee_code) }}" class="font-bold text-cyan-700 hover:underline">
                                            {{ $flag->employee->name }}
                                        </a>
                                        <span class="block text-[10px] text-slate-400 font-semibold">{{ $flag->employee->employee_code }}</span>
                                    @else
                                        <span class="text-slate-400 font-semibold">{{ $master['employee_name'] ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 font-semibold text-slate-700 whitespace-nowrap">
                                    {{ $flag->shop?->name ?? $master['shop_name'] ?? '—' }}
                                </td>
                                <td class="px-3.5 py-3 font-bold text-slate-900 whitespace-nowrap">
                                    {{ $flag->payment_date ? $flag->payment_date->format('d M Y') : ($master['paid_on'] ?? '—') }}
                                </td>
                                <td class="px-3.5 py-3 whitespace-nowrap">
                                    @php($pType = $master['payment_type'] ?? 'salary')
                                    <span class="rounded px-2 py-0.5 text-[10px] font-black uppercase border {{ $pType === 'advance' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                                        {{ $pType === 'advance' ? 'Advance' : 'Salary' }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-3 text-right font-black text-slate-950 whitespace-nowrap">
                                    @if(isset($master['amount']))
                                        ₹{{ number_format((float) $master['amount'], 2) }}
                                    @else
                                        <span class="text-slate-400 font-semibold">None (Orphan)</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-center whitespace-nowrap">
                                    @if(isset($shopHist['amount']))
                                        <span class="inline-flex items-center gap-1 font-bold text-slate-800">
                                            <span>₹{{ number_format((float) $shopHist['amount'], 2) }}</span>
                                            <span class="text-emerald-600">✓</span>
                                        </span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-center whitespace-nowrap">
                                    @if(isset($hrHist['amount']))
                                        <span class="inline-flex items-center gap-1 font-bold text-slate-800">
                                            <span>₹{{ number_format((float) $hrHist['amount'], 2) }}</span>
                                            <span class="text-emerald-600">✓</span>
                                        </span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-center whitespace-nowrap">
                                    @if($cashbook && isset($cashbook['amount']))
                                        @php($amountMatches = isset($master['amount']) && abs((float)$cashbook['amount'] - (float)$master['amount']) < 0.01)
                                        <span class="inline-flex items-center gap-1 font-bold {{ $amountMatches ? 'text-slate-800' : 'text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200' }}">
                                            <span>₹{{ number_format((float) $cashbook['amount'], 2) }}</span>
                                            <span>{{ $amountMatches ? '✓' : '✕' }}</span>
                                        </span>
                                    @else
                                        <span class="rounded bg-rose-50 border border-rose-200 px-1.5 py-0.5 text-[10px] font-black text-rose-700">Missing</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 whitespace-nowrap">
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase border {{ $flag->status === 'open' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        {{ $flag->status }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button"
                                                onclick="openFlagReviewModal({{ $flag->id }})"
                                                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800 active:scale-95 transition shadow-2xs cursor-pointer">
                                            <span>Fix</span>
                                            <svg class="h-3 w-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-xs font-semibold text-slate-400">
                                    <span class="text-xl block mb-1">🎉</span>
                                    No flags found for the selected criteria. All staff payment and Cashbook projections are verified!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($flags->hasPages())
                <div class="border-t border-slate-100 p-4">
                    {{ $flags->links() }}
                </div>
            @endif
        </section>
    </div>

    {{-- Comprehensive Admin Decision Fix Popup Modal --}}
    <div id="flag-review-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
        <div class="relative w-full max-w-4xl rounded-3xl bg-white p-5 sm:p-7 shadow-2xl transition-all border border-slate-200 max-h-[92vh] overflow-y-auto space-y-5">
            {{-- Modal Top Header --}}
            <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center justify-center rounded-lg bg-emerald-100 text-emerald-800 p-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <h3 class="text-base sm:text-lg font-black uppercase tracking-wider text-slate-950">Fix Discrepancy — Admin Decision</h3>
                        <span id="review-modal-issues-badge" class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black text-rose-800">
                            Discrepancy Detected
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-semibold mt-1">The system detects differences across records. As Admin, your decision sets the final correct value across all systems.</p>
                </div>
                <button type="button" onclick="closeFlagReviewModal()" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Summary Key Meta Bar --}}
            <div id="review-meta-bar" class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-2xl bg-slate-50 border border-slate-200 p-3.5 text-xs">
                <div>
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Employee</span>
                    <span id="meta-employee" class="font-black text-slate-900 truncate block mt-0.5">—</span>
                </div>
                <div>
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Shop</span>
                    <span id="meta-shop" class="font-black text-slate-900 truncate block mt-0.5">—</span>
                </div>
                <div>
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Date</span>
                    <span id="meta-date" class="font-black text-slate-900 block mt-0.5">—</span>
                </div>
                <div>
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Record Type</span>
                    <span id="meta-type" class="font-black text-slate-900 block mt-0.5">—</span>
                </div>
            </div>

            {{-- Section 1: WHAT IS WRONG / WHAT IS MISSING --}}
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50/40 p-4 space-y-2.5">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-black uppercase tracking-wider text-rose-950 flex items-center gap-1.5">
                        <span>🔍</span>
                        <span>Detected Problems & Differences</span>
                    </h4>
                    <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider">Automated Audit</span>
                </div>
                <div id="detected-problems-list" class="grid sm:grid-cols-2 gap-2 text-xs">
                    {{-- Dynamically populated --}}
                </div>
            </div>

            {{-- Main Decision Form --}}
            <form id="admin-decision-form" method="POST" class="space-y-5">
                @csrf

                {{-- Orphan Notice & Mode Selector --}}
                <div id="orphan-mode-container" class="hidden rounded-2xl border border-amber-300 bg-amber-50/80 p-4 space-y-3">
                    <div class="flex items-start gap-2">
                        <span class="text-amber-700 text-base font-black">⚠</span>
                        <div>
                            <p class="font-black text-amber-950 text-xs uppercase tracking-wider">Orphan Cashbook Transaction</p>
                            <p id="orphan-description-text" class="text-xs text-amber-800 font-semibold mt-0.5">
                                This Cashbook entry exists without a matching ShopStaffPayment record.
                            </p>
                        </div>
                    </div>
                    <div class="space-y-2 pt-1 border-t border-amber-200/60 text-xs font-bold text-amber-950">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="orphan_action" value="create_payment" id="orphan-action-create" checked onchange="toggleOrphanMode()" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500">
                            <span>This payment should exist <span class="text-amber-700 font-normal">→ Create / restore Staff Payment using Admin-selected values below</span></span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="orphan_action" value="remove_cashbook" id="orphan-action-remove" onchange="toggleOrphanMode()" class="h-4 w-4 text-rose-600 focus:ring-rose-500">
                            <span>This Cashbook entry is wrong <span class="text-rose-700 font-normal">→ Remove / void Cashbook entry and recalculate balances</span></span>
                        </label>
                    </div>
                </div>

                {{-- Missing Cashbook Notice & Mode Selector --}}
                <div id="missing-cashbook-mode-container" class="hidden rounded-2xl border border-rose-200 bg-rose-50/70 p-4 space-y-3">
                    <div class="flex items-start gap-2">
                        <span class="text-rose-700 text-base font-black">⚠</span>
                        <div>
                            <p class="font-black text-rose-950 text-xs uppercase tracking-wider">Missing Cashbook Entry</p>
                            <p class="text-xs text-rose-800 font-semibold mt-0.5">
                                ShopStaffPayment exists in HR/Shop history, but no corresponding Cashbook expense entry was found.
                            </p>
                        </div>
                    </div>
                    <div class="space-y-2 pt-1 border-t border-rose-200/60 text-xs font-bold text-slate-900">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="action" value="apply_final_value" id="missing-cb-action-create" checked onchange="toggleMissingCashbookMode()" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500">
                            <span>Create / synchronize Cashbook entry with Admin-selected final values</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="action" value="cancel_payment" id="missing-cb-action-cancel" onchange="toggleMissingCashbookMode()" class="h-4 w-4 text-rose-600 focus:ring-rose-500">
                            <span>Cancel / void this Staff Payment <span class="text-rose-600 font-normal">(Payment was recorded in error)</span></span>
                        </label>
                    </div>
                </div>

                {{-- Section 2 & 3: SHOW ALL EXISTING VALUES & ADMIN CHOOSES FINAL VALUE --}}
                <div id="decision-fields-card" class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs space-y-0 divide-y divide-slate-100">
                    <div class="bg-slate-50/80 px-4 py-3 flex items-center justify-between">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-950">Field Values Across Sources & Final Decision</h4>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Admin Chooses Truth</span>
                    </div>

                    {{-- Employee Selector (Visible for Orphan Cases) --}}
                    <div id="field-row-employee" class="hidden p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-center bg-amber-50/30">
                        <div class="md:col-span-4">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">FIELD</span>
                            <span class="text-xs font-black text-slate-900">EMPLOYEE</span>
                            <span class="block text-[11px] text-slate-500 font-semibold mt-0.5">Select recipient for orphan entry</span>
                        </div>
                        <div class="md:col-span-8">
                            <label for="final-input-employee" class="block text-[10px] font-black uppercase tracking-wider text-emerald-800 mb-1">Final Employee *</label>
                            <select name="employee_id" id="final-input-employee" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 1. AMOUNT ROW --}}
                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">FIELD</span>
                            <span class="text-xs font-black text-slate-950">AMOUNT (₹)</span>
                        </div>
                        <div class="md:col-span-5 grid grid-cols-3 gap-2 text-xs font-semibold text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Shop Staff</span>
                                <span id="source-amount-shop" class="font-black text-slate-900 block mt-0.5">—</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">HR History</span>
                                <span id="source-amount-hr" class="font-black text-slate-900 block mt-0.5">—</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Cashbook</span>
                                <span id="source-amount-cashbook" class="font-black block mt-0.5">—</span>
                            </div>
                        </div>
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label for="final-input-amount" class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Final Amount (₹) *</label>
                                <span id="status-badge-amount" class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded"></span>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-black text-slate-400">₹</span>
                                <input type="number" step="0.01" min="0.01" name="amount" id="final-input-amount" required class="w-full rounded-xl border border-slate-300 bg-white pl-7 pr-3 py-2 text-xs font-black text-slate-950 shadow-2xs focus:border-emerald-600 focus:ring-emerald-600">
                            </div>
                        </div>
                    </div>

                    {{-- 2. CATEGORY / TYPE ROW --}}
                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">FIELD</span>
                            <span class="text-xs font-black text-slate-950">TYPE / CATEGORY</span>
                        </div>
                        <div class="md:col-span-5 grid grid-cols-3 gap-2 text-xs font-semibold text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Shop Staff</span>
                                <span id="source-type-shop" class="font-bold text-slate-900 block mt-0.5">—</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">HR History</span>
                                <span id="source-type-hr" class="font-bold text-slate-900 block mt-0.5">—</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Cashbook</span>
                                <span id="source-type-cashbook" class="font-bold block mt-0.5">—</span>
                            </div>
                        </div>
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label for="final-input-type" class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Final Category *</label>
                                <span id="status-badge-type" class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded"></span>
                            </div>
                            <select name="payment_type" id="final-input-type" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="advance">Staff Advance</option>
                                <option value="salary">Salary</option>
                            </select>
                        </div>
                    </div>

                    {{-- 3. DATE ROW --}}
                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">FIELD</span>
                            <span class="text-xs font-black text-slate-950">PAYMENT DATE</span>
                        </div>
                        <div class="md:col-span-5 grid grid-cols-3 gap-2 text-xs font-semibold text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Shop Staff</span>
                                <span id="source-date-shop" class="font-bold text-slate-900 block mt-0.5">—</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">HR History</span>
                                <span id="source-date-hr" class="font-bold text-slate-900 block mt-0.5">—</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Cashbook</span>
                                <span id="source-date-cashbook" class="font-bold block mt-0.5">—</span>
                            </div>
                        </div>
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label for="final-input-date" class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Final Date *</label>
                                <span id="status-badge-date" class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded"></span>
                            </div>
                            <input type="date" name="paid_on" id="final-input-date" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-600 focus:ring-emerald-600">
                        </div>
                    </div>

                    {{-- 4. FUND SOURCE ROW --}}
                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">FIELD</span>
                            <span class="text-xs font-black text-slate-950">FUND SOURCE</span>
                        </div>
                        <div class="md:col-span-5 space-y-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-xs">
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Shop Staff</span>
                                    <span id="source-fs-shop" class="font-bold text-slate-900 block mt-0.5">—</span>
                                </div>
                                <div>
                                    <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">HR History</span>
                                    <span id="source-fs-hr" class="font-bold text-slate-900 block mt-0.5">—</span>
                                </div>
                                <div>
                                    <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Cashbook</span>
                                    <span id="source-fs-cashbook" class="font-bold block mt-0.5">—</span>
                                </div>
                            </div>
                            <p id="source-fs-canonical" class="text-[10px] font-bold text-cyan-800 pt-0.5"></p>
                        </div>
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label for="final-input-fund-source" class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Final Fund Source *</label>
                                <span id="status-badge-fs" class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded"></span>
                            </div>
                            <select name="fund_source" id="final-input-fund-source" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="sales">Shop Cash / Sales (sales)</option>
                                <option value="petty_cash">Petty Cash (petty)</option>
                                <option value="company">Company Account (company)</option>
                            </select>
                        </div>
                    </div>

                    {{-- 5. SHOP ROW --}}
                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">FIELD</span>
                            <span class="text-xs font-black text-slate-950">SHOP LOCATION</span>
                        </div>
                        <div class="md:col-span-5 grid grid-cols-2 gap-2 text-xs font-semibold text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Shop / HR Record</span>
                                <span id="source-shop-staff" class="font-bold text-slate-900 block mt-0.5">—</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Cashbook Ledger</span>
                                <span id="source-shop-cashbook" class="font-bold block mt-0.5">—</span>
                            </div>
                        </div>
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label for="final-input-shop" class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Final Shop *</label>
                                <span id="status-badge-shop" class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded"></span>
                            </div>
                            <select name="shop_id" id="final-input-shop" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach($shops as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 6. NOTES ROW --}}
                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                        <div class="md:col-span-3">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">FIELD</span>
                            <span class="text-xs font-black text-slate-950">AUDIT NOTES</span>
                        </div>
                        <div class="md:col-span-9">
                            <label for="final-input-notes" class="block text-[10px] font-black uppercase tracking-wider text-slate-700 mb-1">Remarks / Justification (Optional)</label>
                            <textarea name="notes" id="final-input-notes" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 shadow-2xs focus:border-emerald-600 focus:ring-emerald-600" placeholder="e.g. Realigned Cashbook amount with verified physical receipt"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Action Impact Banner --}}
                <div class="rounded-2xl bg-emerald-50/70 border border-emerald-200 p-3.5 text-xs text-emerald-950 space-y-1">
                    <p class="font-black text-emerald-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                        <span>✓</span>
                        <span>When you apply these values, all linked subsystems will be aligned:</span>
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-bold text-emerald-800 pt-1">
                        <div>1. ShopStaffPayment</div>
                        <div>2. Shop Staff History</div>
                        <div>3. Admin HR History</div>
                        <div>4. Shop Cashbook Ledger</div>
                    </div>
                </div>

                {{-- Footer Buttons --}}
                <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeFlagReviewModal()" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" id="btn-apply-final-value" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-xs font-black text-white hover:bg-emerald-700 active:scale-95 transition cursor-pointer shadow-md">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span id="btn-apply-label">Apply Final Value</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        let currentFlagData = null;

        function openFlagReviewModal(flagId) {
            const modal = document.getElementById('flag-review-modal');
            const form = document.getElementById('admin-decision-form');
            const problemsList = document.getElementById('detected-problems-list');

            if (!modal || !form || !problemsList) return;

            problemsList.innerHTML = '<p class="text-xs text-slate-400 font-semibold py-3 col-span-2">Loading discrepancy details...</p>';

            fetch(`/admin/staff/sync-flags/${flagId}/review`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                currentFlagData = data;
                const flag = data.flag;
                const isOrphan = data.is_orphan;
                const isMissingCb = data.is_missing_cashbook;
                const problems = data.detected_problems || [];
                const vals = data.existing_values || {};

                // Title & Meta Bar
                document.getElementById('review-flag-issue-title')?.remove();
                document.getElementById('meta-employee').textContent = flag.employee ? `${flag.employee.name} (${flag.employee.employee_code})` : (data.flag?.details?.master?.employee_name || 'MISSING');
                document.getElementById('meta-shop').textContent = flag.shop ? flag.shop.name : (vals.shop?.shop_staff_name || vals.shop?.cashbook_name || '—');
                document.getElementById('meta-date').textContent = flag.payment_date ? flag.payment_date.substring(0, 10) : (vals.paid_on?.prefill || '—');
                document.getElementById('meta-type').textContent = vals.category?.shop_staff || vals.category?.cashbook || '—';

                // Render Detected Problems List
                problemsList.innerHTML = '';
                problems.forEach(p => {
                    const div = document.createElement('div');
                    div.className = `flex items-start gap-2 rounded-xl p-2.5 border ${p.is_ok ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : 'bg-rose-50/70 border-rose-200 text-rose-950'}`;
                    div.innerHTML = `
                        <span class="text-xs font-black ${p.is_ok ? 'text-emerald-700' : 'text-rose-700'} shrink-0 mt-0.5">${p.is_ok ? '✓' : '✕'}</span>
                        <div>
                            <p class="font-black text-xs">${p.value}</p>
                            ${p.description ? `<p class="text-[10px] ${p.is_ok ? 'text-emerald-700' : 'text-rose-700'} font-semibold mt-0.5">${p.description}</p>` : ''}
                        </div>
                    `;
                    problemsList.appendChild(div);
                });

                // Mode Containers
                const orphanContainer = document.getElementById('orphan-mode-container');
                const missingCbContainer = document.getElementById('missing-cashbook-mode-container');
                const employeeRow = document.getElementById('field-row-employee');

                if (isOrphan) {
                    orphanContainer.classList.remove('hidden');
                    missingCbContainer.classList.add('hidden');
                    employeeRow.classList.remove('hidden');
                    document.getElementById('orphan-action-create').checked = true;
                } else if (isMissingCb) {
                    orphanContainer.classList.add('hidden');
                    missingCbContainer.classList.remove('hidden');
                    employeeRow.classList.add('hidden');
                    document.getElementById('missing-cb-action-create').checked = true;
                } else {
                    orphanContainer.classList.add('hidden');
                    missingCbContainer.classList.add('hidden');
                    employeeRow.classList.add('hidden');
                }

                // Populate Comparison Values & Prefills
                // 1. Amount
                const amt = vals.amount || {};
                document.getElementById('source-amount-shop').textContent = amt.shop_staff !== null && amt.shop_staff !== undefined ? `₹${parseFloat(amt.shop_staff).toFixed(2)}` : 'MISSING';
                document.getElementById('source-amount-hr').textContent = amt.hr_history !== null && amt.hr_history !== undefined ? `₹${parseFloat(amt.hr_history).toFixed(2)}` : 'MISSING';
                document.getElementById('source-amount-cashbook').textContent = amt.cashbook !== null && amt.cashbook !== undefined ? `₹${parseFloat(amt.cashbook).toFixed(2)}` : 'MISSING';
                document.getElementById('source-amount-cashbook').className = `font-black block mt-0.5 ${amt.is_match ? 'text-slate-900' : 'text-rose-700'}`;
                document.getElementById('final-input-amount').value = amt.prefill !== null && amt.prefill !== undefined ? parseFloat(amt.prefill).toFixed(2) : '';
                renderBadge('status-badge-amount', amt.is_match, 'Amount Mismatch');

                // 2. Category / Type
                const cat = vals.category || {};
                document.getElementById('source-type-shop').textContent = cat.shop_staff || 'MISSING';
                document.getElementById('source-type-hr').textContent = cat.hr_history || 'MISSING';
                document.getElementById('source-type-cashbook').textContent = cat.cashbook || 'MISSING';
                document.getElementById('source-type-cashbook').className = `font-bold block mt-0.5 ${cat.is_match ? 'text-slate-900' : 'text-rose-700'}`;
                document.getElementById('final-input-type').value = cat.prefill === 'advance' ? 'advance' : 'salary';
                renderBadge('status-badge-type', cat.is_match, 'Type Mismatch');

                // 3. Date
                const dt = vals.paid_on || {};
                document.getElementById('source-date-shop').textContent = dt.shop_staff || 'MISSING';
                document.getElementById('source-date-hr').textContent = dt.hr_history || 'MISSING';
                document.getElementById('source-date-cashbook').textContent = dt.cashbook || 'MISSING';
                document.getElementById('source-date-cashbook').className = `font-bold block mt-0.5 ${dt.is_match ? 'text-slate-900' : 'text-rose-700'}`;
                document.getElementById('final-input-date').value = dt.prefill || '';
                renderBadge('status-badge-date', dt.is_match, 'Date Mismatch');

                // 4. Fund Source
                const fs = vals.fund_source || {};
                document.getElementById('source-fs-shop').textContent = fs.shop_staff || 'MISSING';
                document.getElementById('source-fs-hr').textContent = fs.hr_history || 'MISSING';
                document.getElementById('source-fs-cashbook').textContent = fs.cashbook || 'MISSING';
                document.getElementById('source-fs-canonical').textContent = fs.canonical || '';
                const cleanFs = (fs.prefill === 'petty' ? 'petty_cash' : fs.prefill) || 'sales';
                document.getElementById('final-input-fund-source').value = ['sales', 'petty_cash', 'company'].includes(cleanFs) ? cleanFs : 'sales';
                renderBadge('status-badge-fs', fs.is_match, 'Fund Source Diff');

                // 5. Shop
                const sh = vals.shop || {};
                document.getElementById('source-shop-staff').textContent = sh.shop_staff_name || 'MISSING';
                document.getElementById('source-shop-cashbook').textContent = sh.cashbook_name || 'MISSING';
                if (sh.prefill_id) {
                    document.getElementById('final-input-shop').value = sh.prefill_id;
                }
                renderBadge('status-badge-shop', sh.is_match, 'Shop Mismatch');

                // 6. Notes
                document.getElementById('final-input-notes').value = vals.notes?.prefill || '';

                // Form Action
                form.action = `/admin/staff/sync-flags/${flag.id}/apply-fixes`;
                document.getElementById('btn-apply-label').textContent = 'Apply Final Value';

                modal.classList.remove('hidden');
            })
            .catch(err => {
                console.error('Error loading review payload:', err);
                alert('Could not load flag details. Please try again.');
            });
        }

        function renderBadge(elementId, isMatch, mismatchText) {
            const el = document.getElementById(elementId);
            if (!el) return;
            if (isMatch) {
                el.className = 'text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800';
                el.textContent = '✓ Match';
            } else {
                el.className = 'text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded bg-rose-100 text-rose-800';
                el.textContent = `⚠ ${mismatchText}`;
            }
        }

        function toggleOrphanMode() {
            const isRemove = document.getElementById('orphan-action-remove')?.checked;
            const fieldsCard = document.getElementById('decision-fields-card');
            const btnLabel = document.getElementById('btn-apply-label');
            const btn = document.getElementById('btn-apply-final-value');

            if (isRemove) {
                fieldsCard.classList.add('opacity-40', 'pointer-events-none');
                btnLabel.textContent = 'Remove Orphan Cashbook Entry';
                btn.className = 'inline-flex items-center gap-2 rounded-xl bg-rose-600 px-6 py-3 text-xs font-black text-white hover:bg-rose-700 active:scale-95 transition cursor-pointer shadow-md';
            } else {
                fieldsCard.classList.remove('opacity-40', 'pointer-events-none');
                btnLabel.textContent = 'Create Payment & Sync All';
                btn.className = 'inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-xs font-black text-white hover:bg-emerald-700 active:scale-95 transition cursor-pointer shadow-md';
            }
        }

        function toggleMissingCashbookMode() {
            const isCancel = document.getElementById('missing-cb-action-cancel')?.checked;
            const fieldsCard = document.getElementById('decision-fields-card');
            const btnLabel = document.getElementById('btn-apply-label');
            const btn = document.getElementById('btn-apply-final-value');

            if (isCancel) {
                fieldsCard.classList.add('opacity-40', 'pointer-events-none');
                btnLabel.textContent = 'Cancel & Void Staff Payment';
                btn.className = 'inline-flex items-center gap-2 rounded-xl bg-rose-600 px-6 py-3 text-xs font-black text-white hover:bg-rose-700 active:scale-95 transition cursor-pointer shadow-md';
            } else {
                fieldsCard.classList.remove('opacity-40', 'pointer-events-none');
                btnLabel.textContent = 'Apply Final Value';
                btn.className = 'inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-xs font-black text-white hover:bg-emerald-700 active:scale-95 transition cursor-pointer shadow-md';
            }
        }

        function closeFlagReviewModal() {
            const modal = document.getElementById('flag-review-modal');
            if (modal) modal.classList.add('hidden');
        }

        // Form Submit Handler
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('admin-decision-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const btn = document.getElementById('btn-apply-final-value');
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span>Applying Decision...</span>';

                    const formData = new FormData(form);

                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            window.location.reload();
                        } else {
                            alert(res.message || 'Operation could not be completed.');
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    })
                    .catch(err => {
                        console.error('Submit error:', err);
                        form.submit(); // fallback to standard POST
                    });
                });
            }
        });
    </script>
    @endpush
</x-layouts.staff>
