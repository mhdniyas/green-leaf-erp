<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cash Flow Day Journal {{ $date->format('d M Y') }}</title>
    <style>
        body {
            color: #0f172a;
            font-family: Arial, sans-serif;
            margin: 32px;
        }

        h1,
        p {
            margin: 0;
        }

        .meta {
            color: #475569;
            font-size: 12px;
            margin-top: 8px;
        }

        .summary {
            display: flex;
            gap: 16px;
            margin: 20px 0 24px;
        }

        .summary-card {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            min-width: 160px;
            padding: 12px 16px;
        }

        .summary-card span {
            color: #64748b;
            display: block;
            font-size: 11px;
            letter-spacing: 0.12em;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        table {
            border-collapse: collapse;
            font-size: 12px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
        }

        th {
            background: #e2e8f0;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .right {
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Cash Flow Day Journal</h1>
    <p class="meta">{{ $date->format('d M Y') }} journal details</p>

    <div class="summary">
        <div class="summary-card">
            <span>Total In</span>
            <strong>Rs. {{ number_format((float) $rows->where('direction', 'IN')->sum('amount'), 2) }}</strong>
        </div>
        <div class="summary-card">
            <span>Total Out</span>
            <strong>Rs. {{ number_format((float) $rows->where('direction', 'OUT')->sum('amount'), 2) }}</strong>
        </div>
        <div class="summary-card">
            <span>Entries</span>
            <strong>{{ $rows->count() }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Amount</th>
                <th>Debit / Credit</th>
                <th>Journal</th>
                <th>Remarks</th>
                <th>Category</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="right">Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                    <td>{{ $row['direction'] }}</td>
                    <td>{{ $row['journal'] }}</td>
                    <td>{{ $row['remarks'] ?: 'No remarks' }}</td>
                    <td>{{ $row['category'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No journal rows are available for {{ $date->format('d M Y') }}.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
