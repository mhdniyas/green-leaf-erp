@extends('admin.cashbook.layouts.app')

@section('title', 'Green Leaf — Financial Reports & Client Reconciliation')

@section('header_title')
    <i data-lucide="leaf" class="w-5 h-5 text-emerald-600"></i> Green Leaf — Reports & Client Reconciliation
@endsection

@section('header_subtitle')
    Consolidated billing, shop performance, and client settlement position for the selected period.
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-4 sm:space-y-6">
        @include('admin.cashbook.reports.partials.mobile-header')
        @include('admin.cashbook.reports.partials.filters')
        @include('admin.cashbook.reports.partials.section-tabs')
        @include('admin.cashbook.reports.partials.summary-cards')
        @include('admin.cashbook.reports.partials.client-summary')
        @include('admin.cashbook.reports.partials.bank-accounts')
        @include('admin.cashbook.reports.partials.report-matrix')
        @include('admin.cashbook.reports.partials.export-actions')
    </div>
@endsection

@push('scripts')
<script>
    let currentDate = @json($selectedDate);
    let currentTimeframe = @json($timeframe);
    let currentStartDate = @json($startDate);
    let currentEndDate = @json($endDate);

    document.addEventListener('DOMContentLoaded', () => {
        loadReportData();
    });

    function syncGlobalDate(newDate) {
        const url = new URL(window.location.href);
        url.searchParams.set('date', newDate);
        url.searchParams.set('timeframe', currentTimeframe || 'daily');
        if (currentTimeframe === 'custom') {
            url.searchParams.set('start_date', currentStartDate);
            url.searchParams.set('end_date', currentEndDate);
        } else {
            url.searchParams.delete('start_date');
            url.searchParams.delete('end_date');
        }
        window.location.href = url.toString();
    }

    function reportQueryParams() {
        const params = new URLSearchParams({
            business_date: currentDate,
            timeframe: currentTimeframe || 'daily',
        });

        if (currentTimeframe === 'custom') {
            params.set('start_date', currentStartDate);
            params.set('end_date', currentEndDate);
        }

        return params.toString();
    }

    function money(value) {
        return `₹${Number(value || 0).toFixed(2)}`;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.innerText = value;
        }
    }

    async function loadReportData() {
        try {
            const query = reportQueryParams();
            const [overviewRes, clientRes] = await Promise.all([
                fetch(@json(route('admin.cashbook.api.all-shops-overview')) + `?${query}`),
                fetch(@json(route('admin.cashbook.api.client-summary')) + `?${query}`),
            ]);
            const overviewData = await overviewRes.json();
            const clientData = await clientRes.json();

            if (overviewData.success) {
                const totals = overviewData.totals;
                setText('report-total-sales', money(totals.total_sales));
                setText('report-total-expense', money(totals.total_expense));
                setText('report-shop-position', money(totals.closing_shop_position));

                const netPlEl = document.getElementById('report-net-pl');
                if (netPlEl) {
                    netPlEl.innerText = money(totals.net_pl);
                    netPlEl.className = `text-2xl font-extrabold font-mono ${totals.net_pl < 0 ? 'text-rose-600' : 'text-emerald-600'}`;
                }

                setText('report-rec-collections', money(totals.closing_shop_position));
                setText('report-gl-bills', money(totals.total_green_leaf_bills));
                setText('report-comp-expenses', money(totals.closing_company_pending));

                const netClient = totals.net_payable_to_client ?? 0;
                const netEl = document.getElementById('report-rec-net');
                const netSub = document.getElementById('report-rec-net-sub');
                if (netEl && netSub) {
                    netEl.innerText = money(Math.abs(netClient));
                    if (netClient >= 0) {
                        netEl.className = 'text-xl font-mono font-extrabold text-emerald-600';
                        netSub.innerText = 'GL Payable to Aiswarya Veg';
                        netSub.className = 'text-[10px] font-bold text-emerald-600 block';
                    } else {
                        netEl.className = 'text-xl font-mono font-extrabold text-rose-600';
                        netSub.innerText = 'Aiswarya Veg Payable to GL';
                        netSub.className = 'text-[10px] font-bold text-rose-600 block';
                    }
                }

                const sorted = [...overviewData.overview].sort((a, b) =>
                    parseFloat(b.snapshot.total_sales) - parseFloat(a.snapshot.total_sales)
                );

                document.getElementById('report-performance-tbody').innerHTML = sorted.map(item => {
                    const s = item.snapshot;
                    const netPl = parseFloat(s.net_pl || 0);
                    return `
                        <tr class="hidden md:table-row hover:bg-slate-50">
                            <td class="py-2.5 px-3 font-sans font-bold text-slate-900">${item.shop.name}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">${money(s.total_sales)}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-rose-600">${money(s.total_expense)}</td>
                            <td class="py-2.5 px-3 text-right font-bold ${netPl < 0 ? 'text-rose-600' : 'text-emerald-600'}">${money(netPl)}</td>
                        </tr>
                        <tr class="md:hidden">
                            <td colspan="4" class="py-2">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="font-extrabold text-slate-950">${item.shop.name}</div>
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                        <div><span class="block text-slate-400">Sales</span><strong>${money(s.total_sales)}</strong></div>
                                        <div><span class="block text-slate-400">Expense</span><strong class="text-rose-600">${money(s.total_expense)}</strong></div>
                                        <div><span class="block text-slate-400">Net P/L</span><strong class="${netPl < 0 ? 'text-rose-600' : 'text-emerald-600'}">${money(netPl)}</strong></div>
                                    </div>
                                </div>
                            </td>
                        </tr>`;
                }).join('');

                document.getElementById('report-balances-tbody').innerHTML = overviewData.overview.map(item => {
                    const s = item.snapshot;
                    const glBill = item.green_leaf_bill || 0;
                    return `
                        <tr class="hidden md:table-row hover:bg-slate-50">
                            <td class="py-2.5 px-3 font-sans font-bold text-slate-900">${item.shop.name}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-amber-600">${money(glBill)}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">${money(s.closing_shop_position)}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-purple-600">${money(s.closing_company_pending)}</td>
                        </tr>
                        <tr class="md:hidden">
                            <td colspan="4" class="py-2">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="font-extrabold text-slate-950">${item.shop.name}</div>
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                        <div><span class="block text-slate-400">GL Bill</span><strong class="text-amber-600">${money(glBill)}</strong></div>
                                        <div><span class="block text-slate-400">Payable</span><strong>${money(s.closing_shop_position)}</strong></div>
                                        <div><span class="block text-slate-400">Co. Pending</span><strong class="text-purple-600">${money(s.closing_company_pending)}</strong></div>
                                    </div>
                                </div>
                            </td>
                        </tr>`;
                }).join('');
            }

            if (clientData.success && clientData.grand_totals) {
                const gt = clientData.grand_totals;
                setText('gl-total-bills', money(gt.total_gl_bills_issued));
                setText('gl-company-pending', money(gt.total_company_pending));
                setText('gl-received', money(gt.total_received_today));
                setText('gl-net-receivable', money(gt.net_receivable));
                setText('gl-total-sales', money(gt.total_shop_position));
            }
        } catch (err) {
            showToast('Failed to load reports', 'error');
        }
    }
</script>
@endpush
