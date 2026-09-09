<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchaser Purchase & Expense Report</title>
    <style>
        @page {
            margin: 20px 25px;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 9px;
            line-height: 1.3;
        }
        .header {
            border-bottom: 2px solid #047857;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-title {
            font-size: 14px;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .report-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .meta-bar {
            margin-top: 6px;
            font-size: 9px;
            color: #475569;
        }
        .meta-item {
            display: inline-block;
            margin-right: 15px;
        }
        .meta-label {
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 12px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            border: none;
            padding: 2px 4px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.data-table th, table.data-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 5px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            color: #475569;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
        .font-mono {
            font-family: monospace;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
        .badge-purchase {
            color: #047857;
            font-weight: bold;
        }
        .badge-expense {
            color: #b45309;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-title">Green Leaf ERP</div>
        <div class="report-title">Purchaser Purchase & Expense Report</div>
        <div class="meta-bar">
            <span class="meta-item"><span class="meta-label">Period:</span> {{ $summary['date_from_formatted'] }} - {{ $summary['date_to_formatted'] }}</span>
            <span class="meta-item"><span class="meta-label">Purchaser:</span> {{ $purchaserName }}</span>
            <span class="meta-item"><span class="meta-label">Supplier:</span> {{ $supplierName }}</span>
        </div>
    </div>

    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td><strong>Total Purchase:</strong> ₹{{ number_format($summary['total_purchase'], 2) }} ({{ $summary['bills_count'] }} Bills)</td>
                <td><strong>Total Expenses:</strong> ₹{{ number_format($summary['total_expenses'], 2) }} ({{ $summary['expenses_count'] }} Expenses)</td>
                <td><strong>Combined Total:</strong> ₹{{ number_format($summary['combined_total'], 2) }}</td>
                <td><strong>Total Entries:</strong> {{ $summary['total_entries'] }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">Date</th>
                <th style="width: 8%;">Type</th>
                <th style="width: 16%;">Purchaser</th>
                <th style="width: 16%;">Supplier</th>
                <th style="width: 14%;">Reference</th>
                <th style="width: 18%;">Details / Type</th>
                <th style="width: 9%;" class="text-right">Purchase (₹)</th>
                <th style="width: 9%;" class="text-right">Expense (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="font-mono">{{ $row['date_formatted'] }}</td>
                    <td class="{{ $row['type'] === 'purchase' ? 'badge-purchase' : 'badge-expense' }}">
                        {{ $row['type_label'] }}
                    </td>
                    <td class="font-bold">{{ $row['purchaser_name'] }}</td>
                    <td>{{ $row['supplier_name'] }}</td>
                    <td class="font-mono">{{ $row['reference'] }}</td>
                    <td>
                        @if($row['type'] === 'expense')
                            <strong>{{ $row['expense_type'] }}:</strong>
                        @endif
                        {{ $row['details'] }}
                    </td>
                    <td class="text-right font-mono">
                        {{ $row['purchase_amount'] > 0 ? number_format($row['purchase_amount'], 2) : '-' }}
                    </td>
                    <td class="text-right font-mono" style="color: #b45309;">
                        {{ $row['expense_amount'] > 0 ? number_format($row['expense_amount'], 2) : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 15px;">No records found.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rows) > 0)
            <tfoot>
                <tr style="font-weight: bold; background-color: #f8fafc;">
                    <td colspan="6" class="text-right" style="text-transform: uppercase;">Total</td>
                    <td class="text-right font-mono">₹{{ number_format($summary['total_purchase'], 2) }}</td>
                    <td class="text-right font-mono" style="color: #b45309;">₹{{ number_format($summary['total_expenses'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        Generated on {{ $generatedAt->format('d M Y, h:i A') }} | Green Leaf ERP
    </div>
</body>
</html>
