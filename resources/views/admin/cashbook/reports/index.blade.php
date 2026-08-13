@extends('admin.cashbook.layouts.app')

@section('title', 'Green Leaf — Financial Reports & Client Reconciliation')

@section('header_title')
    <i data-lucide="leaf" class="w-5 h-5 text-emerald-600"></i> Green Leaf — Reports & Client Reconciliation
@endsection

@section('header_subtitle')
    Consolidated billing, shop performance, and client settlement position for all Aiswarya Veg shops.
@endsection

@section('content')
<div class="space-y-6">

    {{-- ═══════════════════════════════════════════════════════════
         TIER 1: GREEN LEAF ADMIN — Receivables from all clients
    ═══════════════════════════════════════════════════════════ --}}
    <div class="white-card p-6 rounded-3xl border-l-4 border-l-emerald-600 shadow-lg">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-emerald-600 flex items-center justify-center">
                    <i data-lucide="leaf" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">Green Leaf — Admin Receivables</h2>
                    <p class="text-xs text-slate-500 font-medium">Total stock billed vs. received from all clients</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500">
                <span class="font-extrabold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-xl">Green Leaf</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-slate-500 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-xl">Aiswarya Veg</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-slate-400">{{ $shops->count() }} shops</span>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600 block mb-1">GL Bills Issued</span>
                <div id="gl-total-bills" class="text-xl font-extrabold font-mono text-amber-700">₹0.00</div>
                <span class="text-[10px] text-amber-600 font-medium">Stock invoices today</span>
            </div>
            <div class="p-4 bg-purple-50 border border-purple-200 rounded-2xl">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-600 block mb-1">Company Advances</span>
                <div id="gl-company-pending" class="text-xl font-extrabold font-mono text-purple-700">₹0.00</div>
                <span class="text-[10px] text-purple-600 font-medium">Vehicle & petty paid</span>
            </div>
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 block mb-1">Received Today</span>
                <div id="gl-received" class="text-xl font-extrabold font-mono text-emerald-700">₹0.00</div>
                <span class="text-[10px] text-emerald-600 font-medium">Payments from shops</span>
            </div>
            <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300 block mb-1">Net Receivable</span>
                <div id="gl-net-receivable" class="text-xl font-extrabold font-mono text-white">₹0.00</div>
                <span class="text-[10px] text-slate-400 font-medium">Still owed to GL</span>
            </div>
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 block mb-1">Total Shop Sales</span>
                <div id="gl-total-sales" class="text-xl font-extrabold font-mono text-slate-900">₹0.00</div>
                <span class="text-[10px] text-slate-500 font-medium">All shops combined</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         TIER 2: CLIENT — Aiswarya Veg reconciliation
    ═══════════════════════════════════════════════════════════ --}}
    <div class="white-card p-6 rounded-3xl border-l-4 border-l-slate-900 shadow-lg">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-xl bg-slate-100 flex items-center justify-center">
                    <i data-lucide="briefcase" class="w-4.5 h-4.5 text-slate-700"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900">Aiswarya Veg — Client Settlement Position</h3>
                    <p class="text-xs text-slate-500">GL bills + company expenses deducted from shop collections</p>
                </div>
            </div>
            <span class="px-3 py-1.5 bg-slate-900 text-white font-extrabold text-[10px] rounded-xl">Client: Aiswarya Veg</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">1. Total Shop Collections Owed</span>
                <strong id="report-rec-collections" class="text-lg font-mono font-extrabold text-slate-900">₹0.00</strong>
                <span class="text-[10px] text-slate-500 block">Gross closing balance</span>
            </div>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">2. Green Leaf Stock Bills</span>
                <strong id="report-gl-bills" class="text-lg font-mono font-extrabold text-amber-600">₹0.00</strong>
                <span class="text-[10px] text-slate-500 block">Daily stock invoices issued</span>
            </div>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">3. Company Paid Expenses</span>
                <strong id="report-comp-expenses" class="text-lg font-mono font-extrabold text-purple-600">₹0.00</strong>
                <span class="text-[10px] text-slate-500 block">Vehicle & company pending</span>
            </div>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">4. Net Settlement Position</span>
                <strong id="report-rec-net" class="text-xl font-mono font-extrabold text-slate-900">₹0.00</strong>
                <span id="report-rec-net-sub" class="text-[10px] font-bold text-emerald-600 block">GL Payable to Aiswarya Veg</span>
            </div>
        </div>
    </div>

    {{-- Overall Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="white-card p-5 rounded-3xl space-y-1 shadow-sm">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Aggregate Sales</span>
            <div id="report-total-sales" class="text-2xl font-extrabold font-mono text-slate-900">₹0.00</div>
            <span class="text-xs text-emerald-600 font-semibold block">Across {{ $shops->count() }} Shops</span>
        </div>
        <div class="white-card p-5 rounded-3xl space-y-1 shadow-sm">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Aggregate Expense</span>
            <div id="report-total-expense" class="text-2xl font-extrabold font-mono text-rose-600">₹0.00</div>
            <span class="text-xs text-rose-600 font-semibold block">Total Chargeable P/L Expense</span>
        </div>
        <div class="white-card p-5 rounded-3xl space-y-1 shadow-sm">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Aggregate Net P/L</span>
            <div id="report-net-pl" class="text-2xl font-extrabold font-mono text-slate-900">₹0.00</div>
            <span class="text-xs text-slate-500 font-medium block">Net Profit / Loss</span>
        </div>
        <div class="white-card p-5 rounded-3xl space-y-1 shadow-sm">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Company Payables</span>
            <div id="report-shop-position" class="text-2xl font-extrabold font-mono text-amber-600">₹0.00</div>
            <span class="text-xs text-amber-600 font-semibold block">Total Collections Owed</span>
        </div>
    </div>

    {{-- Company Bank & Cash Accounts --}}
    <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="landmark" class="w-5 h-5 text-emerald-600"></i> Green Leaf — Bank & Cash Accounts
                </h3>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Real-time balances for company bank accounts, cheque deposits, cash vault, and merchant QR accounts.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition-all">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Add Bank Account
                </a>
                <span class="text-xs font-mono font-bold text-slate-500">{{ count($companyAccounts) }} Accounts Active</span>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 pt-1">
            @foreach($companyAccounts as $acc)
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase text-slate-500 tracking-wider flex items-center gap-1">
                            @if($acc->account_type == 'bank')
                                <i data-lucide="landmark" class="w-3 h-3 text-slate-400"></i> BANK
                            @elseif($acc->account_type == 'cash')
                                <i data-lucide="wallet" class="w-3 h-3 text-slate-400"></i> CASH
                            @else
                                <i data-lucide="smartphone" class="w-3 h-3 text-slate-400"></i> WALLET
                            @endif
                        </span>
                        @if($acc->is_default)
                            <span class="px-2 py-0.5 text-[9px] font-extrabold bg-emerald-100 text-emerald-800 rounded-full">Default</span>
                        @endif
                    </div>
                    <h4 class="text-sm font-extrabold text-slate-900 leading-tight">{{ $acc->name }}</h4>
                    <span class="text-[10px] font-mono text-slate-500 block">{{ $acc->bank_name ?: strtoupper($acc->account_type) }} • {{ $acc->account_number ?: 'MAIN' }}</span>
                    <div class="pt-1 border-t border-slate-200 flex items-baseline justify-between">
                        <span class="text-[10px] font-bold text-slate-400">Current Balance:</span>
                        <strong class="font-mono text-base font-extrabold text-emerald-600">₹{{ number_format($acc->current_balance, 2) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Main Reports Tables Matrix --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Report 1: Shop Performance Matrix -->
        <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="trophy" class="w-4 h-4 text-amber-500"></i> Shop Performance Matrix
                </h3>
                <span class="text-xs text-slate-500">Sorted by Sales</span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                            <th class="py-2.5 px-3">Shop</th>
                            <th class="py-2.5 px-3 text-right">Sales</th>
                            <th class="py-2.5 px-3 text-right">Expense</th>
                            <th class="py-2.5 px-3 text-right">Net P/L</th>
                        </tr>
                    </thead>
                    <tbody id="report-performance-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                        <tr><td colspan="4" class="py-6 text-center text-slate-400 font-sans">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Report 2: Cash & Settlement Balance Summary -->
        <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-4 h-4 text-slate-900"></i> Cash & Settlement Balances
                </h3>
                <span class="text-xs text-slate-500">Per Shop Float</span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                            <th class="py-2.5 px-3">Shop</th>
                            <th class="py-2.5 px-3 text-right">GL Bill</th>
                            <th class="py-2.5 px-3 text-right">Payable to Co.</th>
                            <th class="py-2.5 px-3 text-right">Co. Pending</th>
                        </tr>
                    </thead>
                    <tbody id="report-balances-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                        <tr><td colspan="4" class="py-6 text-center text-slate-400 font-sans">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
    let currentDate = '{{ now()->format("Y-m-d") }}';

    document.addEventListener('DOMContentLoaded', () => {
        loadReportData();
    });

    function syncGlobalDate(newDate) {
        currentDate = newDate;
        loadReportData();
    }

    async function loadReportData() {
        try {
            // Load both overview and client summary in parallel
            const [overviewRes, clientRes] = await Promise.all([
                fetch(`/admin/cashbook/api/all-shops-overview?business_date=${currentDate}`),
                fetch(`/admin/cashbook/api/client-summary?business_date=${currentDate}`),
            ]);
            const overviewData = await overviewRes.json();
            const clientData   = await clientRes.json();

            if (overviewData.success) {
                const totals = overviewData.totals;
                document.getElementById('report-total-sales').innerText    = `₹${totals.total_sales.toFixed(2)}`;
                document.getElementById('report-total-expense').innerText  = `₹${totals.total_expense.toFixed(2)}`;
                document.getElementById('report-shop-position').innerText  = `₹${totals.closing_shop_position.toFixed(2)}`;

                const netPlEl = document.getElementById('report-net-pl');
                netPlEl.innerText = `₹${totals.net_pl.toFixed(2)}`;
                netPlEl.className = `text-2xl font-extrabold font-mono ${totals.net_pl < 0 ? 'text-rose-600' : 'text-emerald-600'}`;

                // Client reconciliation card
                document.getElementById('report-rec-collections').innerText = `₹${totals.closing_shop_position.toFixed(2)}`;
                document.getElementById('report-gl-bills').innerText        = `₹${(totals.total_green_leaf_bills || 0).toFixed(2)}`;
                document.getElementById('report-comp-expenses').innerText   = `₹${totals.closing_company_pending.toFixed(2)}`;

                const netClient = totals.net_payable_to_client ?? 0;
                const netEl     = document.getElementById('report-rec-net');
                const netSub    = document.getElementById('report-rec-net-sub');
                netEl.innerText = `₹${Math.abs(netClient).toFixed(2)}`;
                if (netClient >= 0) {
                    netEl.className  = 'text-xl font-mono font-extrabold text-emerald-600';
                    netSub.innerText = 'GL Payable to Aiswarya Veg';
                    netSub.className = 'text-[10px] font-bold text-emerald-600 block';
                } else {
                    netEl.className  = 'text-xl font-mono font-extrabold text-rose-600';
                    netSub.innerText = 'Aiswarya Veg Payable to GL';
                    netSub.className = 'text-[10px] font-bold text-rose-600 block';
                }

                // Performance table
                const sorted = [...overviewData.overview].sort((a, b) =>
                    parseFloat(b.snapshot.total_sales) - parseFloat(a.snapshot.total_sales)
                );
                document.getElementById('report-performance-tbody').innerHTML = sorted.map(item => {
                    const s = item.snapshot;
                    const netPl = parseFloat(s.net_pl);
                    return `
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-3 font-sans font-bold text-slate-900">${item.shop.name}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">₹${parseFloat(s.total_sales).toFixed(2)}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-rose-600">₹${parseFloat(s.total_expense).toFixed(2)}</td>
                            <td class="py-2.5 px-3 text-right font-bold ${netPl < 0 ? 'text-rose-600' : 'text-emerald-600'}">₹${netPl.toFixed(2)}</td>
                        </tr>`;
                }).join('');

                // Balances table
                document.getElementById('report-balances-tbody').innerHTML = overviewData.overview.map(item => {
                    const s = item.snapshot;
                    const glBill = item.green_leaf_bill || 0;
                    return `
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-3 font-sans font-bold text-slate-900">${item.shop.name}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-amber-600">₹${parseFloat(glBill).toFixed(2)}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">₹${parseFloat(s.closing_shop_position).toFixed(2)}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-purple-600">₹${parseFloat(s.closing_company_pending).toFixed(2)}</td>
                        </tr>`;
                }).join('');
            }

            // Green Leaf top-tier receivables from client summary
            if (clientData.success && clientData.grand_totals) {
                const gt = clientData.grand_totals;
                document.getElementById('gl-total-bills').innerText    = `₹${(gt.total_gl_bills_issued || 0).toFixed(2)}`;
                document.getElementById('gl-company-pending').innerText = `₹${(gt.total_company_pending || 0).toFixed(2)}`;
                document.getElementById('gl-received').innerText        = `₹${(gt.total_received_today || 0).toFixed(2)}`;
                document.getElementById('gl-net-receivable').innerText  = `₹${(gt.net_receivable || 0).toFixed(2)}`;
                document.getElementById('gl-total-sales').innerText     = `₹${(gt.total_shop_position || 0).toFixed(2)}`;
            }

        } catch (err) {
            showToast('Failed to load reports', 'error');
        }
    }
</script>
@endpush
