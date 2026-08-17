<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Shops Financial Overview ({{ $startDate }} to {{ $endDate }})</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm 8mm 10mm;
        }
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            html, body {
                background: white !important;
                color: #0f172a !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
            .print-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            table {
                page-break-inside: auto !important;
                width: 100% !important;
            }
            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
            thead {
                display: table-header-group !important;
            }
            tfoot {
                display: table-footer-group !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 sm:p-6 text-slate-900 font-sans">
    <div class="max-w-5xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm print-card space-y-6">
        <!-- Print Top Control Bar -->
        <div class="flex justify-between items-center no-print border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-lg font-black text-slate-900">All Shops Executive PDF Report</h1>
                <p class="text-xs text-slate-500 font-medium">Network multi-shop financial summary and breakdown.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.close()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
                    Close
                </button>
                <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-sm flex items-center gap-1.5 cursor-pointer">
                    Print / Save PDF
                </button>
            </div>
        </div>

        <!-- Banner Header -->
        <div class="bg-slate-950 text-white rounded-xl p-5 flex justify-between items-center">
            <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Green Leaf ERP — Executive Network Report</p>
                <h2 class="text-xl font-black text-white mt-0.5">{{ $title ?? 'All Shops Financial Overview' }}</h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Period: <span class="text-white font-bold">{{ $startDate }}</span> to <span class="text-white font-bold">{{ $endDate }}</span>
                    <span class="ml-1.5 uppercase text-[10px] font-black bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded">{{ $timeframe }}</span>
                    <span class="ml-1 uppercase text-[10px] font-black bg-slate-800 text-slate-300 px-2 py-0.5 rounded">Scope: {{ strtoupper($scope) }}</span>
                </p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black uppercase text-slate-400">Generated On</p>
                <p class="text-xs font-bold text-white">{{ now()->format('d M Y, h:i A') }}</p>
            </div>
        </div>

        <!-- Executive Summary Cards Grid -->
        @php
            $sCarbon = \Carbon\Carbon::parse($startDate);
            $eCarbon = \Carbon\Carbon::parse($endDate);
            $daysCount = max(1, $sCarbon->diffInDays($eCarbon) + 1);

            $totSales = $totals['sales'] ?? 0;
            $totExpense = $totals['expense'] ?? 0;
            $totNet = $totals['net'] ?? 0;
            $totGl = $totals['gl_bills'] ?? 0;

            $totDailyAvgSales = round($totSales / $daysCount, 2);
            $expPct = $totSales > 0 ? round(($totExpense / $totSales) * 100, 1) : 0;
            $netPct = $totSales > 0 ? round(($totNet / $totSales) * 100, 1) : 0;
            $glPct = $totSales > 0 ? round(($totGl / $totSales) * 100, 1) : 0;
        @endphp
        <div class="grid grid-cols-4 gap-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Total Network Sales</p>
                <p class="text-lg font-black text-emerald-700 font-mono mt-0.5">₹{{ number_format($totSales, 2) }}</p>
                @if($daysCount > 1)
                    <p class="text-[10px] font-bold text-emerald-600 mt-1">Avg: ₹{{ number_format($totDailyAvgSales, 2) }}/day</p>
                @else
                    <p class="text-[10px] font-bold text-emerald-600 mt-1">100% (Gross Inflow)</p>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Total Network Expense</p>
                <p class="text-lg font-black text-rose-700 font-mono mt-0.5">₹{{ number_format($totExpense, 2) }}</p>
                <p class="text-[10px] font-bold text-rose-600 mt-1">{{ $expPct }}% of Sales</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Net Network P/L</p>
                <p class="text-lg font-black {{ $totNet >= 0 ? 'text-emerald-700' : 'text-rose-700' }} font-mono mt-0.5">₹{{ number_format($totNet, 2) }}</p>
                <p class="text-[10px] font-bold {{ $totNet >= 0 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">{{ $netPct >= 0 ? '+' : '' }}{{ $netPct }}% Margin</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Total GL Bills</p>
                <p class="text-lg font-black text-amber-800 font-mono mt-0.5">₹{{ number_format($totGl, 2) }}</p>
                <p class="text-[10px] font-bold text-amber-700 mt-1">{{ $glPct }}% of Sales</p>
            </div>
        </div>

        <!-- All Shops Summary Table -->
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase text-slate-500">
                    <tr>
                        <th class="py-2.5 px-3">Shop Name</th>
                        <th class="py-2.5 px-3">Scope</th>
                        <th class="py-2.5 px-3 text-right">Sales Total</th>
                        <th class="py-2.5 px-3 text-right">Total Expense</th>
                        <th class="py-2.5 px-3 text-right">Net Balance</th>
                        <th class="py-2.5 px-3 text-right">GL Bill</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold">
                    @foreach($shopRows as $shopRow)
                        @php
                            $sSales = $shopRow['sales'] ?? 0;
                            $sExpense = $shopRow['expense'] ?? 0;
                            $sNet = $shopRow['net'] ?? 0;
                            $sGl = $shopRow['gl_bills'] ?? 0;

                            $sDailyAvgSales = round($sSales / $daysCount, 2);
                            $sExpPct = $sSales > 0 ? round(($sExpense / $sSales) * 100, 1) : 0;
                            $sNetPct = $sSales > 0 ? round(($sNet / $sSales) * 100, 1) : 0;
                            $sGlPct = $sSales > 0 ? round(($sGl / $sSales) * 100, 1) : 0;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-3 font-bold text-slate-900">{{ $shopRow['name'] }}</td>
                            <td class="py-2.5 px-3 text-slate-500 text-[11px]">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $shopRow['scope'] === 'Direct' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $shopRow['scope'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700">
                                ₹{{ number_format($sSales, 2) }}
                                @if($daysCount > 1)
                                    <span class="block text-[9px] font-bold text-slate-400">(Avg: ₹{{ number_format($sDailyAvgSales, 2) }}/day)</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-700">
                                ₹{{ number_format($sExpense, 2) }}
                                <span class="block text-[9px] font-bold text-slate-400">({{ $sExpPct }}%)</span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold {{ $sNet >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                ₹{{ number_format($sNet, 2) }}
                                <span class="block text-[9px] font-bold {{ $sNet >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">({{ $sNetPct >= 0 ? '+' : '' }}{{ $sNetPct }}%)</span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-amber-800">
                                ₹{{ number_format($sGl, 2) }}
                                <span class="block text-[9px] font-bold text-slate-400">({{ $sGlPct }}%)</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-900 text-white font-black text-xs border-t-2 border-slate-950">
                    <tr>
                        <td class="py-3 px-3">Total ({{ count($shopRows) }} Active Shops)</td>
                        <td class="py-3 px-3 text-slate-400">-</td>
                        <td class="py-3 px-3 text-right font-mono text-emerald-400">
                            ₹{{ number_format($totSales, 2) }}
                            @if($daysCount > 1)
                                <span class="block text-[9px] font-bold text-emerald-300/80">(Avg: ₹{{ number_format($totDailyAvgSales, 2) }}/day)</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-right font-mono text-rose-300">
                            ₹{{ number_format($totExpense, 2) }}
                            <span class="block text-[9px] font-bold text-rose-400">({{ $expPct }}%)</span>
                        </td>
                        <td class="py-3 px-3 text-right font-mono {{ $totNet >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            ₹{{ number_format($totNet, 2) }}
                            <span class="block text-[9px] font-bold {{ $totNet >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">({{ $netPct >= 0 ? '+' : '' }}{{ $netPct }}%)</span>
                        </td>
                        <td class="py-3 px-3 text-right font-mono text-amber-300">
                            ₹{{ number_format($totGl, 2) }}
                            <span class="block text-[9px] font-bold text-amber-400">({{ $glPct }}%)</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
