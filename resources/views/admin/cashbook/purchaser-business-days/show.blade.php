@extends('admin.cashbook.layouts.app')

@section('title', 'Business Day Oversight: ' . $day->business_date->format('d M Y') . ' — ' . $warehouse->name)
@section('page_title', 'Business Day Detail & Audit')
@section('page_description', 'Executive cashbook oversight and reconciliation audit for ' . $warehouse->name . ' on ' . $day->business_date->format('d M Y') . '.')

@section('content')
<div class="space-y-6">
    <!-- Active Business Day Header -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $day->isOpen() ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : ($day->isReopened() ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' : 'bg-slate-700 text-white shadow-md shadow-slate-700/20') }}">
                    <i data-lucide="calendar-check" class="h-7 w-7"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">{{ $warehouse->name }}</span>
                        @if ($day->isOpen())
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-emerald-800">
                                OPEN
                            </span>
                        @elseif ($day->isReopened())
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-amber-800">
                                REOPENED
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-slate-700">
                                CLOSED
                            </span>
                        @endif
                    </div>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">
                        Business Day: {{ $day->business_date->format('d M Y') }}
                    </h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">
                        Opened by {{ $day->openedBy?->name ?? 'System' }} on {{ $day->opened_at?->format('d M Y, h:i A') }}
                        @if ($day->isClosed())
                            · Closed by {{ $day->closedBy?->name ?? 'System' }} on {{ $day->closed_at?->format('d M Y, h:i A') }}
                        @elseif ($day->isReopened())
                            · Reopened by {{ $day->reopenedBy?->name ?? 'System' }} on {{ $day->reopened_at?->format('d M Y, h:i A') }}
                        @endif
                    </p>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.cashbook.purchaser-business-days.index', ['month' => $day->business_date->format('Y-m'), 'warehouse_id' => $day->warehouse_id]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50">
                    ← Oversight Board
                </a>

                @if ($day->isClosed())
                    <button type="button" onclick="document.getElementById('admin-reopen-modal').classList.remove('hidden')" class="inline-flex h-11 items-center justify-center rounded-2xl bg-amber-500 px-5 text-xs font-black text-white shadow-sm transition hover:bg-amber-400">
                        Admin Reopen
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Manager Summary Cards (Canonical) -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <!-- 1. Purchase Bills -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Purchase Bills</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $managerSummary['purchase_bills']['count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $managerSummary['purchase_bills']['products_count'] ?? 0 }} products</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700 truncate" title="{{ $managerSummary['purchase_bills']['formatted_totals'] ?? '0 kg' }}">
                {{ $managerSummary['purchase_bills']['formatted_totals'] ?? '0 kg' }}
            </div>
        </div>

        <!-- 2. Advance Receives -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance Receives</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $managerSummary['advance_receives']['count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $managerSummary['advance_receives']['products_count'] ?? 0 }} products</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700 truncate" title="{{ $managerSummary['advance_receives']['formatted_totals'] ?? '0 kg' }}">
                {{ $managerSummary['advance_receives']['formatted_totals'] ?? '0 kg' }}
            </div>
        </div>

        <!-- 3. Products Tracked -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Products Tracked</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $comparisonRows->count() }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Match rate: {{ $summary['overall_match_pct'] ?? 0 }}%</p>
            <div class="mt-2 text-[11px] font-bold text-emerald-600">
                {{ $summary['total_matched_qty'] ?? 0 }} matched
            </div>
        </div>

        <!-- 4. Pending Bills After Match -->
        <div class="rounded-2xl border {{ $pendingList->isNotEmpty() ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-slate-400' }}">Pending Bills</p>
            <p class="mt-1 text-2xl font-black {{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-slate-900' }}">{{ $pendingList->count() }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Pending items</p>
            <div class="mt-2 text-[11px] font-bold text-rose-600 truncate">
                {{ $summary['total_pending_bill_qty'] ?? 0 }} pending qty
            </div>
        </div>

        <!-- 5. Unit Fix Required -->
        <div class="rounded-2xl border {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' }}">Unit Fix Required</p>
            <p class="mt-1 text-2xl font-black {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $summary['unit_fix_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Unit mismatches</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">
                {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'Action required' : 'All units valid' }}
            </div>
        </div>

        <!-- 6. Inventory Balance -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Inventory Status</p>
            <p class="mt-1 text-2xl font-black text-slate-900">Live</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Stock Balance</p>
            <div class="mt-2 text-[11px] font-bold text-teal-600">
                Verified On-Hand
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <button type="button" onclick="switchAdminTab('comparison')" id="admin-tab-btn-comparison" class="admin-tab-btn border-b-2 border-teal-600 px-4 py-3 text-xs font-black text-teal-600">
                Daily Comparison Table
            </button>
            <button type="button" onclick="switchAdminTab('pending_snapshot')" id="admin-tab-btn-pending_snapshot" class="admin-tab-btn border-b-2 border-transparent px-4 py-3 text-xs font-bold text-slate-500 hover:text-slate-700">
                Pending at Close &amp; Current Status
                @if (!empty($firstCloseSnapshot) || $pendingList->isNotEmpty())
                    <span class="ml-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                        {{ count($firstCloseSnapshot) }} snapshot · {{ $pendingList->count() }} current
                    </span>
                @endif
            </button>
            <button type="button" onclick="switchAdminTab('timeline')" id="admin-tab-btn-timeline" class="admin-tab-btn border-b-2 border-transparent px-4 py-3 text-xs font-bold text-slate-500 hover:text-slate-700">
                Audit Activity Timeline
                <span class="ml-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700">{{ $timeline->count() }}</span>
            </button>
        </nav>
    </div>

    <!-- TAB 1: Comparison Table -->
    <div id="admin-tab-panel-comparison" class="admin-tab-panel space-y-4">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-950">Advance vs Bill Comparison</h3>
                    <p class="text-xs font-semibold text-slate-400">Scoped strictly to Business Day #{{ $day->id }} · {{ $warehouse->name }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                            <th class="py-3.5 px-6">Product</th>
                            <th class="py-3.5 px-4 text-right">Advance</th>
                            <th class="py-3.5 px-4 text-right">Bill</th>
                            <th class="py-3.5 px-4 text-right">Matched</th>
                            <th class="py-3.5 px-4 text-right">Pending Bill</th>
                            <th class="py-3.5 px-4 text-right">Diff</th>
                            <th class="py-3.5 px-4 text-right">Inventory Balance</th>
                            <th class="py-3.5 px-6 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse ($comparisonRows as $row)
                            @php
                                $adv = (float) ($row['advance_qty'] ?? 0);
                                $bill = (float) ($row['bill_qty'] ?? 0);
                                $matched = (float) ($row['matched_bill_qty'] ?? 0);
                                $pendingAdv = max(0.0, $adv - $matched);
                                $isUnitMismatch = (bool) ($row['unit_mismatch'] ?? false);
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-6 font-black text-slate-900">
                                    <div>
                                        <span>{{ $row['product_name'] }}</span>
                                        <span class="block text-[10px] font-bold text-slate-400">{{ $row['sku'] ?? $row['product_code'] ?? '' }} · {{ $row['unit'] }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-right font-bold text-slate-900">{{ $row['formatted_advance'] }}</td>
                                <td class="py-4 px-4 text-right font-bold text-slate-900">{{ $row['formatted_bill'] }}</td>
                                <td class="py-4 px-4 text-right font-bold text-emerald-600">{{ number_format($matched, 2) }} {{ $row['unit'] }}</td>
                                <td class="py-4 px-4 text-right">
                                    @if ($pendingAdv > 0.0001)
                                        <span class="font-black text-rose-600">{{ number_format($pendingAdv, 2) }} {{ $row['unit'] }}</span>
                                    @else
                                        <span class="text-slate-400">0 {{ $row['unit'] }}</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-right font-bold {{ (float) ($row['diff'] ?? 0) > 0 ? 'text-amber-600' : 'text-slate-600' }}">
                                    {{ $row['formatted_diff'] ?? '--' }}
                                </td>
                                <td class="py-4 px-4 text-right font-bold text-slate-800">
                                    {{ $row['formatted_inventory_balance'] ?? $row['formatted_stock_balance'] ?? '--' }}
                                </td>
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    @if ($isUnitMismatch)
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-rose-700">
                                            Unit Fix
                                        </span>
                                    @elseif ($pendingAdv > 0.0001)
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-rose-600">
                                            Pending Bill
                                        </span>
                                    @elseif ($bill > $adv && $adv > 0)
                                        <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-sky-700">
                                            Excess Bill
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-700">
                                            Matched
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-sm font-semibold text-slate-400">
                                    No goods received or bills recorded for this business day.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: Pending at Close Snapshot vs Current Status -->
    <div id="admin-tab-panel-pending_snapshot" class="admin-tab-panel hidden space-y-6">
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Historical Snapshot at Close -->
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm space-y-4">
                <div class="border-b border-slate-100 p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-black text-slate-950">Historical Snapshot (At First Close)</h3>
                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800">
                            Immutable Record
                        </span>
                    </div>
                    <p class="text-xs font-semibold text-slate-400 mt-1">
                        Preserved snapshot when this business day was first closed.
                    </p>
                    @if ($day->close_note)
                        <div class="mt-3 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-900 border border-amber-200">
                            <span class="font-bold">Close Reason:</span> {{ $day->close_note }}
                        </div>
                    @endif
                </div>

                <div class="p-6 pt-0">
                    @if (!empty($firstCloseSnapshot))
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="pb-3">Product</th>
                                    <th class="pb-3 text-right">Pending Qty at Close</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                                @foreach ($firstCloseSnapshot as $snapItem)
                                    <tr>
                                        <td class="py-3 font-bold text-slate-900">
                                            {{ $snapItem['product_name'] ?? 'Product' }} ({{ $snapItem['sku'] ?? '' }})
                                        </td>
                                        <td class="py-3 text-right font-black text-amber-700">
                                            {{ number_format((float) ($snapItem['pending_qty'] ?? 0), 2) }} {{ $snapItem['unit'] ?? 'kg' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="py-8 text-center text-xs font-bold text-slate-400">
                            No pending exceptions existed when this day was closed (Clean close).
                        </div>
                    @endif
                </div>
            </div>

            <!-- Current Realtime Pending Status -->
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm space-y-4">
                <div class="border-b border-slate-100 p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-black text-slate-950">Current Realtime Pending Status</h3>
                        <span class="rounded-full bg-teal-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-teal-800">
                            Live Status
                        </span>
                    </div>
                    <p class="text-xs font-semibold text-slate-400 mt-1">
                        Reflects any subsequent bill entries or corrections made after reopening.
                    </p>
                </div>

                <div class="p-6 pt-0">
                    @if ($pendingList->isNotEmpty())
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="pb-3">Product</th>
                                    <th class="pb-3 text-right">Current Pending</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                                @foreach ($pendingList as $pItem)
                                    <tr>
                                        <td class="py-3 font-bold text-slate-900">
                                            {{ $pItem['product_name'] }} ({{ $pItem['sku'] }})
                                        </td>
                                        <td class="py-3 text-right font-black text-rose-600">
                                            {{ $pItem['formatted_pending'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="py-8 text-center text-xs font-bold text-emerald-600">
                            🎉 Clean State! All advance items currently have matching vendor bills.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: Audit Activity Timeline -->
    <div id="admin-tab-panel-timeline" class="admin-tab-panel hidden space-y-4">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="border-b border-slate-100 pb-4 mb-6">
                <h3 class="text-base font-black text-slate-950">Exhaustive Activity Audit Timeline</h3>
                <p class="text-xs font-semibold text-slate-400">Complete chronological record of all goods receipts, bills, matches, closures, and reopening events.</p>
            </div>

            <div class="relative border-l-2 border-slate-200 ml-4 space-y-6">
                @forelse ($timeline as $event)
                    @php
                        $ts = $event['timestamp'] instanceof \Carbon\Carbon ? $event['timestamp'] : \Carbon\Carbon::parse($event['timestamp']);
                    @endphp
                    <div class="relative pl-6">
                        <!-- Bullet point marker -->
                        <div class="absolute -left-[9px] top-1 h-4 w-4 rounded-full border-2 border-white bg-slate-900 shadow-xs"></div>

                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 space-y-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-black text-slate-900 text-sm">{{ $event['title'] }}</span>
                                <span class="text-[11px] font-bold text-slate-400">{{ $ts->format('d M Y, h:i:s A') }}</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-600">{{ $event['description'] }}</p>
                            <p class="text-[11px] font-bold text-slate-500">Actor: <span class="text-slate-800">{{ $event['user'] }}</span></p>
                        </div>
                    </div>
                @empty
                    <div class="pl-6 text-sm text-slate-400">No lifecycle events recorded yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Modal: Admin Reopen Day -->
<div id="admin-reopen-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs hidden">
    <div class="w-full max-w-md overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <div>
                <h3 class="text-base font-black text-slate-950">Admin Reopen Business Day</h3>
                <p class="text-xs font-semibold text-slate-400">{{ $warehouse->name }} · {{ $day->business_date->format('d M Y') }}</p>
            </div>
            <button type="button" onclick="document.getElementById('admin-reopen-modal').classList.add('hidden')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.cashbook.purchaser-business-days.reopen', $day->uuid) }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label for="reopen_reason" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Reopen Reason (Mandatory Audit)</label>
                <textarea id="reopen_reason" name="reopen_reason" rows="3" required placeholder="Explain why admin is reopening this business day..." class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs font-bold text-slate-900 focus:border-amber-500 focus:bg-white focus:outline-none"></textarea>
            </div>

            <p class="text-[11px] font-semibold text-slate-500">
                Reopening will allow purchaser bill corrections and rerun auto-matching across all inventory batches.
            </p>

            <div class="pt-3 flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('admin-reopen-modal').classList.add('hidden')" class="rounded-2xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-2xl bg-amber-600 px-5 py-2.5 text-xs font-black text-white hover:bg-amber-500 shadow-sm">
                    Confirm Admin Reopen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchAdminTab(tabName) {
        document.querySelectorAll('.admin-tab-btn').forEach(btn => {
            btn.className = 'admin-tab-btn border-b-2 border-transparent px-4 py-3 text-xs font-bold text-slate-500 hover:text-slate-700';
        });
        document.querySelectorAll('.admin-tab-panel').forEach(panel => {
            panel.classList.add('hidden');
        });

        const activeBtn = document.getElementById('admin-tab-btn-' + tabName);
        const activePanel = document.getElementById('admin-tab-panel-' + tabName);
        if (activeBtn && activePanel) {
            activeBtn.className = 'admin-tab-btn border-b-2 border-teal-600 px-4 py-3 text-xs font-black text-teal-600';
            activePanel.classList.remove('hidden');
        }
    }
</script>
@endsection
