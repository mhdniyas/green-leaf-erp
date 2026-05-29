<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Requisition {{ $order->order_number }}</title>
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

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-submitted { background-color: #fef3c7; color: #92400e; }
        .badge-approved { background-color: #d1fae5; color: #065f46; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-update_requested { background-color: #e0f2fe; color: #075985; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; }

        .footer {
            margin-top: 60px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 11px;
        }

        .signatures {
            margin-top: 80px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
        }

        .sig-line {
            border-top: 1px solid #cbd5e1;
            text-align: center;
            padding-top: 8px;
            color: #475569;
            font-weight: 600;
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
            <h2>Requisition Statement</h2>
            <p><strong>ID:</strong> {{ $order->order_number }}</p>
            <p><strong>Date:</strong> {{ $order->created_at->format('d M Y h:i A') }}</p>
        </div>
    </div>

    <div class="grid-details">
        <div class="card">
            <h3>Requesting Outlet</h3>
            <p>{{ $order->shop ? $order->shop->name : 'CASIO HYPERMARKET' }}</p>
            <p style="font-weight: normal; color: #64748b; font-size: 11px;">
                Code: {{ $order->shop ? $order->shop->code : 'SHOP_001' }}
            </p>
        </div>
        <div class="card">
            <h3>Delivery Target</h3>
            <p>Tomorrow ({{ \Carbon\Carbon::parse($order->business_date)->format('d M Y') }})</p>
            <p style="font-weight: normal; color: #64748b;">
                Status: <span class="badge badge-{{ $order->state }}">{{ $order->state }}</span>
            </p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">#</th>
                <th style="width: 25%;">SKU</th>
                <th style="width: 35%;">Product Name</th>
                <th style="width: 15%; text-align: right;">Requested Qty</th>
                <th style="width: 15%; text-align: right;">Approved Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: 600;">{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="text-right" style="font-weight: 600;">{{ $item->requested_qty }} {{ $item->unit }}</td>
                    <td class="text-right" style="font-weight: 800; color: #0f172a;">
                        {{ $item->approved_qty !== null ? $item->approved_qty . ' ' . $item->unit : 'Pending' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->update_reason)
        <div class="card" style="margin-top: -20px; margin-bottom: 40px;">
            <h3>Update Request Comment</h3>
            <p style="font-weight: normal; font-style: italic; color: #475569;">"{{ $order->update_reason }}"</p>
        </div>
    @endif

    <div class="signatures">
        <div class="sig-line">Requested By (Shop Representative)</div>
        <div class="sig-line">Approved By (Purchase Manager)</div>
    </div>

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
