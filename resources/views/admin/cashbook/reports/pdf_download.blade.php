<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Cashbook Report' }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            line-height: 1.35;
        }

        /* Banner Header */
        .banner-table {
            width: 100%;
            background-color: #090d16;
            color: #ffffff;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .banner-table td {
            vertical-align: middle;
        }
        .banner-tag {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #34d399;
            letter-spacing: 0.5px;
        }
        .banner-title {
            font-size: 13.5px;
            font-weight: bold;
            color: #ffffff;
            margin: 2px 0;
            letter-spacing: -0.3px;
        }
        .banner-sub {
            font-size: 8.5px;
            color: #94a3b8;
        }
        .banner-sub strong {
            color: #ffffff;
        }
        .badge-green {
            background-color: #064e3b;
            color: #6ee7b7;
            padding: 1.5px 5px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .gen-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #94a3b8;
            text-align: right;
        }
        .gen-time {
            font-size: 8.5px;
            font-weight: bold;
            color: #ffffff;
            text-align: right;
            margin-top: 1px;
            white-space: nowrap;
        }

        /* 4 Summary Cards */
        .cards-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 10px;
        }
        .card-box {
            width: 25%;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 8px;
            text-align: center;
        }
        .card-label {
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .card-val {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 1px;
            white-space: nowrap;
        }
        .card-sub {
            font-size: 6.5px;
            font-weight: bold;
        }

        /* Color classes */
        .text-emerald-700 { color: #047857; }
        .text-emerald-600 { color: #059669; }
        .text-rose-700 { color: #be123c; }
        .text-rose-600 { color: #e11d48; }
        .text-amber-800 { color: #92400e; }
        .text-amber-700 { color: #b45309; }
        .text-slate-400 { color: #94a3b8; }
        .text-slate-900 { color: #0f172a; }

        /* Data Table */
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 12px;
            margin-bottom: 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #cbd5e1;
        }
        .table-wrap {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .table-wrap th {
            background-color: #f8fafc;
            color: #64748b;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 7px;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-wrap td {
            padding: 5px 7px;
            font-size: 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .table-wrap tbody tr:nth-child(even) {
            background-color: #ffffff;
        }
        .table-wrap tbody tr:nth-child(odd) {
            background-color: #fcfdfe;
        }
        .table-wrap tbody tr.row-total td {
            background-color: #090d16 !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 8.5px;
            border-top: 2px solid #020617;
            border-bottom: none;
        }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .text-emerald { color: #047857; }
    </style>
</head>
<body>
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

    <!-- Dark Banner Header -->
    <table class="banner-table">
        <tr>
            <td style="width: 70%;">
                <div class="banner-tag">Green Leaf ERP — Single Outlet Finance Report</div>
                <div class="banner-title">{{ $title ?? 'Financial Summary & Ledger Details' }}</div>
                <div class="banner-sub">
                    Period: <strong>{{ $startDate }}</strong> to <strong>{{ $endDate }}</strong>
                    &nbsp;<span class="badge-green">{{ strtoupper($timeframe) }}</span>
                </div>
            </td>
            <td style="width: 30%;">
                <div class="gen-label">GENERATED ON</div>
                <div class="gen-time">{{ now()->format('d M Y, h:i A') }}</div>
            </td>
        </tr>
    </table>

    <!-- 4 Executive Summary Cards -->
    <table class="cards-table">
        <tr>
            <td class="card-box">
                <div class="card-label">TOTAL SALES</div>
                <div class="card-val text-emerald-700">₹{{ number_format($totSales, 2) }}</div>
                @if($daysCount > 1)
                    <div class="card-sub text-emerald-600">Avg: ₹{{ number_format($totDailyAvgSales, 2) }}/day</div>
                @else
                    <div class="card-sub text-emerald-600">100% (Gross Inflow)</div>
                @endif
            </td>
            <td class="card-box">
                <div class="card-label">TOTAL EXPENSE</div>
                <div class="card-val text-rose-700">₹{{ number_format($totExpense, 2) }}</div>
                <div class="card-sub text-rose-600">{{ $expPct }}% of Sales</div>
            </td>
            <td class="card-box">
                <div class="card-label">NET BALANCE</div>
                <div class="card-val {{ $totNet >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($totNet, 2) }}</div>
                <div class="card-sub {{ $totNet >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $netPct >= 0 ? '+' : '' }}{{ $netPct }}% Margin</div>
            </td>
            <td class="card-box">
                <div class="card-label">TOTAL GL BILLS</div>
                <div class="card-val text-amber-700">₹{{ number_format($totGl, 2) }}</div>
                <div class="card-sub text-amber-700">{{ $glPct }}% of Sales</div>
            </td>
        </tr>
    </table>

    <!-- Export Tables Loop -->
    @php
        $inTable = false;
    @endphp

    @foreach($exportRows as $rowIndex => $row)
        @if(empty($row) || (count($row) === 1 && empty($row[0])))
            @if($inTable)
                </tbody>
                </table>
                @php $inTable = false; @endphp
            @endif
            @continue
        @endif

        @if(count($row) === 1 && in_array($row[0], ['Total Sales Details', 'Total Expense Details']))
            @if($inTable)
                </tbody>
                </table>
                @php $inTable = false; @endphp
            @endif
            <div class="section-title">{{ $row[0] }}</div>
        @elseif(isset($row[0]) && in_array($row[0], ['Date', 'Shop Name', 'Shop']))
            @if($inTable)
                </tbody>
                </table>
                @php $inTable = false; @endphp
            @endif
            <table class="table-wrap">
                <thead>
                    <tr>
                        @foreach($row as $colIndex => $colHeader)
                            @php
                                $isRight = in_array($colHeader, ['Sales Total', 'Total Expense', 'Net Balance', 'GL Bill', 'Income', 'Expense', 'Amount']);
                            @endphp
                            <th class="{{ $isRight ? 'text-right' : 'text-left' }}">{{ $colHeader }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @php $inTable = true; @endphp
        @else
            @php
                $isTotalRow = isset($row[0]) && $row[0] === 'Total';
            @endphp
            <tr class="{{ $isTotalRow ? 'row-total' : '' }}">
                @foreach($row as $colIndex => $colVal)
                    @php
                        $isNumeric = is_numeric($colVal) && $colIndex > 0;
                        $isSalesCol = ($colIndex === 1 && count($row) === 5) || ($colIndex === 2 && count($row) === 6);
                    @endphp
                    <td class="{{ $isNumeric ? 'text-right font-bold' : 'text-left font-bold' }} {{ ($isSalesCol && ! $isTotalRow) ? 'text-emerald' : '' }}">
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
    @endif
</body>
</html>
