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
        @include('admin.cashbook.reports.partials.attention-required')
        @include('admin.cashbook.reports.partials.receivable-ageing')
        @include('admin.cashbook.reports.partials.client-groups')
        @include('admin.cashbook.reports.partials.direct-shops')
        @include('admin.cashbook.reports.partials.bill-details')
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

    function shopUrl(shop, date = currentDate) {
        const identifier = shop.slug || shop.id;
        return `/admin/cashbook/shops/${identifier}?date=${date}`;
    }

    function scopeBadge(scope) {
        return scope === 'direct'
            ? '<span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black uppercase text-amber-700">Direct</span>'
            : '<span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-700">Client</span>';
    }

    function renderClientGroups(groups) {
        const list = document.getElementById('client-groups-list');
        setText('client-group-count', `${groups.length} groups`);

        if (!groups.length) {
            list.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-400">No client groups in this period.</div>';
            return;
        }

        list.innerHTML = groups.map(group => {
            const shops = group.shops || [];
            const totals = shops.reduce((carry, item) => {
                carry.bill += Number(item.green_leaf_bill || 0);
                carry.received += Number(item.received_today || 0);
                carry.pending += Number(item.snapshot?.closing_company_pending || 0);
                carry.payable += Number(item.snapshot?.closing_shop_position || 0);
                return carry;
            }, { bill: 0, received: 0, pending: 0, payable: 0 });

            const shopRows = shops.map(item => `
                <a href="${shopUrl(item.shop)}" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-black text-slate-950">${item.shop.name}</div>
                            <div class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">${item.shop.code || ''}</div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600">Open</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        <div><span class="block text-slate-400">Bill</span><strong class="font-mono text-amber-600">${money(item.green_leaf_bill)}</strong></div>
                        <div><span class="block text-slate-400">Received</span><strong class="font-mono text-emerald-600">${money(item.received_today)}</strong></div>
                        <div><span class="block text-slate-400">Payable</span><strong class="font-mono text-slate-900">${money(item.snapshot?.closing_shop_position)}</strong></div>
                        <div><span class="block text-slate-400">Co. Pending</span><strong class="font-mono text-purple-600">${money(item.snapshot?.closing_company_pending)}</strong></div>
                    </div>
                </a>
            `).join('');

            return `
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h4 class="text-base font-black text-slate-950">${group.client?.name || 'Client'}</h4>
                            <p class="mt-1 text-xs font-semibold text-slate-500">${shops.length} shops in this period</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs sm:flex">
                            <span class="rounded-2xl bg-white px-3 py-2 font-black text-amber-700">Bill ${money(totals.bill)}</span>
                            <span class="rounded-2xl bg-white px-3 py-2 font-black text-emerald-700">Received ${money(totals.received)}</span>
                            <span class="rounded-2xl bg-white px-3 py-2 font-black text-slate-700">Payable ${money(totals.payable)}</span>
                            <span class="rounded-2xl bg-white px-3 py-2 font-black text-purple-700">Pending ${money(totals.pending)}</span>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                        ${shopRows}
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderDirectShops(items) {
        const list = document.getElementById('direct-shops-list');
        setText('direct-shop-count', `${items.length} shops`);

        if (!items.length) {
            list.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-400">No direct shops in this period.</div>';
            return;
        }

        list.innerHTML = `
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                ${items.map(item => `
                    <a href="${shopUrl(item.shop)}" class="block rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-black text-slate-950">${item.shop.name}</div>
                                <div class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">${item.shop.code || ''}</div>
                            </div>
                            <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black text-amber-700">Direct</span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <div><span class="block text-slate-400">GL Bill</span><strong class="font-mono text-amber-700">${money(item.green_leaf_bill)}</strong></div>
                            <div><span class="block text-slate-400">Received</span><strong class="font-mono text-emerald-600">${money(item.received_today)}</strong></div>
                            <div><span class="block text-slate-400">Payable</span><strong class="font-mono text-slate-900">${money(item.snapshot?.closing_shop_position)}</strong></div>
                            <div><span class="block text-slate-400">Co. Pending</span><strong class="font-mono text-purple-600">${money(item.snapshot?.closing_company_pending)}</strong></div>
                        </div>
                    </a>
                `).join('')}
            </div>
        `;
    }

    function renderBillDetails(rows, totals) {
        setText('bill-count', `${rows.length} bills`);
        setText('bill-total-billed', money(totals.total_billed));
        setText('bill-total-paid', money(totals.total_paid));
        setText('bill-total-balance', money(totals.total_balance));

        const tbody = document.getElementById('bill-details-tbody');
        const cards = document.getElementById('bill-details-cards');

        if (!rows.length) {
            const empty = 'No bills found for this period.';
            tbody.innerHTML = `<tr><td colspan="8" class="py-6 text-center font-sans text-slate-400">${empty}</td></tr>`;
            cards.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-400">${empty}</div>`;
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr class="hover:bg-slate-50">
                <td class="px-3 py-2.5 font-sans font-bold text-slate-900">${row.business_date || '-'}</td>
                <td class="px-3 py-2.5"><a href="${row.invoice_url}" class="font-mono font-black text-cyan-700 hover:underline">${row.invoice_number}</a></td>
                <td class="px-3 py-2.5">${scopeBadge(row.scope)}</td>
                <td class="px-3 py-2.5"><a href="${row.shop_url}" class="font-sans font-bold text-slate-900 hover:underline">${row.shop?.name || 'Shop'}</a></td>
                <td class="px-3 py-2.5 text-right font-bold text-slate-900">${money(row.final_total)}</td>
                <td class="px-3 py-2.5 text-right font-bold text-emerald-600">${money(row.paid_amount)}</td>
                <td class="px-3 py-2.5 text-right font-bold text-amber-600">${money(row.balance_amount)}</td>
                <td class="px-3 py-2.5">
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-700">${row.payment_status || row.status}</span>
                </td>
            </tr>
        `).join('');

        cards.innerHTML = rows.map(row => `
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <a href="${row.invoice_url}" class="font-mono text-sm font-black text-cyan-700 hover:underline">${row.invoice_number}</a>
                        <div class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">${row.business_date || '-'}</div>
                    </div>
                    ${scopeBadge(row.scope)}
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <a href="${row.shop_url}" class="text-sm font-black text-slate-950 hover:underline">${row.shop?.name || 'Shop'}</a>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-700">${row.payment_status || row.status}</span>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3 text-xs">
                    <div><span class="block text-slate-400">Bill</span><strong class="font-mono text-slate-950">${money(row.final_total)}</strong></div>
                    <div><span class="block text-slate-400">Paid</span><strong class="font-mono text-emerald-600">${money(row.paid_amount)}</strong></div>
                    <div><span class="block text-slate-400">Balance</span><strong class="font-mono text-amber-600">${money(row.balance_amount)}</strong></div>
                </div>
            </div>
        `).join('');
    }

    function renderAttentionAndAgeing(rows) {
        const openRows = rows.filter(row => Number(row.balance_amount || 0) > 0);
        const unpaidRows = openRows.filter(row => Number(row.paid_amount || 0) <= 0);
        const partialRows = openRows.filter(row => Number(row.paid_amount || 0) > 0);
        const directOpenRows = openRows.filter(row => row.scope === 'direct');
        const today = new Date(`${currentEndDate}T00:00:00`);

        const ageBuckets = {
            '0_7': { count: 0, value: 0 },
            '8_14': { count: 0, value: 0 },
            '15_30': { count: 0, value: 0 },
            '31_60': { count: 0, value: 0 },
            'above_60': { count: 0, value: 0 },
        };

        openRows.forEach(row => {
            const balance = Number(row.balance_amount || 0);
            const invoiceDate = row.business_date ? new Date(`${row.business_date}T00:00:00`) : today;
            const diffDays = Math.max(0, Math.floor((today - invoiceDate) / 86400000));

            const bucket = diffDays <= 7
                ? '0_7'
                : (diffDays <= 14
                    ? '8_14'
                    : (diffDays <= 30
                        ? '15_30'
                        : (diffDays <= 60 ? '31_60' : 'above_60')));

            ageBuckets[bucket].count += 1;
            ageBuckets[bucket].value += balance;
        });

        const sumBalance = (items) => items.reduce((carry, row) => carry + Number(row.balance_amount || 0), 0);
        const over7Count = ageBuckets['8_14'].count + ageBuckets['15_30'].count + ageBuckets['31_60'].count + ageBuckets['above_60'].count;
        const over7Value = ageBuckets['8_14'].value + ageBuckets['15_30'].value + ageBuckets['31_60'].value + ageBuckets['above_60'].value;

        setText('attention-total-open', `${openRows.length} open bills`);
        setText('attention-unpaid-count', unpaidRows.length);
        setText('attention-unpaid-value', money(sumBalance(unpaidRows)));
        setText('attention-partial-count', partialRows.length);
        setText('attention-partial-value', money(sumBalance(partialRows)));
        setText('attention-over7-count', over7Count);
        setText('attention-over7-value', money(over7Value));
        setText('attention-direct-open-count', directOpenRows.length);
        setText('attention-direct-open-value', money(sumBalance(directOpenRows)));
        setText('ageing-total-balance', `${money(sumBalance(openRows))} open balance`);

        Object.entries(ageBuckets).forEach(([key, value]) => {
            setText(`ageing-${key}-value`, money(value.value));
            setText(`ageing-${key}-count`, `${value.count} bills`);
        });
    }

    async function loadReportData() {
        try {
            const query = reportQueryParams();
            const [overviewRes, clientRes, billRes] = await Promise.all([
                fetch(@json(route('admin.cashbook.api.all-shops-overview')) + `?${query}`),
                fetch(@json(route('admin.cashbook.api.client-summary')) + `?${query}`),
                fetch(@json(route('admin.cashbook.api.report-bills')) + `?${query}`),
            ]);
            const overviewData = await overviewRes.json();
            const clientData = await clientRes.json();
            const billData = await billRes.json();

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

                renderClientGroups(overviewData.client_groups || []);
                renderDirectShops(overviewData.direct_owned_shops || []);
            }

            if (clientData.success && clientData.grand_totals) {
                const gt = clientData.grand_totals;
                setText('gl-total-bills', money(gt.total_gl_bills_issued));
                setText('gl-company-pending', money(gt.total_company_pending));
                setText('gl-received', money(gt.total_received_today));
                setText('gl-net-receivable', money(gt.net_receivable));
                setText('gl-total-sales', money(gt.total_shop_position));
            }

            if (billData.success) {
                renderBillDetails(billData.rows || [], billData.totals || {});
                renderAttentionAndAgeing(billData.rows || []);
            }
        } catch (err) {
            showToast('Failed to load reports', 'error');
        }
    }
</script>
@endpush
