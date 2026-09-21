<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Sale Split Report ({{ $period['start_date'] }} to {{ $period['end_date'] }})</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 8mm;
        }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 8px;
            color: #0f172a;
            margin: 0;
            padding: 8px;
            background: #fff;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 8px;
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
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.data-table th {
            font-size: 7.5px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 5px;
            border: 1px solid #0f172a;
            text-align: center;
        }
        table.data-table th.th-fruits { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        table.data-table th.th-veg { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        table.data-table th.th-stationery { background: #fefce8; color: #854d0e; border-color: #fef08a; }
        table.data-table th.th-other { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
        table.data-table th.th-totals { background: #0f172a; color: #ffffff; border-color: #0f172a; }

        table.data-table td {
            font-size: 7.5px;
            padding: 3px 5px;
            border: 1px solid #e2e8f0;
        }
        table.data-table tr:nth-child(even) {
            background: #f8fafc;
        }
        table.data-table tfoot td {
            background: #e2e8f0;
            font-weight: 900;
            border-top: 2px solid #0f172a;
            font-size: 8px;
        }
        .text-right { text-align: right; }
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
            <div class="title">Green Leaf — Monthly Sale Split & Purchase Allocation</div>
            <div class="subtitle">Period: {{ $period['start_date'] }} to {{ $period['end_date'] }} ({{ $period['month_label'] }})</div>
        </div>
        <div class="meta-box">
            <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
            <div>Reconciliation Status: {{ strtoupper($reconciliation['status']) }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="th-totals" style="width: 9%;">Date</th>
                <th colspan="2" class="th-fruits">Fruits</th>
                <th colspan="2" class="th-veg">Vegetables (Veg)</th>
                <th colspan="2" class="th-stationery">Stationery</th>
                <th colspan="3" class="th-other">Other & Indirect</th>
                <th colspan="3" class="th-totals">Aggregated Total</th>
            </tr>
            <tr>
                <th class="th-fruits">Sale</th>
                <th class="th-fruits">Purchase</th>
                <th class="th-veg">Sale</th>
                <th class="th-veg">Purchase</th>
                <th class="th-stationery">Sale</th>
                <th class="th-stationery">Purchase</th>
                <th class="th-other">Sale</th>
                <th class="th-other">Prod Exp</th>
                <th class="th-other">Other Exp</th>
                <th class="th-totals">Total Sales</th>
                <th class="th-totals">Total Exp</th>
                <th class="th-totals">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($daily_rows as $row)
                <tr>
                    <td class="font-mono"><strong>{{ \Carbon\Carbon::parse($row['date'])->format('d M') }}</strong> ({{ \Carbon\Carbon::parse($row['date'])->format('D') }})</td>
                    <td class="text-right font-mono">{{ number_format($row['fruits_sale'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['fruits_expense'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['veg_sale'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['veg_expense'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['stationery_sale'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['stationery_expense'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['other_sale'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['other_product_expense'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['other_expenses'], 2) }}</td>
                    <td class="text-right font-mono font-bold">{{ number_format($row['total_sales'], 2) }}</td>
                    <td class="text-right font-mono text-rose">{{ number_format($row['total_expenses'], 2) }}</td>
                    <td class="text-right font-mono font-bold {{ $row['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                <td class="text-right font-mono">{{ number_format($summary['fruits_sale'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['fruits_expense'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['veg_sale'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['veg_expense'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['stationery_sale'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['stationery_expense'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['other_sale'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['other_product_expense'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['other_expenses'], 2) }}</td>
                <td class="text-right font-mono">{{ number_format($summary['total_sales'], 2) }}</td>
                <td class="text-right font-mono text-rose">{{ number_format($summary['total_expenses'], 2) }}</td>
                <td class="text-right font-mono {{ $summary['balance'] >= 0 ? 'text-emerald' : 'text-rose' }}">{{ number_format($summary['balance'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <span>Green Leaf ERP — Finance Monthly Reports</span>
        <span>Reconciliation Difference: ₹{{ number_format($reconciliation['difference'], 2) }}</span>
    </div>
</body>
</html>
