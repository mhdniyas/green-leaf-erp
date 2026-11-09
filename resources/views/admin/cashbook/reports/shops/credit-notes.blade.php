@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Credit Notes Register — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-minus" class="w-5 h-5 text-emerald-600"></i> Shop Credit Notes Register
@endsection

@section('header_subtitle')
    Audited office register of shop credit notes, invoice credit allocations, and physical return status.
@endsection

@section('content')
<div x-data="{ activeNoteId: null }" class="mx-auto max-w-[96rem] space-y-5">

    <!-- Top Sub-Nav Header -->
    @include('admin.cashbook.reports.shops.partials.header', ['activeTab' => 'credit-notes'])

    <!-- Flash Alerts -->
    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-xs flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span class="text-xs font-bold text-emerald-900">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 shadow-xs flex items-center gap-3">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
            <span class="text-xs font-bold text-rose-800">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Summary Metric Cards (5 Cards) -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <!-- 1. Finalized Credit Amount -->
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-emerald-800">Finalized Credits</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-emerald-700">
                ₹{{ number_format((float) ($summary['finalized_credit_amount'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">
                {{ $summary['finalized_count'] ?? 0 }} active credit {{ Str::plural('note', $summary['finalized_count'] ?? 0) }}
            </span>
        </div>

        <!-- 2. Applied Credit Balance -->
        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-blue-800">Applied Credit</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-blue-700">
                ₹{{ number_format((float) ($summary['applied_credit_amount'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-blue-600">Allocated to specific invoices</span>
        </div>

        <!-- 3. Unapplied Credit Balance -->
        <div class="rounded-2xl border border-indigo-200/80 bg-indigo-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-indigo-800">Unapplied Credit</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-indigo-700">
                ₹{{ number_format((float) ($summary['unapplied_credit_amount'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-indigo-600">Available for bill allocation</span>
        </div>

        <!-- 4. Voided Credit Notes (Separate) -->
        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Voided (Cancelled)</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-slate-600">
                ₹{{ number_format((float) ($summary['voided_credit_amount'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">
                {{ $summary['voided_count'] ?? 0 }} voided (excluded from active)
            </span>
        </div>

        <!-- 5. Draft Notes (Separate) -->
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4 shadow-xs col-span-2 sm:col-span-1">
            <span class="block text-[10px] font-black uppercase tracking-wider text-amber-800">Draft Notes</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-amber-700">
                {{ $summary['draft_count'] ?? 0 }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-amber-600">
                ₹{{ number_format((float) ($summary['draft_amount'] ?? 0), 2) }} pending review
            </span>
        </div>
    </div>
    <p class="text-[11px] text-slate-400 font-medium px-1 -mt-2">
        * Summary totals reflect financial balances for Credit Notes dated within the selected reporting period. Applied + Unapplied balances reconcile exactly to Finalized Credits.
    </p>

    <!-- Filters Section -->
    <div class="white-card rounded-3xl p-4 sm:p-5">
        <!-- Preset Timeframe Buttons -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-4">
            <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'physical_status' => $selectedPhysicalStatus ?? null, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'yesterday', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'physical_status' => $selectedPhysicalStatus ?? null, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'yesterday' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Yesterday
                </a>
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'this_month', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'physical_status' => $selectedPhysicalStatus ?? null, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'this_month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    This Month
                </a>
                @php
                    $prevMonthName = now()->subMonth()->format('M');
                @endphp
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'prev_month', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'physical_status' => $selectedPhysicalStatus ?? null, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'prev_month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ $prevMonthName }} (Prev Month)
                </a>
            </div>

            <span class="text-xs font-semibold text-slate-400">
                Period: <span class="font-bold text-slate-700">{{ Carbon\Carbon::parse($startDate)->format('d M Y') }}</span> to <span class="font-bold text-slate-700">{{ Carbon\Carbon::parse($endDate)->format('d M Y') }}</span>
            </span>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.cashbook.reports.shops.credit-notes') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
            <input type="hidden" name="timeframe" value="custom">

            <!-- From Date -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">From Date</span>
                <input type="date" name="start_date" value="{{ $startDate }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs">
            </label>

            <!-- To Date -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">To Date</span>
                <input type="date" name="end_date" value="{{ $endDate }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs">
            </label>

            <!-- Shop Dropdown -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Shop</span>
                <select name="shop_id" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs cursor-pointer">
                    <option value="">All Shops</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" @selected($selectedShopId === (int) $shop->id)>
                            {{ $shop->name }} ({{ $shop->code }})
                        </option>
                    @endforeach
                </select>
            </label>

            <!-- Financial Status Dropdown -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Financial Status</span>
                <select name="status" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="draft" @selected($selectedStatus === 'draft')>Draft</option>
                    <option value="approved" @selected($selectedStatus === 'approved')>Finalized (Approved)</option>
                    <option value="voided" @selected($selectedStatus === 'voided')>Voided (Cancelled)</option>
                </select>
            </label>

            <!-- Physical Return Status Dropdown -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Physical Return</span>
                <select name="physical_status" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs cursor-pointer">
                    <option value="">All Physical</option>
                    <option value="pending" @selected(($selectedPhysicalStatus ?? '') === 'pending')>Pending Receipt</option>
                    <option value="partially_received" @selected(($selectedPhysicalStatus ?? '') === 'partially_received')>Partially Received</option>
                    <option value="verified" @selected(($selectedPhysicalStatus ?? '') === 'verified')>Fully Verified</option>
                    <option value="disputed" @selected(($selectedPhysicalStatus ?? '') === 'disputed')>Disputed</option>
                </select>
            </label>

            <!-- Note / Invoice Search -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Search #</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="SCN-... or SINV-..." class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs">
            </label>

            <!-- Actions Bar -->
            <div class="sm:col-span-2 lg:col-span-6 flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs text-slate-400 font-semibold">Quick Filters:</span>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['status' => 'draft', 'timeframe' => $timeframe]) }}"
                        class="rounded-lg px-2.5 py-1 text-[11px] font-bold border transition {{ $selectedStatus === 'draft' ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        Drafts ({{ $summary['draft_count'] ?? 0 }})
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['status' => 'approved', 'timeframe' => $timeframe]) }}"
                        class="rounded-lg px-2.5 py-1 text-[11px] font-bold border transition {{ $selectedStatus === 'approved' ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        Finalized ({{ $summary['finalized_count'] ?? 0 }})
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['status' => 'voided', 'timeframe' => $timeframe]) }}"
                        class="rounded-lg px-2.5 py-1 text-[11px] font-bold border transition {{ $selectedStatus === 'voided' ? 'bg-slate-200 text-slate-900 border-slate-300' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        Voided ({{ $summary['voided_count'] ?? 0 }})
                    </a>
                </div>

                <div class="flex items-center gap-2">
                    @if($selectedShopId || $selectedStatus || !empty($selectedPhysicalStatus) || $invoiceSearch || $search || $timeframe !== 'today')
                        <a href="{{ route('admin.cashbook.reports.shops.credit-notes') }}" class="h-10 inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition" title="Reset Filters">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            <span>Reset</span>
                        </a>
                    @endif
                    <button type="submit" class="h-10 rounded-xl bg-slate-900 px-5 text-xs font-bold text-white hover:bg-slate-800 shadow-xs transition">
                        Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between px-1">
            <div>
                <h3 class="text-sm font-black text-slate-900">Shop Credit Notes Register</h3>
                <p class="text-[11px] font-medium text-slate-400">
                    Showing {{ $creditNotes->firstItem() ?? 0 }} to {{ $creditNotes->lastItem() ?? 0 }} of {{ $creditNotes->total() }} records
                </p>
            </div>
            @if ($creditNotes->total() > 0)
                <span class="text-[10px] font-bold text-slate-400">Page {{ $creditNotes->currentPage() }} of {{ $creditNotes->lastPage() }}</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-50/50">
                        <th class="py-3 px-2 text-center w-10">#</th>
                        <th class="py-3 px-3">Credit Note #</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Shop</th>
                        <th class="py-3 px-3">Source Invoices</th>
                        <th class="py-3 px-3 text-right">Total Credit</th>
                        <th class="py-3 px-3 text-right">Applied</th>
                        <th class="py-3 px-3 text-right">Unapplied</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-center">Physical Return</th>
                        <th class="py-3 px-3">Audit</th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                    @forelse ($creditNotes as $note)
                        @php
                            $invNums = $note->sourceInvoiceNumbers();
                            $retSummary = $note->physicalReturnSummary();
                            $appliedAmt = $note->appliedCreditTotal();
                            $unappliedAmt = $note->unappliedCreditTotal();
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Serial Number -->
                            <td class="py-3 px-2 text-center text-[11px] text-slate-400 font-mono">
                                {{ $loop->iteration + ($creditNotes->currentPage() - 1) * $creditNotes->perPage() }}
                            </td>

                            <!-- Credit Note # -->
                            <td class="py-3 px-3">
                                <a href="{{ route('admin.cashbook.reports.shops.credit-notes.show', $note) }}"
                                    class="font-bold text-slate-900 block font-mono hover:text-emerald-700 hover:underline">
                                    {{ $note->credit_note_number }}
                                </a>
                                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider mt-0.5
                                    {{ $note->isShopLevelMode() ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $note->isShopLevelMode() ? 'Shop-Level' : 'Legacy Linked' }}
                                </span>
                            </td>

                            <!-- Date -->
                            <td class="py-3 px-3 text-slate-600 whitespace-nowrap">
                                {{ optional($note->credit_note_date)->format('d M Y') }}
                            </td>

                            <!-- Shop -->
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-900 block">{{ $note->shop?->name ?: 'Shop #'.$note->shop_id }}</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">{{ $note->shop?->code }}</span>
                            </td>

                            <!-- Source Invoices -->
                            <td class="py-3 px-3 font-mono">
                                @if (empty($invNums))
                                    <span class="text-slate-400">—</span>
                                @elseif (count($invNums) === 1)
                                    <span class="font-bold text-slate-800 text-[11px]">{{ $invNums[0] }}</span>
                                @else
                                    <span class="font-bold text-slate-800 text-[11px]">{{ $invNums[0] }}</span>
                                    <span class="inline-flex rounded bg-slate-100 px-1 py-0.5 text-[9px] text-slate-500 font-bold ml-1 cursor-help"
                                        title="{{ implode(', ', $invNums) }}">
                                        +{{ count($invNums) - 1 }} more
                                    </span>
                                @endif
                            </td>

                            <!-- Total Credit Amount -->
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                ₹{{ number_format((float) $note->total_credit_amount, 2) }}
                            </td>

                            <!-- Applied Amount -->
                            <td class="py-3 px-3 text-right font-mono font-bold text-blue-700 whitespace-nowrap">
                                ₹{{ number_format((float) $appliedAmt, 2) }}
                            </td>

                            <!-- Unapplied Amount -->
                            <td class="py-3 px-3 text-right font-mono font-bold {{ $unappliedAmt > 0 ? 'text-indigo-700' : 'text-slate-400' }} whitespace-nowrap">
                                ₹{{ number_format((float) $unappliedAmt, 2) }}
                            </td>

                            <!-- Financial Status -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider
                                    {{ $note->isApproved() ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200' : ($note->isDraft() ? 'bg-amber-100 text-amber-800 ring-1 ring-amber-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200') }}">
                                    @if ($note->isApproved())
                                        <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i>
                                    @elseif ($note->isDraft())
                                        <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i>
                                    @else
                                        <i data-lucide="x" class="w-3 h-3 text-slate-500"></i>
                                    @endif
                                    <span>{{ $note->status->value }}</span>
                                </span>
                            </td>

                            <!-- Physical Return Status -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @if (!$retSummary['has_physical_return'])
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-400 uppercase">
                                        Financial Only
                                    </span>
                                @else
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider
                                            {{ $retSummary['status'] === 'received_and_verified' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($retSummary['status'] === 'partially_received' ? 'bg-amber-50 text-amber-700 border border-amber-200' : ($retSummary['status'] === 'disputed' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200')) }}">
                                            <span>{{ $retSummary['status_label'] }}</span>
                                        </span>
                                        <span class="text-[10px] font-mono text-slate-500 mt-0.5">
                                            {{ $retSummary['received_quantity'] }} / {{ $retSummary['expected_quantity'] }} kg
                                        </span>
                                        @if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('warehouse_receiver'))
                                            <a href="{{ route('warehouse.receiver.credit-notes.index', ['q' => $note->credit_note_number]) }}"
                                                class="text-[9px] text-indigo-600 hover:underline mt-0.5"
                                                title="Open in Warehouse Return Queue">
                                                Warehouse &rarr;
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- Created By / Finalized By -->
                            <td class="py-3 px-3 text-[11px] text-slate-500 whitespace-nowrap">
                                <span class="block text-slate-700 font-semibold">
                                    By: {{ $note->creator?->name ?? 'System' }}
                                </span>
                                @if($note->isApproved())
                                    <span class="block text-emerald-700 text-[10px]">
                                        Approved: {{ $note->approvedBy?->name ?? $note->approver?->name ?? '—' }}
                                    </span>
                                @elseif($note->isVoided())
                                    <span class="block text-rose-700 text-[10px]">
                                        Voided: {{ $note->voidedBy?->name ?? '—' }}
                                    </span>
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    @if ($note->isDraft())
                                        <a href="{{ route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $note) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-900 hover:bg-amber-100 shadow-2xs"
                                            title="Edit Draft">
                                            <i data-lucide="edit-3" class="w-3 h-3 text-amber-700"></i>
                                            <span>Edit Draft</span>
                                        </a>
                                    @elseif ($note->isApproved())
                                        <a href="{{ route('admin.cashbook.reports.shops.credit-notes.show', $note) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-900 hover:bg-emerald-100 shadow-2xs"
                                            title="View Details &amp; Apply Credit">
                                            <i data-lucide="external-link" class="w-3 h-3 text-emerald-700"></i>
                                            <span>View &amp; Apply</span>
                                        </a>
                                    @else
                                        <a href="{{ route('admin.cashbook.reports.shops.credit-notes.show', $note) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 shadow-2xs"
                                            title="View Audit Details">
                                            <i data-lucide="eye" class="w-3 h-3 text-slate-500"></i>
                                            <span>View Audit</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-12 text-center text-xs font-semibold text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="h-10 w-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400">
                                        <i data-lucide="inbox" class="w-5 h-5"></i>
                                    </div>
                                    <p class="text-slate-600 font-bold">No Shop Credit Notes Found</p>
                                    <p class="text-[11px] text-slate-400">
                                        No credit notes match the selected date range, shop, or status criteria.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($creditNotes->hasPages())
            <div class="pt-2 border-t border-slate-100">
                {{ $creditNotes->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
