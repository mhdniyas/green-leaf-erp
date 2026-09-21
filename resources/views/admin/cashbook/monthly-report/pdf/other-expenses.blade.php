<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Other Expenses Audit Report ({{ $period['start_date'] }} to {{ $period['end_date'] }})</title>
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
            text-align: left;
        }
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
            <div class="title">Green Leaf — Other Expenses Detailed Audit</div>
            <div class="subtitle">Period: {{ $period['start_date'] }} to {{ $period['end_date'] }} ({{ $period['month_label'] }})</div>
        </div>
        <div class="meta-box">
            <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
            <div>Total Expense: ₹{{ number_format($total_other_expenses, 2) }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 15%;">Original Category</th>
                <th style="width: 15%;">Report Group</th>
                <th style="width: 10%;">Source</th>
                <th style="width: 18%;">Entity / Target</th>
                <th style="width: 18%;">Description / Ref</th>
                <th class="text-right" style="width: 12%;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detailed_rows as $row)
                <tr>
                    <td class="font-mono">{{ \Carbon\Carbon::parse($row['business_date'])->format('d M Y') }}</td>
                    <td><strong>{{ $row['original_category'] }}</strong></td>
                    <td>{{ $row['heading_label'] }}</td>
                    <td class="font-mono text-slate-500">{{ strtoupper(str_replace('_', ' ', $row['source_type'])) }}</td>
                    <td>{{ $row['entity_name'] }}</td>
                    <td>
                        {{ $row['description'] }}
                        @if($row['reference'])
                            <span class="font-mono text-slate-500">({{ $row['reference'] }})</span>
                        @endif
                    </td>
                    <td class="text-right font-mono font-bold text-rose">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 16px;">No other expenses recorded for this period.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right">TOTAL OTHER EXPENSES</td>
                <td class="text-right font-mono text-rose">₹{{ number_format($total_other_expenses, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <span>Green Leaf ERP — Finance Monthly Reports</span>
        <span>Count: {{ count($detailed_rows) }} records</span>
    </div>
</body>
</html>
