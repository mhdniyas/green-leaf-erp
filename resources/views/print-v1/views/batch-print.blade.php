<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Batch Print — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; color: #000; padding: 8mm; }
        .no-print { display: flex; gap: 8px; margin-bottom: 12px; }
        .btn-print { background: #000; color: #fff; font-weight: 700; padding: 7px 18px; border: 1px solid #000; border-radius: 6px; cursor: pointer; }
        .preset-break { break-after: page; page-break-after: always; margin-bottom: 8mm; }
        .preset-break:last-child { break-after: auto; page-break-after: auto; }
        .preset-banner { background: #000; color: #fff; font-weight: 900; font-size: 13px; padding: 4px 8px; text-transform: uppercase; margin-bottom: 6px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5px; }
        th, td { border: 1px solid #000; padding: 3px 4px; text-align: center; }
        th { font-size: 9px; font-weight: 700; text-transform: uppercase; }
        td.item-cell { text-align: left; font-weight: 600; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Batch</button>
        <button class="btn-print" style="background:#fff; color:#000;" onclick="window.close()">Close</button>
        <span>{{ count($batchItems) }} presets in batch &bull; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
    </div>

    @foreach($batchItems as $item)
        @php
            $preset = $item['preset'];
            $result = $item['result'];
            $filteredShops = $result->filteredShops;
            $matrix = $result->matrix;
            $productMeta = $result->productMeta;
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        @endphp

        <div class="preset-break">
            <div class="preset-banner">
                Preset: {{ $preset->name }} &bull; {{ $result->gradeHeading() }} &bull; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                @if($result->selectedWarehouse)
                    &bull; {{ $result->selectedWarehouse->name }}
                @endif
            </div>

            @if($filteredShops->count() > 0 && count($matrix) > 0)
                <table>
                    <thead>
                        <tr>
                            <th style="width: 5%;">SL</th>
                            <th style="width: 25%; text-align: left;">Item Description</th>
                            @foreach($filteredShops as $shop)
                                <th>{{ $shop->warehouse_tag ?: $shop->name }}</th>
                            @endforeach
                            <th style="width: 8%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $sl = 1; @endphp
                        @foreach($matrix as $rowKey => $shopQtys)
                            @php
                                $meta = $productMeta[$rowKey];
                                $total = array_sum($shopQtys);
                            @endphp
                            <tr>
                                <td>{{ $sl++ }}</td>
                                <td class="item-cell">{{ $meta['display_name'] }}</td>
                                @foreach($filteredShops as $shop)
                                    @php $qty = (float) ($shopQtys[$shop->id] ?? 0); @endphp
                                    <td>{{ $qty > 0 ? $formatQty($qty) : '-' }}</td>
                                @endforeach
                                <td style="font-weight: 800;">{{ $formatQty((float) $total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="padding: 20px; font-style: italic;">No approved order quantities found for this preset.</p>
            @endif
        </div>
    @endforeach
</body>
</html>

