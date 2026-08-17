<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Cashbook Report' }} ({{ $startDate }} to {{ $endDate }})</title>
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
    <div class="max-w-4xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm print-card space-y-6">
        <!-- Print Header -->
        <div class="flex justify-between items-center no-print border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-lg font-bold text-slate-900">Finance Statement Export</h1>
                <p class="text-xs text-slate-500">Print or save as PDF using your browser print dialog.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.close()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer">
                    Close
                </button>
                <button onclick="window.print()" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print
                </button>
                <a href="{{ request()->fullUrlWithQuery(['download' => 1]) }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download PDF
                </a>
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
        <div class="space-y-6">
            @php $inTable = false; @endphp
            @foreach($exportRows as $rowIndex => $row)
                @if(empty($row))
                    @if($inTable)
                        </tbody>
                        </table>
                        </div>
                        @php $inTable = false; @endphp
                    @endif
                    @continue
                @endif

                @if(count($row) === 1 && in_array($row[0], ['Total Sales Details', 'Total Expense Details']))
                    @if($inTable)
                        </tbody>
                        </table>
                        </div>
                        @php $inTable = false; @endphp
                    @endif
                    <div class="pt-4 border-t border-slate-200">
                        <h3 class="text-base font-black text-slate-900">{{ $row[0] }}</h3>
                    </div>
                @elseif(isset($row[0]) && in_array($row[0], ['Date', 'Shop Name', 'Shop']))
                    @if($inTable)
                        </tbody>
                        </table>
                        </div>
                        @php $inTable = false; @endphp
                    @endif
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase text-slate-500">
                                <tr>
                                    @foreach($row as $colIndex => $colHeader)
                                        @php
                                            $isRight = in_array($colHeader, ['Sales Total', 'Total Expense', 'Net Balance', 'GL Bill', 'Income', 'Expense']);
                                        @endphp
                                        <th class="py-2.5 px-3 {{ $isRight ? 'text-right' : 'text-left' }}">{{ $colHeader }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @php $inTable = true; @endphp
                @else
                    <tr class="hover:bg-slate-50 {{ isset($row[0]) && $row[0] === 'Total' ? 'bg-slate-100/80 font-black' : '' }}">
                        @foreach($row as $colIndex => $colVal)
                            @php
                                $isNumeric = is_numeric($colVal) && $colIndex > 0;
                                $isSalesCol = ($colIndex === 1 && count($row) === 5) || ($colIndex === 2 && count($row) === 6);
                            @endphp
                            <td class="py-2.5 px-3 {{ $isNumeric ? 'text-right font-mono font-bold' : 'text-left font-semibold text-slate-800' }} {{ $isSalesCol ? 'text-emerald-700 font-extrabold' : '' }}">
                                @if($isNumeric)
                                    ₹{{ number_format((float) $colVal, 2) }}
                                @else
                                    {{ $colVal }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endif
            @endforeach

            @if($inTable)
                </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
