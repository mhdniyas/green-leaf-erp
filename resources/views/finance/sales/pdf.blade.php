<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Reports PDF</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, h2 { margin: 0 0 8px; }
        p { margin: 0 0 12px; }
        .meta { margin-bottom: 20px; color: #475569; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; }
        .label { font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
        .value { font-size: 20px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; font-size: 12px; text-align: left; }
        th { background: #e2e8f0; }
        .num { text-align: right; }
    </style>
</head>
<body>
    @php
        $sales = $finance['sales'];
        $summary = $sales['summary'];
    @endphp
    <h1>Sales Reports</h1>
    <p class="meta">Period: {{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</p>

    <div class="grid">
        <div class="card"><div class="label">Shops</div><div class="value">{{ number_format($summary['count']) }}</div></div>
        <div class="card"><div class="label">Credit</div><div class="value">Rs. {{ number_format($summary['total_amount'], 2) }}</div></div>
        <div class="card"><div class="label">Debit</div><div class="value">Rs. {{ number_format($summary['paid_amount'], 2) }}</div></div>
        <div class="card"><div class="label">Balance</div><div class="value">Rs. {{ number_format($summary['outstanding_amount'], 2) }}</div></div>
    </div>

    <h2>Daily Credit and Debit</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Lead Shop</th>
                <th class="num">Credit</th>
                <th class="num">Debit</th>
                <th class="num">Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sales['daily_rows'] as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                    <td>{{ $row['lead_label'] }}</td>
                    <td class="num">Rs. {{ number_format($row['credit_amount'], 2) }}</td>
                    <td class="num">Rs. {{ number_format($row['debit_amount'], 2) }}</td>
                    <td class="num">Rs. {{ number_format($row['balance_amount'], 2) }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
