@extends('admin.cashbook.layouts.app')

@section('title', 'Pending Warehouse Bills - Reports V1')

@section('header_title')
    <i data-lucide="clipboard-list" class="h-5 w-5 text-indigo-600"></i> Pending Warehouse Bills Management
@endsection

@section('header_subtitle')
    Authoritative reconciliation & physical receiving center for outstanding warehouse purchaser bills.
@endsection

@php
    $currentStatus = $filters['status'] ?? 'pending';
    $currentPreset = $filters['date_preset'] ?? 'all';
    $currentSearch = $filters['search'] ?? '';
    $currentPurchaser = $filters['purchaser_id'] ?? null;
    $currentSupplier = $filters['supplier_id'] ?? null;
    $currentWarehouse = $filters['warehouse_id'] ?? null;
    $currentPerPage = $filters['per_page'] ?? 25;
@endphp

@section('content')
<div
    class="mx-auto max-w-[96rem] space-y-6"
    x-data="{
        selectedBills: [],
        selectAll: false,
        pageBillIds: {{ Js::from($bills->pluck('id')->all()) }},
        detailsModalOpen: false,
        matchModalOpen: false,
        receiveModalOpen: false,
        bulkMatchModalOpen: false,
        bulkReceiveModalOpen: false,
        activeBill: null,
        activeDetails: null,
        loadingDetails: false,
        submittingAction: false,
        bulkMatchPreview: null,
        loadingBulkPreview: false,

        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedBills = [...this.pageBillIds];
            } else {
                this.selectedBills = [];
            }
        },
        updateSelectAllState() {
            this.selectAll = this.pageBillIds.length > 0 && this.selectedBills.length === this.pageBillIds.length;
        },
        openDetails(billId) {
            this.loadingDetails = true;
            this.detailsModalOpen = true;
            this.activeDetails = null;
            fetch('{{ url('admin/cashbook/reports-v1/pending-bills') }}/' + billId + '/details')
                .then(r => r.json())
                .then(data => {
                    this.loadingDetails = false;
                    if (data.success) {
                        this.activeDetails = data.data;
                        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
                    } else {
                        alert(data.message || 'Failed to load bill details.');
                    }
                })
                .catch(() => {
                    this.loadingDetails = false;
                    alert('Network error loading bill details.');
                });
        },
        openMatchModal(bill) {
            this.activeBill = bill;
            this.matchModalOpen = true;
            this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
        },
        openReceiveModal(bill) {
            this.activeBill = bill;
            this.receiveModalOpen = true;
            this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
        },
        openBulkMatch() {
            if (this.selectedBills.length === 0) return;
            this.loadingBulkPreview = true;
            this.bulkMatchModalOpen = true;
            this.bulkMatchPreview = null;
            fetch('{{ route('admin.cashbook.reports-v1.pending-bills.bulk-match-preview') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    invoice_ids: this.selectedBills,
                    warehouse_id: '{{ $currentWarehouse ?: '' }}' || null
                })
            })
            .then(r => r.json())
            .then(data => {
                this.loadingBulkPreview = false;
                if (data.success) {
                    this.bulkMatchPreview = data.data;
                    this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
                } else {
                    alert(data.message || 'Failed to generate bulk match preview.');
                }
            })
            .catch(() => {
                this.loadingBulkPreview = false;
                alert('Network error generating bulk match preview.');
            });
        },
        openBulkReceive() {
            if (this.selectedBills.length === 0) return;
            this.bulkReceiveModalOpen = true;
            this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
        }
    }"
