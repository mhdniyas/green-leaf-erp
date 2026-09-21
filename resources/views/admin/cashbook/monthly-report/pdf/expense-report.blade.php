<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Operating Expense Report ({{ $period['start_date'] }} to {{ $period['end_date'] }})</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 8.5px;
            color: #0f172a;
            margin: 0;
            padding: 8px;
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
            font-size: 15px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 9.5px;
            color: #475569;
            margin-top: 1px;
            font-weight: 600;
        }
        .meta-box {
            text-align: right;
            font-size: 8.5px;
            color: #64748b;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.data-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #0f172a;
            text-align: right;
        }
        table.data-table th:first-child { text-align: left; }
        table.data-table td {
            font-size: 8px;
            padding: 4px 6px;
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
        .text-center { text-align: center; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .text-rose { color: #be123c; }
        .footer {
            margin-top: 12px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
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
            <div class="title">Green Leaf — Consolidated Operating Expense Matrix</div>
            <div class="subtitle">Period: {{ $period['start_date'] }} to {{ $period['end_date'] }} ({{ $period['month_label'] }})</div>
        </div>
        <div class="meta-box">
            <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
            <div>Total Operating Expense: ₹{{ number_format($total_operating_expenses, 2) }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 16%;">Date</th>
                <th style="width: 14%;">Salary (₹)</th>
                <th style="width: 14%;">Rent (₹)</th>
                <th style="width: 14%;">Vehicle / Fuel (₹)</th>
                <th style="width: 14%;">Food / Mess (₹)</th>
                <th style="width: 14%;">Others (₹)</th>
                <th style="width: 14%;">Daily Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($daily_matrix as $row)
                <tr>
                    <td class="font-mono" style="text-align: left;"><strong>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</strong> ({{ \Carbon\Carbon::parse($row['date'])->format('D') }})</td>
                    <td class="text-right font-mono">{{ number_format($row['salary'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['rent'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['vehicle_fuel'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['food_mess'], 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($row['other_expense'], 2) }}</td>
                    <td class="text-right font-mono font-bold text-rose">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="text-align: left;">TOTAL</td>
                <td class="text-right font-mono">₹{{ number_format($totals['salary'] ?? 0, 2) }}</td>
                <td class="text-right font-mono">₹{{ number_format($totals['rent'] ?? 0, 2) }}</td>
                <td class="text-right font-mono">₹{{ number_format($totals['vehicle_fuel'] ?? 0, 2) }}</td>
                <td class="text-right font-mono">₹{{ number_format($totals['food_mess'] ?? 0, 2) }}</td>
                <td class="text-right font-mono">₹{{ number_format($totals['other_expense'] ?? 0, 2) }}</td>
                <td class="text-right font-mono text-rose">₹{{ number_format($total_operating_expenses, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <span>Green Leaf ERP — Finance Monthly Reports</span>
        <span>Reconciliation Difference: ₹{{ number_format($reconciliation['difference'], 2) }}</span>
    </div>
</body>
</html>
