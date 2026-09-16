<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 12mm 15mm;
        }
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            html, body {
                background: white !important;
                color: #0f172a !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                font-size: 13px !important;
            }
            .no-print {
                display: none !important;
            }
            .print-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            table {
                page-break-inside: auto !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }
            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
            thead {
                display: table-header-group !important;
            }
            tfoot {
                display: table-footer-group !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 sm:p-6 text-slate-900 font-sans antialiased">
    <div class="max-w-3xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm print-card space-y-6">

        <!-- Top Control Bar (Screen only) -->
        <div class="no-print flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.cashbook.inventory', array_filter(['date' => $date, 'warehouse_id' => $selectedWarehouseId])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                    ← Back to Inventory
                </a>
                <div>
                    <h1 class="text-base font-extrabold text-slate-900">Pending Bills Print / PDF</h1>
                    <p class="text-xs text-slate-500">Filtered 2-column list of pending purchase bills after match.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.close()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer transition">
                    Close
                </button>
                <button onclick="window.print()" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print / Save as PDF
                </button>
            </div>
        </div>

        <!-- Document Header -->
        <div class="border-b border-slate-200 pb-4">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded border border-emerald-200">Green Leaf ERP</span>
                    <h2 class="text-xl font-black text-slate-900 mt-2">Green Leaf - Pending Bills</h2>
                </div>
                <div class="text-right text-xs text-slate-600 space-y-1">
                    <div>
                        <span class="font-semibold text-slate-500">Date:</span>
                        <span class="font-black text-slate-900">{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</span>
                    </div>
                    <div>
                        <span class="font-semibold text-slate-500">Warehouse:</span>
                        <span class="font-black text-slate-900">{{ $selectedWarehouse?->name ?? 'All Warehouses' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2-Column Clean Table: Product | Pending -->
        <div class="border border-slate-300 rounded-xl overflow-hidden">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-300">
                        <th class="py-3 px-4 font-black text-slate-900 uppercase tracking-wider text-xs">
                            Product
                        </th>
                        <th class="py-3 px-4 font-black text-slate-900 uppercase tracking-wider text-xs text-right w-40">
                            Pending
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($managerSummary['pending_bills_after_match']['products'] as $product)
                        <tr class="hover:bg-slate-50 transition-colors {{ !empty($product['unit_mismatch']) ? 'bg-amber-50/40' : '' }}">
                            <td class="py-3 px-4 font-bold text-slate-900">
                                {{ $product['product_name'] }}
                                @if (!empty($product['unit_mismatch']))
                                    <span class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 font-bold border border-amber-200">Unit Fix Required</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-black font-mono text-slate-900 text-right">
                                {{ $product['pending_qty'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="py-8 text-center text-slate-500 font-bold">
                                No pending bills. All advance receives are fully matched! ✓
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($managerSummary['pending_bills_after_match']['products']))
                    <tfoot>
                        <tr class="bg-slate-50 border-t-2 border-slate-300 font-black">
                            <td class="py-3 px-4 text-slate-900">
                                Pending Products: {{ $managerSummary['pending_bills_after_match']['pending_products_count'] }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-900">
                                {{ $managerSummary['pending_bills_after_match']['formatted_totals'] }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="text-xs text-slate-500 text-center pt-2">
            Generated on {{ now()->format('d M Y, h:i A') }} · Green Leaf Manager Inventory
        </div>
    </div>
</body>
</html>
