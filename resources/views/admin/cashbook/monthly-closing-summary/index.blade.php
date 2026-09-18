@extends('admin.cashbook.layouts.app')

@section('title', 'Monthly Closing Summary — ' . $summary['formatted_month'])

@section('header_title')
    <i data-lucide="calendar-check" class="w-5 h-5 text-emerald-600"></i> Monthly Closing Summary
@endsection

@section('header_subtitle')
    Consolidated end-of-month physical cash positions, company settlement, available credit, and carry-forward status.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6">

    <!-- Top Controls & Month Filter Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-3xl bg-white p-5 border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-xs">
                <i data-lucide="calendar" class="h-5 w-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-black tracking-tight text-slate-900">
                        Monthly Closing Summary
                    </h1>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black text-slate-600 font-mono">
                        {{ $summary['formatted_month'] }}
                    </span>
                    <span class="rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                        Read Only
                    </span>
                </div>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    Select a month to inspect audited closing balances and who-owes-whom carry-forwards.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label for="month-select" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Month:</label>
                <select
                    id="month-select"
                    onchange="window.location.href='{{ route('admin.cashbook.monthly-closing-summary.index') }}?month=' + this.value"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 cursor-pointer min-w-[160px]"
                >
                    @foreach($availableMonths as $m)
                        <option value="{{ $m['value'] }}" {{ $m['value'] === $month ? 'selected' : '' }}>
                            {{ $m['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <a href="{{ route('admin.cashbook.all-shops') }}" class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                <i data-lucide="layout-grid" class="h-4 w-4 text-slate-500"></i>
                <span>All Shops Workspace</span>
            </a>
        </div>
    </div>

    <!-- 6 Consolidated Metric Cards -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- Settlement Due -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Total Settlement Due</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-slate-900">
                ₹{{ number_format((float) ($summary['grand_totals']['settlement_due'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Current month obligation</span>
        </div>

        <!-- Company Received -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Company Received</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-emerald-700">
                ₹{{ number_format((float) ($summary['grand_totals']['received'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">Verified receipts</span>
        </div>

        <!-- Allocated This Month -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Allocated To Month</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-indigo-700">
                ₹{{ number_format((float) ($summary['grand_totals']['allocated'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-indigo-600">Matched to obligations</span>
        </div>

        <!-- Available Advance Credit -->
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">Available Credit</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-amber-950">
                ₹{{ number_format((float) ($summary['grand_totals']['closing_available_credit'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-amber-700">Company-held advance</span>
        </div>

        <!-- Pending Verification -->
        <div class="rounded-2xl border {{ (float) ($summary['grand_totals']['pending_verification'] ?? 0) > 0 ? 'border-sky-300 bg-sky-50/50' : 'border-slate-200/80 bg-white' }} p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider {{ (float) ($summary['grand_totals']['pending_verification'] ?? 0) > 0 ? 'text-sky-900' : 'text-slate-500' }}">Pending Verification</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold {{ (float) ($summary['grand_totals']['pending_verification'] ?? 0) > 0 ? 'text-sky-950' : 'text-slate-700' }}">
                ₹{{ number_format((float) ($summary['grand_totals']['pending_verification'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Unverified payment requests</span>
        </div>

        <!-- Net Physical Position -->
        <div class="rounded-2xl border border-slate-300 bg-slate-900 text-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-300">Net Physical Cash</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-black text-white">
                ₹{{ number_format(abs((float) ($summary['grand_totals']['closing_physical_net'] ?? 0)), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-bold text-emerald-400">
                {{ (float) ($summary['grand_totals']['closing_physical_net'] ?? 0) >= 0 ? 'Shops → Company' : 'Company → Shops' }}
            </span>
        </div>
    </div>

    <!-- All Shops Table Container -->
    <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-6 py-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="store" class="h-4 w-4 text-emerald-600"></i>
                    <span>All Shops Closing Matrix</span>
                </h2>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    Click any shop row to view detailed breakdown, credit timeline, and read-only drilldowns.
                </p>
            </div>
            <span class="rounded-full bg-slate-200/70 px-3 py-1 text-xs font-bold text-slate-700">
                {{ count($summary['shops']) }} Shops
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/70 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200/80">
                    <tr>
                        <th class="px-5 py-3.5">Shop</th>
                        <th class="px-4 py-3.5 text-right">Opening Position</th>
                        <th class="px-4 py-3.5 text-right">Settlement Due</th>
                        <th class="px-4 py-3.5 text-right">Received</th>
                        <th class="px-4 py-3.5 text-right">Closing Position</th>
                        <th class="px-4 py-3.5 text-right">Available Credit</th>
                        <th class="px-4 py-3.5 text-right">Pending</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($summary['shops'] as $row)
                        @php
                            $detailUrl = route('admin.cashbook.monthly-closing-summary.show', ['shop' => $row['slug'], 'month' => $month]);
                            $opening = $row['opening'];
                            $activity = $row['activity'];
                            $closing = $row['closing'];
                            $credit = $row['credit'];
                            $status = $row['status'];
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition group cursor-pointer" onclick="window.location.href='{{ $detailUrl }}'">
                            <!-- Shop Name & Code -->
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 font-mono text-xs font-bold text-slate-700 group-hover:bg-emerald-100 group-hover:text-emerald-800 transition">
                                        {{ substr($row['code'], 0, 2) }}
                                    </div>
                                    <div>
                                        <a href="{{ $detailUrl }}" class="font-bold text-slate-900 group-hover:text-emerald-700 transition">
                                            {{ $row['name'] }}
                                        </a>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="font-mono text-[10px] text-slate-400 font-semibold">{{ $row['code'] }}</span>
                                            @if($row['client_name'])
                                                <span class="rounded bg-slate-100 px-1.5 py-0.2 text-[9px] font-bold text-slate-500">{{ $row['client_name'] }}</span>
                                            @elseif($row['is_direct'])
                                                <span class="rounded bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.2 text-[9px] font-bold">Direct</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Opening Position -->
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-900">
                                    ₹{{ number_format((float) $opening['physical_position'], 2) }}
                                </span>
                                <span class="block text-[10px] font-bold {{ $opening['direction'] === 'shop_owes_company' ? 'text-amber-700' : ($opening['direction'] === 'company_owes_shop' ? 'text-indigo-700' : 'text-slate-400') }}">
                                    {{ $opening['direction_label'] }}
                                </span>
                            </td>

                            <!-- Settlement Due -->
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-900">
                                    ₹{{ number_format((float) $activity['settlement_due'], 2) }}
                                </span>
                            </td>

                            <!-- Received -->
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <span class="font-mono font-bold text-emerald-700">
                                    ₹{{ number_format((float) $activity['company_received'], 2) }}
                                </span>
                            </td>

                            <!-- Closing Position -->
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <span class="font-mono font-black text-slate-900">
                                    ₹{{ number_format((float) $closing['physical_position'], 2) }}
                                </span>
                                <span class="block text-[10px] font-black {{ $closing['direction'] === 'shop_owes_company' ? 'text-amber-700' : ($closing['direction'] === 'company_owes_shop' ? 'text-indigo-700' : 'text-slate-400') }}">
                                    {{ $closing['direction_label'] }}
                                </span>
                            </td>

                            <!-- Available Credit -->
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <span class="font-mono font-bold {{ (float) $credit['closing_available_credit'] > 0 ? 'text-amber-800 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200' : 'text-slate-400' }}">
                                    ₹{{ number_format((float) $credit['closing_available_credit'], 2) }}
                                </span>
                            </td>

                            <!-- Pending -->
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <span class="font-mono font-bold {{ (float) $activity['pending_verification'] > 0 ? 'text-sky-700' : 'text-slate-400' }}">
                                    ₹{{ number_format((float) $activity['pending_verification'], 2) }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                @php
                                    $badgeStyle = match($status['badge_color']) {
                                        'amber' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'sky' => 'bg-sky-50 text-sky-800 border-sky-200',
                                        default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $badgeStyle }}">
                                    {{ $status['label'] }}
                                </span>
                            </td>

                            <!-- Action -->
                            <td class="px-5 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-2xs">
                                    <span>Inspect</span>
                                    <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-400"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-12 text-center text-xs font-semibold text-slate-400">
                                No shop records found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
