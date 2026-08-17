<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'All Shops Financial Overview' }}</title>
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
            margin-bottom: 10px;
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
        .badge-dark {
            background-color: #1e293b;
            color: #cbd5e1;
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
        .text-emerald-400 { color: #34d399; }
        .text-rose-700 { color: #be123c; }
        .text-rose-600 { color: #e11d48; }
        .text-rose-400 { color: #f87171; }
        .text-amber-800 { color: #92400e; }
        .text-amber-700 { color: #b45309; }
        .text-amber-400 { color: #fbbf24; }
        .text-slate-400 { color: #94a3b8; }
        .text-slate-900 { color: #0f172a; }

        /* Main Table */
        .table-wrap {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            border-collapse: collapse;
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
        .sub-stat {
            display: block;
            font-size: 6.5px;
            font-weight: bold;
            margin-top: 1px;
        }

        /* Footer Row */
        .footer-row td {
            background-color: #090d16 !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 8.5px;
            padding: 6px 7px;
            border-top: 2px solid #020617;
            border-bottom: none;
        }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $uniqueScopes = collect($shopRows)->pluck('scope')->filter()->unique();
        $hasMultipleScopes = $uniqueScopes->count() > 1;
        $singleScopeName = $uniqueScopes->count() === 1 ? $uniqueScopes->first() : null;
        $displayScope = $singleScopeName ?: (strtoupper($scope) !== 'ALL' ? strtoupper($scope) : 'ALL');

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
                <div class="banner-tag">GREEN LEAF ERP — EXECUTIVE NETWORK REPORT</div>
                <div class="banner-title">{{ $title ?? 'All Shops Executive Financial Overview' }}</div>
                <div class="banner-sub">
                    Period: <strong>{{ $startDate }}</strong> to <strong>{{ $endDate }}</strong>
                    &nbsp;<span class="badge-green">{{ strtoupper($timeframe) }}</span>
                    &nbsp;<span class="badge-dark">SCOPE: {{ strtoupper($displayScope) }}</span>
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
                <div class="card-label">TOTAL NETWORK SALES</div>
                <div class="card-val text-emerald-700">₹{{ number_format($totSales, 2) }}</div>
                @if($daysCount > 1)
                    <div class="card-sub text-emerald-600">Avg: ₹{{ number_format($totDailyAvgSales, 2) }}/day</div>
                @else
                    <div class="card-sub text-emerald-600">100% (Gross Inflow)</div>
                @endif
            </td>
            <td class="card-box">
                <div class="card-label">TOTAL NETWORK EXPENSE</div>
                <div class="card-val text-rose-700">₹{{ number_format($totExpense, 2) }}</div>
                <div class="card-sub text-rose-600">{{ $expPct }}% of Sales</div>
            </td>
            <td class="card-box">
                <div class="card-label">NET NETWORK P/L</div>
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

    <!-- All Shops Summary Table -->
    <table class="table-wrap">
        <thead>
            <tr>
                <th class="text-left" style="{{ $hasMultipleScopes ? 'width: 26%;' : 'width: 32%;' }}">SHOP NAME</th>
                @if($hasMultipleScopes)
                    <th class="text-left" style="width: 14%;">SCOPE</th>
                @endif
                <th class="text-right" style="{{ $hasMultipleScopes ? 'width: 15%;' : 'width: 17%;' }}">SALES TOTAL</th>
                <th class="text-right" style="{{ $hasMultipleScopes ? 'width: 15%;' : 'width: 17%;' }}">TOTAL EXPENSE</th>
                <th class="text-right" style="{{ $hasMultipleScopes ? 'width: 15%;' : 'width: 17%;' }}">NET BALANCE</th>
                <th class="text-right" style="{{ $hasMultipleScopes ? 'width: 15%;' : 'width: 17%;' }}">GL BILL</th>
            </tr>
        </thead>
        <tbody>
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
                <tr>
                    <td class="text-left font-bold text-slate-900">{{ $shopRow['name'] }}</td>
                    @if($hasMultipleScopes)
                        <td class="text-left text-slate-400">
                            <span style="background-color: #dcfce7; color: #15803d; padding: 1px 4px; border-radius: 3px; font-size: 6.5px; font-weight: bold; text-transform: uppercase;">
                                {{ $shopRow['scope'] }}
                            </span>
                        </td>
                    @endif
                    <td class="text-right font-bold text-emerald-700">
                        ₹{{ number_format($sSales, 2) }}
                        @if($daysCount > 1)
                            <span class="sub-stat text-slate-400">(Avg: ₹{{ number_format($sDailyAvgSales, 2) }}/day)</span>
                        @endif
                    </td>
                    <td class="text-right font-bold text-rose-700">
                        ₹{{ number_format($sExpense, 2) }}
                        <span class="sub-stat text-slate-400">({{ $sExpPct }}%)</span>
                    </td>
                    <td class="text-right font-bold {{ $sNet >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        ₹{{ number_format($sNet, 2) }}
                        <span class="sub-stat {{ $sNet >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">({{ $sNetPct >= 0 ? '+' : '' }}{{ $sNetPct }}%)</span>
                    </td>
                    <td class="text-right font-bold text-amber-800">
                        ₹{{ number_format($sGl, 2) }}
                        <span class="sub-stat text-slate-400">({{ $sGlPct }}%)</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="footer-row">
                <td class="text-left">Total ({{ count($shopRows) }} Active Shops)</td>
                @if($hasMultipleScopes)
                    <td class="text-left text-slate-400">-</td>
                @endif
                <td class="text-right text-emerald-400">
                    ₹{{ number_format($totSales, 2) }}
                    @if($daysCount > 1)
                        <span class="sub-stat text-emerald-400" style="color: #6ee7b7;">(Avg: ₹{{ number_format($totDailyAvgSales, 2) }}/day)</span>
                    @endif
                </td>
                <td class="text-right text-rose-400">
                    ₹{{ number_format($totExpense, 2) }}
                    <span class="sub-stat text-rose-400" style="color: #fda4af;">({{ $expPct }}%)</span>
                </td>
                <td class="text-right {{ $totNet >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    ₹{{ number_format($totNet, 2) }}
                    <span class="sub-stat" style="{{ $totNet >= 0 ? 'color: #6ee7b7;' : 'color: #fda4af;' }}">({{ $netPct >= 0 ? '+' : '' }}{{ $netPct }}%)</span>
                </td>
                <td class="text-right text-amber-400">
                    ₹{{ number_format($totGl, 2) }}
                    <span class="sub-stat text-amber-400" style="color: #fde68a;">({{ $glPct }}%)</span>
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
