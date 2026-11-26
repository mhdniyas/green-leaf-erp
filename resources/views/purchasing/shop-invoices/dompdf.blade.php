@php
    $formatUnit = fn (?string $unit): string => \App\Models\ProductUnit::normalizeUnit($unit) === 'piece'
        ? 'PCE'
        : strtoupper(str_replace('_', ' ', \App\Models\ProductUnit::normalizeUnit($unit)));
    $money = fn (float|int|string|null $amount): string => 'Rs. '.number_format((float) $amount, 2);
    $lineQuantity = function ($item): float {
        return round((float) ($item->delivered_price_quantity ?: $item->price_quantity ?: $item->delivered_qty ?: $item->approved_qty), 4);
    };
    $finalQuantity = function ($item) use ($lineQuantity): float {
        return round((float) ($item->orderItem?->shop_reported_received_qty ?: $item->delivered_qty ?: $item->orderItem?->delivered_qty ?: $lineQuantity($item)), 4);
    };
    $lineAmount = fn ($item): float => round((float) ($item->final_line_total ?: $item->line_subtotal), 2);
    $items = $items ?? $invoice->items;
    $discountTotal = $discountTotal ?? round((float) $invoice->discount_total, 2);
    $subtotal = $subtotal ?? round((float) $invoice->subtotal, 2);
    $finalTotal = $finalTotal ?? round((float) $invoice->final_total, 2);
    $bill = $finalBill ?? $invoice->finalBill();
    $appliedAdjustments = $invoice->appliedAdjustments();
    $isFinalized = $invoice->isFinalized();
    $invoiceStatus = $isFinalized ? 'FINALIZED' : strtoupper(str_replace('_', ' ', (string) $invoice->status));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Shop Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 14mm 12mm 14mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #0f172a;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .bill-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px 24px;
            background: #ffffff;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px dashed #e2e8f0;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }

        .header-title-tag {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            margin: 0 0 4px 0;
        }

        .shop-name {
            font-size: 20px;
            font-weight: 900;
            color: #020617;
            margin: 0 0 3px 0;
        }

        .shop-code {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin: 0;
        }

        .badge-finalized {
            display: inline-block;
            border: 1px solid #a7f3d0;
            background-color: #ecfdf5;
            color: #047857;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 4px 10px;
            border-radius: 9999px;
        }

        .badge-status {
            display: inline-block;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            color: #334155;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 4px 10px;
            border-radius: 9999px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .meta-label {
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
            margin: 0 0 3px 0;
        }

        .meta-value {
            font-size: 11px;
            font-weight: 900;
            color: #020617;
            margin: 0;
        }

        .finalized-box {
            margin-top: 12px;
            border: 1px solid #a7f3d0;
            background-color: #ecfdf5;
            border-radius: 6px;
            padding: 10px 12px;
            color: #064e3b;
        }

        .finalized-box-title {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin: 0 0 6px 0;
        }

        .items-table-container {
            margin-top: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .items-table th {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 7px 10px;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
        }

        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10.5px;
            vertical-align: middle;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .product-name {
            font-weight: 900;
            color: #020617;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-section {
            margin-top: 14px;
            border-top: 2px dashed #e2e8f0;
            padding-top: 12px;
            page-break-inside: avoid;
        }

        .totals-table {
            width: 320px;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 3px 0;
            font-size: 11px;
            font-weight: bold;
            color: #334155;
        }

        .totals-table .final-total-row td {
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            font-weight: 900;
            color: #020617;
        }

        .section-header {
            margin-top: 14px;
            margin-bottom: 5px;
        }

        .section-title {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #020617;
            margin: 0;
        }

        .section-subtitle {
            font-size: 8px;
            color: #64748b;
            margin: 1px 0 0 0;
        }

        .adjustments-section {
            margin-top: 14px;
            page-break-inside: avoid;
        }

        .adjustments-table th {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
        }

        .adjustments-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10px;
            vertical-align: middle;
        }

        .adjustments-table tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="bill-card">
        {{-- Header Section --}}
        <table class="header-table">
            <tr>
                <td style="vertical-align: top;">
                    <div class="header-title-tag">Shop Bill</div>
                    <div class="shop-name">{{ $invoice->shop?->name ?? 'Shop' }}</div>
                    <div class="shop-code">{{ $invoice->shop?->code ?? $invoice->shop?->warehouse_tag }}</div>
                </td>
                <td style="vertical-align: top; text-align: right;">
                    @if ($isFinalized)
                        <div class="badge-finalized">FINALIZED BILL</div>
                    @else
                        <div class="badge-status">{{ $invoiceStatus }}</div>
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <table class="meta-table">
                        <tr>
                            <td style="width: 33.3%;">
                                <div class="meta-label">Invoice Number</div>
                                <div class="meta-value">{{ $invoice->invoice_number }}</div>
                            </td>
                            <td style="width: 33.3%;">
                                <div class="meta-label">Date</div>
                                <div class="meta-value">{{ $invoice->business_date->format('d M Y') }}</div>
                            </td>
                            <td style="width: 33.3%;">
                                <div class="meta-label">Status</div>
                                <div class="meta-value">{{ $isFinalized ? 'Finalized' : 'Open' }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            @if ($isFinalized)
                <tr>
                    <td colspan="2">
                        <div class="finalized-box">
                            <div class="finalized-box-title">FINALIZED BILL</div>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 33.3%;">
                                        <div style="font-size: 8px; font-weight: 900; text-transform: uppercase; color: #047857;">Finalized By</div>
                                        <div style="font-size: 10px; font-weight: 900; margin-top: 2px;">{{ $invoice->finalizedBy?->name ?? 'System' }}</div>
                                    </td>
                                    <td style="width: 33.3%;">
                                        <div style="font-size: 8px; font-weight: 900; text-transform: uppercase; color: #047857;">Finalized At</div>
                                        <div style="font-size: 10px; font-weight: 900; margin-top: 2px;">{{ $invoice->finalized_at?->format('d M Y, h:i A') ?? 'Recorded' }}</div>
                                    </td>
                                    <td style="width: 33.3%;">
                                        <div style="font-size: 8px; font-weight: 900; text-transform: uppercase; color: #047857;">{{ $bill->hasAdjustments ? 'Net Payable' : 'Final Amount' }}</div>
                                        <div style="font-size: 10px; font-weight: 900; margin-top: 2px;">{{ $money($bill->hasAdjustments ? $bill->finalNetPayable : $finalTotal) }}</div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            @endif
        </table>

        {{-- Products Table --}}
        <div class="items-table-container">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 35px;">Sl No</th>
                        <th>Product</th>
                        <th class="text-right" style="width: 60px;">Qty</th>
                        <th style="width: 50px;">Unit</th>
                        <th class="text-right" style="width: 75px;">Price</th>
                        <th class="text-right" style="width: 85px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $qty = $lineQuantity($item);
                            $displayQty = rtrim(rtrim(number_format($qty, 4), '0'), '.');
                            $price = round((float) $item->unit_price, 2);
                            $productName = $item->product?->name ?? $item->product_name;
                            $itemGrade = (string) ($item->product_grade ?? 'A');
                            $showGrade = $itemGrade !== 'A' || $items->where('product_id', $item->product_id)->count() > 1;
                            $displayProductName = $showGrade ? "{$productName} — Grade {$itemGrade}" : $productName;
                            $lineTotal = $lineAmount($item);
                        @endphp
                        <tr>
                            <td style="color: #64748b; font-weight: bold;">{{ $loop->iteration }}</td>
                            <td>
                                <div class="product-name">{{ $displayProductName }}</div>
                            </td>
                            <td class="text-right" style="font-weight: bold; color: #1e293b;">{{ $displayQty }}</td>
                            <td style="font-weight: bold; color: #475569;">{{ $formatUnit($item->unit) }}</td>
                            <td class="text-right" style="font-weight: bold; color: #1e293b;">{{ $money($price) }}</td>
                            <td class="text-right" style="font-weight: 900; color: #020617;">{{ $money($lineTotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Applied Credit & Debit Notes Section --}}
        @if ($appliedAdjustments->isNotEmpty())
            <div class="adjustments-section">
                <div class="section-header">
                    <div class="section-title">Applied Credit &amp; Debit Notes</div>
                    <div class="section-subtitle">Audited adjustments and credit notes applied to this bill</div>
                </div>
                <div class="items-table-container" style="margin-top: 4px;">
                    <table class="items-table adjustments-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">Sl No</th>
                                <th style="width: 140px;">Credit / Debit Note #</th>
                                <th style="width: 75px;">Date</th>
                                <th style="width: 80px;">Type</th>
                                <th>Description / Reason</th>
                                <th class="text-right" style="width: 85px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($appliedAdjustments as $adj)
                                <tr>
                                    <td style="color: #64748b; font-weight: bold;">{{ $loop->iteration }}</td>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 900; color: #020617;">{{ $adj['number'] }}</span>
                                    </td>
                                    <td style="color: #475569; font-weight: bold;">{{ $adj['date'] }}</td>
                                    <td>
                                        @if ($adj['is_debit'])
                                            <span style="color: #b45309; font-weight: 900;">Debit Note</span>
                                        @else
                                            <span style="color: #047857; font-weight: 900;">Credit Note</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="font-weight: 900; color: #020617;">{{ $adj['reason'] }}</div>
                                        @if (! empty($adj['notes']) && $adj['notes'] !== $adj['reason'])
                                            <div style="font-size: 8.5px; color: #64748b; font-style: italic; margin-top: 1px;">{{ $adj['notes'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right" style="font-weight: 900; color: {{ $adj['is_debit'] ? '#b45309' : '#047857' }};">
                                        {{ $adj['is_debit'] ? '+ ' : '- ' }}{{ $money($adj['amount']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Totals Section --}}
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">{{ $money($subtotal) }}</td>
                </tr>
                <tr>
                    <td>Discount</td>
                    <td class="text-right">{{ $money($discountTotal) }}</td>
                </tr>
                @if ($bill->hasAdjustments)
                    <tr style="border-top: 1px solid #f1f5f9;">
                        <td>Original Invoice Amount</td>
                        <td class="text-right">{{ $money($bill->originalInvoiceTotal) }}</td>
                    </tr>
                    @if ($bill->finalizedCreditTotal > 0.0001)
                        <tr style="color: #047857;">
                            <td>Credit Note Deductions</td>
                            <td class="text-right">- {{ $money($bill->finalizedCreditTotal) }}</td>
                        </tr>
                    @endif
                    @if ($bill->finalizedDebitTotal > 0.0001)
                        <tr style="color: #b45309;">
                            <td>Debit Note Additions</td>
                            <td class="text-right">+ {{ $money($bill->finalizedDebitTotal) }}</td>
                        </tr>
                    @endif
                @endif
                <tr class="final-total-row">
                    <td>Net Payable</td>
                    <td class="text-right">{{ $money($bill->hasAdjustments ? $bill->finalNetPayable : $finalTotal) }}</td>
                </tr>
                <tr>
                    <td style="color: #047857;">Paid Amount</td>
                    <td class="text-right" style="color: #047857;">{{ $money($bill->amountPaid) }}</td>
                </tr>
                <tr>
                    <td style="font-weight: 900; color: {{ $bill->remainingBalance > 0.0001 ? '#b91c1c' : '#047857' }};">Outstanding Balance</td>
                    <td class="text-right" style="font-weight: 900; color: {{ $bill->remainingBalance > 0.0001 ? '#b91c1c' : '#047857' }};">{{ $money($bill->remainingBalance) }}</td>
                </tr>
                @if ($bill->overpaymentCredit > 0.0001)
                    <tr style="color: #047857;">
                        <td>Overpayment Credit</td>
                        <td class="text-right">{{ $money($bill->overpaymentCredit) }}</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>
</body>
</html>
