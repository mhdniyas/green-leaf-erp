<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report — {{ $shop->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 15px;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        .summary-box {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .summary-box td {
            padding: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .summary-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .summary-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            padding: 6px 8px;
            border: 1px solid #0f172a;
        }
        .table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .bg-totals {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .footer {
            margin-top: 25px;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $shop->name }} — Sales Report</div>
        <div class="subtitle">Period: {{ $report['period']['label'] }} ({{ $report['period']['formatted_range'] }})</div>
    </div>

    <table class="summary-box">
        <tr>
            <td>
                <div class="summary-label">Total Sales</div>
                <div class="summary-value" style="color: #047857;">₹{{ number_format($report['summary']['total_sales'], 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Rent</div>
                <div class="summary-value" style="color: #b91c1c;">₹{{ number_format($report['summary']['total_rent'], 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Cash Purchase</div>
                <div class="summary-value" style="color: #c2410c;">₹{{ number_format($report['summary']['total_purchase'], 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Other Expense</div>
                <div class="summary-value" style="color: #be123c;">₹{{ number_format($report['summary']['total_other_expense'], 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Net Balance</div>
                <div class="summary-value" style="color: #1d4ed8;">₹{{ number_format($report['summary']['net_total'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th class="text-right">Sales (₹)</th>
                <th class="text-right">Rent (₹)</th>
                <th class="text-right">Cash Purchase (₹)</th>
                <th class="text-right">Other Expense (₹)</th>
                <th class="text-right">Total Expenses (₹)</th>
                <th class="text-right">Net Balance (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['daily_rows'] as $row)
                <tr>
                    <td class="font-bold">{{ $row['formatted_date'] }}</td>
                    <td>{{ $row['day_name'] }}</td>
                    <td class="text-right" style="color: #047857;">{{ number_format($row['sales'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['rent'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['purchase'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['other_expense'], 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['total_expenses'], 2) }}</td>
                    <td class="text-right font-bold" style="color: {{ $row['net_balance'] >= 0 ? '#1d4ed8' : '#b91c1c' }};">
                        {{ number_format($row['net_balance'], 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-totals">
                <td colspan="2" class="font-bold">TOTALS</td>
                <td class="text-right font-bold" style="color: #047857;">{{ number_format($report['summary']['total_sales'], 2) }}</td>
                <td class="text-right font-bold">{{ number_format($report['summary']['total_rent'], 2) }}</td>
                <td class="text-right font-bold">{{ number_format($report['summary']['total_purchase'], 2) }}</td>
                <td class="text-right font-bold">{{ number_format($report['summary']['total_other_expense'], 2) }}</td>
                <td class="text-right font-bold">{{ number_format($report['summary']['total_expenses'], 2) }}</td>
                <td class="text-right font-bold" style="color: {{ $report['summary']['net_total'] >= 0 ? '#1d4ed8' : '#b91c1c' }};">
                    {{ number_format($report['summary']['net_total'], 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generated on {{ now()->format('d M Y, h:i A') }} • Green Leaf ERP Cashbook Engine
    </div>
</body>
</html>
