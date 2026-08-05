<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $order->order_number }} - Delivery Note</title>
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 12mm;
        }

        .screen-tools {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin: 12px auto;
            width: 210mm;
        }

        .print-btn {
            border: 0;
            background: #0f172a;
            color: #fff;
            font-weight: 700;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
        }

        .top-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 10mm;
            border-bottom: 1px solid #dbe5ef;
            padding-bottom: 7mm;
        }

        .title {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.4px;
        }

        .label {
            font-size: 11px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #475569;
            font-weight: 700;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .meta td {
            padding: 2.5px 0;
            vertical-align: top;
        }

        .meta td:first-child {
            color: #475569;
            width: 120px;
            font-weight: 600;
        }

        .section-title {
            margin: 7mm 0 3mm;
            font-size: 13px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #334155;
            font-weight: 800;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .items th,
        .items td {
            border: 1px solid #dbe5ef;
            padding: 7px 8px;
        }

        .items th {
            background: #f8fafc;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            font-size: 10.5px;
            color: #334155;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .summary {
            margin-top: 5mm;
            margin-left: auto;
            width: 78mm;
            border-collapse: collapse;
            font-size: 12px;
        }

        .summary td {
            border: 1px solid #dbe5ef;
            padding: 7px 8px;
        }

        .summary tr:last-child td {
            font-weight: 800;
            font-size: 13px;
            background: #f0fdf4;
        }

        .note-box {
            margin-top: 5mm;
            border: 1px solid #dbe5ef;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 12px;
            color: #334155;
        }

        .footer-sign {
            margin-top: 12mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12mm;
        }

        .sign-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 5px;
            font-size: 11px;
            color: #475569;
            text-align: center;
        }

        @media print {
            body {
                background: #fff;
            }

            .screen-tools {
                display: none;
            }

            .page {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="screen-tools">
        <div>
            <div class="label">Delivery PDF</div>
            <h1 class="title" style="font-size: 18px; margin-top: 2px;">{{ $order->order_number }}</h1>
        </div>
        <button type="button" onclick="window.print()" class="print-btn">Print / Save PDF</button>
    </div>

    <div class="page">
        <div class="top-grid">
            <div>
                <div class="label">Company</div>
                <h2 class="title">{{ $companyDetails['name'] ?? 'Green Leaf' }}</h2>
                @if (!empty($companyDetails['address']))
                    <div style="margin-top: 6px; font-size: 12px; color: #334155;">{{ $companyDetails['address'] }}</div>
                @endif
                <div style="margin-top: 4px; font-size: 12px; color: #334155;">
                    @if (!empty($companyDetails['phone']))
                        <span>Phone: {{ $companyDetails['phone'] }}</span>
                    @endif
                    @if (!empty($companyDetails['phone']) && !empty($companyDetails['email']))
                        <span> | </span>
                    @endif
                    @if (!empty($companyDetails['email']))
                        <span>Email: {{ $companyDetails['email'] }}</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="label">Delivery Note</div>
                <h2 class="title">{{ $order->order_number }}</h2>
                <table class="meta" style="margin-top: 4mm;">
                    <tr>
                        <td>Shop Name</td>
                        <td>{{ $order->shop?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Business Date</td>
                        <td>{{ $order->business_date?->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td>Delivery Status</td>
                        <td>{{ str((string) $order->delivery_status)->replace('_', ' ')->title() }}</td>
                    </tr>
                    <tr>
                        <td>Generated At</td>
                        <td>{{ now()->format('d M Y h:i A') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @php
            $sortedItems = $order->items->sortBy(
                fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
            );
            $invoice = $order->invoice;
            $invoiceItemsByProductId = $invoice?->items?->keyBy('product_id') ?? collect();

            $recalculatedSubtotal = (float) $invoiceItemsByProductId->sum(function ($invoiceItem) {
                $qty = (float) ($invoiceItem->delivered_price_quantity ?? $invoiceItem->price_quantity ?? $invoiceItem->delivered_qty ?? 0);
                $rate = (float) ($invoiceItem->unit_price ?? 0);
                return $qty * $rate;
            });

            $fulfilledItems = $sortedItems
                ->filter(fn ($item) => $item->sorting_status === 'loaded' && (float) ($item->loaded_qty ?? 0) > 0)
                ->groupBy('product_id')
                ->map(function ($group) {
                    $loadedRow = $group->first(fn ($i) => $i->sorting_status === 'loaded' || (float) ($i->loaded_qty ?? 0) > 0);
                    return $loadedRow ?: $group->first();
                })
                ->values();
        @endphp

        <h3 class="section-title">Delivered Items</h3>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Item Details</th>
                    <th class="text-right" style="width: 120px;">Delivered Qty</th>
                    <th class="text-right" style="width: 110px;">Rate</th>
                    <th class="text-right" style="width: 120px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($fulfilledItems as $item)
                    @php
                        $invoiceItem = $invoiceItemsByProductId->get($item->product_id);
                        if ($invoiceItem) {
                            $approvedQty = (float) ($invoiceItem->delivered_price_quantity ?? $invoiceItem->price_quantity ?? $invoiceItem->delivered_qty ?? 0);
                            $displayUnitLabel = strtoupper((string) ($invoiceItem->price_unit ?: $item->product?->unit ?: 'kg'));
                            $unitRate = (float) ($invoiceItem->unit_price ?? 0);
                            $lineTotal = $approvedQty * $unitRate;
                        } else {
                            $approvedQty = (float) ($item->loaded_qty ?? $item->approved_qty ?? 0);
                            $displayUnitLabel = strtoupper((string) ($item->product?->unit ?: 'kg'));
                            $unitRate = null;
                            $lineTotal = null;
                        }
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            @if($item->product?->sku)
                                <span style="color:#64748b; font-weight:600;">[{{ $item->product->sku }}]</span>
                            @endif
                            <span style="font-weight:700;">{{ $item->product?->name ?? 'Unknown Item' }}</span>
                        </td>
                        <td class="text-right">{{ number_format($approvedQty, 2) }} {{ $displayUnitLabel }}</td>
                        <td class="text-right">{{ $unitRate !== null ? 'Rs. '.number_format($unitRate, 2) : '-' }}</td>
                        <td class="text-right" style="font-weight:700;">{{ $lineTotal !== null ? 'Rs. '.number_format($lineTotal, 2) : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center; color:#64748b;">No loaded items available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($invoice)
            <table class="summary">
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">Rs. {{ number_format($recalculatedSubtotal, 2) }}</td>
                </tr>
                <tr>
                    <td>Shortage</td>
                    <td class="text-right">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</td>
                </tr>
                <tr>
                    <td>Excess</td>
                    <td class="text-right">Rs. {{ number_format((float) $invoice->excess_total, 2) }}</td>
                </tr>
                <tr>
                    <td>Discount</td>
                    <td class="text-right">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</td>
                </tr>
                <tr>
                    <td>Final Total</td>
                    <td class="text-right">Rs. {{ number_format($recalculatedSubtotal - (float) $invoice->shortage_total + (float) $invoice->excess_total - (float) $invoice->discount_total, 2) }}</td>
                </tr>
            </table>
        @endif

        @if (!empty($invoice?->delivery_note))
            <div class="note-box">
                <strong>Delivery Note:</strong>
                <div style="margin-top: 4px;">{{ $invoice->delivery_note }}</div>
            </div>
        @endif

        <div class="footer-sign">
            <div class="sign-box">Prepared By</div>
            <div class="sign-box">Received By (Shop)</div>
        </div>
    </div>
</body>
</html>
