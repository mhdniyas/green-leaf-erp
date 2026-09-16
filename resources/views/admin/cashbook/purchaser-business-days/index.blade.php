@extends('admin.cashbook.layouts.app')

@section('title', 'Purchaser Business Days — Admin Oversight')
@section('page_title', 'Purchaser Business Days Oversight')
@section('page_description', 'Executive cashbook oversight, daily matching verification, and business day lifecycle reports.')

@section('content')
<div class="space-y-6">
    <!-- Header Controls & Navigation -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Cashbook Oversight</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-700 border border-emerald-200">
                        Admin View Only
                    </span>
                </div>
                <h2 class="mt-1 text-2xl font-black text-slate-950">
                    Purchaser Business Days — {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
                </h2>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">
                    Monitoring and audit control for all warehouse business days. Read-only canonical synchronization.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.cashbook.purchaser-business-days.reports', ['month' => $month]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-teal-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-teal-500">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4 mr-2"></i>
                    Business Day Reports
                </a>
            </div>
        </div>
    </div>

    <!-- Monthly Executive Summary Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Business Days</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_days'] ?? 0 }}</p>
            <div class="mt-2 flex items-center gap-2 text-[11px] font-bold text-slate-600">
                <span class="text-emerald-600">{{ $monthlySummary['open_count'] ?? 0 }} Open</span>
                <span>·</span>
                <span class="text-slate-500">{{ $monthlySummary['closed_count'] ?? 0 }} Closed</span>
                <span>·</span>
                <span class="text-amber-600">{{ $monthlySummary['reopened_count'] ?? 0 }} Reopened</span>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Purchase Bills</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_bills'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Bills Recorded</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">Total Vendor Bills</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance Receives</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_advance'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Advance GRNs</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">Direct Inward Loads</div>
        </div>

        <div class="rounded-2xl border {{ ($monthlySummary['pending_products_count'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($monthlySummary['pending_products_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' }}">Pending Bills</p>
            <p class="mt-1 text-2xl font-black {{ ($monthlySummary['pending_products_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $monthlySummary['pending_products_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Unmatched Items</p>
            <div class="mt-2 text-[11px] font-bold text-rose-600">Pending Bill Entries</div>
        </div>

        <div class="rounded-2xl border {{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' }}">Unit Issues</p>
            <p class="mt-1 text-2xl font-black {{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $monthlySummary['unit_issues_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Mismatches</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">{{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'Fix Required' : 'All Clean' }}</div>
        </div>

        <div class="rounded-2xl border {{ ($monthlySummary['closed_with_pending_count'] ?? 0) > 0 ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($monthlySummary['closed_with_pending_count'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-400' }}">Closed w/ Pending</p>
            <p class="mt-1 text-2xl font-black {{ ($monthlySummary['closed_with_pending_count'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $monthlySummary['closed_with_pending_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Days With Exceptions</p>
            <div class="mt-2 text-[11px] font-bold text-amber-700">Audit Notes Recorded</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <form method="GET" action="{{ route('admin.cashbook.purchaser-business-days.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6 items-end">
            <!-- Month Picker -->
            <div>
                <label for="month" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Month</label>
                <input id="month" type="month" name="month" value="{{ $month }}" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>

            <!-- Warehouse Filter -->
            <div>
                <label for="warehouse_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Warehouse</label>
                <select id="warehouse_id" name="warehouse_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected((int) $selectedWarehouseId === (int) $wh->id)>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Purchaser Filter -->
            <div>
                <label for="purchaser_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Purchaser</label>
                <select id="purchaser_id" name="purchaser_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="">All Purchasers</option>
                    @foreach ($purchasers as $p)
                        <option value="{{ $p->id }}" @selected((int) $selectedPurchaserId === (int) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label for="status" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Status</label>
                <select id="status" name="status" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="all" @selected($statusFilter === 'all')>All Statuses</option>
                    <option value="open" @selected($statusFilter === 'open')>Open Only</option>
                    <option value="closed" @selected($statusFilter === 'closed')>Closed Only</option>
                    <option value="reopened" @selected($statusFilter === 'reopened')>Reopened Only</option>
                </select>
            </div>

            <!-- Checkbox Toggles -->
            <div class="flex flex-col justify-center space-y-1.5 py-1">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                    <input type="checkbox" name="pending_only" value="1" @checked($pendingOnly) class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <span>Pending Only</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                    <input type="checkbox" name="reopened_only" value="1" @checked($reopenedOnly) class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <span>Reopened Only</span>
                </label>
            </div>

            <!-- Filter Button -->
            <div class="flex gap-2">
                <button type="submit" class="h-10 w-full rounded-xl bg-slate-900 px-4 text-xs font-black text-white hover:bg-slate-800 transition">
                    Filter
                </button>
                <a href="{{ route('admin.cashbook.purchaser-business-days.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-600 hover:bg-slate-100">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Monthly Table (Latest First) -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
            <div>
                <h3 class="text-base font-black text-slate-950">Purchaser Business Days Log</h3>
                <p class="text-xs font-semibold text-slate-400">Chronological registry for {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }} · Latest business dates first</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">
                {{ count($daysData) }} Records
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                        <th class="py-3.5 px-6">Date</th>
                        <th class="py-3.5 px-4">Warehouse</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4">Purchaser</th>
                        <th class="py-3.5 px-4 text-right">Bills</th>
                        <th class="py-3.5 px-4 text-right">Advance</th>
                        <th class="py-3.5 px-4 text-right">Products</th>
                        <th class="py-3.5 px-4 text-right">Pending</th>
                        <th class="py-3.5 px-4 text-right">Unit Issues</th>
                        <th class="py-3.5 px-4">Closed At</th>
                        <th class="py-3.5 px-6 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse ($daysData as $item)
                        @php
                            $day = $item['day'];
                            $isOpen = $day->isOpen();
                            $isClosed = $day->isClosed();
                            $isReopened = $day->isReopened();
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6 font-black text-slate-900">
                                <span>{{ $item['business_date']->format('d M Y') }}</span>
                                <span class="block text-[10px] font-bold text-slate-400">{{ $item['business_date']->format('l') }}</span>
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-900">
                                {{ $item['warehouse']?->name ?? 'Warehouse' }}
                            </td>
                            <td class="py-4 px-4 text-center">
                                @if ($isOpen)
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">
                                        OPEN
                                    </span>
                                @elseif ($isReopened)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800">
                                        REOPENED
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-700">
                                        CLOSED
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-700">
                                {{ $item['purchaser'] }}
                            </td>
                            <td class="py-4 px-4 text-right font-black text-slate-900">
                                <div>{{ $item['bills_count'] }} bills</div>
                                <span class="text-[10px] font-bold text-slate-400">{{ $item['bills_formatted_total'] }}</span>
                            </td>
                            <td class="py-4 px-4 text-right font-black text-slate-900">
                                <div>{{ $item['advance_count'] }} GRNs</div>
                                <span class="text-[10px] font-bold text-slate-400">{{ $item['advance_formatted_total'] }}</span>
                            </td>
                            <td class="py-4 px-4 text-right font-bold text-slate-800">
                                {{ $item['products_count'] }}
                            </td>
                            <td class="py-4 px-4 text-right">
                                @if ($item['pending_count'] > 0)
                                    <span class="font-black text-rose-600 bg-rose-50 px-2 py-0.5 rounded-lg">{{ $item['pending_count'] }} pending</span>
                                @else
                                    <span class="text-emerald-600 font-bold">0</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right">
                                @if ($item['unit_issues_count'] > 0)
                                    <span class="font-black text-rose-600 bg-rose-50 px-2 py-0.5 rounded-lg">{{ $item['unit_issues_count'] }} fix</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-slate-600 text-[11px]">
                                @if ($day->closed_at)
                                    <span class="font-bold text-slate-800">{{ $day->closed_at->format('d M, h:i A') }}</span>
                                    <span class="block text-[10px] text-slate-400">by {{ $day->closedBy?->name ?? 'System' }}</span>
                                @else
                                    <span class="text-slate-400 font-semibold">--</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center whitespace-nowrap">
                                <a href="{{ route('admin.cashbook.purchaser-business-days.show', $day->uuid) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800 shadow-xs transition">
                                    View Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-12 text-center text-sm font-semibold text-slate-400">
                                No business day records found for the selected month and filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
