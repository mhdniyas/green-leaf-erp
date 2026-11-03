<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vendor Purchase Report — Green Leaf ERP</title>
    <style>
        @page {
            margin: 20px 24px 28px 24px;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 8.5px;
            line-height: 1.3;
            background-color: #ffffff;
        }
        .w-full {
            width: 100%;
            border-collapse: collapse;
        }

        /* 1. Professional PDF Header (Once at top) */
        .report-header {
            border-bottom: 2px solid #059669;
            padding-bottom: 7px;
            margin-bottom: 9px;
        }
        .company-brand {
            font-size: 13px;
            font-weight: bold;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .report-main-title {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 1px;
        }
        .meta-tag {
            font-size: 7.5px;
            font-weight: bold;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .meta-date {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 1px;
        }
        .filter-meta-bar {
            margin-top: 5px;
            padding-top: 4px;
            border-top: 1px solid #f1f5f9;
            font-size: 8px;
            color: #475569;
        }
        .meta-item {
            display: inline-block;
            margin-right: 14px;
        }
        .meta-item strong {
            color: #0f172a;
        }

        /* Overall Financial Summary */
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 12px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            border: none;
            padding: 1px 4px;
            font-size: 8px;
            color: #475569;
            vertical-align: top;
        }
        .summary-table td strong {
            color: #0f172a;
            font-size: 7.5px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 1px;
        }
        .mono-bold {
            font-family: monospace;
            font-weight: bold;
            font-size: 8.5px;
            color: #0f172a;
        }
        .green-text {
            color: #047857;
        }
        .rose-text {
            color: #be123c;
        }

        /* Section Heading */
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0f172a;
            margin-bottom: 6px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
        }

        /* 2. Compact Vendor Sections */
        .vendor-list {
            width: 100%;
        }
        .vendor-section {
            page-break-inside: avoid;
            margin-bottom: 7px;
            padding-bottom: 7px;
            border-bottom: 1px solid #e2e8f0;
        }
        .vendor-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .vendor-title-bar {
            margin-bottom: 3px;
        }
        .vendor-num {
            font-size: 9.5px;
            font-weight: bold;
            color: #059669;
            font-family: monospace;
        }
        .vendor-name {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-left: 2px;
        }
        .vendor-bills {
            font-size: 8px;
            font-weight: 600;
            color: #64748b;
            margin-left: 4px;
        }
        .vendor-tags {
            font-size: 7.5px;
            color: #94a3b8;
            margin-left: 4px;
        }

        /* Financial Data Table for Vendor */
        .vendor-finance-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
        }
        .vendor-finance-table th {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            padding: 3px 6px;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
            background-color: #f1f5f9;
        }
        .vendor-finance-table td {
            padding: 3px 6px;
            font-size: 8.5px;
            font-family: monospace;
            font-weight: bold;
            vertical-align: middle;
        }
        .credit-due-header {
            color: #9f1239 !important;
            background-color: #ffe4e6 !important;
        }
        .credit-due-cell {
            color: #be123c !important;
            background-color: #fff1f2 !important;
        }

        /* Reconciliation Detailed Table */
        .page-break {
            page-break-before: always;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #334155;
            text-align: left;
        }
        table.data-table td {
            border: 1px solid #e2e8f0;
            padding: 3px 4px;
            font-size: 7.5px;
            vertical-align: middle;
        }
        table.data-table tfoot td {
            background-color: #f1f5f9;
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            font-weight: bold;
            font-size: 8px;
            padding: 4px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
    </style>
</head>
<body>
    @php
        if (! function_exists('formatPdfInr')) {
            function formatPdfInr(float|int|null $amount): string {
                $amount = (float) ($amount ?? 0);
                $isNegative = $amount < 0;
                $amount = abs($amount);
                $decimal = sprintf('%02d', round(($amount - floor($amount)) * 100));
                $integer = (string) ((int) floor($amount));

                if (strlen($integer) > 3) {
                    $lastThree = substr($integer, -3);
                    $rest = substr($integer, 0, -3);
                    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
                    $formatted = $rest . ',' . $lastThree;
                } else {
                    $formatted = $integer;
                }

                return ($isNegative ? '-' : '') . 'Rs. ' . $formatted . '.' . $decimal;
            }
        }

        $paymentFilterLabel = match(strtolower($filters['payment'] ?? 'all')) {
            'cash' => 'Cash Only',
            'credit' => 'Credit Only',
            default => 'All (Cash & Credit)',
        };

        $totalVendors = $vendors->count();
        $totalBills = (int) ($summary->invoice_count ?? 0);
        $totalPurchase = (float) ($summary->total_purchase ?? 0);
        $totalPaid = (float) ($summary->paid_amount ?? $summary->credit_paid ?? 0);
        $totalDiscount = (float) ($summary->discount ?? 0);
        $totalCreditDue = (float) ($summary->credit_outstanding ?? 0);
        $hasTotalCreditDue = $totalCreditDue > 0.009;
    @endphp

    {{-- 1. PROFESSIONAL PDF HEADER (Rendered ONCE at the top) --}}
    <div class="report-header">
        <table class="w-full">
            <tr>
                <td style="vertical-align: top; width: 68%;">
                    <div class="company-brand">GREEN LEAF</div>
                    <div class="report-main-title">
                        @if(!empty($singleVendor))
                            VENDOR PURCHASE STATEMENT &mdash; {{ strtoupper($singleVendor->name) }}
                        @else
                            VENDOR PURCHASE REPORT &mdash; FINANCIAL STATEMENT
                        @endif
                    </div>
                </td>
                <td style="vertical-align: top; text-align: right; width: 32%;">
                    <div class="meta-tag">FINANCIAL STATEMENT</div>
                    <div class="meta-date">Generated: {{ $generatedAt->format('d M Y, h:i A') }}</div>
                </td>
            </tr>
        </table>

        <div class="filter-meta-bar">
            <span class="meta-item"><strong>Date Range:</strong> {{ $filters['formatted_range'] ?? ($filters['start_date'].' – '.$filters['end_date']) }}</span>
            <span class="meta-item"><strong>Payment Filter:</strong> {{ $paymentFilterLabel }}</span>
            <span class="meta-item"><strong>Purchaser:</strong> {{ $purchaserName ?? 'All Purchasers' }}</span>
            @if(!empty($productFilterName) && $productFilterName !== 'All Products')
                <span class="meta-item"><strong>Product Filter:</strong> {{ $productFilterName }}</span>
            @endif
        </div>
    </div>

    {{-- 2. REPORT SUMMARY (Authoritative aggregated values) --}}
    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td style="width: 14%;">
                    <strong>Total Vendors</strong>
                    <span class="mono-bold">{{ number_format($totalVendors) }}</span>
                </td>
                <td style="width: 14%;">
                    <strong>Total Bills</strong>
                    <span class="mono-bold">{{ number_format($totalBills) }}</span>
                </td>
                <td style="width: 22%;">
                    <strong>Purchase Total</strong>
                    <span class="mono-bold">{{ formatPdfInr($totalPurchase) }}</span>
                </td>
                <td style="width: 20%;">
                    <strong>Total Cash Paid</strong>
                    <span class="mono-bold green-text">{{ formatPdfInr($totalPaid) }}</span>
                </td>
                <td style="width: 15%;">
                    <strong>Total Discount</strong>
                    <span class="mono-bold">{{ formatPdfInr($totalDiscount) }}</span>
                </td>
                @if($hasTotalCreditDue)
                    <td style="width: 15%;">
                        <strong>Credit Due</strong>
                        <span class="mono-bold rose-text">{{ formatPdfInr($totalCreditDue) }}</span>
                    </td>
                @endif
            </tr>
        </table>
    </div>

    {{-- 3. COMPACT VENDOR SECTIONS --}}
    <div class="section-title">Vendor Purchase Summary</div>

    <div class="vendor-list">
        @forelse($vendors as $vendor)
            @php
                $cash = (float) ($vendor->cash_purchase ?? 0);
                $credit = (float) ($vendor->credit_purchase ?? 0);
                if ($credit <= 0.009) {
                    $paidLabel = 'Cash Paid';
                    $paidAmount = (float) ($vendor->paid_amount ?? $vendor->cash_purchase);
                } elseif ($cash <= 0.009) {
                    $paidLabel = 'Paid';
                    $paidAmount = (float) ($vendor->paid_amount ?? $vendor->credit_paid);
                } else {
                    $paidLabel = 'Paid';
                    $paidAmount = (float) ($vendor->paid_amount ?? ($cash + (float) $vendor->credit_paid));
                }
                $discountAmount = (float) ($vendor->discount ?? 0);
                $creditDue = (float) ($vendor->outstanding ?? 0);
                $hasCreditDue = $creditDue > 0.009;
                $billCount = (int) ($vendor->invoice_count ?? 0);
                $slNo = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
            @endphp
            <div class="vendor-section">
                <div class="vendor-title-bar">
                    <span class="vendor-num">{{ $slNo }}.</span>
                    <span class="vendor-name">{{ $vendor->supplier_name ?? 'Unassigned Vendor' }}</span>
                    <span class="vendor-bills">&mdash; {{ number_format($billCount) }} {{ \Illuminate\Support\Str::plural('Purchase Bill', $billCount) }}</span>
                    @if(!empty($vendor->category_tags))
                        <span class="vendor-tags">
                            &bull; {{ implode(', ', array_map(fn($t) => explode('|', $t)[1] ?? $t, array_filter(explode(',', $vendor->category_tags)))) }}
                        </span>
                    @endif
                </div>

                <table class="vendor-finance-table">
                    <thead>
                        <tr>
                            <th style="{{ $hasCreditDue ? 'width: 28%;' : 'width: 36%;' }}">Purchase Total</th>
                            <th style="{{ $hasCreditDue ? 'width: 26%;' : 'width: 34%;' }}">{{ $paidLabel }}</th>
                            <th style="{{ $hasCreditDue ? 'width: 22%;' : 'width: 30%;' }}">Discount</th>
                            @if($hasCreditDue)
                                <th style="width: 24%;" class="text-right credit-due-header">Credit Due</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="{{ $hasCreditDue ? 'width: 28%;' : 'width: 36%;' }}">
                                {{ formatPdfInr($vendor->total_purchase) }}
                            </td>
                            <td style="{{ $hasCreditDue ? 'width: 26%;' : 'width: 34%;' }}" class="green-text">
                                {{ formatPdfInr($paidAmount) }}
                            </td>
                            <td style="{{ $hasCreditDue ? 'width: 22%;' : 'width: 30%;' }}" style="color: #475569;">
                                {{ formatPdfInr($discountAmount) }}
                            </td>
                            @if($hasCreditDue)
                                <td style="width: 24%;" class="text-right credit-due-cell rose-text">
                                    {{ formatPdfInr($creditDue) }}
                                </td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>
        @empty
            <div style="padding: 12px; text-align: center; color: #94a3b8; border: 1px dashed #cbd5e1; border-radius: 4px;">
                No vendor purchases recorded for this period.
            </div>
        @endforelse
    </div>

    {{-- 4. RECONCILIATION DETAILS (Only included when requested or single vendor report) --}}
    @if(!empty($includeDetails) && $invoices->isNotEmpty())
        <div class="page-break"></div>

        <div class="report-header">
            <table class="w-full">
                <tr>
                    <td>
                        <div class="company-brand">GREEN LEAF</div>
                        <div class="report-main-title">Underlying Purchase Bills &amp; Invoices &mdash; Detailed Reconciliation</div>
                    </td>
                    <td style="text-align: right;">
                        <div class="meta-tag">AUDIT RECONCILIATION</div>
                        <div class="meta-date">Total Bills: {{ number_format($invoices->count()) }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 9%;">Date</th>
                    <th style="width: 15%;">Invoice #</th>
                    <th style="width: 20%;">Vendor</th>
                    <th style="width: 14%;">Purchaser</th>
                    <th style="width: 7%;">Terms</th>
                    <th style="width: 11%;" class="text-right">Purchase Total</th>
                    <th style="width: 11%;" class="text-right">Paid</th>
                    <th style="width: 7%;" class="text-right">Discount</th>
                    <th style="width: 6%;" class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $inv)
                    @php
                        $invTotal = (float) ($inv->total_purchase ?? 0);
                        $invPaid = (float) ($inv->paid_amount ?? 0);
                        $invDiscount = (float) ($inv->discount ?? 0);
                        $invDue = (float) ($inv->outstanding ?? 0);
                        $invDate = $inv->business_date ? \Illuminate\Support\Carbon::parse($inv->business_date)->format('d M Y') : '—';
                    @endphp
                    <tr>
                        <td style="font-family: monospace;">{{ $invDate }}</td>
                        <td style="font-family: monospace; font-weight: bold;">{{ $inv->invoice_number ?: ('#'.$inv->invoice_id) }}</td>
                        <td style="font-weight: bold;">{{ $inv->supplier_name ?: '—' }}</td>
                        <td>{{ $inv->purchaser_name ?: '—' }}</td>
                        <td style="text-transform: uppercase;">{{ $inv->payment_class ?: 'cash' }}</td>
                        <td class="text-right" style="font-family: monospace; font-weight: bold;">{{ formatPdfInr($invTotal) }}</td>
                        <td class="text-right green-text" style="font-family: monospace;">{{ formatPdfInr($invPaid) }}</td>
                        <td class="text-right" style="font-family: monospace; color: #64748b;">{{ formatPdfInr($invDiscount) }}</td>
                        <td class="text-center" style="font-size: 7px; font-weight: bold;">
                            @if($invDue <= 0.009)
                                <span style="color: #047857;">PAID</span>
                            @elseif($invPaid > 0.009)
                                <span style="color: #b45309;">PARTIAL</span>
                            @else
                                <span style="color: #be123c;">UNPAID</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right" style="font-weight: bold; text-transform: uppercase;">
                        TOTAL ({{ number_format($invoices->count()) }} {{ \Illuminate\Support\Str::plural('BILL', $invoices->count()) }})
                    </td>
                    <td class="text-right mono-bold">
                        {{ formatPdfInr($invoices->sum('total_purchase')) }}
                    </td>
                    <td class="text-right mono-bold green-text">
                        {{ formatPdfInr($invoices->sum('paid_amount')) }}
                    </td>
                    <td class="text-right mono-bold" style="color: #64748b;">
                        {{ formatPdfInr($invoices->sum('discount')) }}
                    </td>
                    <td class="text-center" style="font-size: 7px; font-weight: bold;">
                        @php
                            $totalDue = (float) $invoices->sum('outstanding');
                        @endphp
                        @if($totalDue <= 0.009)
                            <span style="color: #047857;">SETTLED</span>
                        @else
                            <span class="rose-text">{{ formatPdfInr($totalDue) }}</span>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Page Number Script for DomPDF --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 7.5;
            $color = array(0.45, 0.5, 0.55);
            $y = $pdf->get_height() - 20;
            $x = $pdf->get_width() - 85;
            $pdf->page_text($x, $y, $text, $font, $size, $color);

            $brandText = "Green Leaf ERP — Vendor Purchase Report";
            $pdf->page_text(24, $y, $brandText, $font, $size, $color);
        }
    </script>
</body>
</html>