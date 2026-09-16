@extends('admin.cashbook.layouts.app')

@section('title', 'Purchaser Business Day Reports — Admin Cashbook')
@section('page_title', 'Business Day Executive Reports')
@section('page_description', 'Comprehensive multi-dimensional reporting across daily reconciliation, pending bills, reopen audit, and vendor outstanding.')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Cashbook Reports</span>
                    <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-teal-700 border border-teal-200">
                        Executive Hub
                    </span>
                </div>
                <h2 class="mt-1 text-2xl font-black text-slate-950">
                    Business Day Reporting Hub
                </h2>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">
                    Audit and analysis for Monthly summary, Pending purchase bills, Reopened days, Exceptions, and Vendor pending.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.cashbook.purchaser-business-days.index') }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50">
                    ← Oversight Board
                </a>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200">
        <nav class="flex flex-wrap gap-2" aria-label="Report Tabs">
            <a href="{{ route('admin.cashbook.purchaser-business-days.reports', ['tab' => 'monthly', 'month' => $month, 'warehouse_id' => $selectedWarehouseId]) }}" class="border-b-2 px-4 py-3 text-xs font-black {{ $tab === 'monthly' ? 'border-teal-600 text-teal-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Monthly Summary
            </a>
            <a href="{{ route('admin.cashbook.purchaser-business-days.reports', ['tab' => 'pending', 'month' => $month, 'warehouse_id' => $selectedWarehouseId]) }}" class="border-b-2 px-4 py-3 text-xs font-black {{ $tab === 'pending' ? 'border-teal-600 text-teal-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Pending Purchase Bills ({{ $allPendingItems->count() }})
            </a>
            <a href="{{ route('admin.cashbook.purchaser-business-days.reports', ['tab' => 'reopened', 'month' => $month, 'warehouse_id' => $selectedWarehouseId]) }}" class="border-b-2 px-4 py-3 text-xs font-black {{ $tab === 'reopened' ? 'border-teal-600 text-teal-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Reopened Days ({{ $reopenedDays->count() }})
            </a>
            <a href="{{ route('admin.cashbook.purchaser-business-days.reports', ['tab' => 'close_with_pending', 'month' => $month, 'warehouse_id' => $selectedWarehouseId]) }}" class="border-b-2 px-4 py-3 text-xs font-black {{ $tab === 'close_with_pending' ? 'border-teal-600 text-teal-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Close-With-Pending ({{ $closeWithPendingDays->count() }})
            </a>
            <a href="{{ route('admin.cashbook.purchaser-business-days.reports', ['tab' => 'vendor_pending', 'month' => $month, 'warehouse_id' => $selectedWarehouseId]) }}" class="border-b-2 px-4 py-3 text-xs font-black {{ $tab === 'vendor_pending' ? 'border-teal-600 text-teal-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Vendor Pending Aggregation ({{ $vendorPendingList->count() }})
            </a>
        </nav>
    </div>

    <!-- TAB: Monthly Summary -->
    @if ($tab === 'monthly')
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Business Days</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_days'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">In selected month</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 shadow-xs">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Open Days</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900">{{ $monthlySummary['open_count'] }}</p>
                    <p class="mt-0.5 text-xs text-emerald-700">Currently active</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Closed Days</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['closed_count'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">Clean &amp; locked</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4 shadow-xs">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-800">Reopened Days</p>
                    <p class="mt-1 text-2xl font-black text-amber-900">{{ $monthlySummary['reopened_count'] }}</p>
                    <p class="mt-0.5 text-xs text-amber-700">Under correction</p>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4 shadow-xs">
                    <p class="text-[10px] font-black uppercase tracking-wider text-rose-600">Pending Products</p>
                    <p class="mt-1 text-2xl font-black text-rose-600">{{ $monthlySummary['pending_products_count'] }}</p>
                    <p class="mt-0.5 text-xs text-rose-500">Awaiting supplier bills</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Bills</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_bills'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $monthlySummary['total_advance'] }} advance GRNs</p>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB: Pending Purchase Bills -->
    @if ($tab === 'pending')
        <div class="space-y-4">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-black text-slate-950">Pending Purchase Bills Worklist</h3>
                        <p class="text-xs font-semibold text-slate-400">Advance receipts across open business days awaiting vendor bill settlement.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="shareAdminPendingWhatsApp()" class="inline-flex h-9 items-center justify-center rounded-xl bg-emerald-600 px-3.5 text-xs font-black text-white shadow-xs transition hover:bg-emerald-500">
                            <i data-lucide="share-2" class="w-3.5 h-3.5 mr-1.5"></i>
                            Share WhatsApp
                        </button>
                        <a href="{{ route('admin.cashbook.purchaser-business-days.reports.pending-pdf', ['warehouse_id' => $selectedWarehouseId]) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-900 px-3.5 text-xs font-black text-white shadow-xs transition hover:bg-slate-800">
                            <i data-lucide="download" class="w-3.5 h-3.5 mr-1.5"></i>
                            Export PDF
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="admin-pending-table">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500">
                                <th class="py-3.5 px-6">Business Day</th>
                                <th class="py-3.5 px-4">Warehouse</th>
                                <th class="py-3.5 px-4">Product</th>
                                <th class="py-3.5 px-4 text-right">Advance Received</th>
                                <th class="py-3.5 px-4 text-right">Matched</th>
                                <th class="py-3.5 px-4 text-right">Pending Bill Qty</th>
                                <th class="py-3.5 px-6 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                            @forelse ($allPendingItems as $item)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="py-4 px-6 font-black text-slate-900">
                                        {{ $item['business_date']->format('d M Y') }}
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-800">
                                        {{ $item['warehouse']?->name ?? 'Warehouse' }}
                                    </td>
                                    <td class="py-4 px-4 font-black text-slate-900">
                                        <span>{{ $item['product_name'] }}</span>
                                        <span class="block text-[10px] font-bold text-slate-400">{{ $item['sku'] }}</span>
                                    </td>
                                    <td class="py-4 px-4 text-right font-bold text-slate-700">{{ number_format($item['advance_qty'], 2) }} {{ $item['unit'] }}</td>
                                    <td class="py-4 px-4 text-right font-bold text-emerald-600">{{ number_format($item['matched_qty'], 2) }} {{ $item['unit'] }}</td>
                                    <td class="py-4 px-4 text-right font-black text-rose-600 text-sm">
                                        {{ $item['formatted_pending'] }}
                                    </td>
                                    <td class="py-4 px-6 text-center whitespace-nowrap">
                                        <a href="{{ route('admin.cashbook.purchaser-business-days.show', $item['business_day_uuid']) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 hover:bg-slate-200">
                                            View Day
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-sm font-semibold text-emerald-600">
                                        🎉 No pending purchase bills across active business days.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB: Reopened Days Report -->
    @if ($tab === 'reopened')
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-black text-slate-950">Reopened Business Days Audit Report</h3>
                <p class="text-xs font-semibold text-slate-400">All business days that were reopened for bill corrections and late invoice settlement.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-6">Date</th>
                            <th class="py-3.5 px-4">Warehouse</th>
                            <th class="py-3.5 px-4">Reopened By</th>
                            <th class="py-3.5 px-4">Reopened At</th>
                            <th class="py-3.5 px-6">Mandatory Audit Reason</th>
                            <th class="py-3.5 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse ($reopenedDays as $rDay)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-4 px-6 font-black text-slate-900">{{ $rDay->business_date->format('d M Y') }}</td>
                                <td class="py-4 px-4 font-bold text-slate-800">{{ $rDay->warehouse?->name ?? 'Warehouse' }}</td>
                                <td class="py-4 px-4 font-bold text-slate-900">{{ $rDay->reopenedBy?->name ?? 'System' }}</td>
                                <td class="py-4 px-4 text-slate-600">{{ $rDay->reopened_at?->format('d M Y, h:i A') ?? '--' }}</td>
                                <td class="py-4 px-6 text-amber-900 font-semibold bg-amber-50/40 rounded-xl">
                                    {{ $rDay->reopen_reason }}
                                </td>
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <a href="{{ route('admin.cashbook.purchaser-business-days.show', $rDay->uuid) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-1 text-xs font-black text-white hover:bg-slate-800">
                                        View Day
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-sm font-semibold text-slate-400">
                                    No business days have been reopened in this month.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- TAB: Close-With-Pending Report -->
    @if ($tab === 'close_with_pending')
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-black text-slate-950">Close-With-Pending Exceptions Report</h3>
                <p class="text-xs font-semibold text-slate-400">Business days that were closed with pending bills, comparing initial snapshot against current resolution.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-6">Date</th>
                            <th class="py-3.5 px-4">Warehouse</th>
                            <th class="py-3.5 px-4 text-right">Snapshot Items</th>
                            <th class="py-3.5 px-4 text-right">Current Pending</th>
                            <th class="py-3.5 px-6">Close Reason / Note</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse ($closeWithPendingDays as $cItem)
                            @php
                                $cDay = $cItem['day'];
                            @endphp
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-4 px-6 font-black text-slate-900">{{ $cDay->business_date->format('d M Y') }}</td>
                                <td class="py-4 px-4 font-bold text-slate-800">{{ $cDay->warehouse?->name ?? 'Warehouse' }}</td>
                                <td class="py-4 px-4 text-right font-black text-amber-700">
                                    {{ count($cItem['first_close_snapshot']) }} items
                                </td>
                                <td class="py-4 px-4 text-right font-black">
                                    @if ($cItem['is_resolved'])
                                        <span class="text-emerald-600 font-bold">Resolved (0)</span>
                                    @else
                                        <span class="text-rose-600">{{ $cItem['current_pending']->count() }} pending</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-slate-700 text-xs font-semibold">
                                    {{ $cDay->close_note }}
                                </td>
                                <td class="py-4 px-4 text-center">
                                    @if ($cItem['is_resolved'])
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">
                                            Resolved
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-rose-800">
                                            Active Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <a href="{{ route('admin.cashbook.purchaser-business-days.show', $cDay->uuid) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-1 text-xs font-black text-white hover:bg-slate-800">
                                        View Day
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-sm font-semibold text-slate-400">
                                    No days were closed with pending items in this month.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- TAB: Vendor Pending Aggregation Report -->
    @if ($tab === 'vendor_pending')
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-black text-slate-950">Vendor &amp; Product Pending Aggregation</h3>
                <p class="text-xs font-semibold text-slate-400">Aggregated pending quantities grouped by product across active business days.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-6">Product</th>
                            <th class="py-3.5 px-4 text-right">Total Pending Qty</th>
                            <th class="py-3.5 px-4 text-center">Occurrences</th>
                            <th class="py-3.5 px-6">Business Dates</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse ($vendorPendingList as $vItem)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-4 px-6 font-black text-slate-900">
                                    <span>{{ $vItem['product_name'] }}</span>
                                    <span class="block text-[10px] font-bold text-slate-400">{{ $vItem['sku'] }}</span>
                                </td>
                                <td class="py-4 px-4 text-right font-black text-rose-600 text-sm">
                                    {{ $vItem['formatted_total_pending'] }}
                                </td>
                                <td class="py-4 px-4 text-center font-bold text-slate-700">
                                    {{ $vItem['occurrences_count'] }} days
                                </td>
                                <td class="py-4 px-6 text-slate-600 font-bold">
                                    {{ $vItem['days'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center text-sm font-semibold text-slate-400">
                                    No aggregated pending items currently.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
    function shareAdminPendingWhatsApp() {
        const pendingItems = @json($allPendingItems);
        if (!pendingItems.length) {
            alert('No pending purchase bills to share.');
            return;
        }

        let message = `*Green Leaf - Pending Purchase Bills*\n`;
        message += `Generated: ${new Date().toLocaleDateString('en-GB')}\n\n`;

        pendingItems.forEach((item, index) => {
            message += `${index + 1}. *${item.product_name}*: ${item.formatted_pending} (${item.warehouse ? item.warehouse.name : ''})\n`;
        });

        message += `\n*Pending Products Total: ${pendingItems.length}*`;

        const encoded = encodeURIComponent(message);
        window.open(`https://api.whatsapp.com/send?text=${encoded}`, '_blank');
    }
</script>
@endsection
