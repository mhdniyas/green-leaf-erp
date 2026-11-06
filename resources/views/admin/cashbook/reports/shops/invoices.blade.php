@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Invoices Report — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="receipt" class="w-5 h-5 text-emerald-600"></i> Shop Invoices
@endsection

@section('header_subtitle')
    Historical shop billing, credit note adjustments, and effective payable balances.
@endsection

@section('content')
<div x-data="{ activeCreditNotesInvoiceId: null }" class="mx-auto max-w-[96rem] space-y-5">

    <!-- Top Sub-Nav Header -->
    @include('admin.cashbook.reports.shops.partials.header', ['activeTab' => 'invoices'])

    <!-- Summary Metric Cards (6 Cards) -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- 1. Original Invoice Total -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Original Total</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-slate-900">
                ₹{{ number_format((float) ($summary['original_total'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">
                {{ $summary['invoice_count'] ?? 0 }} invoices
            </span>
        </div>

        <!-- 2. Approved Credits -->
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-emerald-800">Approved Credits</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-emerald-700">
                -₹{{ number_format((float) ($summary['approved_credits'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">Reduces payable</span>
        </div>

        <!-- 3. Approved Debits -->
        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-blue-800">Approved Debits</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-blue-700">
                +₹{{ number_format((float) ($summary['approved_debits'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-blue-600">Increases payable</span>
        </div>

        <!-- 4. Net Payable -->
        <div class="rounded-2xl border border-slate-900 bg-slate-900 p-4 shadow-xs text-white">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-300">Net Payable</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-white">
                ₹{{ number_format((float) ($summary['net_payable'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Effective baseline</span>
        </div>

        <!-- 5. Paid -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Paid Amount</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-slate-800">
                ₹{{ number_format((float) ($summary['total_paid'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Recorded payments</span>
        </div>

        <!-- 6. Outstanding Balance -->
        <div class="rounded-2xl border border-rose-200/80 bg-rose-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-rose-800">Outstanding Balance</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-rose-700">
                ₹{{ number_format((float) ($summary['outstanding_balance'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-rose-600">Pending shop pay</span>
        </div>
    </div>

    <!-- Filters Section (Cashbook Style) -->
    <div class="white-card rounded-3xl p-4 sm:p-5">
        <!-- Preset Timeframe Buttons -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-4">
            <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                <a href="{{ route('admin.cashbook.reports.shops.invoices', ['timeframe' => 'today', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.reports.shops.invoices', ['timeframe' => 'yesterday', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'yesterday' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Yesterday
                </a>
                <a href="{{ route('admin.cashbook.reports.shops.invoices', ['timeframe' => 'this_month', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'this_month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    This Month
                </a>
                @php
                    $prevMonthName = now()->subMonth()->format('M');
                @endphp
                <a href="{{ route('admin.cashbook.reports.shops.invoices', ['timeframe' => 'prev_month', 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'search' => $search]) }}"
                    class="rounded-xl px-3 py-1 text-xs font-bold transition {{ $timeframe === 'prev_month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ $prevMonthName }} (Prev Month)
                </a>
            </div>

            <span class="text-xs font-semibold text-slate-400">
                Period: <span class="font-bold text-slate-700">{{ Carbon\Carbon::parse($startDate)->format('d M Y') }}</span> to <span class="font-bold text-slate-700">{{ Carbon\Carbon::parse($endDate)->format('d M Y') }}</span>
            </span>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.cashbook.reports.shops.invoices') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
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
                    <option value="finalized" @selected($selectedStatus === 'finalized')>Finalized</option>
                    <option value="paid" @selected($selectedStatus === 'paid')>Paid</option>
                    <option value="partially_paid" @selected($selectedStatus === 'partially_paid')>Partially Paid</option>
                    <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                </select>
            </label>

            <!-- Search by Invoice Number & Submit -->
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Search Invoice</span>
                <div class="flex items-center gap-1.5">
                    <input type="text" name="search" value="{{ $search }}" placeholder="INV-..." class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs">
                    <button type="submit" class="h-10 rounded-xl bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800 shrink-0">
                        Apply
                    </button>
                    @if($selectedShopId || $selectedStatus || $search || $timeframe !== 'this_month')
                        <a href="{{ route('admin.cashbook.reports.shops.invoices') }}" class="h-10 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-500 hover:bg-slate-50 shrink-0" title="Reset Filters">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between px-1">
            <div>
                <h3 class="text-sm font-black text-slate-900">Shop Invoices List</h3>
                <p class="text-[11px] font-medium text-slate-400">
                    Showing {{ $invoices->firstItem() ?? 0 }} to {{ $invoices->lastItem() ?? 0 }} of {{ $invoices->total() }} invoices
                </p>
            </div>
            @if ($invoices->total() > 0)
                <span class="text-[10px] font-bold text-slate-400">Page {{ $invoices->currentPage() }} of {{ $invoices->lastPage() }}</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-50/50">
                        <th class="py-3 px-3">Invoice</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Shop</th>
                        <th class="py-3 px-3 text-right">Original</th>
                        <th class="py-3 px-3 text-right">Credit</th>
                        <th class="py-3 px-3 text-right">Debit</th>
                        <th class="py-3 px-3 text-right">Net Payable</th>
                        <th class="py-3 px-3 text-right">Paid</th>
                        <th class="py-3 px-3 text-right">Balance</th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                    @forelse ($invoices as $inv)
                        @php
                            $approvedCredit = $inv->approvedCreditTotal();
                            $approvedDebit = $inv->approvedDebitTotal();
                            $netPayable = $inv->effectiveFinalTotal();
                            $balance = $inv->effectiveBalanceAmount();
                            $notesCount = $inv->creditNotes->count();
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Invoice -->
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-900 block font-mono">{{ $inv->invoice_number }}</span>
                                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider mt-0.5
                                    {{ $inv->isFinalized() ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $inv->status ?: 'Finalized' }}
                                </span>
                            </td>

                            <!-- Date -->
                            <td class="py-3 px-3 text-slate-600 whitespace-nowrap">
                                {{ optional($inv->business_date ?? $inv->created_at)->format('d M Y') }}
                            </td>

                            <!-- Shop -->
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-900 block">{{ $inv->shop?->name ?: 'Shop #'.$inv->shop_id }}</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">{{ $inv->shop?->code }}</span>
                            </td>

                            <!-- Original Invoice Value (Always unmodified) -->
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format((float) $inv->final_total, 2) }}
                            </td>

                            <!-- Approved Credit -->
                            <td class="py-3 px-3 text-right font-mono">
                                @if ($approvedCredit > 0)
                                    <span class="font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                        -₹{{ number_format($approvedCredit, 2) }}
                                    </span>
                                @else
                                    <span class="text-slate-300 font-normal">—</span>
                                @endif
                            </td>

                            <!-- Approved Debit -->
                            <td class="py-3 px-3 text-right font-mono">
                                @if ($approvedDebit > 0)
                                    <span class="font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200">
                                        +₹{{ number_format($approvedDebit, 2) }}
                                    </span>
                                @else
                                    <span class="text-slate-300 font-normal">—</span>
                                @endif
                            </td>

                            <!-- Net Payable -->
                            <td class="py-3 px-3 text-right font-mono font-black text-slate-900">
                                ₹{{ number_format($netPayable, 2) }}
                            </td>

                            <!-- Paid Amount -->
                            <td class="py-3 px-3 text-right font-mono text-slate-600">
                                ₹{{ number_format((float) $inv->paid_amount, 2) }}
                            </td>

                            <!-- Outstanding Balance -->
                            <td class="py-3 px-3 text-right font-mono font-bold">
                                @if ($balance > 0)
                                    <span class="text-rose-700">₹{{ number_format($balance, 2) }}</span>
                                @else
                                    <span class="text-emerald-700 font-black">₹0.00</span>
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('purchasing.shop-invoices.show', $inv) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 shadow-2xs"
                                        title="View Original Invoice">
                                        <i data-lucide="eye" class="w-3 h-3 text-slate-500"></i>
                                        <span>Invoice</span>
                                    </a>

                                    @if ($canCreateCreditNote ?? false)
                                        @if ($inv->status === 'cancelled')
                                            <button type="button"
                                                disabled
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-bold text-slate-400 cursor-not-allowed shadow-2xs opacity-60"
                                                title="Cancelled invoices cannot receive credit notes">
                                                <i data-lucide="ban" class="w-3 h-3 text-slate-400"></i>
                                                <span>Credit Note</span>
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('admin.cashbook.reports.shops.credit-notes.select') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="invoice_ref" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) $inv->id) }}">
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100 hover:border-emerald-400 transition shadow-2xs cursor-pointer"
                                                    title="Add Credit Note for this invoice">
                                                    <i data-lucide="plus-circle" class="w-3 h-3 text-emerald-600"></i>
                                                    <span>Credit Note</span>
                                                </button>
                                            </form>
                                        @endif
                                    @endif

                                    @if ($notesCount > 0)
                                        <button type="button"
                                            @click="activeCreditNotesInvoiceId = {{ $inv->id }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-200 shadow-2xs"
                                            title="View {{ $notesCount }} Credit Note(s)">
                                            <i data-lucide="file-text" class="w-3 h-3 text-slate-600"></i>
                                            <span>{{ $notesCount }} Note{{ $notesCount > 1 ? 's' : '' }}</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-xs font-semibold text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="h-10 w-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400">
                                        <i data-lucide="inbox" class="w-5 h-5"></i>
                                    </div>
                                    <p class="font-bold text-slate-600">No shop invoices found</p>
                                    <p class="text-[11px] text-slate-400 max-w-sm">
                                        No invoices match the selected date range, shop, or status criteria.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($invoices->hasPages())
            <div class="pt-2 border-t border-slate-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

    <!-- Related Credit Notes Modals (One per invoice with notes) -->
    @foreach ($invoices as $inv)
        @if ($inv->creditNotes->isNotEmpty())
            <div
                x-show="activeCreditNotesInvoiceId === {{ $inv->id }}"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
                @click.self="activeCreditNotesInvoiceId = null"
            >
                <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl p-5 sm:p-6 max-h-[90vh] overflow-y-auto custom-scrollbar space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <div class="h-8 w-8 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center">
                                <i data-lucide="file-minus" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Credit Notes for {{ $inv->invoice_number }}</h3>
                                <p class="text-[11px] font-semibold text-slate-400">{{ $inv->shop?->name }} &bull; Original Total: ₹{{ number_format((float) $inv->final_total, 2) }}</p>
                            </div>
                        </div>
                        <button @click="activeCreditNotesInvoiceId = null" class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold text-sm">✕</button>
                    </div>

                    <div class="space-y-3">
                        @foreach ($inv->creditNotes as $note)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 space-y-2">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-slate-900 text-xs">{{ $note->credit_note_number }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                            {{ $note->isApproved() ? 'bg-emerald-100 text-emerald-800' : ($note->isDraft() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                            {{ $note->status->value }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                            {{ $note->direction->value === 'credit' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                            {{ $note->direction->value }}
                                        </span>
                                    </div>
                                    <span class="font-mono font-black text-sm {{ $note->direction->value === 'credit' ? 'text-emerald-700' : 'text-blue-700' }}">
                                        {{ $note->direction->value === 'credit' ? '-' : '+' }}₹{{ number_format((float) $note->total_credit_amount, 2) }}
                                    </span>
                                </div>

                                <div class="text-[11px] text-slate-500 flex flex-wrap gap-x-4 gap-y-1">
                                    <span>Date: <strong class="text-slate-700">{{ optional($note->credit_note_date)->format('d M Y') }}</strong></span>
                                    <span>Reason: <strong class="text-slate-700 uppercase">{{ $note->reason_type->value }}</strong></span>
                                    <span>Action: <strong class="text-slate-700 uppercase">{{ $note->inventory_action->value }}</strong></span>
                                </div>

                                @if ($note->items->isNotEmpty())
                                    <div class="pt-2 border-t border-slate-200/60 overflow-x-auto">
                                        <table class="w-full text-left text-[11px]">
                                            <thead>
                                                <tr class="text-[9px] font-bold uppercase text-slate-400">
                                                    <th class="py-1">Item / Grade</th>
                                                    <th class="py-1 text-right">Qty</th>
                                                    <th class="py-1 text-right">Rate</th>
                                                    <th class="py-1 text-right">Adjustment</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach ($note->items as $nItem)
                                                    <tr>
                                                        <td class="py-1 font-semibold text-slate-700">
                                                            {{ $nItem->product_name ?: ($nItem->invoiceItem?->product?->name ?? 'Invoice Item #'.$nItem->shop_invoice_item_id) }}
                                                            @if($nItem->product_grade) <span class="text-[9px] text-slate-400 uppercase">({{ $nItem->product_grade }})</span> @endif
                                                        </td>
                                                        <td class="py-1 text-right font-mono">{{ $nItem->adjusted_quantity !== null ? number_format((float) $nItem->adjusted_quantity, 2) : '—' }}</td>
                                                        <td class="py-1 text-right font-mono">{{ $nItem->adjusted_unit_price !== null ? '₹'.number_format((float) $nItem->adjusted_unit_price, 2) : '—' }}</td>
                                                        <td class="py-1 text-right font-mono font-bold text-slate-900">₹{{ number_format((float) $nItem->line_credit_amount, 2) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endforeach

</div>
@endsection
