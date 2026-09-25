<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dynamic Cashbook Section Reports ({{ $period['start_date'] }} to {{ $period['end_date'] }})</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 8px;
            color: #0f172a;
            margin: 0;
            padding: 4px;
            background: #fff;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .title {
            font-size: 14px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 9px;
            color: #475569;
            margin-top: 1px;
            font-weight: 600;
        }
        .meta-box {
            text-align: right;
            font-size: 8px;
            color: #64748b;
        }
        .section-block {
            margin-bottom: 16px;
        }
        .page-break {
            page-break-before: always;
        }
        .section-header {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            margin-bottom: 4px;
            font-size: 10px;
            font-weight: 900;
            color: #0f172a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge {
            font-size: 7.5px;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 3px;
            background: #e2e8f0;
            color: #334155;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        table.data-table th {
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 6px;
            border: 1px solid #0f172a;
            background: #0f172a;
            color: #ffffff;
            text-align: center;
        }
        table.data-table th.th-left { text-align: left; }
        table.data-table th.th-right { text-align: right; }
        table.data-table td {
            font-size: 8px;
            padding: 3px 6px;
            border: 1px solid #e2e8f0;
        }
        table.data-table tr:nth-child(even) {
            background: #f8fafc;
        }
        table.data-table tfoot td {
            background: #e2e8f0;
            font-weight: 900;
            border-top: 2px solid #0f172a;
            font-size: 8.5px;
        }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .text-emerald { color: #047857; }
        .text-rose { color: #be123c; }
        .footer {
            margin-top: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 7.5px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="title">Green Leaf — Monthly Section Reports</div>
            <div class="subtitle">Period: {{ $period['start_date'] }} to {{ $period['end_date'] }} ({{ $period['period_label'] }})</div>
        </div>
        <div class="meta-box">
            <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
            <div>Sections: {{ count($sections) }} Active</div>
        </div>
    </div>

    <!-- OVERALL SUMMARY TABLE -->
    @if(!$is_single_section)
        <div class="section-block">
            <div class="section-header">
                <span>Consolidated Sections Overview</span>
                <span class="badge">Summary</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="th-left" style="width: 40%;">Section Name</th>
                        <th class="th-left" style="width: 20%;">Type</th>
                        <th class="th-right" style="width: 14%;">Sales (₹)</th>
                        <th class="th-right" style="width: 14%;">Purchases (₹)</th>
                        <th class="th-right" style="width: 12%;">Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sections as $sec)
                        @php $isTrading = ($sec['type'] === 'trading'); @endphp
                        <tr>
                            <td><strong>{{ $sec['name'] }}</strong></td>
                            <td>{{ $isTrading ? 'Product & Trading' : 'Operating Overhead' }}</td>
                            <td class="text-right font-mono">{{ $isTrading ? number_format($sec['summary']['sales'], 2) : '—' }}</td>
                            <td class="text-right font-mono">{{ $isTrading ? number_format($sec['summary']['purchases'], 2) : '—' }}</td>
                            <td class="text-right font-mono font-bold {{ ($isTrading && $sec['summary']['balance'] >= 0) ? 'text-emerald' : 'text-rose' }}">
                                {{ $isTrading ? ($sec['summary']['balance'] < 0 ? '-' : '').number_format(abs($sec['summary']['balance']), 2) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TOTAL (ALL SECTIONS)</td>
                        <td class="text-right font-mono">{{ number_format($overall_summary['total_sales'], 2) }}</td>
                        <td class="text-right font-mono">{{ number_format($overall_summary['total_purchases'], 2) }}</td>
                        <td class="text-right font-mono {{ $overall_summary['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">
                            {{ $overall_summary['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($overall_summary['balance']), 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <!-- INDIVIDUAL SECTION MATRICES -->
    @foreach($sections as $secKey => $sec)
        @if(!$is_single_section && !$loop->first)
            <div class="page-break"></div>
        @endif
        @if(!$is_single_section || $selected_section_key === $secKey)
            @php $isTrading = ($sec['type'] === 'trading'); @endphp
            <div class="section-block">
                <div class="section-header">
                    <span>{{ $sec['name'] }}</span>
                    <span class="badge">{{ $isTrading ? 'Trading / Product' : 'Operating Expense' }}</span>
                </div>

                <table class="data-table">
                    @if($isTrading)
                        <thead>
                            <tr>
                                <th class="th-left" style="width: 25%;">Date</th>
                                <th class="th-right" style="width: 25%;">Sale (₹)</th>
                                <th class="th-right" style="width: 25%;">Purchase (₹)</th>
                                <th class="th-right" style="width: 25%;">Balance (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sec['daily_rows'] as $row)
                                <tr>
                                    <td class="font-mono"><strong>{{ \Carbon\Carbon::parse($row['date'])->format('d M') }}</strong> ({{ \Carbon\Carbon::parse($row['date'])->format('D') }})</td>
                                    <td class="text-right font-mono">{{ number_format($row['sale'], 2) }}</td>
                                    <td class="text-right font-mono">{{ number_format($row['purchase'], 2) }}</td>
                                    <td class="text-right font-mono font-bold {{ $row['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">
                                        {{ $row['balance'] < 0 ? '-' : '' }}{{ number_format(abs($row['balance']), 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>MONTHLY TOTAL</td>
                                <td class="text-right font-mono">{{ number_format($sec['summary']['sales'], 2) }}</td>
                                <td class="text-right font-mono">{{ number_format($sec['summary']['purchases'], 2) }}</td>
                                <td class="text-right font-mono {{ $sec['summary']['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">
                                    {{ $sec['summary']['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($sec['summary']['balance']), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @else
                        <thead>
                            <tr>
                                <th class="th-left" style="width: 30%;">Date</th>
                                <th class="th-right" style="width: 70%;">Operating Expense (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sec['daily_rows'] as $row)
                                <tr>
                                    <td class="font-mono"><strong>{{ \Carbon\Carbon::parse($row['date'])->format('d M') }}</strong> ({{ \Carbon\Carbon::parse($row['date'])->format('D') }})</td>
                                    <td class="text-right font-mono">{{ number_format($row['expense'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>MONTHLY TOTAL</td>
                                <td class="text-right font-mono text-rose">₹{{ number_format($sec['summary']['total_expenses'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    @endforeach

    <div class="footer">
        <span>Green Leaf ERP — Finance Monthly Section Reports</span>
        <span>Reconciliation Verified</span>
    </div>
</body>
</html>
