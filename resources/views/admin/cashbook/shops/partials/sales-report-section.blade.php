@php
    $salesSummary = $salesReport['summary'] ?? [
        'total_sales' => 0.0,
        'total_rent' => 0.0,
        'total_purchase' => 0.0,
        'total_other_expense' => 0.0,
        'total_expenses' => 0.0,
        'net_total' => 0.0,
        'gl_bills_total' => 0.0,
    ];
    $dailyRows = $salesReport['daily_rows'] ?? [];
    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;

    $exportParams = [
        'shop' => $currentShopSlugOrId,
        'month' => $month,
        'period_mode' => $periodMode,
    ];
    if ($periodMode === 'day') {
        $exportParams['date'] = $periodStart;
    } elseif ($periodMode === 'custom') {
        $exportParams['from'] = $periodStart;
        $exportParams['to'] = $periodEnd;
    }
@endphp

<section id="sales-report-tab" class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-6">
    <!-- Section Header & Export Actions -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-slate-100 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
                <h2 class="text-xl font-black text-slate-950 tracking-tight">Sales &amp; Financial Execution Report</h2>
            </div>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                Period: <strong class="text-slate-800 font-bold">{{ $salesReport['period']['formatted_range'] ?? $salesReport['period']['label'] }}</strong> &bull; Aggregated from Cashbook Payment Settings
            </p>
        </div>

        <!-- Export & Share Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.cashbook.shop.sales-report.pdf', $exportParams) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-800 hover:bg-rose-100 transition shadow-2xs">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span>PDF Report</span>
            </a>

            <a href="{{ route('admin.cashbook.shop.sales-report.excel', $exportParams) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-800 hover:bg-emerald-100 transition shadow-2xs">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Excel XLSX</span>
            </a>

            <a href="{{ route('admin.cashbook.shop.sales-report.csv', $exportParams) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-800 hover:bg-slate-100 transition shadow-2xs">
                <svg class="w-4 h-4 text-slate-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>CSV Stream</span>
            </a>

            <button type="button"
                    onclick="shareSalesReport('{{ addslashes($currentShop->name) }}', '{{ $salesReport['period']['formatted_range'] }}', '{{ number_format($salesSummary['total_sales'], 2) }}', '{{ number_format($salesSummary['net_total'], 2) }}')"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-black text-indigo-900 hover:bg-indigo-100 transition shadow-2xs cursor-pointer">
                <svg class="w-4 h-4 text-indigo-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
                <span>Share</span>
            </button>
        </div>
    </div>

    <!-- 5 Key Summary Cards Grid -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- 1. TOTAL SALES -->
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/80 to-teal-50/50 p-4 shadow-2xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Total Sales</span>
            <div class="mt-1 text-lg sm:text-xl font-black font-mono text-emerald-900">
                ₹{{ number_format($salesSummary['total_sales'], 2) }}
            </div>
            <span class="mt-1 block text-[10px] font-bold text-emerald-700">Cashbook Sales Bucket</span>
        </div>

        <!-- 2. RENT -->
        <div class="rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50/80 to-pink-50/50 p-4 shadow-2xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-rose-800">Rent Expense</span>
            <div class="mt-1 text-lg sm:text-xl font-black font-mono text-rose-900">
                ₹{{ number_format($salesSummary['total_rent'], 2) }}
            </div>
            <span class="mt-1 block text-[10px] font-bold text-rose-700">Shop Rent Entries</span>
        </div>

        <!-- 3. CASH PURCHASE -->
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50/80 to-orange-50/50 p-4 shadow-2xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-900">Cash Purchase</span>
            <div class="mt-1 text-lg sm:text-xl font-black font-mono text-amber-950">
                ₹{{ number_format($salesSummary['total_purchase'], 2) }}
            </div>
            <span class="mt-1 block text-[10px] font-bold text-amber-800">Direct Vendor Purchases</span>
        </div>

        <!-- 4. OTHER EXPENSE -->
        <div class="rounded-2xl border border-purple-200 bg-gradient-to-br from-purple-50/80 to-indigo-50/50 p-4 shadow-2xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-purple-900">Other Expense</span>
            <div class="mt-1 text-lg sm:text-xl font-black font-mono text-purple-950">
                ₹{{ number_format($salesSummary['total_other_expense'], 2) }}
            </div>
            <span class="mt-1 block text-[10px] font-bold text-purple-800">Operational Expenses</span>
        </div>

        <!-- 5. NET BALANCE -->
        <div class="rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50/80 to-blue-50/50 p-4 shadow-2xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-sky-900">Net Operating Balance</span>
            <div class="mt-1 text-lg sm:text-xl font-black font-mono {{ $salesSummary['net_total'] >= 0 ? 'text-sky-950' : 'text-rose-700' }}">
                ₹{{ number_format($salesSummary['net_total'], 2) }}
            </div>
            <span class="mt-1 block text-[10px] font-bold text-sky-800">Sales &minus; Expenses</span>
        </div>

        <!-- 6. GL BILLS (Informational Reference) -->
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-2xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">GL Bills Ref</span>
            <div class="mt-1 text-lg sm:text-xl font-black font-mono text-slate-700">
                ₹{{ number_format($salesSummary['gl_bills_total'], 2) }}
            </div>
            <span class="mt-1 block text-[10px] font-bold text-slate-400">System Invoices (Informational)</span>
        </div>
    </div>

    <!-- Daily Sales Breakdown Table -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-600">Daily Sales Breakdown</h3>
            <span class="text-xs font-bold text-slate-400">{{ count($dailyRows) }} Days in Period</span>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-2xs">
            <table id="sales-report-table" class="w-full text-left text-xs sortable-table" data-sortable="true" data-default-sort-col="0" data-default-sort-dir="desc">
                <thead class="bg-slate-900 text-white uppercase text-[10px] tracking-wider font-extrabold">
                    <tr>
                        <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 0)" title="Sort by Date">Date <span class="sort-icon ml-1 text-[9px] opacity-100 text-emerald-400">▼</span></th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 1)" title="Sort by Day">Day <span class="sort-icon ml-1 text-[9px] opacity-70">↕</span></th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 2)" title="Sort by Sales">Sales (₹) <span class="sort-icon ml-1 text-[9px] opacity-70">↕</span></th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 3)" title="Sort by Rent">Rent (₹) <span class="sort-icon ml-1 text-[9px] opacity-70">↕</span></th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 4)" title="Sort by Cash Purchase">Cash Purchase (₹) <span class="sort-icon ml-1 text-[9px] opacity-70">↕</span></th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 5)" title="Sort by Other Expense">Other Expense (₹) <span class="sort-icon ml-1 text-[9px] opacity-70">↕</span></th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 6)" title="Sort by Total Expenses">Total Expenses (₹) <span class="sort-icon ml-1 text-[9px] opacity-70">↕</span></th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-800 transition" onclick="sortVanillaTable('sales-report-table', 7)" title="Sort by Net Balance">Net Balance (₹) <span class="sort-icon ml-1 text-[9px] opacity-70">↕</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono font-medium text-slate-700 bg-white">
                    @forelse($dailyRows as $row)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-4 py-3 font-bold font-sans text-slate-900" data-date-raw="{{ $row['date'] }}">{{ $row['formatted_date'] }}</td>
                            <td class="px-4 py-3 font-sans text-slate-500">{{ $row['day_name'] }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-700">₹{{ number_format($row['sales'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-700">₹{{ number_format($row['rent'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-amber-700">₹{{ number_format($row['purchase'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-purple-700">₹{{ number_format($row['other_expense'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900">₹{{ number_format($row['total_expenses'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-bold {{ $row['net_balance'] >= 0 ? 'text-blue-700' : 'text-rose-700' }}">
                                ₹{{ number_format($row['net_balance'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-400 font-sans font-bold">
                                No sales transactions recorded in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-100 font-mono font-black text-slate-950 uppercase border-t-2 border-slate-300">
                    <tr>
                        <td colspan="2" class="px-4 py-3 font-sans">TOTALS</td>
                        <td class="px-4 py-3 text-right text-emerald-800">₹{{ number_format($salesSummary['total_sales'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-rose-800">₹{{ number_format($salesSummary['total_rent'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-amber-900">₹{{ number_format($salesSummary['total_purchase'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-purple-900">₹{{ number_format($salesSummary['total_other_expense'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-slate-950">₹{{ number_format($salesSummary['total_expenses'], 2) }}</td>
                        <td class="px-4 py-3 text-right {{ $salesSummary['net_total'] >= 0 ? 'text-blue-900' : 'text-rose-900' }}">
                            ₹{{ number_format($salesSummary['net_total'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

<script>
    (function() {
        window.tableSortStates = window.tableSortStates || {};

        window.sortVanillaTable = function(tableId, colIndex) {
            const table = typeof tableId === 'string' ? document.getElementById(tableId) : tableId;
            if (!table) return;

            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
            if (rows.length <= 1) return;

            const key = (table.id || 'table') + '_' + colIndex;
            let currentDir = window.tableSortStates[key];
            let newDir = 'asc';
            if (!currentDir) {
                newDir = (colIndex === 0) ? 'desc' : 'desc';
            } else {
                newDir = currentDir === 'asc' ? 'desc' : 'asc';
            }
            window.tableSortStates[key] = newDir;

            // Update header indicators
            const ths = table.querySelectorAll('thead th');
            ths.forEach((th, idx) => {
                const icon = th.querySelector('.sort-icon');
                if (icon) {
                    if (idx === colIndex) {
                        icon.textContent = newDir === 'asc' ? '▲' : '▼';
                        icon.classList.remove('opacity-70');
                        icon.classList.add('opacity-100', 'text-emerald-400');
                    } else {
                        icon.textContent = '↕';
                        icon.classList.add('opacity-70');
                        icon.classList.remove('opacity-100', 'text-emerald-400');
                    }
                }
            });

            rows.sort((rowA, rowB) => {
                const cellA = rowA.children[colIndex];
                const cellB = rowB.children[colIndex];
                if (!cellA || !cellB) return 0;

                let valA = cellA.getAttribute('data-date-raw') || cellA.getAttribute('data-sort-value') || cellA.textContent.trim();
                let valB = cellB.getAttribute('data-date-raw') || cellB.getAttribute('data-sort-value') || cellB.textContent.trim();

                const rawNumA = valA.replace(/[₹,%\s]/g, '');
                const rawNumB = valB.replace(/[₹,%\s]/g, '');

                let cmp = 0;

                if (cellA.hasAttribute('data-date-raw') && cellB.hasAttribute('data-date-raw')) {
                    cmp = valA.localeCompare(valB);
                } else if (!isNaN(parseFloat(rawNumA)) && !isNaN(parseFloat(rawNumB)) && isFinite(rawNumA) && isFinite(rawNumB)) {
                    cmp = parseFloat(rawNumA) - parseFloat(rawNumB);
                } else {
                    cmp = valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' });
                }

                return newDir === 'asc' ? cmp : -cmp;
            });

            rows.forEach(r => tbody.appendChild(r));
        };

        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('sales-report-table');
            if (table) {
                window.tableSortStates['sales-report-table_0'] = 'desc';
            }
        });
    })();

    function shareSalesReport(shopName, periodRange, salesTotal, netTotal) {
        const textToShare = `Sales Report — ${shopName}\nPeriod: ${periodRange}\nTotal Sales: ₹${salesTotal}\nNet Balance: ₹${netTotal}`;
        const currentUrl = window.location.href;

        if (navigator.share) {
            navigator.share({
                title: `Sales Report — ${shopName}`,
                text: textToShare,
                url: currentUrl
            }).catch(() => {
                copyToClipboard(textToShare);
            });
        } else {
            copyToClipboard(textToShare);
        }
    }

    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Sales Report summary copied to clipboard!');
            }).catch(() => {
                fallbackCopyTextToClipboard(text);
            });
        } else {
            fallbackCopyTextToClipboard(text);
        }
    }

    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            alert('Sales Report summary copied to clipboard!');
        } catch (err) {
            alert('Could not copy summary to clipboard.');
        }
        document.body.removeChild(textArea);
    }
</script>
