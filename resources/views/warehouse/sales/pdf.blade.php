@php
    $payment = $sale->primaryPayment();
    $paymentMethod = strtolower((string) ($payment?->payment_method ?? 'cash'));
    $subtotal = round((float) $sale->subtotal, 2);
    $discount = round((float) $sale->discount, 2);
    $totalAmount = round((float) $sale->total_amount, 2);
    $paidAmount = $paymentMethod === 'credit' ? 0.0 : round((float) $sale->paid_amount, 2);
    $balanceAmount = max(0.0, round($totalAmount - $paidAmount, 2));

    $statusText = match (true) {
        $sale->isCancelled() => 'CANCELLED',
        $paymentMethod === 'credit' => 'CREDIT',
        $balanceAmount <= 0 => 'PAID',
        default => strtoupper((string) $sale->status),
    };

    $statusBg = match ($statusText) {
        'PAID' => '#059669',
        'CANCELLED' => '#e11d48',
        default => '#d97706',
    };

    $paymentMeta = match ($paymentMethod) {
        'cash' => [
            'label' => 'Cash',
            'desc' => $payment?->money_holder_type === 'user'
                ? 'Held by '.($payment->moneyHolderUser?->name ?? 'Staff')
                : 'Company Direct',
            'border' => '#10b981',
            'bg' => '#ecfdf5',
            'color' => '#064e3b',
        ],
        'upi' => ['label' => 'UPI', 'desc' => 'UPI Transfer', 'border' => '#10b981', 'bg' => '#ecfdf5', 'color' => '#064e3b'],
        'bank' => ['label' => 'Bank', 'desc' => 'Bank Transfer', 'border' => '#10b981', 'bg' => '#ecfdf5', 'color' => '#064e3b'],
        'card' => ['label' => 'Card', 'desc' => 'Card Payment', 'border' => '#10b981', 'bg' => '#ecfdf5', 'color' => '#064e3b'],
        default => ['label' => 'Credit', 'desc' => 'Customer Due', 'border' => '#f59e0b', 'bg' => '#fffbeb', 'color' => '#78350f'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bill Invoice - {{ $sale->invoice_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 14mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #020617;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .card {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 20px 24px;
            background: #ffffff;
        }
        .header {
            border-bottom: 1px dashed #94a3b8;
            padding-bottom: 12px;
            text-align: center;
            position: relative;
        }
        .status-pill {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.2px;
            color: #ffffff;
            background: {{ $statusBg }};
            margin-bottom: 6px;
        }
        .header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #020617;
        }
        .header .brand {
            margin: 4px 0 0;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            color: #020617;
        }
        .header .sub {
            margin: 2px 0 0;
            font-size: 10px;
            color: #475569;
            font-weight: bold;
        }
        .section-dashed {
            border-bottom: 1px dashed #94a3b8;
            padding: 10px 0;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        .meta-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            margin: 0 0 2px;
        }
        .bill-no {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 13px;
            font-weight: bold;
            color: #020617;
            margin: 0;
        }
        .customer-name {
            font-size: 14px;
            font-weight: bold;
            color: #020617;
            margin: 3px 0 2px;
        }
        .muted {
            color: #475569;
            font-size: 10px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        table.items thead th {
            border-bottom: 1px dashed #94a3b8;
            padding: 5px 4px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #020617;
        }
        table.items tbody td {
            border-bottom: 1px solid #f1f5f9;
            padding: 7px 4px;
            vertical-align: top;
        }
        .num { text-align: right !important; }
        .summary-wrap {
            width: 56%;
            margin-left: auto;
            border-bottom: 1px dashed #94a3b8;
            padding: 10px 0;
            font-size: 10.5px;
        }
        table.summary {
            width: 100%;
            border-collapse: collapse;
        }
        table.summary td {
            padding: 3px 0;
            font-weight: bold;
        }
        .total-row td {
            font-size: 13px;
            font-weight: bold;
            padding: 6px 0;
            color: #020617;
        }
        .paid-box {
            background: #ecfdf5;
            color: #065f46;
            border-radius: 6px;
            padding: 5px 8px;
            margin-top: 4px;
        }
        .balance-box {
            background: #fffbeb;
            color: #78350f;
            border-radius: 6px;
            padding: 5px 8px;
            margin-top: 4px;
        }
        .pay-box {
            margin-top: 6px;
            border: 1px solid {{ $paymentMeta['border'] }};
            background: {{ $paymentMeta['bg'] }};
            color: {{ $paymentMeta['color'] }};
            border-radius: 8px;
            padding: 8px 12px;
        }
        .footer {
            margin-top: 14px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        <!-- Header -->
        <div class="header">
            <div class="status-pill">{{ $statusText }}</div>
            <h2>BILL INVOICE</h2>
            <p class="brand">GREEN LEAF</p>
            <p class="sub">Fresh Produce &amp; Supplies Accounting</p>
        </div>

        <!-- Bill Details Grid -->
        <div class="section-dashed">
            <table class="meta-table">
                <tr>
                    <td style="width: 55%; vertical-align: top;">
                        <p class="meta-label">Bill No</p>
                        <p class="bill-no">{{ $sale->invoice_number }}</p>
                        <p class="muted" style="margin: 3px 0 0;">Warehouse: <strong>{{ $sale->warehouse?->name ?? 'N/A' }}</strong></p>
                    </td>
                    <td style="width: 45%; vertical-align: top; text-align: right;">
                        <p style="margin: 0;">Date: <strong>{{ $sale->business_date?->format('d M Y') ?? now()->format('d M Y') }}</strong></p>
                        <p style="margin: 3px 0 0;">Sold By: <strong>{{ $sale->soldBy?->name ?? 'N/A' }}</strong></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Customer Block -->
        <div class="section-dashed">
            <p class="meta-label">CUSTOMER</p>
            <p class="customer-name">
                @if($sale->isShopSale() && $sale->shop)
                    {{ $sale->shop->name }}
                @else
                    {{ $sale->customerDisplayName() }}
                @endif
            </p>
            <p class="muted" style="margin: 0;">
                @if($sale->isShopSale())
                    Shop Sale
                @elseif($sale->isWalkingCustomer())
                    Walking Customer
                @else
                    Cash Sales
                @endif
                @if($sale->customer_phone_snapshot)
                    &bull; {{ $sale->customer_phone_snapshot }}
                @endif
            </p>
        </div>

        <!-- Items Table -->
        <div class="section-dashed">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 7%;">SN</th>
                        <th style="width: 43%;">ITEM</th>
                        <th style="width: 15%;" class="num">QTY</th>
                        <th style="width: 16%;" class="num">PRICE</th>
                        <th style="width: 19%;" class="num">AMT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sale->items as $item)
                        <tr>
                            <td style="color: #475569; font-weight: bold;">{{ $loop->iteration }}</td>
                            <td>
                                <strong style="color: #020617;">{{ $item->product?->name ?? 'Item' }}</strong>
                                <div class="muted">{{ $item->entered_unit ?: ($item->product?->unit ?? 'kg') }}</div>
                            </td>
                            <td class="num" style="font-weight: bold;">{{ number_format((float) $item->entered_qty, 2) }}</td>
                            <td class="num" style="color: #334155;">Rs. {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="num" style="font-weight: bold; color: #020617;">Rs. {{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 12px; color: #64748b;">No items recorded on bill.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Subtotal / Summary Block -->
        <div class="summary-wrap">
            <table class="summary">
                <tr>
                    <td>Subtotal</td>
                    <td class="num">Rs. {{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td style="color: #475569;">Discount</td>
                    <td class="num" style="color: #475569;">Rs. {{ number_format($discount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td style="text-transform: uppercase;">TOTAL</td>
                    <td class="num">Rs. {{ number_format($totalAmount, 2) }}</td>
                </tr>
            </table>
            <div class="paid-box">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="font-weight: bold;">Paid</td>
                        <td class="num" style="font-weight: bold;">Rs. {{ number_format($paidAmount, 2) }}</td>
                    </tr>
                </table>
            </div>
            <div class="balance-box">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="font-weight: bold;">Balance</td>
                        <td class="num" style="font-weight: bold;">Rs. {{ number_format($balanceAmount, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payment Method -->
        <div style="padding-top: 12px;">
            <p class="meta-label">PAYMENT METHOD</p>
            <div class="pay-box">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="font-weight: bold; font-size: 12px;">{{ $paymentMeta['label'] }}</td>
                        <td class="num" style="font-weight: bold; font-size: 10px;">{{ $paymentMeta['desc'] }}</td>
                    </tr>
                </table>
            </div>
            @if($payment?->reference)
                <p class="muted" style="margin: 6px 0 0;">Reference: <strong>{{ $payment->reference }}</strong></p>
            @endif
        </div>

        @if($sale->isCancelled())
            <div style="margin-top: 10px; padding: 8px 10px; border-radius: 6px; background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; font-size: 10px;">
                <strong>Cancelled</strong> on {{ $sale->cancelled_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}
                by {{ $sale->cancelledBy?->name ?? 'User' }}.
                @if($sale->cancel_reason)
                    Reason: {{ $sale->cancel_reason }}
                @endif
            </div>
        @endif

        <div class="footer">
            Green Leaf &bull; Fresh Produce &amp; Supplies Accounting &bull; {{ now()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}
        </div>
    </div>
</body>
</html>
