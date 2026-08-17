<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Shops Financial Overview ({{ $startDate }} to {{ $endDate }})</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            .print-card { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
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
        <div class="grid grid-cols-4 gap-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Total Network Sales</p>
                <p class="text-lg font-black text-emerald-700 font-mono mt-0.5">₹{{ number_format($totals['sales'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Total Network Expense</p>
                <p class="text-lg font-black text-rose-700 font-mono mt-0.5">₹{{ number_format($totals['expense'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Net Network P/L</p>
                <p class="text-lg font-black {{ $totals['net'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }} font-mono mt-0.5">₹{{ number_format($totals['net'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Total GL Bills</p>
                <p class="text-lg font-black text-amber-800 font-mono mt-0.5">₹{{ number_format($totals['gl_bills'], 2) }}</p>
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
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-3 font-bold text-slate-900">{{ $shopRow['name'] }}</td>
                            <td class="py-2.5 px-3 text-slate-500 text-[11px]">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $shopRow['scope'] === 'Direct' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $shopRow['scope'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700">₹{{ number_format($shopRow['sales'], 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-700">₹{{ number_format($shopRow['expense'], 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold {{ $shopRow['net'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($shopRow['net'], 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-amber-800">₹{{ number_format($shopRow['gl_bills'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-900 text-white font-black text-xs border-t-2 border-slate-950">
                    <tr>
                        <td class="py-3 px-3">Total ({{ count($shopRows) }} Active Shops)</td>
                        <td class="py-3 px-3 text-slate-400">-</td>
                        <td class="py-3 px-3 text-right font-mono text-emerald-400">₹{{ number_format($totals['sales'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono text-rose-300">₹{{ number_format($totals['expense'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono {{ $totals['net'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">₹{{ number_format($totals['net'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono text-amber-300">₹{{ number_format($totals['gl_bills'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
