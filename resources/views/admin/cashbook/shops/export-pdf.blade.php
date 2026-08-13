<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cashbook Export — {{ $shop->name }} ({{ $startDate }} to {{ $endDate }})</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            .print-card { border: 1px solid #cbd5e1 !important; shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 sm:p-6 text-slate-900 font-sans">
    <div class="max-w-4xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm print-card">
        <!-- Print Button Header -->
        <div class="flex justify-between items-center no-print mb-6 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-lg font-bold text-slate-900">Cashbook Statement Export</h1>
                <p class="text-xs text-slate-500">Print or save as PDF using your browser print dialog.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.close()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">
                    Close Window
                </button>
                <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-sm flex items-center gap-1.5">
                    Print / Save PDF
                </button>
            </div>
        </div>

        <!-- Statement Header -->
        <div class="bg-slate-950 text-white rounded-xl p-5 flex justify-between items-center">
            <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Green Leaf ERP — Shop Cashbook Ledger</p>
                <h2 class="text-xl font-black text-white mt-0.5">{{ $shop->name }}</h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Period: <span class="text-white font-bold">{{ $startDate }}</span> to <span class="text-white font-bold">{{ $endDate }}</span> 
                    <span class="ml-1 uppercase text-[10px] font-black bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded">{{ $timeframe }}</span>
                </p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black uppercase text-slate-400">Generated On</p>
                <p class="text-xs font-bold text-white">{{ now()->format('d M Y, h:i A') }}</p>
            </div>
        </div>

        <!-- Metrics Summary Strip -->
        <div class="grid grid-cols-3 gap-3 my-5">
            <div class="border border-slate-200 rounded-xl p-3 bg-slate-50 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500">Total Sales</p>
                <p class="text-base font-black text-emerald-700 mt-0.5">Rs. {{ number_format($totalSales, 2) }}</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-3 bg-slate-50 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500">Total Expense</p>
                <p class="text-base font-black text-rose-700 mt-0.5">Rs. {{ number_format($totalExpense, 2) }}</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-3 bg-slate-50 text-center">
                <p class="text-[10px] font-black uppercase text-slate-500">Net Position</p>
                <p class="text-base font-black text-slate-950 mt-0.5">Rs. {{ number_format($netPosition, 2) }}</p>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="mt-4 border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100 text-[10px] font-black uppercase text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="p-2.5">Date</th>
                        <th class="p-2.5">ID</th>
                        <th class="p-2.5">Entry Type</th>
                        <th class="p-2.5">Category</th>
                        <th class="p-2.5">Funding</th>
                        <th class="p-2.5 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($transactions as $tx)
                        <tr>
                            <td class="p-2.5 font-bold text-slate-800">{{ $tx->business_date }}</td>
                            <td class="p-2.5 text-slate-500">#{{ $tx->id }}</td>
                            <td class="p-2.5 font-semibold text-slate-900">{{ $tx->entryType ? $tx->entryType->name : $tx->entry_type_code }}</td>
                            <td class="p-2.5 text-slate-600 uppercase text-[10px] font-bold">{{ $tx->entryType ? $tx->entryType->category : '-' }}</td>
                            <td class="p-2.5 text-slate-600 capitalize">{{ $tx->funding_source ?: 'default' }}</td>
                            <td class="p-2.5 text-right font-black text-slate-950">Rs. {{ number_format((float) $tx->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400 font-semibold">No transaction records found for this export timeframe.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-200 flex justify-between text-[11px] text-slate-500 font-semibold">
            <p>Green Leaf ERP System • Shop Cashbook Export</p>
            <p>Generated for {{ $shop->name }}</p>
        </div>
    </div>
</body>
</html>
