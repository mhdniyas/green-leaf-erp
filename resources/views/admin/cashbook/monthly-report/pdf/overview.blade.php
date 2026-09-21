<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Overview Report ({{ $period['start_date'] }} to {{ $period['end_date'] }})</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
            padding: 10px;
            background: #fff;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .title {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .subtitle {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
            font-weight: 600;
        }
        .meta-box {
            text-align: right;
            font-size: 9px;
            color: #64748b;
        }
        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 14px;
            border-collapse: separate;
            border-spacing: 6px;
        }
        .summary-cell {
            display: table-cell;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            width: 20%;
        }
        .summary-label {
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
        }
        .summary-value {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            margin-top: 3px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.data-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #0f172a;
        }
        table.data-table td {
            font-size: 9px;
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
        }
        table.data-table tr:nth-child(even) {
            background: #f8fafc;
        }
        table.data-table tfoot td {
            background: #e2e8f0;
            font-weight: 900;
            border-top: 2px solid #0f172a;
            font-size: 9.5px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .text-emerald { color: #047857; }
        .text-rose { color: #be123c; }
        .text-slate-500 { color: #64748b; }
        .footer {
            margin-top: 14px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="title">Green Leaf — Monthly Executive Overview</div>
            <div class="subtitle">Period: {{ $period['start_date'] }} to {{ $period['end_date'] }} ({{ $period['month_label'] }})</div>
        </div>
        <div class="meta-box">
            <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
            <div>Reconciliation Status: {{ strtoupper($reconciliation['status']) }} (Diff: ₹{{ number_format($reconciliation['difference'], 2) }})</div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="summary-label">Client / Owned Sales</div>
            <div class="summary-value font-mono">₹{{ number_format($summary['client_sales'], 2) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">All Other Sales</div>
            <div class="summary-value font-mono">₹{{ number_format($summary['all_other_sales'], 2) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Total Gross Sales</div>
            <div class="summary-value font-mono text-emerald">₹{{ number_format($summary['total_sales'], 2) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Total Expenses</div>
            <div class="summary-value font-mono text-rose">₹{{ number_format($summary['total_expenses'], 2) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Net Balance</div>
            <div class="summary-value font-mono {{ $summary['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">
                ₹{{ number_format($summary['balance'], 2) }}
            </div>
        </div>
    </div>

    {{-- Daily Breakdown Table --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th class="text-right" style="width: 20%;">Client / Owned Sales (₹)</th>
                <th class="text-right" style="width: 20%;">All Other Sales (₹)</th>
                <th class="text-right" style="width: 15%;">Total Sales (₹)</th>
                <th class="text-right" style="width: 15%;">Total Expenses (₹)</th>
                <th class="text-right" style="width: 15%;">Balance (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($daily_rows as $row)
                <tr>
                    <td class="font-mono"><strong>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</strong> ({{ \Carbon\Carbon::parse($row['date'])->format('D') }})</td>
                    <td class="text-right font-mono">{{ number_format($row['client_sales'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['all_other_sales'], 2) }}</td>
                    <td class="text-right font-mono font-bold">{{ number_format($row['total_sales'], 2) }}</td>
                    <td class="text-right font-mono text-rose">{{ number_format($row['total_expenses'], 2) }}</td>
                    <td class="text-right font-mono font-bold {{ $row['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">
                        {{ number_format($row['balance'], 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                <td class="text-right font-mono">₹{{ number_format($summary['client_sales'], 2) }}</td>
                <td class="text-right font-mono">₹{{ number_format($summary['all_other_sales'], 2) }}</td>
                <td class="text-right font-mono">₹{{ number_format($summary['total_sales'], 2) }}</td>
                <td class="text-right font-mono text-rose">₹{{ number_format($summary['total_expenses'], 2) }}</td>
                <td class="text-right font-mono {{ $summary['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">
                    ₹{{ number_format($summary['balance'], 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <span>Green Leaf ERP — Finance Monthly Reports</span>
        <span>Reconciliation Difference: ₹{{ number_format($reconciliation['difference'], 2) }}</span>
    </div>
</body>
</html>
