<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Statement Export ({{ $startDate }} to {{ $endDate }})</title>
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
    <div class="max-w-4xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm print-card space-y-6">
        <!-- Print Header -->
        <div class="flex justify-between items-center no-print border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-lg font-bold text-slate-900">Finance Statement Export</h1>
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

        <!-- Banner -->
        <div class="bg-slate-950 text-white rounded-xl p-5 flex justify-between items-center">
            <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Green Leaf ERP — Finance Executive Report</p>
                <h2 class="text-xl font-black text-white mt-0.5">Financial Summary &amp; Ledger Details</h2>
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

        <!-- Render Export Tables -->
        @php
            $currentSection = null;
        @endphp

        <div class="space-y-6">
            @foreach($exportRows as $row)
                @if(empty($row))
                    @continue
                @endif

                @if(count($row) === 1 && in_array($row[0], ['Total Sales Details', 'Total Expense Details']))
                    @php $currentSection = $row[0]; @endphp
                    <div class="pt-4 border-t border-slate-200">
                        <h3 class="text-base font-black text-slate-900">{{ $currentSection }}</h3>
                    </div>
                @elseif(isset($row[0]) && $row[0] === 'Date')
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase text-slate-500">
                                <tr>
                                    @foreach($row as $colIndex => $colHeader)
                                        <th class="py-2.5 px-3 {{ $colIndex > 0 ? 'text-right' : '' }}">{{ $colHeader }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono">
                @else
                    <tr class="hover:bg-slate-50">
                        @foreach($row as $colIndex => $colVal)
                            <td class="py-2.5 px-3 {{ $colIndex > 0 ? 'text-right font-bold' : 'font-bold text-slate-900' }}">
                                @if(is_numeric($colVal) && $colIndex > 0)
                                    ₹{{ number_format((float) $colVal, 2) }}
                                @else
                                    {{ $colVal }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @if($loop->last || (isset($exportRows[$loop->index + 1]) && (empty($exportRows[$loop->index + 1]) || count($exportRows[$loop->index + 1]) === 1)))
                            </tbody>
                        </table>
                    </div>
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</body>
</html>
