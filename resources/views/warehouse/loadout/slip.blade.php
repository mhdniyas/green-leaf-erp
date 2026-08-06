<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loadout Slip — {{ $shopOrder->order_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #0f172a;
            background: #f8fafc;
            line-height: 1.4;
        }

        .no-print-bar {
            max-width: 210mm;
            margin: 16px auto;
            padding: 10px 16px;
            background: #0f172a;
            color: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }

        .no-print-bar a {
            color: #93c5fd;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .no-print-bar a:hover {
            color: #ffffff;
        }

        .btn-print {
            background: #3b82f6;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s ease;
        }

        .btn-print:hover {
            background: #2563eb;
        }

        .invoice-card {
            max-width: 210mm;
            margin: 0 auto 30px auto;
            background: #ffffff;
            padding: 12mm 14mm;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        /* ── Header ── */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -0.5px;
            color: #0f172a;
            text-transform: uppercase;
        }

        .doc-subtitle {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            color: #2563eb;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .invoice-meta-right {
            text-align: right;
        }

        .order-title {
            font-size: 18px;
            font-family: monospace;
            font-weight: 800;
            color: #0f172a;
        }

        .meta-date {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .status-in_transit { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .status-ready_for_dispatch { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .status-delivered { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .status-pending_delivery { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        /* ── Info Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }

        .info-block-title {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .shop-name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .shop-meta {
            font-size: 11px;
            color: #475569;
            margin-top: 2px;
        }

        .tag-badge {
            font-family: monospace;
            background: #0f172a;
            color: #ffffff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
        }

        /* ── Table ── */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.items-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #0f172a;
        }

        table.items-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 11px;
        }

        table.items-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .col-center { text-align: center; }
        .col-right { text-align: right; }

        .item-name {
            font-weight: 700;
            color: #0f172a;
        }

        .item-category {
            font-size: 9px;
            color: #64748b;
            font-weight: 600;
        }

        .qty-num {
            font-family: monospace;
            font-size: 12px;
            font-weight: 800;
        }

        .qty-loaded {
            color: #047857;
            background: #ecfdf5;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #a7f3d0;
        }

        .qty-zero {
            color: #94a3b8;
        }

        .unit-label {
            font-size: 10px;
            font-weight: 600;
            color: #475569;
        }

        /* ── Summary & Notes ── */
        .summary-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #f1f5f9;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .total-pill {
            text-align: right;
        }

        .total-pill .total-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .total-pill .total-val {
            font-size: 18px;
            font-weight: 900;
            font-family: monospace;
            color: #0f172a;
        }

        /* ── Signatures ── */
        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
        }

        .sig-box {
            border-top: 1.5px solid #0f172a;
            padding-top: 6px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Print Overrides ── */
        @media print {
            body {
                background: #ffffff;
            }
            .no-print-bar {
                display: none !important;
            }
            .invoice-card {
                border: none;
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            table.items-table th {
                background: #0f172a !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .qty-loaded {
                background: transparent !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>

    {{-- Screen Action Bar --}}
    <div class="no-print-bar">
        <a href="{{ route('warehouse.loadout.show', $shopOrder) }}">
            &larr; Back to Loadout Order
        </a>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-family: monospace; font-weight: 700; font-size: 13px;">{{ $shopOrder->order_number }}</span>
            <button type="button" class="btn-print" onclick="window.print()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m11.32-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.32 0h-11.32M18 10.5h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                </svg>
                Print / Save PDF
            </button>
        </div>
    </div>

    {{-- Printable Manifest Invoice Card --}}
    <div class="invoice-card">

        {{-- Header --}}
        <div class="invoice-header">
            <div>
                <div class="brand-name">Green Leaf Fresh</div>
                <div class="doc-subtitle">Warehouse Loadout Manifest &amp; Dispatch Slip</div>
            </div>
            <div class="invoice-meta-right">
                <div class="order-title">{{ $shopOrder->order_number }}</div>
                <div class="meta-date">Date: {{ $shopOrder->business_date->format('d M Y') }}</div>
                @php
                    $statusLabels = [
                        'pending_delivery' => 'Waiting for Loadout',
                        'ready_for_dispatch' => 'Ready for Delivery',
                        'in_transit' => 'In Transit / Out for Delivery',
                        'delivered' => 'Delivered',
                        'partially_delivered' => 'Partially Delivered',
                    ];
                    $statusName = $statusLabels[$shopOrder->delivery_status] ?? strtoupper($shopOrder->delivery_status);
                @endphp
                <div class="status-badge status-{{ $shopOrder->delivery_status }}">
                    {{ $statusName }}
                </div>
            </div>
        </div>

        {{-- Info Grid --}}
        <div class="info-grid">
            <div>
                <div class="info-block-title">Destination Shop</div>
                <div class="shop-name">{{ $shopOrder->shop?->name ?? 'Direct Customer' }}</div>
                <div class="shop-meta">
                    Shop Code: <strong>{{ $shopOrder->shop?->code ?? 'N/A' }}</strong>
                    @if($shopOrder->shop?->warehouse_tag)
                        &middot; Tag: <span class="tag-badge">{{ $shopOrder->shop->warehouse_tag }}</span>
                    @endif
                </div>
                @if($shopOrder->shop?->address)
                    <div class="shop-meta" style="margin-top: 4px; font-size: 10px; color: #64748b;">
                        {{ $shopOrder->shop->address }}
                    </div>
                @endif
            </div>

            <div>
                <div class="info-block-title">Dispatch Details</div>
                <div class="shop-meta">
                    Order Source: <strong>{{ ucwords(str_replace('_', ' ', $shopOrder->order_source ?? 'shop')) }}</strong>
                </div>
                <div class="shop-meta">
                    Loaded Items: <strong>{{ $totalLoadedItems }} of {{ count($productGroups) }} product line(s)</strong>
                </div>
                @if($shopOrder->deliveredBy)
                    <div class="shop-meta">
                        Dispatched By: <strong>{{ $shopOrder->deliveredBy->name }}</strong>
                    </div>
                @endif
                @if($shopOrder->delivery_notes)
                    <div class="shop-meta" style="margin-top: 4px; font-style: italic; color: #475569;">
                        Notes: {{ $shopOrder->delivery_notes }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Dispatched Items Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 32px;" class="col-center">#</th>
                    <th style="width: 90px;">SKU</th>
                    <th>Product &amp; Category</th>
                    <th style="width: 100px;" class="col-right">Approved Qty</th>
                    <th style="width: 110px;" class="col-right">Loaded / Out Qty</th>
                    <th style="width: 110px;" class="col-center">Order Unit Qty</th>
                    <th style="width: 90px;" class="col-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productGroups as $index => $group)
                    <tr>
                        <td class="col-center" style="font-weight: 700; color: #64748b;">{{ $index + 1 }}</td>
                        <td style="font-family: monospace; font-weight: 700; font-size: 10px;">
                            {{ $group['product']?->sku ?? 'N/A' }}
                        </td>
                        <td>
                            <div class="item-name">{{ $group['product']?->name ?? 'Unknown Product' }}</div>
                            <div class="item-category">
                                {{ $group['product']?->category?->name ?? 'General' }}
                                &middot; Grade {{ $group['product_grade'] }}
                            </div>
                        </td>
                        <td class="col-right">
                            <span class="qty-num">{{ number_format($group['total_approved'], 2) }}</span>
                            <span class="unit-label">{{ $group['unit'] }}</span>
                        </td>
                        <td class="col-right">
                            @if($group['total_loaded'] > 0)
                                <span class="qty-num qty-loaded">{{ number_format($group['total_loaded'], 2) }}</span>
                                <span class="unit-label" style="font-weight: 700; color: #047857;">{{ $group['unit'] }}</span>
                            @else
                                <span class="qty-num qty-zero">0.00</span>
                                <span class="unit-label">{{ $group['unit'] }}</span>
                            @endif
                        </td>
                        <td class="col-center">
                            @if($group['has_secondary_unit'] && $group['loaded_order_unit_qty'] > 0)
                                <span class="qty-num" style="color: #2563eb;">{{ number_format($group['loaded_order_unit_qty'], 1) }}</span>
                                <span class="unit-label">{{ $group['requested_unit_label'] }}</span>
                            @elseif($group['has_secondary_unit'])
                                <span class="qty-num qty-zero">0</span>
                                <span class="unit-label">{{ $group['requested_unit_label'] }}</span>
                            @else
                                <span style="color: #cbd5e1;">&mdash;</span>
                            @endif
                        </td>
                        <td class="col-center">
                            @if($group['sorting_status'] === 'loaded' || $group['total_loaded'] > 0)
                                <span style="color: #047857; font-weight: 800; font-size: 9px; text-transform: uppercase;">Loaded ✓</span>
                            @elseif($group['sorting_status'] === 'not_available')
                                <span style="color: #dc2626; font-weight: 800; font-size: 9px; text-transform: uppercase;">N/A</span>
                            @else
                                <span style="color: #d97706; font-weight: 700; font-size: 9px; text-transform: uppercase;">Pending</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="col-center" style="padding: 20px; color: #94a3b8;">
                            No items found in this order.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Summary Box --}}
        <div class="summary-box">
            <div>
                <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">Summary</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                    Total Product Lines Out: <strong style="color: #0f172a;">{{ $totalLoadedItems }}</strong> of <strong>{{ count($productGroups) }}</strong>
                </div>
            </div>

            <div class="total-pill">
                <div class="total-label">Total Dispatched Weight</div>
                <div class="total-val">{{ number_format($totalLoadedWeight, 2) }} KG</div>
            </div>
        </div>

        {{-- Signatures --}}
        <div class="signatures-grid">
            <div class="sig-box">
                Warehouse Dispatcher Signature &amp; Date
            </div>
            <div class="sig-box">
                Driver / Receiver Signature &amp; Date
            </div>
        </div>

    </div>

</body>
</html>
