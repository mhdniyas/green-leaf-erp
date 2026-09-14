<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm 8mm 10mm;
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
                font-size: 11px !important;
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
    <div class="max-w-7xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm print-card space-y-5"
         x-data="{
            sortColumn: '{{ $sortBy ?? 'product_code' }}',
            sortDirection: '{{ $sortDir ?? 'asc' }}',
            rows: @js($rows),
            sortBy(column) {
                if (this.sortColumn === column) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
            },
            get sortedRows() {
                let list = [...(this.rows || [])];
                if (!this.sortColumn) return list;
                return list.sort((a, b) => {
                    let res = 0;
                    if (this.sortColumn === 'product_code' || this.sortColumn === 'code' || this.sortColumn === 'sku') {
                        const codeA = String(a.product_code || a.sku || '').trim();
                        const codeB = String(b.product_code || b.sku || '').trim();
                        const isNumA = /^\d+$/.test(codeA);
                        const isNumB = /^\d+$/.test(codeB);
                        if (isNumA && isNumB) {
                            res = parseInt(codeA, 10) - parseInt(codeB, 10);
                        } else if (isNumA && !isNumB) {
                            res = -1;
                        } else if (!isNumA && isNumB) {
                            res = 1;
                        } else {
                            res = codeA.localeCompare(codeB, undefined, { numeric: true, sensitivity: 'base' });
                        }
                        if (res === 0) {
                            res = (a.product_name || '').localeCompare(b.product_name || '');
                        }
                    } else if (this.sortColumn === 'product_name' || this.sortColumn === 'name') {
                        const nameA = (a.product_name || '').toLowerCase();
                        const nameB = (b.product_name || '').toLowerCase();
                        res = nameA.localeCompare(nameB);
                        if (res === 0) {
                            const codeA = String(a.product_code || a.sku || '').trim();
                            const codeB = String(b.product_code || b.sku || '').trim();
                            res = codeA.localeCompare(codeB, undefined, { numeric: true, sensitivity: 'base' });
                        }
                    } else if (this.sortColumn === 'advance_qty' || this.sortColumn === 'advance') {
                        res = (Number(a.advance_qty) || 0) - (Number(b.advance_qty) || 0);
                    } else if (this.sortColumn === 'bill_qty' || this.sortColumn === 'bill') {
                        res = (Number(a.bill_qty) || 0) - (Number(b.bill_qty) || 0);
                    } else if (this.sortColumn === 'diff') {
                        const valA = a.diff === null || a.diff === undefined ? (this.sortDirection === 'asc' ? 999999999 : -999999999) : Number(a.diff);
                        const valB = b.diff === null || b.diff === undefined ? (this.sortDirection === 'asc' ? 999999999 : -999999999) : Number(b.diff);
                        res = valA - valB;
                    } else if (this.sortColumn === 'matched_bill_qty' || this.sortColumn === 'matched_qty' || this.sortColumn === 'matched') {
                        res = (Number(a.matched_bill_qty) || 0) - (Number(b.matched_bill_qty) || 0);
                    } else if (this.sortColumn === 'unmatched_bill_qty' || this.sortColumn === 'pending_qty' || this.sortColumn === 'pending') {
                        res = (Number(a.unmatched_bill_qty) || 0) - (Number(b.unmatched_bill_qty) || 0);
                    } else if (this.sortColumn === 'match_pct' || this.sortColumn === 'match') {
                        const valA = a.match_pct === null || a.match_pct === undefined ? -1 : Number(a.match_pct);
                        const valB = b.match_pct === null || b.match_pct === undefined ? -1 : Number(b.match_pct);
                        res = valA - valB;
                    } else if (this.sortColumn === 'stock_balance' || this.sortColumn === 'inv_stock' || this.sortColumn === 'stock') {
                        res = (Number(a.stock_balance) || 0) - (Number(b.stock_balance) || 0);
                    }
                    return this.sortDirection === 'asc' ? res : -res;
                });
            }
         }">

        <!-- Top Control Bar (Screen only) -->
        <div class="no-print flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.cashbook.inventory', array_filter(['date' => $date, 'warehouse_id' => $selectedWarehouseId])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                    ← Back to Inventory
                </a>
                <div>
                    <h1 class="text-base font-extrabold text-slate-900">Inventory Discrepancy Print View</h1>
                    <p class="text-xs text-slate-500">Only items that are not 100% matched (< 100%, unit mismatch, or difference).</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.close()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer transition">
                    Close
                </button>
                <button onclick="window.print()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print / Save as PDF
                </button>
            </div>
        </div>

        <!-- Printable Document Header -->
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Green Leaf ERP</span>
                    <span class="text-xs font-bold text-slate-400">·</span>
                    <span class="text-xs font-bold text-slate-500">Warehouse Inventory Comparison</span>
                </div>
                <h2 class="text-lg font-black text-slate-900 mt-1">Daily Inventory Discrepancies (< 100% Match)</h2>
                <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                    <div>
                        <span class="font-semibold text-slate-500">Date:</span>
                        <span class="font-bold text-slate-900">{{ \Carbon\Carbon::parse($date)->format('d-m-Y (l, d M Y)') }}</span>
                    </div>
                    <span>·</span>
                    <div>
                        <span class="font-semibold text-slate-500">Warehouse:</span>
                        <span class="font-bold text-slate-900">{{ $selectedWarehouse?->name ?? 'All Warehouses' }}</span>
                    </div>
                </div>
            </div>

            <div class="text-left sm:text-right text-xs text-slate-500">
                <div>Generated: <span class="font-bold text-slate-700">{{ now()->format('d-m-Y H:i') }}</span></div>
                <div class="mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-200">
                        {{ $summary['total_items'] }} Discrepant {{ \Illuminate\Support\Str::plural('Product', $summary['total_items']) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Summary KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
            <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="text-[10px] font-bold uppercase text-slate-500">Total Unmatched Items</div>
                <div class="text-base font-black font-mono text-slate-900 mt-0.5">{{ $summary['total_items'] }}</div>
            </div>
            <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="text-[10px] font-bold uppercase text-slate-500">Advance Qty</div>
                <div class="text-base font-black font-mono text-slate-900 mt-0.5">{{ number_format($summary['total_advance_qty'], 2) }}</div>
            </div>
            <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="text-[10px] font-bold uppercase text-slate-500">Bill Qty</div>
                <div class="text-base font-black font-mono text-slate-900 mt-0.5">{{ number_format($summary['total_bill_qty'], 2) }}</div>
            </div>
            <div class="p-2.5 rounded-xl border border-amber-200 bg-amber-50">
                <div class="text-[10px] font-bold uppercase text-amber-800">Bill Pending Qty</div>
                <div class="text-base font-black font-mono text-amber-900 mt-0.5">{{ number_format($summary['total_unmatched_bill_qty'], 2) }}</div>
            </div>
            <div class="p-2.5 rounded-xl border border-rose-200 bg-rose-50">
                <div class="text-[10px] font-bold uppercase text-rose-800">Unit Mismatches</div>
                <div class="text-base font-black font-mono text-rose-900 mt-0.5">{{ $summary['unit_fix_count'] }}</div>
            </div>
        </div>

        <!-- Printable Discrepancy Table -->
        <div class="overflow-x-auto">
            @if(count($rows) === 0)
                <div class="py-12 text-center rounded-xl border border-emerald-200 bg-emerald-50">
                    <div class="text-sm font-bold text-emerald-800">All products are 100% matched!</div>
                    <p class="text-xs text-emerald-600 mt-1">No unit mismatches or quantity differences found for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</p>
                </div>
            @else
                <table class="w-full text-left border-collapse text-xs border border-slate-200">
                    <thead>
                        <tr class="bg-slate-100 text-slate-800 border-b border-slate-300 font-bold uppercase text-[10px] select-none">
                            <th scope="col" class="py-2 px-2 text-center border-r border-slate-200 w-10">#</th>
                            <th scope="col" @click="sortBy('product_code')" class="py-2 px-2.5 border-r border-slate-200 w-20 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center gap-1">
                                    <span>Code</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'product_code' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'product_code' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('product_name')" class="py-2 px-3 border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center gap-1">
                                    <span>Product Name</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'product_name' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'product_name' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('advance_qty')" class="py-2 px-2.5 text-right border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Advance</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'advance_qty' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'advance_qty' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('bill_qty')" class="py-2 px-2.5 text-right border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Bill</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'bill_qty' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'bill_qty' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('diff')" class="py-2 px-2.5 text-right border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Diff</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'diff' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'diff' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('matched_bill_qty')" class="py-2 px-2.5 text-right border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Matched</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'matched_bill_qty' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'matched_bill_qty' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('unmatched_bill_qty')" class="py-2 px-2.5 text-right border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Pending</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'unmatched_bill_qty' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'unmatched_bill_qty' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('match_pct')" class="py-2 px-2 text-right border-r border-slate-200 w-16 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Match %</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'match_pct' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'match_pct' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('stock_balance')" class="py-2 px-2.5 text-right border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Inv Stock</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none print:hidden">
                                        <span :class="sortColumn === 'stock_balance' && sortDirection === 'asc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'stock_balance' && sortDirection === 'desc' ? 'text-emerald-700 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" class="py-2 px-3 text-left">Issue / Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <template x-for="(row, idx) in sortedRows" :key="row.product_id + '-' + row.unit">
                            <tr :class="row.unit_mismatch ? 'bg-rose-50/40' : (idx % 2 === 1 ? 'bg-slate-50/50' : 'bg-white')">
                                <td class="py-2 px-2 text-center font-mono font-bold text-slate-400 border-r border-slate-200"
                                    x-text="idx + 1">
                                </td>
                                <td class="py-2 px-2.5 font-mono text-[11px] font-bold text-slate-700 border-r border-slate-200"
                                    x-text="row.product_code || row.sku || '—'">
                                </td>
                                <td class="py-2 px-3 font-semibold text-slate-900 border-r border-slate-200"
                                    x-text="row.product_name">
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono text-slate-800 border-r border-slate-200"
                                    x-text="row.formatted_advance">
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono text-slate-800 border-r border-slate-200"
                                    x-text="row.formatted_bill">
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono font-bold border-r border-slate-200">
                                    <template x-if="row.unit_mismatch">
                                        <span class="text-rose-700">Unit Mismatch</span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && row.diff > 0.0001">
                                        <span class="text-emerald-700" x-text="row.formatted_diff"></span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && row.diff < -0.0001">
                                        <span class="text-rose-700" x-text="row.formatted_diff"></span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && Math.abs(row.diff || 0) <= 0.0001">
                                        <span class="text-slate-400">0</span>
                                    </template>
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono text-slate-700 border-r border-slate-200"
                                    x-text="Number(row.matched_bill_qty || 0).toFixed(2) + ' ' + row.unit">
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono font-bold text-amber-800 border-r border-slate-200"
                                    x-text="Number(row.unmatched_bill_qty || 0).toFixed(2) + ' ' + row.unit">
                                </td>
                                <td class="py-2 px-2 text-right font-mono font-bold border-r border-slate-200">
                                    <template x-if="row.unit_mismatch">
                                        <span class="text-slate-400">--</span>
                                    </template>
                                    <template x-if="!row.unit_mismatch">
                                        <span :class="Number(row.match_pct || 0) >= 100 ? 'text-emerald-700' : 'text-amber-700'"
                                              x-text="row.formatted_match_pct">
                                        </span>
                                    </template>
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono text-slate-800 border-r border-slate-200"
                                    x-text="row.formatted_stock_balance">
                                </td>
                                <td class="py-2 px-3 text-left">
                                    <template x-if="row.unit_mismatch">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-200"
                                              x-text="'UNIT MISMATCH (' + row.unit + ')'">
                                        </span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && Number(row.bill_qty || 0) <= 0.0001 && Number(row.advance_qty || 0) > 0">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            Advance Only (No Bill)
                                        </span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && Number(row.advance_qty || 0) <= 0.0001 && Number(row.bill_qty || 0) > 0">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                            Bill Only (No Advance)
                                        </span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && Number(row.unmatched_bill_qty || 0) > 0.0001 && Number(row.bill_qty || 0) > 0 && Number(row.advance_qty || 0) > 0">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                                              x-text="'Bill Pending: ' + Number(row.unmatched_bill_qty || 0).toFixed(2) + ' ' + row.unit">
                                        </span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && Number(row.diff || 0) > 0.0001 && Number(row.unmatched_bill_qty || 0) <= 0.0001 && Number(row.bill_qty || 0) > 0 && Number(row.advance_qty || 0) > 0">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200"
                                              x-text="'Advance Excess: +' + Number(row.diff || 0).toFixed(2) + ' ' + row.unit">
                                        </span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && Number(row.unmatched_bill_qty || 0) <= 0.0001 && Number(row.diff || 0) <= 0.0001 && Number(row.bill_qty || 0) > 0 && Number(row.advance_qty || 0) > 0">
                                        <span class="text-slate-500 font-medium">Discrepancy</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Printable Footer -->
        <div class="pt-4 border-t border-slate-200 flex flex-wrap items-center justify-between text-xs text-slate-500">
            <div>
                Showing <span class="font-bold text-slate-800">{{ count($rows) }}</span> discrepant item(s) for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.
            </div>
            <div class="flex items-center gap-8 mt-2 sm:mt-0">
                <div>Checked By: <span class="inline-block border-b border-slate-400 w-32 ml-1"></span></div>
                <div>Approved By: <span class="inline-block border-b border-slate-400 w-32 ml-1"></span></div>
            </div>
        </div>

    </div>
</body>
</html>
