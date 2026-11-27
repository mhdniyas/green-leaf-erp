<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shop Credit Notes — Summary Report</title>
    <style>
        @page {
            margin: 22px 24px 32px 24px;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 8.5px;
            line-height: 1.35;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .w-full { width: 100%; border-collapse: collapse; }
        .text-right { text-align: right; }
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
            margin-bottom: 14px;
        }
        .kpi-table td {
            width: 25%;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
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

        .section-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #065f46;
            text-transform: uppercase;
            border-bottom: 1px solid #a7f3d0;
            padding-bottom: 3px;
            margin-top: 12px;
            margin-bottom: 6px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table thead { display: table-header-group; }
        .data-table th {
            background-color: #065f46;
            color: #ffffff;
            font-size: 7.5px;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #065f46;
            text-align: left;
        }
        .data-table th.text-right { text-align: right; }
        .data-table td {
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
        }
        .subtotal-row td {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .grand-total-row td {
            background-color: #ecfdf5;
            font-weight: bold;
            color: #065f46;
            border-top: 2px solid #059669;
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
                                <div class="report-title">Shop Credit Note Report — Summary (Daily &amp; Shop Wise)</div>
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

    <div class="section-title">Section A — Period Overview</div>
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
                <div class="kpi-label">Unique Affected Shops</div>
                <div class="kpi-value">{{ $overview['total_affected_shops'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Unique Affected Invoices</div>
                <div class="kpi-value">{{ $overview['total_affected_invoices'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Section B — Daily Summary</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-right">Shops</th>
                <th class="text-right">Bills</th>
                <th class="text-right">Credit Notes</th>
                <th class="text-right">Credit Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['daily_summary'] as $row)
                <tr>
                    <td>{{ $row['formatted_date'] }}</td>
                    <td class="text-right">{{ $row['shops_count'] }}</td>
                    <td class="text-right">{{ $row['bills_count'] }}</td>
                    <td class="text-right">{{ $row['credit_notes_count'] }}</td>
                    <td class="text-right">₹{{ number_format((float) $row['credit_amount'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="grand-total-row">
                <td>Grand Total</td>
                <td class="text-right">—</td>
                <td class="text-right">—</td>
                <td class="text-right">{{ $overview['total_credit_notes'] }}</td>
                <td class="text-right">₹{{ number_format((float) $overview['total_credit_amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Section C — Shop-wise Period Summary</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Shop Code</th>
                <th>Shop Name</th>
                <th class="text-right">Affected Bills</th>
                <th class="text-right">Credit Notes</th>
                <th class="text-right">Credit Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['shop_summary'] as $row)
                <tr>
                    <td class="mono">{{ $row['shop_code'] }}</td>
                    <td>{{ $row['shop_name'] }}</td>
                    <td class="text-right">{{ $row['bills_count'] }}</td>
                    <td class="text-right">{{ $row['credit_notes_count'] }}</td>
                    <td class="text-right">₹{{ number_format((float) $row['credit_amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b;">No shop credit notes in this period.</td>
                </tr>
            @endforelse
            <tr class="grand-total-row">
                <td colspan="2">Grand Total</td>
                <td class="text-right">{{ $overview['total_affected_invoices'] }}</td>
                <td class="text-right">{{ $overview['total_credit_notes'] }}</td>
                <td class="text-right">₹{{ number_format((float) $overview['total_credit_amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Section D — Daily Shop-wise Summary</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Shop Code</th>
                <th>Shop Name</th>
                <th class="text-right">Bills</th>
                <th class="text-right">Credit Notes</th>
                <th class="text-right">Credit Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['daily_shop_grouped'] as $group)
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['formatted_date'] }}</td>
                        <td class="mono">{{ $row['shop_code'] }}</td>
                        <td>{{ $row['shop_name'] }}</td>
                        <td class="text-right">{{ $row['bills_count'] }}</td>
                        <td class="text-right">{{ $row['credit_notes_count'] }}</td>
                        <td class="text-right">₹{{ number_format((float) $row['credit_amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td colspan="3">Daily Subtotal — {{ $group['formatted_date'] }}</td>
                    <td class="text-right">{{ $group['subtotal_bills'] }}</td>
                    <td class="text-right">{{ $group['subtotal_notes'] }}</td>
                    <td class="text-right">₹{{ number_format((float) $group['subtotal_amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b;">No daily shop-wise credit notes in this period.</td>
                </tr>
            @endforelse
            <tr class="grand-total-row">
                <td colspan="3">Period Grand Total</td>
                <td class="text-right">{{ $overview['total_affected_invoices'] }}</td>
                <td class="text-right">{{ $overview['total_credit_notes'] }}</td>
                <td class="text-right">₹{{ number_format((float) $overview['total_credit_amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 7.5;
            $color = array(0.45, 0.5, 0.55);
            $y = $pdf->get_height() - 20;
            $x = $pdf->get_width() - 85;
            $pdf->page_text($x, $y, $text, $font, $size, $color);
            $pdf->page_text(24, $y, "Green Leaf ERP — Shop Credit Note Summary Report", $font, $size, $color);
        }
    </script>
</body>
</html>
