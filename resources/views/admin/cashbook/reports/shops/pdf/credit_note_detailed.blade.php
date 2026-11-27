<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shop Credit Notes — Detailed Report</title>
    <style>
        @page {
            margin: 22px 24px 32px 24px;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 8px;
            line-height: 1.35;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .w-full { width: 100%; border-collapse: collapse; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .mono { font-family: DejaVu Sans Mono, DejaVu Sans, monospace; }

        .report-header {
            border-bottom: 2px solid #059669;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .brand-title {
            font-size: 13px;
            font-weight: bold;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .meta-bar {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px solid #e2e8f0;
            font-size: 7.5px;
            color: #475569;
        }
        .meta-bar span { margin-right: 14px; }

        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .kpi-table td {
            width: 25%;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: center;
        }
        .kpi-label {
            font-size: 7px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .kpi-value {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }

        .date-banner {
            background-color: #065f46;
            color: #ffffff;
            padding: 5px 8px;
            font-size: 8.5px;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 6px;
        }
        .shop-banner {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 4px 8px;
            font-size: 8px;
            font-weight: bold;
            margin-top: 6px;
            margin-bottom: 4px;
        }
        .invoice-block {
            border: 1px solid #cbd5e1;
            margin-bottom: 8px;
            padding: 6px;
        }
        .invoice-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 6px;
            margin-bottom: 5px;
            font-size: 8px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 5px;
        }
        .data-table thead { display: table-header-group; }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 7px;
            text-transform: uppercase;
            padding: 4px 5px;
            border: 1px solid #0f172a;
            text-align: left;
        }
        .data-table th.text-right { text-align: right; }
        .data-table td {
            padding: 3.5px 5px;
            border: 1px solid #cbd5e1;
            font-size: 7.5px;
            vertical-align: top;
        }
        .reconciliation-box {
            width: 55%;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 4px;
            page-break-inside: avoid;
        }
        .reconciliation-box td {
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            font-size: 7.5px;
        }
        .reconciliation-box tr.net-row td {
            background-color: #ecfdf5;
            font-weight: bold;
            color: #065f46;
        }
        .subtotal-bar {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            font-size: 8px;
            font-weight: bold;
            margin-top: 4px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .grand-total-bar {
            background-color: #0f172a;
            color: #ffffff;
            padding: 7px 10px;
            font-size: 9px;
            font-weight: bold;
            margin-top: 12px;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/greenleaf-logo.png');
        $logoDataUri = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
        $overview = $report['overview'];
    @endphp

    <div class="report-header">
        <table class="w-full">
            <tr>
                <td style="width: 65%; vertical-align: middle;">
                    <table>
                        <tr>
                            @if ($logoDataUri)
                                <td style="padding-right: 8px; vertical-align: middle;">
                                    <img src="{{ $logoDataUri }}" alt="Green Leaf" style="height: 28px;">
                                </td>
                            @endif
                            <td style="vertical-align: middle;">
                                <div class="brand-title">Green Leaf ERP</div>
                                <div class="report-title">Shop Credit Note Report — Detailed (Shop &amp; Bill Wise)</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="text-right" style="width: 35%; vertical-align: middle; font-size: 7.5px; color: #64748b;">
                    <div><strong>Generated:</strong> {{ now()->format('d M Y, h:i A') }}</div>
                    <div><strong>Timezone:</strong> {{ config('app.timezone') }}</div>
                </td>
            </tr>
        </table>
        <div class="meta-bar">
            <span><strong>Reporting Period:</strong> {{ $overview['period_label'] }}</span>
            <span><strong>Shop Filter:</strong> {{ $overview['shop_name'] }}</span>
            <span><strong>Status Scope:</strong> Approved Credit Notes Only</span>
        </div>
    </div>

    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-label">Total Credit Notes</div>
                <div class="kpi-value">{{ $overview['total_credit_notes'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Credit Amount</div>
                <div class="kpi-value" style="color: #059669;">₹{{ number_format((float) $overview['total_credit_amount'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Affected Shops</div>
                <div class="kpi-value">{{ $overview['total_affected_shops'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Affected Invoices</div>
                <div class="kpi-value">{{ $overview['total_affected_invoices'] }}</div>
            </td>
        </tr>
    </table>

    @forelse ($report['date_groups'] as $dateGroup)
        <div class="date-banner">
            <table class="w-full" style="color: #ffffff;">
                <tr>
                    <td>Date: {{ $dateGroup['formatted_date'] }}</td>
                    <td class="text-right">
                        {{ $dateGroup['total_shops'] }} Shops &bull; {{ $dateGroup['total_bills'] }} Bills &bull; {{ $dateGroup['total_credit_notes'] }} Credit Notes &bull; Subtotal: ₹{{ number_format((float) $dateGroup['subtotal_amount'], 2) }}
                    </td>
                </tr>
            </table>
        </div>

        @foreach ($dateGroup['shops'] as $shopGroup)
            <div class="shop-banner">
                <table class="w-full" style="color: #065f46;">
                    <tr>
                        <td>Shop: {{ $shopGroup['shop_name'] }} ({{ $shopGroup['shop_code'] }})</td>
                        <td class="text-right">
                            Affected Bills: {{ $shopGroup['total_affected_bills'] }} &bull; Credit Notes: {{ $shopGroup['total_credit_notes'] }} &bull; Shop Subtotal: ₹{{ number_format((float) $shopGroup['subtotal_amount'], 2) }}
                        </td>
                    </tr>
                </table>
            </div>

            @foreach ($shopGroup['invoices'] as $invGroup)
                <div class="invoice-block">
                    <div class="invoice-header">
                        <table class="w-full">
                            <tr>
                                <td>
                                    <strong>Invoice:</strong> {{ $invGroup['invoice_number'] }}
                                    @if (! $invGroup['is_unapplied'])
                                        &nbsp;|&nbsp; <strong>Invoice Date:</strong> {{ $invGroup['formatted_invoice_date'] }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    <strong>Period Credit Subtotal:</strong> ₹{{ number_format((float) $invGroup['period_subtotal'], 2) }}
                                </td>
                            </tr>
                        </table>
                    </div>

                    @foreach ($invGroup['credit_notes'] as $cn)
                        <div style="margin-bottom: 6px;">
                            <table class="w-full" style="font-size: 7.5px; margin-bottom: 3px;">
                                <tr>
                                    <td>
                                        <strong>CN #:</strong> <span class="mono">{{ $cn['credit_note_number'] }}</span>
                                        &nbsp;|&nbsp; <strong>Doc Date:</strong> {{ $cn['formatted_credit_note_date'] }}
                                        &nbsp;|&nbsp; <strong>Applied Date:</strong> {{ $cn['applied_date'] }}
                                        &nbsp;|&nbsp; <strong>Reason:</strong> {{ $cn['reason'] }}
                                    </td>
                                    <td class="text-right">
                                        <strong>Applied By:</strong> {{ $cn['applied_by'] }}
                                        &nbsp;|&nbsp; <strong>Status:</strong> {{ $cn['status'] }}
                                        &nbsp;|&nbsp; <strong>Amount:</strong> ₹{{ number_format((float) $cn['amount'], 2) }}
                                    </td>
                                </tr>
                                @if ($cn['notes'] !== '')
                                    <tr>
                                        <td colspan="2" style="color: #475569; font-style: italic;">Notes: {{ $cn['notes'] }}</td>
                                    </tr>
                                @endif
                            </table>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Product Code</th>
                                        <th>Product / Adjustment</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-right">Original Rate</th>
                                        <th class="text-right">Corrected Rate</th>
                                        <th class="text-right">Difference</th>
                                        <th class="text-right">Credit Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cn['items'] as $item)
                                        <tr>
                                            <td class="mono">{{ $item['product_code'] }}</td>
                                            <td>
                                                {{ $item['product_name'] }}
                                                @if ($item['is_monetary_only'])
                                                    <span style="color: #64748b;">({{ $item['reason'] }})</span>
                                                @endif
                                            </td>
                                            <td class="text-right">{{ $item['quantity'] !== null ? number_format((float) $item['quantity'], 2) . ' ' . $item['unit'] : '—' }}</td>
                                            <td class="text-right">{{ $item['original_rate'] !== null ? '₹' . number_format((float) $item['original_rate'], 2) : '—' }}</td>
                                            <td class="text-right">{{ $item['corrected_rate'] !== null ? '₹' . number_format((float) $item['corrected_rate'], 2) : '—' }}</td>
                                            <td class="text-right">{{ $item['difference'] !== null ? '₹' . number_format((float) $item['difference'], 2) : '—' }}</td>
                                            <td class="text-right font-bold">₹{{ number_format((float) $item['credit_amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    @if ($invGroup['reconciliation'])
                        <table class="reconciliation-box">
                            <tr>
                                <td>Original Invoice</td>
                                <td class="text-right">₹{{ number_format((float) $invGroup['reconciliation']['original_invoice'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Period Credits (Selected Range)</td>
                                <td class="text-right" style="color: #059669;">−₹{{ number_format((float) $invGroup['period_subtotal'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Cumulative Total Credits (Lifetime)</td>
                                <td class="text-right" style="color: #059669;">−₹{{ number_format((float) $invGroup['reconciliation']['total_credits'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Cumulative Total Debits (Lifetime)</td>
                                <td class="text-right">₹{{ number_format((float) $invGroup['reconciliation']['total_debits'], 2) }}</td>
                            </tr>
                            <tr class="net-row">
                                <td>Effective Net Bill</td>
                                <td class="text-right">₹{{ number_format((float) $invGroup['reconciliation']['effective_net_bill'], 2) }}</td>
                            </tr>
                        </table>
                    @endif
                </div>
            @endforeach

            <div class="subtotal-bar">
                <table class="w-full">
                    <tr>
                        <td>Shop Subtotal — {{ $shopGroup['shop_name'] }} ({{ $shopGroup['shop_code'] }})</td>
                        <td class="text-right">
                            {{ $shopGroup['total_affected_bills'] }} Bills &bull; {{ $shopGroup['total_credit_notes'] }} Credit Notes &bull; ₹{{ number_format((float) $shopGroup['subtotal_amount'], 2) }}
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach

        <div class="subtotal-bar" style="background-color: #e2e8f0;">
            <table class="w-full">
                <tr>
                    <td>Daily Subtotal — {{ $dateGroup['formatted_date'] }}</td>
                    <td class="text-right">
                        {{ $dateGroup['total_shops'] }} Shops &bull; {{ $dateGroup['total_bills'] }} Bills &bull; {{ $dateGroup['total_credit_notes'] }} Credit Notes &bull; ₹{{ number_format((float) $dateGroup['subtotal_amount'], 2) }}
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <div style="padding: 20px; text-align: center; border: 1px solid #cbd5e1; color: #64748b;">
            No approved shop credit notes found for the selected reporting period and filters.
        </div>
    @endforelse

    <div class="grand-total-bar">
        <table class="w-full" style="color: #ffffff;">
            <tr>
                <td>PERIOD GRAND TOTAL ({{ $overview['period_label'] }})</td>
                <td class="text-right">
                    {{ $overview['total_affected_shops'] }} Shops &bull; {{ $overview['total_affected_invoices'] }} Bills &bull; {{ $overview['total_credit_notes'] }} Credit Notes &bull; ₹{{ number_format((float) $overview['total_credit_amount'], 2) }}
                </td>
            </tr>
        </table>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 7.5;
            $color = array(0.45, 0.5, 0.55);
            $y = $pdf->get_height() - 20;
            $x = $pdf->get_width() - 85;
            $pdf->page_text($x, $y, $text, $font, $size, $color);
            $pdf->page_text(24, $y, "Green Leaf ERP — Shop Credit Note Detailed Report", $font, $size, $color);
        }
    </script>
</body>
</html>