>
    <!-- TOP SUMMARY KPI CARDS (5 METRICS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <!-- 1. Total Pending Bills -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-slate-500 mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider">Total Pending Bills</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <i data-lucide="layers" class="h-4 w-4"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-slate-900 tracking-tight">
                        {{ number_format($metrics['total_pending_bills']) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">bills</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-xs text-slate-500">
                <span>All historical dates</span>
                <span class="font-bold text-slate-700">Awaiting action</span>
            </div>
        </div>

        <!-- 2. Total Outstanding KG -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-slate-500 mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider">Outstanding Weight</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <i data-lucide="scale" class="h-4 w-4"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-indigo-700 font-mono tracking-tight">
                        {{ number_format($metrics['total_outstanding_qty'], 1) }}
                    </span>
                    <span class="text-xs font-bold text-indigo-500 uppercase tracking-wider">kg</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-xs text-slate-500">
                <span>Total unreceived qty</span>
                <span class="font-bold text-indigo-700 font-mono">{{ number_format($metrics['total_outstanding_qty'] / 1000, 2) }} Tons</span>
            </div>
        </div>

        <!-- 3. Eligible for Advance Matching -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-slate-500 mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider">Advance Matchable</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <i data-lucide="git-merge" class="h-4 w-4"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-emerald-700 tracking-tight">
                        {{ number_format($metrics['bills_eligible_for_advance_matching']) }}
                    </span>
                    <span class="text-xs font-bold text-emerald-500 uppercase tracking-wider">bills</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-xs text-slate-500">
                <span>Open advances available</span>
                <span class="font-bold text-emerald-700">Ready to match</span>
            </div>
        </div>

        <!-- 4. Awaiting Physical Receiving -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-slate-500 mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider">Awaiting Physical Receive</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <i data-lucide="truck" class="h-4 w-4"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-amber-700 tracking-tight">
                        {{ number_format($metrics['awaiting_physical_receiving']) }}
                    </span>
                    <span class="text-xs font-bold text-amber-500 uppercase tracking-wider">bills</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-xs text-slate-500">
                <span>Pending warehouse arrival</span>
                <span class="font-bold text-amber-700">Direct receive</span>
            </div>
        </div>

        <!-- 5. Cleared Today -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-slate-500 mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider">Cleared Today</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-slate-900 tracking-tight">
                        {{ number_format($metrics['cleared_today_count']) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">bills</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-xs text-slate-500">
                <span>Weight cleared today:</span>
                <span class="font-bold text-slate-800 font-mono">{{ number_format($metrics['cleared_today_qty'], 1) }} kg</span>
            </div>
        </div>
    </div>

    <!-- FILTER & STATUS TOOLBAR -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <!-- Top Row: Status Tabs & Quick Presets -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <!-- Status Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
                @php
                    $tabs = [
                        'pending' => 'Pending Bills (' . number_format($metrics['total_pending_bills']) . ')',
                        'not_received' => 'Not Received',
                        'partially_received' => 'Partially Received',
                        'cleared' => 'Cleared History',
                        'all' => 'All Records',
                    ];
                @endphp
                @foreach ($tabs as $key => $label)
                    <a
                        href="{{ route('admin.cashbook.reports-v1.pending-bills', array_merge(request()->query(), ['status' => $key, 'page' => 1])) }}"
                        class="px-3.5 py-1.5 rounded-xl font-bold whitespace-nowrap transition {{ $currentStatus === $key ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <!-- Date Presets -->
            <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
                <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px] mr-1">Date:</span>
                @foreach (['all' => 'All Time', 'today' => 'Today', 'yesterday' => 'Yesterday', 'last_7_days' => 'Last 7 Days', 'this_month' => 'This Month'] as $pKey => $pLabel)
                    <a
                        href="{{ route('admin.cashbook.reports-v1.pending-bills', array_merge(request()->query(), ['date_preset' => $pKey, 'page' => 1])) }}"
                        class="px-2.5 py-1 rounded-lg font-semibold whitespace-nowrap text-xs transition {{ $currentPreset === $pKey ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' }}"
                    >
                        {{ $pLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Filter Controls Form -->
        <form method="GET" action="{{ route('admin.cashbook.reports-v1.pending-bills') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-xs">
            <input type="hidden" name="status" value="{{ $currentStatus }}">
            <input type="hidden" name="date_preset" value="{{ $currentPreset }}">

            <!-- Search -->
            <div class="lg:col-span-2 relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $currentSearch }}"
                    placeholder="Search by bill #, purchaser, supplier..."
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 py-2 text-xs font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                >
            </div>

            <!-- Purchaser Dropdown -->
            <div>
                <select
                    name="purchaser_id"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-indigo-500 focus:outline-hidden"
                >
                    <option value="">All Purchasers</option>
                    @foreach ($filterOptions['purchasers'] as $p)
                        <option value="{{ $p['id'] }}" {{ (string)$currentPurchaser === (string)$p['id'] ? 'selected' : '' }}>
                            {{ $p['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Supplier Dropdown -->
            <div>
                <select
                    name="supplier_id"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-indigo-500 focus:outline-hidden"
                >
                    <option value="">All Suppliers</option>
                    @foreach ($filterOptions['suppliers'] as $s)
                        <option value="{{ $s['id'] }}" {{ (string)$currentSupplier === (string)$s['id'] ? 'selected' : '' }}>
                            {{ $s['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Warehouse Dropdown -->
            <div>
                <select
                    name="warehouse_id"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-indigo-500 focus:outline-hidden"
                >
                    <option value="">All Warehouses</option>
                    @foreach ($filterOptions['warehouses'] as $w)
                        <option value="{{ $w['id'] }}" {{ (string)$currentWarehouse === (string)$w['id'] ? 'selected' : '' }}>
                            {{ $w['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Actions / Submit -->
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 rounded-xl bg-slate-900 px-4 py-2 font-bold text-white hover:bg-slate-800 transition"
                >
                    Apply Filter
                </button>
                <a
                    href="{{ route('admin.cashbook.reports-v1.pending-bills') }}"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 font-bold text-slate-600 hover:bg-slate-100 transition"
                    title="Reset all filters"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- BULK ACTIONS FLOATING BAR -->
    <div
        x-show="selectedBills.length > 0"
        x-cloak
        class="sticky top-4 z-30 flex items-center justify-between rounded-2xl border border-indigo-200 bg-indigo-50/95 px-5 py-3 shadow-lg backdrop-blur-md animate-in fade-in slide-in-from-top-2 duration-150"
    >
        <div class="flex items-center gap-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white" x-text="selectedBills.length"></span>
            <span class="text-xs font-bold text-indigo-950">
                <span x-text="selectedBills.length"></span> bill(s) selected
            </span>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <button
                type="button"
                @click="openBulkMatch()"
                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 font-bold text-white shadow-xs hover:bg-emerald-700 transition"
            >
                <i data-lucide="git-merge" class="h-3.5 w-3.5"></i>
                <span>Bulk Match Advance</span>
            </button>
            <button
                type="button"
                @click="openBulkReceive()"
                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-1.5 font-bold text-white shadow-xs hover:bg-indigo-700 transition"
            >
                <i data-lucide="check" class="h-3.5 w-3.5"></i>
                <span>Bulk Receive Goods</span>
            </button>
            <button
                type="button"
                @click="selectedBills = []; selectAll = false"
                class="rounded-xl border border-indigo-200 bg-white px-3 py-1.5 font-bold text-indigo-700 hover:bg-indigo-50 transition"
            >
                Clear Selection
            </button>
        </div>
    </div>

    <!-- MAIN DATA TABLE -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 pl-4 pr-2 w-8 text-center">
                            <input
                                type="checkbox"
                                x-model="selectAll"
                                @change="toggleSelectAll()"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </th>
                        <th class="py-3.5 px-3">Date</th>
                        <th class="py-3.5 px-3">Bill Number</th>
                        <th class="py-3.5 px-3">Purchaser</th>
                        <th class="py-3.5 px-3">Supplier</th>
                        <th class="py-3.5 px-3">Warehouse</th>
                        <th class="py-3.5 px-3 text-right">Bill Qty</th>
                        <th class="py-3.5 px-3 text-right">Already Received</th>
                        <th class="py-3.5 px-3 text-right">Advance Matched</th>
                        <th class="py-3.5 px-3 text-right">Outstanding Qty</th>
                        <th class="py-3.5 px-3 text-center">Current Status</th>
                        <th class="py-3.5 pr-4 pl-3 text-right">Available Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse ($bills as $bill)
                        <tr class="hover:bg-slate-50/80 transition {{ in_array($bill['id'], $filters['highlight'] ?? []) ? 'bg-amber-50/40' : '' }}">
                            <!-- Checkbox -->
                            <td class="py-3 pl-4 pr-2 text-center">
                                <input
                                    type="checkbox"
                                    value="{{ $bill['id'] }}"
                                    x-model="selectedBills"
                                    @change="updateSelectAllState()"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                >
                            </td>

                            <!-- Date -->
                            <td class="py-3 px-3 whitespace-nowrap font-mono text-slate-600">
                                {{ $bill['date'] }}
                            </td>

                            <!-- Bill Number -->
                            <td class="py-3 px-3 whitespace-nowrap font-mono font-bold text-slate-900">
                                {{ $bill['invoice_number'] }}
                            </td>

                            <!-- Purchaser -->
                            <td class="py-3 px-3 whitespace-nowrap font-semibold text-slate-800">
                                {{ $bill['purchaser'] }}
                            </td>

                            <!-- Supplier -->
                            <td class="py-3 px-3 whitespace-nowrap text-slate-600">
                                {{ $bill['supplier'] }}
                            </td>

                            <!-- Warehouse -->
                            <td class="py-3 px-3 whitespace-nowrap text-slate-600">
                                {{ $bill['warehouse_name'] }}
                            </td>

                            <!-- Bill Qty -->
                            <td class="py-3 px-3 text-right font-mono text-slate-600 whitespace-nowrap">
                                {{ number_format($bill['bill_qty'], 1) }} kg
                            </td>

                            <!-- Already Received -->
                            <td class="py-3 px-3 text-right font-mono text-slate-600 whitespace-nowrap">
                                {{ number_format($bill['already_received_qty'], 1) }} kg
                            </td>

                            <!-- Advance Matched -->
                            <td class="py-3 px-3 text-right font-mono text-emerald-700 font-semibold whitespace-nowrap">
                                {{ number_format($bill['advance_matched_qty'], 1) }} kg
                            </td>

                            <!-- Outstanding Qty -->
                            <td class="py-3 px-3 text-right font-mono font-bold text-indigo-700 whitespace-nowrap">
                                {{ number_format($bill['outstanding_qty'], 1) }} kg
                            </td>

                            <!-- Current Status -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @if ($bill['status'] === 'Partially Received')
                                    <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        <span>Partially Received</span>
                                    </span>
                                @elseif ($bill['status'] === 'Cleared')
                                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Cleared</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        <span>Not Received</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3 pr-4 pl-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Match Advance Button -->
                                    @if ($bill['can_match_advance'])
                                        <button
                                            type="button"
                                            @click="openMatchModal({{ Js::from($bill) }})"
                                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 transition"
                                            title="Match with open warehouse advances ({{ $bill['suggested_match_qty'] }} kg eligible)"
                                        >
                                            <i data-lucide="git-merge" class="h-3 w-3"></i>
                                            <span>Match ({{ $bill['suggested_match_qty'] }}k)</span>
                                        </button>
                                    @endif

                                    <!-- Receive Goods Button -->
                                    @if ($bill['can_receive_goods'])
                                        <button
                                            type="button"
                                            @click="openReceiveModal({{ Js::from($bill) }})"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100 transition"
                                            title="Confirm physical warehouse receiving"
                                        >
                                            <i data-lucide="truck" class="h-3 w-3"></i>
                                            <span>Receive</span>
                                        </button>
                                    @endif

                                    <!-- View Details Button -->
                                    <button
                                        type="button"
                                        @click="openDetails({{ $bill['id'] }})"
                                        class="rounded-lg border border-slate-200 bg-white p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition"
                                        title="View full bill breakdown"
                                    >
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-10 w-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-2">
                                        <i data-lucide="inbox" class="h-5 w-5"></i>
                                    </div>
                                    <p class="text-sm font-bold text-slate-700">No Bills Found</p>
                                    <p class="text-xs text-slate-500 mt-0.5">There are no bills matching the selected status and filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <div class="border-t border-slate-200 px-6 py-4 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <span class="text-slate-500">
                Showing <span class="font-bold text-slate-800">{{ $bills->firstItem() ?? 0 }}</span> to <span class="font-bold text-slate-800">{{ $bills->lastItem() ?? 0 }}</span> of <span class="font-bold text-slate-800">{{ $bills->total() }}</span> records
            </span>
            <div>
                {{ $bills->links() }}
            </div>
        </div>
    </div>

    <!-- BILL DETAILS MODAL -->
    <div
        x-show="detailsModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6"
        @click.self="detailsModalOpen = false"
        @keydown.escape.window="detailsModalOpen = false"
    >
        <div
            class="flex flex-col w-full max-w-4xl max-h-[85vh] rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 bg-slate-50/75">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <i data-lucide="file-text" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-slate-900" x-text="activeDetails ? activeDetails.invoice_number : 'Bill Details'"></h3>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-mono font-bold text-slate-600" x-text="activeDetails ? activeDetails.date : ''"></span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Purchaser: <span class="font-semibold text-slate-700" x-text="activeDetails ? activeDetails.purchaser_name : ''"></span> &bull;
                            Supplier: <span class="font-semibold text-slate-700" x-text="activeDetails ? activeDetails.supplier_name : ''"></span>
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="detailsModalOpen = false"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                >
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 p-6 space-y-6">
                <!-- Loading State -->
                <div x-show="loadingDetails" class="py-12 text-center text-slate-400">
                    <i data-lucide="loader" class="h-8 w-8 animate-spin mx-auto text-indigo-600 mb-2"></i>
                    <p class="text-xs font-semibold">Loading details...</p>
                </div>

                <!-- Content -->
                <template x-if="!loadingDetails && activeDetails">
                    <div class="space-y-6">
                        <!-- Items Table -->
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Bill Items</h4>
                            <div class="rounded-xl border border-slate-200 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="py-2.5 px-3">Product</th>
                                            <th class="py-2.5 px-3 text-right">Bill Qty</th>
                                            <th class="py-2.5 px-3 text-right">Batch Status</th>
                                            <th class="py-2.5 px-3 text-right">Pending Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                        <template x-for="item in activeDetails.items" :key="item.id">
                                            <tr>
                                                <td class="py-2.5 px-3">
                                                    <span class="font-bold text-slate-900" x-text="item.product_name"></span>
                                                    <span class="text-[10px] font-mono text-slate-400 ml-1" x-text="item.product_sku ? '(' + item.product_sku + ')' : ''"></span>
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-mono" x-text="item.received_qty.toFixed(1) + ' ' + item.unit"></td>
                                                <td class="py-2.5 px-3 text-right">
                                                    <span
                                                        class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold"
                                                        :class="item.batch_pending ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                                                        x-text="item.batch_pending ? 'Receive Pending' : 'Confirmed'"
                                                    ></span>
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-mono font-bold" :class="item.batch_pending ? 'text-indigo-700' : 'text-slate-400'" x-text="item.batch_total_kg.toFixed(1) + ' kg'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Advance Matches History -->
                        <div x-show="activeDetails.advance_matches && activeDetails.advance_matches.length > 0">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Linked Advance Matches</h4>
                            <div class="rounded-xl border border-slate-200 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="py-2.5 px-3">Advance Receipt</th>
                                            <th class="py-2.5 px-3 text-right">Matched Qty</th>
                                            <th class="py-2.5 px-3 text-right">Confirmed At</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                        <template x-for="m in activeDetails.advance_matches" :key="m.id">
                                            <tr>
                                                <td class="py-2.5 px-3 font-mono font-bold text-slate-800" x-text="m.advance_grn_number"></td>
                                                <td class="py-2.5 px-3 text-right font-mono text-emerald-700 font-bold" x-text="m.matched_qty.toFixed(1) + ' ' + (m.unit || 'kg')"></td>
                                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" x-text="m.confirmed_at"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-end border-t border-slate-200 px-6 py-3 bg-slate-50 text-xs">
                <button
                    type="button"
                    @click="detailsModalOpen = false"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-bold text-slate-700 hover:bg-slate-100 transition"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- MATCH ADVANCE CONFIRMATION MODAL -->
    <div
        x-show="matchModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6"
        @click.self="matchModalOpen = false"
        @keydown.escape.window="matchModalOpen = false"
    >
        <div
            class="flex flex-col w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 bg-emerald-50/60">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <i data-lucide="git-merge" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Confirm Advance Match</h3>
                        <p class="text-xs text-slate-500">Reconcile bill with confirmed warehouse advance receipts.</p>
                    </div>
                </div>
                <button type="button" @click="matchModalOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form
                method="POST"
                :action="'{{ url('admin/cashbook/reports-v1/pending-bills') }}/' + (activeBill ? activeBill.id : '') + '/match-advance'"
                class="p-6 space-y-4 text-xs"
            >
                @csrf
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 space-y-2">
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Bill Number:</span>
                        <span class="font-bold text-slate-900 font-mono" x-text="activeBill ? activeBill.invoice_number : ''"></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Bill Quantity:</span>
                        <span class="font-bold text-slate-900 font-mono" x-text="activeBill ? activeBill.bill_qty + ' kg' : ''"></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Eligible Advance Match:</span>
                        <span class="font-black text-emerald-700 font-mono" x-text="activeBill ? activeBill.suggested_match_qty + ' kg' : ''"></span>
                    </div>
                </div>

                <p class="text-slate-600 leading-relaxed">
                    Matching will clear the advance receipt allocation and deduct duplicate bill stock so physical inventory remains strictly accurate.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="matchModalOpen = false"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-bold text-slate-700 hover:bg-slate-100 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-600 px-5 py-2 font-bold text-white hover:bg-emerald-700 transition shadow-xs"
                    >
                        Confirm Match
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RECEIVE GOODS CONFIRMATION MODAL -->
    <div
        x-show="receiveModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6"
        @click.self="receiveModalOpen = false"
        @keydown.escape.window="receiveModalOpen = false"
    >
        <div
            class="flex flex-col w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 bg-indigo-50/60">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <i data-lucide="truck" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Confirm Physical Receiving</h3>
                        <p class="text-xs text-slate-500">Record physical delivery into warehouse inventory.</p>
                    </div>
                </div>
                <button type="button" @click="receiveModalOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form
                method="POST"
                :action="'{{ url('admin/cashbook/reports-v1/pending-bills') }}/' + (activeBill ? activeBill.id : '') + '/receive-goods'"
                class="p-6 space-y-4 text-xs"
            >
                @csrf
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 space-y-2">
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Bill Number:</span>
                        <span class="font-bold text-slate-900 font-mono" x-text="activeBill ? activeBill.invoice_number : ''"></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Outstanding To Receive:</span>
                        <span class="font-black text-indigo-700 font-mono" x-text="activeBill ? activeBill.outstanding_qty + ' kg' : ''"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-700 font-bold mb-1">Target Warehouse</label>
                    <select
                        name="warehouse_id"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-indigo-500 focus:outline-hidden"
                    >
                        @foreach ($filterOptions['warehouses'] as $w)
                            <option value="{{ $w['id'] }}" :selected="activeBill && activeBill.warehouse_id == {{ $w['id'] }}">
                                {{ $w['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <p class="text-slate-600 leading-relaxed">
                    This confirms physical arrival of goods, updates stock batch status to received, and adds the weight into central warehouse inventory.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="receiveModalOpen = false"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-bold text-slate-700 hover:bg-slate-100 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-indigo-600 px-5 py-2 font-bold text-white hover:bg-indigo-700 transition shadow-xs"
                    >
                        Confirm Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- BULK MATCH CONFIRMATION MODAL -->
    <div
        x-show="bulkMatchModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6"
        @click.self="bulkMatchModalOpen = false"
        @keydown.escape.window="bulkMatchModalOpen = false"
    >
        <div
            class="flex flex-col w-full max-w-xl max-h-[85vh] rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 bg-emerald-50/75">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <i data-lucide="git-merge" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Bulk Advance Matching</h3>
                        <p class="text-xs text-slate-500">Match multiple selected bills with available warehouse advance receipts.</p>
                    </div>
                </div>
                <button type="button" @click="bulkMatchModalOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 p-6 space-y-4 text-xs">
                <div x-show="loadingBulkPreview" class="py-12 text-center text-slate-400">
                    <i data-lucide="loader" class="h-8 w-8 animate-spin mx-auto text-emerald-600 mb-2"></i>
                    <p class="text-xs font-semibold">Analyzing candidate advances for selected bills...</p>
                </div>

                <template x-if="!loadingBulkPreview && bulkMatchPreview">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4">
                                <span class="text-slate-500 text-[10px] font-bold uppercase tracking-wider block">Matchable Bills</span>
                                <span class="text-2xl font-black text-emerald-700" x-text="bulkMatchPreview.matchable_count"></span>
                                <span class="text-xs text-slate-500 block mt-1" x-text="bulkMatchPreview.total_matchable_qty.toFixed(1) + ' kg total'"></span>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                                <span class="text-slate-500 text-[10px] font-bold uppercase tracking-wider block">Skipped (No Matches)</span>
                                <span class="text-2xl font-black text-slate-700" x-text="bulkMatchPreview.skipped_count"></span>
                                <span class="text-xs text-slate-400 block mt-1">Remain pending receiving</span>
                            </div>
                        </div>

                        <!-- Matchable List -->
                        <div x-show="bulkMatchPreview.matchable_bills.length > 0">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Bills to be Matched</h4>
                            <div class="rounded-xl border border-slate-200 max-h-48 overflow-y-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500 border-b border-slate-200 sticky top-0">
                                        <tr>
                                            <th class="py-2 px-3">Bill</th>
                                            <th class="py-2 px-3">Supplier</th>
                                            <th class="py-2 px-3 text-right">Match Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                        <template x-for="b in bulkMatchPreview.matchable_bills" :key="b.id">
                                            <tr>
                                                <td class="py-2 px-3 font-mono font-bold" x-text="b.bill_number"></td>
                                                <td class="py-2 px-3 text-slate-600" x-text="b.supplier"></td>
                                                <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700" x-text="b.matched_qty.toFixed(1) + ' kg'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <form
                method="POST"
                action="{{ route('admin.cashbook.reports-v1.pending-bills.bulk-match-confirm') }}"
                class="flex items-center justify-end gap-2 border-t border-slate-200 px-6 py-3 bg-slate-50 text-xs"
            >
                @csrf
                <template x-for="id in selectedBills" :key="id">
                    <input type="hidden" name="invoice_ids[]" :value="id">
                </template>
                <input type="hidden" name="warehouse_id" value="{{ $currentWarehouse ?: '' }}">

                <button
                    type="button"
                    @click="bulkMatchModalOpen = false"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-bold text-slate-700 hover:bg-slate-100 transition"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="loadingBulkPreview || !bulkMatchPreview || bulkMatchPreview.matchable_count === 0"
                    class="rounded-xl bg-emerald-600 px-5 py-2 font-bold text-white hover:bg-emerald-700 transition disabled:opacity-50 shadow-xs"
                >
                    Confirm Bulk Match
                </button>
            </form>
        </div>
    </div>

    <!-- BULK RECEIVE CONFIRMATION MODAL -->
    <div
        x-show="bulkReceiveModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6"
        @click.self="bulkReceiveModalOpen = false"
        @keydown.escape.window="bulkReceiveModalOpen = false"
    >
        <div
            class="flex flex-col w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 bg-indigo-50/75">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <i data-lucide="truck" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Bulk Warehouse Receiving</h3>
                        <p class="text-xs text-slate-500">Receive all selected bills directly into warehouse inventory.</p>
                    </div>
                </div>
                <button type="button" @click="bulkReceiveModalOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('admin.cashbook.reports-v1.pending-bills.bulk-receive-confirm') }}"
                class="p-6 space-y-4 text-xs"
            >
                @csrf
                <template x-for="id in selectedBills" :key="id">
                    <input type="hidden" name="invoice_ids[]" :value="id">
                </template>

                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4">
                    <span class="text-slate-500 text-[10px] font-bold uppercase tracking-wider block">Selected Bills</span>
                    <span class="text-2xl font-black text-indigo-900" x-text="selectedBills.length"></span>
                    <p class="text-xs text-slate-600 mt-2">
                        All unreceived quantities across these bills will be physically confirmed into inventory.
                    </p>
                </div>

                <div>
                    <label class="block text-slate-700 font-bold mb-1">Target Warehouse</label>
                    <select
                        name="warehouse_id"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-indigo-500 focus:outline-hidden"
                    >
                        @foreach ($filterOptions['warehouses'] as $w)
                            <option value="{{ $w['id'] }}" {{ (string)$currentWarehouse === (string)$w['id'] ? 'selected' : '' }}>
                                {{ $w['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="bulkReceiveModalOpen = false"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-bold text-slate-700 hover:bg-slate-100 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-indigo-600 px-5 py-2 font-bold text-white hover:bg-indigo-700 transition shadow-xs"
                    >
                        Confirm Bulk Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

