<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ledger Statement ({{ $startDate }} to {{ $endDate }})</title>
    <!-- Outfit Font for Premium Feel -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 40px;
            font-size: 13px;
            line-height: 1.5;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo-section h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.05em;
            color: #059669; /* Emerald 600 */
        }

        .logo-section p {
            margin: 4px 0 0 0;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.1em;
            color: #64748b;
        }

        .meta-section {
            text-align: right;
        }

        .meta-section h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }

        .meta-section p {
            margin: 4px 0;
            color: #64748b;
        }

        .grid-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }

        .card {
            background-color: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 16px;
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .card p {
            margin: 4px 0;
            font-weight: 600;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        tr:nth-child(even) td {
            background-color: #fafbfc;
        }

        .text-right {
            text-align: right;
        }

        .text-rose {
            color: #b91c1c;
        }

        .text-emerald {
            color: #047857;
        }

        .footer {
            margin-top: 60px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 11px;
        }

        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>GREEN LEAF ERP</h1>
            <p>Procurement & Distribution Network</p>
        </div>
        <div class="meta-section">
            <h2>Ledger Account Statement</h2>
            <p><strong>Period:</strong> {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
            <p><strong>Generated At:</strong> {{ now()->format('d M Y h:i A') }}</p>
        </div>
    </div>

    <div class="grid-details">
        <div class="card">
            <h3>Opening Balance</h3>
            <p>₹{{ number_format($ledgerData['opening_balance'], 2) }}</p>
        </div>
        <div class="card">
            <h3>Closing Balance</h3>
            <p>₹{{ number_format($ledgerData['closing_balance'], 2) }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 40%;">Description</th>
                <th style="width: 15%; text-align: right;">Debit (Outflow)</th>
                <th style="width: 15%; text-align: right;">Credit (Inflow)</th>
                <th style="width: 15%; text-align: right;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ledgerData['lines'] as $line)
                <tr>
                    <td>{{ $line->date ? $line->date->format('d M Y') : '—' }}</td>
                    <td style="font-weight: 600;">{{ $line->description }}</td>
                    <td class="text-right text-rose">
                        {{ $line->debit !== null ? '₹' . number_format($line->debit, 2) : '—' }}
                    </td>
                    <td class="text-right text-emerald">
                        {{ $line->credit !== null ? '₹' . number_format($line->credit, 2) : '—' }}
                    </td>
                    <td class="text-right" style="font-weight: bold; color: #0f172a;">
                        ₹{{ number_format($line->balance, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div>Generated automatically by Green Leaf ERP System.</div>
        <div>Page 1 of 1</div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
        }
    </script>
</body>
</html>
