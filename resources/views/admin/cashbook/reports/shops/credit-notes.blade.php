@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Credit Notes Report — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-minus" class="w-5 h-5 text-emerald-600"></i> Shop Credit Notes
@endsection

@section('header_subtitle')
    Independent price, quantity, shortage, and return adjustments against finalized shop invoices.
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
        <!-- 1. Total Credit Notes -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Total Notes</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-slate-900">
                {{ $summary['total_count'] ?? 0 }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Total recorded notes</span>
        </div>

        <!-- 2. Total Approved Credits -->
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-emerald-800">Approved Credits</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-emerald-700">
                -₹{{ number_format((float) ($summary['approved_credits'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">Reduces shop payable</span>
        </div>

        <!-- 3. Total Approved Debits -->
        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-blue-800">Approved Debits</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-blue-700">
                +₹{{ number_format((float) ($summary['approved_debits'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-blue-600">Increases shop payable</span>
        </div>

        <!-- 4. Net Adjustment -->
        <div class="rounded-2xl border border-slate-900 bg-slate-900 p-4 shadow-xs text-white">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-300">Net Adjustment</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold {{ ($summary['net_adjustment'] ?? 0) <= 0 ? 'text-emerald-400' : 'text-blue-400' }}">
                {{ ($summary['net_adjustment'] ?? 0) <= 0 ? '' : '+' }}₹{{ number_format((float) ($summary['net_adjustment'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">
                {{ ($summary['net_adjustment'] ?? 0) <= 0 ? 'Net payable reduction' : 'Net payable increase' }}
            </span>
        </div>

        <!-- 5. Draft / Pending Notes -->
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4 shadow-xs col-span-2 sm:col-span-1">
            <span class="block text-[10px] font-black uppercase tracking-wider text-amber-800">Draft / Pending Notes</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-amber-700">
                {{ $summary['draft_count'] ?? 0 }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-amber-600">Awaiting approval</span>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="white-card rounded-3xl p-4 sm:p-5">
        <!-- Preset Timeframe Buttons -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-4">
            <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'direction' => $selectedDirection, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'yesterday', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'direction' => $selectedDirection, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'yesterday' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Yesterday
                </a>
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'this_month', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'direction' => $selectedDirection, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'this_month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    This Month
                </a>
                @php
                    $prevMonthName = now()->subMonth()->format('M');
                @endphp
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'prev_month', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'direction' => $selectedDirection, 'invoice_search' => $invoiceSearch, 'search' => $search]) }}"
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

            <!-- Status Dropdown -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Status</span>
                <select name="status" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="draft" @selected($selectedStatus === 'draft')>Draft</option>
                    <option value="approved" @selected($selectedStatus === 'approved')>Approved</option>
                    <option value="voided" @selected($selectedStatus === 'voided')>Voided</option>
                </select>
            </label>

            <!-- Direction Dropdown -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Type</span>
                <select name="direction" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs cursor-pointer">
                    <option value="">All Types</option>
                    <option value="credit" @selected($selectedDirection === 'credit')>Credit (-Payable)</option>
                    <option value="debit" @selected($selectedDirection === 'debit')>Debit (+Payable)</option>
                </select>
            </label>

            <!-- Invoice Reference Search -->
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Invoice Ref</span>
                <input type="text" name="invoice_search" value="{{ $invoiceSearch }}" placeholder="INV-..." class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs">
            </label>

            <!-- Note Search & Submit Buttons -->
            <div class="sm:col-span-2 lg:col-span-6 flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-2 flex-1 max-w-md">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 shrink-0">Note #:</span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="SCN-..." class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs">
                </div>
                <div class="flex items-center gap-2">
                    @if($selectedShopId || $selectedStatus || $selectedDirection || $invoiceSearch || $search || $timeframe !== 'this_month')
                        <a href="{{ route('admin.cashbook.reports.shops.credit-notes') }}" class="h-10 inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition" title="Reset Filters">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            <span>Reset</span>
                        </a>
                    @endif
                    <button type="submit" class="h-10 rounded-xl bg-slate-900 px-5 text-xs font-bold text-white hover:bg-slate-800 shadow-xs">
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
                <h3 class="text-sm font-black text-slate-900">Shop Credit Notes List</h3>
                <p class="text-[11px] font-medium text-slate-400">
                    Showing {{ $creditNotes->firstItem() ?? 0 }} to {{ $creditNotes->lastItem() ?? 0 }} of {{ $creditNotes->total() }} notes
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
                        <th class="py-3 px-3">Credit Note</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Shop</th>
                        <th class="py-3 px-3">Invoice Ref</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3 text-right">Amount</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                    @forelse ($creditNotes as $note)
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Credit Note # -->
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-900 block font-mono">{{ $note->credit_note_number }}</span>
                                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider mt-0.5
                                    {{ $note->inventory_action->value === 'return_to_stock' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $note->inventory_action->value === 'return_to_stock' ? 'Stock Return' : 'Financial Only' }}
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

                            <!-- Invoice Reference -->
                            <td class="py-3 px-3 font-mono">
                                @if ($note->invoice)
                                    <a href="{{ route('purchasing.shop-invoices.show', $note->invoice) }}"
                                        target="_blank"
                                        class="text-indigo-600 hover:text-indigo-800 hover:underline font-bold inline-flex items-center gap-1"
                                        title="View Linked Original Invoice">
                                        <span>{{ $note->invoice->invoice_number }}</span>
                                        <i data-lucide="external-link" class="w-3 h-3 text-indigo-400"></i>
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- Type / Direction & Reason -->
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider
                                    {{ $note->direction->value === 'credit' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-blue-50 text-blue-800 border border-blue-200' }}">
                                    {{ $note->direction->value === 'credit' ? 'Credit' : 'Debit' }}
                                </span>
                                <span class="block text-[10px] text-slate-400 mt-0.5 uppercase font-medium">
                                    {{ str_replace('_', ' ', $note->reason_type->value) }}
                                </span>
                            </td>

                            <!-- Amount -->
                            <td class="py-3 px-3 text-right font-mono font-black text-sm {{ $note->direction->value === 'credit' ? 'text-emerald-700' : 'text-blue-700' }}">
                                {{ $note->direction->value === 'credit' ? '-' : '+' }}₹{{ number_format((float) $note->total_credit_amount, 2) }}
                            </td>

                            <!-- Status -->
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

                            <!-- Action -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button"
                                        @click="activeNoteId = {{ $note->id }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 shadow-2xs"
                                        title="View Credit Note Details">
                                        <i data-lucide="eye" class="w-3 h-3 text-slate-500"></i>
                                        <span>Details</span>
                                    </button>

                                    @if ($note->invoice)
                                        <a href="{{ route('purchasing.shop-invoices.show', $note->invoice) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-600 hover:bg-slate-50 shadow-2xs"
                                            title="View Original Invoice">
                                            <i data-lucide="receipt" class="w-3 h-3 text-slate-400"></i>
                                            <span>Invoice</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-xs font-semibold text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="h-10 w-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400">
                                        <i data-lucide="file-x" class="w-5 h-5"></i>
                                    </div>
                                    <p class="font-bold text-slate-600">No shop credit notes found</p>
                                    <p class="text-[11px] text-slate-400 max-w-sm">
                                        No credit notes match the selected date range, shop, status, or direction criteria.
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

    <!-- Credit Note Details Modal -->
    @foreach ($creditNotes as $note)
        <div
            x-show="activeNoteId === {{ $note->id }}"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
            @click.self="activeNoteId = null"
        >
            <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl p-5 sm:p-6 max-h-[90vh] overflow-y-auto custom-scrollbar space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center shadow-2xs">
                            <i data-lucide="file-minus" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-900 font-mono">{{ $note->credit_note_number }}</h3>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider
                                    {{ $note->isApproved() ? 'bg-emerald-100 text-emerald-800' : ($note->isDraft() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $note->status->value }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 font-medium">
                                {{ $note->shop?->name }} ({{ $note->shop?->code }}) &bull; Date: {{ optional($note->credit_note_date)->format('d M Y') }}
                            </p>
                        </div>
                    </div>
                    <button @click="activeNoteId = null" class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold text-sm">✕</button>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50/70 p-3.5 rounded-2xl border border-slate-100 text-xs">
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase">Direction</span>
                        <span class="font-extrabold uppercase {{ $note->direction->value === 'credit' ? 'text-emerald-700' : 'text-blue-700' }}">
                            {{ $note->direction->value === 'credit' ? 'Credit (-Payable)' : 'Debit (+Payable)' }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase">Reason</span>
                        <span class="font-bold text-slate-800 uppercase">{{ str_replace('_', ' ', $note->reason_type->value) }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase">Inventory Action</span>
                        <span class="font-bold text-slate-800 uppercase">{{ str_replace('_', ' ', $note->inventory_action->value) }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase">Total Amount</span>
                        <span class="font-mono font-black text-sm {{ $note->direction->value === 'credit' ? 'text-emerald-700' : 'text-blue-700' }}">
                            {{ $note->direction->value === 'credit' ? '-' : '+' }}₹{{ number_format((float) $note->total_credit_amount, 2) }}
                        </span>
                    </div>
                </div>

                <!-- Linked Original Invoice Details -->
                <div class="rounded-2xl border border-slate-200/80 p-3.5 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Linked Original Invoice</span>
                        @if ($note->invoice)
                            <a href="{{ route('purchasing.shop-invoices.show', $note->invoice) }}" target="_blank" class="text-xs font-bold text-indigo-600 hover:underline inline-flex items-center gap-1">
                                <span>Open Full Invoice</span>
                                <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        @endif
                    </div>
                    @if ($note->invoice)
                        <div class="flex flex-wrap items-center justify-between text-xs pt-1">
                            <span class="font-mono font-bold text-slate-800">{{ $note->invoice->invoice_number }}</span>
                            <span>Original Total: <strong class="font-mono text-slate-900">₹{{ number_format((float) $note->invoice->final_total, 2) }}</strong></span>
                            <span>Effective Net: <strong class="font-mono text-slate-900">₹{{ number_format($note->invoice->effectiveFinalTotal(), 2) }}</strong></span>
                        </div>
                    @else
                        <p class="text-xs text-slate-400 font-medium">No original invoice linked.</p>
                    @endif
                </div>

                <!-- Line Items Table -->
                <div class="space-y-2">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-700">Adjusted Items</h4>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400 border-b border-slate-200/80">
                                    <th class="py-2.5 px-3">Product / Grade</th>
                                    <th class="py-2.5 px-3 text-right">Adjusted Qty</th>
                                    <th class="py-2.5 px-3 text-right">Rate Diff</th>
                                    <th class="py-2.5 px-3 text-right">Line Total</th>
                                    <th class="py-2.5 px-3">Reason</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                                @forelse ($note->items as $item)
                                    <tr>
                                        <td class="py-2 px-3">
                                            <span class="font-bold text-slate-900 block">
                                                {{ $item->product_name ?: ($item->product?->name ?? ($item->invoiceItem?->product?->name ?? 'Invoice Item #'.$item->shop_invoice_item_id)) }}
                                            </span>
                                            @if ($item->product_grade)
                                                <span class="text-[10px] text-slate-400 uppercase font-medium">Grade {{ $item->product_grade }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono">
                                            {{ $item->adjusted_quantity !== null ? number_format((float) $item->adjusted_quantity, 2) : '—' }}
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono">
                                            {{ $item->adjusted_unit_price !== null ? '₹'.number_format((float) $item->adjusted_unit_price, 2) : '—' }}
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">
                                            ₹{{ number_format((float) $item->line_credit_amount, 2) }}
                                        </td>
                                        <td class="py-2 px-3 text-slate-500 uppercase text-[10px]">
                                            {{ str_replace('_', ' ', $note->reason_type->value) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-slate-400 text-xs">
                                            No line items recorded.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Audit Metadata -->
                <div class="rounded-2xl bg-slate-50/70 border border-slate-100 p-3.5 space-y-1.5 text-[11px] text-slate-500">
                    <div class="flex items-center justify-between">
                        <span>Created by: <strong class="text-slate-700">{{ $note->creator?->name ?? 'System' }}</strong></span>
                        <span>Created at: <strong class="text-slate-700">{{ $note->created_at?->format('d M Y, h:i A') }}</strong></span>
                    </div>
                    @if ($note->isApproved())
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200/50">
                            <span>Approved by: <strong class="text-emerald-700">{{ $note->approver?->name ?? 'Admin' }}</strong></span>
                            <span>Approved at: <strong class="text-emerald-700">{{ $note->approved_at?->format('d M Y, h:i A') }}</strong></span>
                        </div>
                        <div class="flex items-center gap-1.5 pt-1 text-emerald-700 font-bold">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                            <span>Financial balance posted to Shop Ledger. Effective payable adjusted.</span>
                        </div>
                    @else
                        <div class="flex items-center gap-1.5 pt-1 text-amber-700 font-bold">
                            <i data-lucide="info" class="w-3.5 h-3.5"></i>
                            <span>Draft status. No financial or inventory changes have been posted.</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

</div>
@endsection
