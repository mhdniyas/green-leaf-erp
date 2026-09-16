@extends('purchaser.business-days.layouts.app')

@section('title', 'Purchaser Business Days — History & Control Board')
@section('page_title', 'Business Days')

@section('content')
<div class="space-y-6">
    <!-- Active Business Day Header / Empty State -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $activeDay ? ($activeDay->isOpen() ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'bg-amber-500 text-white shadow-md shadow-amber-500/20') : 'bg-slate-100 text-slate-400' }}">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">{{ $selectedWarehouse?->name ?? 'Warehouse' }}</span>
                        @if ($activeDay)
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider {{ $activeDay->isOpen() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $activeDay->status }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-slate-600">
                                No active Business Day
                            </span>
                        @endif
                    </div>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">
                        @if ($activeDay)
                            Business Day: {{ $activeDay->business_date->format('d M Y') }}
                        @else
                            No active Business Day
                        @endif
                    </h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">
                        @if ($activeDay)
                            Opened by {{ $activeDay->openedBy?->name ?? 'Purchaser' }} at {{ $activeDay->opened_at?->format('h:i A') }}
                        @else
                            Open a business day to record advance bills, reconcile goods, and manage purchasing.
                        @endif
                    </p>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex flex-wrap items-center gap-3">
                @if ($activeDay)
                    <a href="{{ route('purchasing.business-days.show', $activeDay->uuid) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-teal-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-teal-500">
                        Today's Business Day →
                    </a>
                    @if ($activeDay->isOpen() || $activeDay->isReopened())
                        <a href="{{ route('purchaser.business-days.close.index', $activeDay->uuid) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 text-xs font-black text-slate-800 shadow-xs transition hover:bg-slate-50">
                            Verify &amp; Close Day
                        </a>
                    @endif
                @else
                    <button type="button" onclick="document.getElementById('open-day-modal').classList.remove('hidden')" class="inline-flex h-11 items-center justify-center rounded-2xl bg-teal-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-teal-500">
                        + Open Business Day
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Monthly Control Board Filters -->
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('purchasing.business-days.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <div>
                <label for="month" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Month</label>
                <input id="month" type="month" name="month" value="{{ $month }}" class="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="warehouse_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Warehouse</label>
                <select id="warehouse_id" name="warehouse_id" class="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected($selectedWarehouseId === (int) $wh->id)>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Status</label>
                <select id="status" name="status" class="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="all" @selected($statusFilter === 'all')>All Statuses</option>
                    <option value="open" @selected($statusFilter === 'open')>OPEN</option>
                    <option value="closed" @selected($statusFilter === 'closed')>CLOSED</option>
                    <option value="reopened" @selected($statusFilter === 'reopened')>REOPENED</option>
                </select>
            </div>

            <div class="flex items-center h-11 pt-4">
                <label class="inline-flex cursor-pointer items-center gap-2">
                    <input type="checkbox" name="pending_only" value="1" @checked($pendingOnly) class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-xs font-bold text-slate-700">Pending Only</span>
                </label>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-slate-900 px-4 text-xs font-black text-white shadow-sm transition hover:bg-slate-800">
                    Filter Board
                </button>
                <a href="{{ route('purchasing.business-days.index') }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Monthly Control Board Table -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
            <div>
                <h3 class="text-base font-black text-slate-950">Monthly Control Board</h3>
                <p class="text-xs font-semibold text-slate-400">Latest business day first · Canonical reconciliation data</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                {{ $daysData->count() }} Recorded Days
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                        <th class="py-3.5 px-6">Date</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Bills</th>
                        <th class="py-3.5 px-4 text-right">Advance</th>
                        <th class="py-3.5 px-4 text-right">Products</th>
                        <th class="py-3.5 px-4 text-right">Fully Billed</th>
                        <th class="py-3.5 px-4 text-right">Pending</th>
                        <th class="py-3.5 px-4 text-right">Unit Issues</th>
                        <th class="py-3.5 px-6 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse ($daysData as $item)
                        @php
                            $d = $item['day'];
                            $isClean = $item['pending_count'] === 0 && $item['unit_issues_count'] === 0;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6 font-black text-slate-900 whitespace-nowrap">
                                <div>
                                    <span>{{ $item['business_date']->format('d M Y') }}</span>
                                    <span class="block text-[10px] font-bold text-slate-400">{{ $item['business_date']->format('l') }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                @if ($item['status'] === 'open')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">
                                        OPEN
                                    </span>
                                @elseif ($item['status'] === 'reopened')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800">
                                        REOPENED
                                    </span>
                                @else
                                    @if ($item['open_carry_count'] > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-teal-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-teal-900" title="{{ $item['open_carry_count'] }} pending tasks carried forward">
                                            <span>CLOSED</span>
                                            <span class="rounded-full bg-teal-200 px-1.5 py-0.2 text-[9px] font-black text-teal-950">{{ $item['open_carry_count'] }} Carried</span>
                                        </span>
                                    @elseif ($item['total_carry_count'] > 0)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-700" title="Carried forward tasks resolved">
                                            CLOSED · Resolved ✓
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-700">
                                            CLOSED
                                        </span>
                                    @endif
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right font-bold text-slate-900">{{ $item['bills_count'] }}</td>
                            <td class="py-4 px-4 text-right font-bold text-slate-900">{{ $item['advance_count'] }}</td>
                            <td class="py-4 px-4 text-right font-bold text-slate-900">{{ $item['products_count'] }}</td>
                            <td class="py-4 px-4 text-right font-bold text-emerald-600">{{ $item['fully_billed_count'] }}</td>
                            <td class="py-4 px-4 text-right">
                                @if ($item['pending_count'] > 0)
                                    <span class="inline-flex items-center justify-center rounded-lg bg-rose-50 px-2 py-0.5 text-xs font-black text-rose-600">
                                        {{ $item['pending_count'] }}
                                    </span>
                                @else
                                    <span class="font-bold text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right">
                                @if ($item['unit_issues_count'] > 0)
                                    <span class="inline-flex items-center justify-center rounded-lg bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-700">
                                        {{ $item['unit_issues_count'] }}
                                    </span>
                                @else
                                    <span class="font-bold text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center whitespace-nowrap">
                                <a href="{{ route('purchasing.business-days.show', $d->uuid) }}" class="inline-flex items-center justify-center rounded-xl {{ $item['status'] === 'closed' ? 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' : 'bg-teal-600 text-white hover:bg-teal-500' }} px-3.5 py-1.5 text-xs font-black shadow-xs transition">
                                    {{ $item['status'] === 'closed' ? 'View' : 'Review' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-sm font-semibold text-slate-400">
                                No business days found for the selected month and filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Open Business Day -->
<div id="open-day-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs hidden">
    <div class="w-full max-w-md overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <h3 class="text-base font-black text-slate-950">Open New Business Day</h3>
            <button type="button" onclick="document.getElementById('open-day-modal').classList.add('hidden')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('purchasing.business-days.open') }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label for="modal_warehouse_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Warehouse</label>
                <select id="modal_warehouse_id" name="warehouse_id" required class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected($selectedWarehouseId === (int) $wh->id)>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="modal_business_date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Business Date</label>
                <input id="modal_business_date" type="date" name="business_date" value="{{ now()->toDateString() }}" required class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>

            <div class="pt-3 flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('open-day-modal').classList.add('hidden')" class="rounded-2xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-2xl bg-teal-600 px-5 py-2.5 text-xs font-black text-white hover:bg-teal-500 shadow-sm">
                    Open Day
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
