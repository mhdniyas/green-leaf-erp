@extends('admin.cashbook.layouts.app')

@section('title', 'Manager Daily Purchase Match Summary — ' . \Carbon\Carbon::parse($date)->format('d M Y'))

@section('header_title')
    <i data-lucide="scale" class="w-5 h-5 text-emerald-600"></i> Daily Inventory Comparison
@endsection

@section('header_subtitle')
    Manager daily purchase bills, advance receives, same-day match, and pending bills summary.
@endsection

@section('content')
    <div class="mx-auto max-w-6xl space-y-4"
         x-data="{
            modalOpen: false,
            currentRow: null,
            saving: false,
            matching: false,
            matchingAll: false,
            purchaseBillsModalOpen: false,
            advanceReceivesModalOpen: false,
            pendingBillsModalOpen: false,
            otherDatesModalOpen: false,
            otherDatesLoading: false,
            otherDatesList: [],
            receivingSingleId: null,
            receivingPending: false,
            receivingDate: null,
            savingItemId: null,
            availableUnits: ['kg', 'box', 'piece', 'bunch', 'bag', 'packet', 'crate'],
            search: '',
            matchFilter: 'all',
            perPage: 50,
            currentPage: 1,
            sortColumn: 'product_code',
            sortDirection: 'asc',
            date: '{{ $date }}',
            formattedDate: '{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}',
            selectedWarehouseId: '{{ $selectedWarehouseId }}' || null,
            currentWarehouseId: '{{ $selectedWarehouseId }}',
            selectedWarehouseName: '{{ $selectedWarehouse?->name ?? 'All Warehouses' }}',
            pendingBillsCount: {{ (int) $pendingBillsCount }},
            pendingBillsList: @js($pendingBillsList),
            managerSummary: @js($managerSummary),
            rows: @js($rows),
            summary: @js($summary),
            get pendingOnlyCount() {
                return (this.rows || []).filter(r => {
                    const adv = Number(r.advance_qty || 0);
                    const matched = Number(r.matched_bill_qty || 0);
                    const pending = Math.max(0, adv - matched);
                    return r.unit_mismatch || pending > 0.0001 || (Number(r.unmatched_bill_qty || 0) > 0.0001);
                }).length;
            },
            get unitFixCount() {
                return (this.rows || []).filter(r => r.unit_mismatch).length;
            },
            get printUrl() {
                const base = '{{ route('admin.cashbook.inventory.print-unmatched') }}';
                const params = new URLSearchParams();
                if (this.date) params.set('date', this.date);
                if (this.selectedWarehouseId) params.set('warehouse_id', this.selectedWarehouseId);
                if (this.sortColumn) params.set('sort_by', this.sortColumn);
                if (this.sortDirection) params.set('sort_dir', this.sortDirection);
                return base + '?' + params.toString();
            },
            get printPendingUrl() {
                const base = '{{ route('admin.cashbook.inventory.print-pending') }}';
                const params = new URLSearchParams();
                if (this.date) params.set('date', this.date);
                if (this.selectedWarehouseId) params.set('warehouse_id', this.selectedWarehouseId);
                return base + '?' + params.toString();
            },
            sortBy(column) {
                if (this.sortColumn === column) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
                this.currentPage = 1;
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            },
            get filteredRows() {
                let list = this.rows || [];
                const q = (this.search || '').trim().toLowerCase();
                if (q) {
                    list = list.filter(r => {
                        const name = (r.product_name || '').toLowerCase();
                        const code = (r.product_code || r.sku || '').toLowerCase();
                        return name.includes(q) || code.includes(q);
                    });
                }
                if (this.matchFilter === 'pending_only') {
                    list = list.filter(r => {
                        const adv = Number(r.advance_qty || 0);
                        const matched = Number(r.matched_bill_qty || 0);
                        const pending = Math.max(0, adv - matched);
                        return r.unit_mismatch || pending > 0.0001 || (Number(r.unmatched_bill_qty || 0) > 0.0001);
                    });
                } else if (this.matchFilter === 'unit_fix') {
                    list = list.filter(r => r.unit_mismatch);
                }
                if (this.sortColumn) {
                    list = [...list].sort((a, b) => {
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
                        } else if (this.sortColumn === 'product_name') {
                            const nameA = (a.product_name || '').toLowerCase();
                            const nameB = (b.product_name || '').toLowerCase();
                            res = nameA.localeCompare(nameB);
                            if (res === 0) {
                                const codeA = String(a.product_code || a.sku || '').trim();
                                const codeB = String(b.product_code || b.sku || '').trim();
                                res = codeA.localeCompare(codeB, undefined, { numeric: true, sensitivity: 'base' });
                            }
                        } else if (this.sortColumn === 'advance_qty') {
                            res = (Number(a.advance_qty) || 0) - (Number(b.advance_qty) || 0);
                        } else if (this.sortColumn === 'bill_qty') {
                            res = (Number(a.bill_qty) || 0) - (Number(b.bill_qty) || 0);
                        } else if (this.sortColumn === 'diff') {
                            const valA = a.diff === null || a.diff === undefined ? (this.sortDirection === 'asc' ? 999999999 : -999999999) : Number(a.diff);
                            const valB = b.diff === null || b.diff === undefined ? (this.sortDirection === 'asc' ? 999999999 : -999999999) : Number(b.diff);
                            res = valA - valB;
                        } else if (this.sortColumn === 'match_pct') {
                            const valA = a.match_pct === null || a.match_pct === undefined ? -1 : Number(a.match_pct);
                            const valB = b.match_pct === null || b.match_pct === undefined ? -1 : Number(b.match_pct);
                            res = valA - valB;
                        } else if (this.sortColumn === 'stock_balance') {
                            res = (Number(a.stock_balance) || 0) - (Number(b.stock_balance) || 0);
                        }
                        return this.sortDirection === 'asc' ? res : -res;
                    });
                }
                return list;
            },
            get paginatedRows() {
                if (this.perPage === 'all' || Number(this.perPage) <= 0) {
                    return this.filteredRows;
                }
                const pSize = Number(this.perPage);
                const start = (this.currentPage - 1) * pSize;
                return this.filteredRows.slice(start, start + pSize);
            },
            get totalPages() {
                if (this.perPage === 'all' || Number(this.perPage) <= 0) {
                    return 1;
                }
                return Math.ceil(this.filteredRows.length / Number(this.perPage)) || 1;
            },
            formatNumber(val) {
                if (val === null || val === undefined) return '0';
                const num = parseFloat(val);
                if (isNaN(num)) return '0';
                return Number.isInteger(num) ? num.toString() : parseFloat(num.toFixed(3)).toString();
            },
            openModal(row) {
                this.currentRow = row;
                this.modalOpen = true;
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            },
            async fetchOtherDates() {
                this.otherDatesLoading = true;
                this.otherDatesModalOpen = true;
                try {
                    const url = '{{ route('admin.cashbook.inventory.pending-bills-days') }}' + (this.selectedWarehouseId ? '?warehouse_id=' + this.selectedWarehouseId : '');
                    const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        this.otherDatesList = data.data.days || [];
                    } else {
                        this.otherDatesList = [];
                    }
                } catch (e) {
                    this.otherDatesList = [];
                } finally {
                    this.otherDatesLoading = false;
                    this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
                }
            },
            async receiveSingle(bill) {
                const key = bill.type + '_' + bill.id;
                if (this.receivingSingleId === key) return;
                this.receivingSingleId = key;
                try {
                    const res = await fetch('{{ route('admin.cashbook.inventory.receive-single-bill') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            type: bill.type,
                            id: bill.id,
                            warehouse_id: this.selectedWarehouseId,
                            date: this.date
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        this.pendingBillsList = this.pendingBillsList.filter(b => !(b.type === bill.type && b.id === bill.id));
                        this.pendingBillsCount = this.pendingBillsList.length;
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to receive bill');
                        this.receivingSingleId = null;
                    }
                } catch (e) {
                    alert('Error receiving bill: ' + e.message);
                    this.receivingSingleId = null;
                }
            },
            async receiveAllPending() {
                if (this.pendingBillsCount <= 0 || this.receivingPending) return;
                const count = this.pendingBillsCount;
                const confirmMsg = 'Receive all ' + count + ' pending purchase bills for ' + this.formattedDate + ' / ' + this.selectedWarehouseName + '?';
                if (!confirm(confirmMsg)) {
                    return;
                }
                this.receivingPending = true;
                try {
                    const res = await fetch('{{ route('admin.cashbook.inventory.receive-all-pending-bills') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            date: this.date,
                            warehouse_id: this.selectedWarehouseId
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to receive pending bills');
                        this.receivingPending = false;
                    }
                } catch (e) {
                    alert('Error receiving pending bills: ' + e.message);
                    this.receivingPending = false;
                }
            },
            async receiveDateInModal(dateStr, count) {
                if (this.receivingDate) return;
                const targetWarehouseName = this.selectedWarehouseName || 'All Warehouses';
                const confirmMsg = 'Receive all ' + count + ' pending purchase bills for ' + dateStr + ' (' + targetWarehouseName + ')?';
                if (!confirm(confirmMsg)) {
                    return;
                }
                this.receivingDate = dateStr;
                try {
                    const res = await fetch('{{ route('admin.cashbook.inventory.receive-all-pending-bills') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            date: dateStr,
                            warehouse_id: this.selectedWarehouseId
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        if (dateStr === this.date) {
                            window.location.reload();
                        } else {
                            await this.fetchOtherDates();
                        }
                    } else {
                        alert(data.message || 'Failed to receive pending bills for ' + dateStr);
                    }
                } catch (e) {
                    alert('Error receiving pending bills: ' + e.message);
                } finally {
                    this.receivingDate = null;
                    this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
                }
            },
            async matchRow(row) {
                if (!row || !row.product_id) return;
                this.matching = true;
                try {
                    const res = await fetch('{{ route('admin.cashbook.inventory.match-day') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            date: this.date,
                            warehouse_id: this.selectedWarehouseId,
                            product_id: row.product_id,
                            unit: row.unit
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to match inventory');
                        this.matching = false;
                    }
                } catch (e) {
                    alert('Error matching inventory: ' + e.message);
                    this.matching = false;
                }
            },
            async matchAll() {
                this.matchingAll = true;
                try {
                    const res = await fetch('{{ route('admin.cashbook.inventory.match-all-day') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            date: this.date,
                            warehouse_id: this.selectedWarehouseId
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        alert(data.message || 'Match All completed successfully');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to execute Match All');
                        this.matchingAll = false;
                    }
                } catch (e) {
                    alert('Error executing Match All: ' + e.message);
                    this.matchingAll = false;
                }
            },
            sharePendingWhatsApp() {
                const pendingItems = this.managerSummary?.pending_bills_after_match?.products || [];
                if (pendingItems.length === 0) {
                    alert('All bills are fully matched for this date and warehouse!');
                    return;
                }

                const lines = [
                    'Green Leaf - Pending Bills',
                    'Date: ' + this.formattedDate,
                    'Warehouse: ' + this.selectedWarehouseName,
                    '',
                    'Product               Pending'
                ];

                for (const item of pendingItems) {
                    const padLength = Math.max(1, 22 - (item.product_name || '').length);
                    const spaces = ' '.repeat(padLength);
                    lines.push(`${item.product_name}${spaces}${item.pending_qty}`);
                }

                lines.push('');
                lines.push('Pending Products: ' + pendingItems.length);

                const message = lines.join('\n');
                window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(message), '_blank', 'noopener');
            },
            getUnitsForItem(item) {
                const list = [...this.availableUnits];
                if (item && item.unit) {
                    const uTrim = item.unit.trim().toLowerCase();
                    if (uTrim && !list.map(u => u.toLowerCase()).includes(uTrim)) {
                        list.push(item.unit.trim());
                    }
                }
                return list;
            },
            async toggleItemUnit(item, targetUnit) {
                if (!item || !targetUnit) return;
                const currentUnit = (item.unit || '').trim().toLowerCase();
                const newUnit = targetUnit.trim().toLowerCase();
                if (currentUnit === newUnit || this.saving) return;

                this.saving = true;
                this.savingItemId = item.id;
                try {
                    const res = await fetch('{{ route('admin.cashbook.inventory.update-item-unit') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            goods_received_item_id: item.id,
                            new_unit: targetUnit
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        item.unit = targetUnit;
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to update unit');
                        this.saving = false;
                        this.savingItemId = null;
                    }
                } catch (e) {
                    alert('Error updating unit: ' + e.message);
                    this.saving = false;
                    this.savingItemId = null;
                }
            }
         }">

        <!-- 1. Date + Warehouse Navigation -->
        <div class="flex flex-wrap items-center justify-between gap-3 py-1">
            <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
                <a href="{{ route('admin.cashbook.inventory', array_filter(['date' => $prevDate, 'warehouse_id' => $selectedWarehouseId])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                    <i data-lucide="chevron-left" class="w-4 h-4 text-slate-500"></i>
                    <span class="hidden sm:inline">Prev</span>
                </a>

                <input type="date"
                       name="date"
                       value="{{ $date }}"
                       onchange="this.form.submit()"
                       class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-black text-slate-900 shadow-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 cursor-pointer">

                <a href="{{ route('admin.cashbook.inventory', array_filter(['date' => $nextDate, 'warehouse_id' => $selectedWarehouseId])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                    <span class="hidden sm:inline">Next</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-500"></i>
                </a>

                <!-- Warehouse Dropdown -->
                <div class="relative flex items-center">
                    <select name="warehouse_id"
                            onchange="this.form.submit()"
                            class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-800 shadow-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 cursor-pointer">
                        <option value="">All Warehouses</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected((string)$selectedWarehouseId === (string)$wh->id)>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <!-- 2. TOP MANAGER SUMMARY CARDS (4 Compact Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Card A: Purchase Bills -->
            <div @click="purchaseBillsModalOpen = true; $nextTick(() => { if (window.lucide) lucide.createIcons(); });"
                 class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 hover:shadow-sm transition cursor-pointer flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Purchase Bills</span>
                        <span class="p-1 rounded-lg bg-slate-100 text-slate-600">
                            <i data-lucide="receipt" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black font-mono text-slate-900" x-text="managerSummary?.purchase_bills?.count || 0"></span>
                        <span class="text-xs font-bold text-slate-500">bills</span>
                        <span class="text-slate-300">·</span>
                        <span class="text-xs font-bold text-slate-600" x-text="(managerSummary?.purchase_bills?.products_count || 0) + ' products'"></span>
                    </div>
                </div>
                <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="font-mono font-bold text-slate-700 truncate" x-text="managerSummary?.purchase_bills?.formatted_totals || '0'"></span>
                    <span class="text-[11px] font-bold text-emerald-700 flex items-center gap-0.5 shrink-0 ml-1">
                        <span>Details</span>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
            </div>

            <!-- Card B: Advance Receives -->
            <div @click="advanceReceivesModalOpen = true; $nextTick(() => { if (window.lucide) lucide.createIcons(); });"
                 class="rounded-2xl border border-indigo-200 bg-indigo-50/30 p-4 shadow-xs hover:border-indigo-300 hover:shadow-sm transition cursor-pointer flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-800">Advance Receives</span>
                        <span class="p-1 rounded-lg bg-indigo-100 text-indigo-700">
                            <i data-lucide="truck" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black font-mono text-indigo-950" x-text="managerSummary?.advance_receives?.count || 0"></span>
                        <span class="text-xs font-bold text-indigo-800">GRNs</span>
                        <span class="text-indigo-300">·</span>
                        <span class="text-xs font-bold text-indigo-800" x-text="(managerSummary?.advance_receives?.products_count || 0) + ' products'"></span>
                    </div>
                </div>
                <div class="mt-3 pt-2.5 border-t border-indigo-100/80 flex items-center justify-between text-xs">
                    <span class="font-mono font-bold text-indigo-900 truncate" x-text="managerSummary?.advance_receives?.formatted_totals || '0'"></span>
                    <span class="text-[11px] font-bold text-indigo-700 flex items-center gap-0.5 shrink-0 ml-1">
                        <span>Details</span>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
            </div>

            <!-- Card C: Pending Bills After Match (MAIN CARD) -->
            <div @click="pendingBillsModalOpen = true; $nextTick(() => { if (window.lucide) lucide.createIcons(); });"
                 class="rounded-2xl border p-4 shadow-xs hover:shadow-sm transition cursor-pointer flex flex-col justify-between relative"
                 :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'border-amber-300 bg-amber-50/50' : 'border-emerald-300 bg-emerald-50/50'">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider"
                              :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'text-amber-900' : 'text-emerald-900'">
                            Pending Bills After Match
                        </span>
                        <span class="p-1 rounded-lg"
                              :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'bg-amber-200 text-amber-900' : 'bg-emerald-200 text-emerald-900'">
                            <i :data-lucide="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'alert-circle' : 'check-circle-2'" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div class="mt-2">
                        <div class="text-base font-black font-mono leading-snug"
                             :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'text-amber-950' : 'text-emerald-950'"
                             x-text="managerSummary?.pending_bills_after_match?.formatted_totals || '0'"></div>
                        <div class="mt-1 flex items-center gap-2 text-xs font-semibold"
                             :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'text-amber-800' : 'text-emerald-800'">
                            <span>Pending Products: <strong class="font-mono text-sm" x-text="managerSummary?.pending_bills_after_match?.pending_products_count || 0"></strong></span>
                            <span>·</span>
                            <span>Cleared: <strong class="font-mono" x-text="managerSummary?.pending_bills_after_match?.formatted_advance_cleared_pct || 'N/A'"></strong></span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-2.5 border-t flex items-center justify-between text-xs"
                     :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'border-amber-200' : 'border-emerald-200'">
                    <span class="text-[11px] font-bold"
                          :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'text-amber-800' : 'text-emerald-800'">
                        <span x-text="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'Missing bills detected' : 'All advance matched ✓'"></span>
                    </span>
                    <span class="text-[11px] font-black flex items-center gap-0.5"
                          :class="(managerSummary?.pending_bills_after_match?.pending_products_count || 0) > 0 ? 'text-amber-900' : 'text-emerald-900'">
                        <span>View 2-Col List</span>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
            </div>

            <!-- Card D: Unit Fix Required -->
            <div @click="matchFilter = 'unit_fix'; currentPage = 1"
                 class="rounded-2xl border p-4 shadow-xs hover:shadow-sm transition cursor-pointer flex flex-col justify-between"
                 :class="(managerSummary?.unit_fix_required?.count || 0) > 0 ? 'border-rose-300 bg-rose-50/50' : 'border-slate-200 bg-white'">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider"
                              :class="(managerSummary?.unit_fix_required?.count || 0) > 0 ? 'text-rose-900' : 'text-slate-500'">
                            Unit Fix Required
                        </span>
                        <span class="p-1 rounded-lg"
                              :class="(managerSummary?.unit_fix_required?.count || 0) > 0 ? 'bg-rose-200 text-rose-900' : 'bg-slate-100 text-slate-600'">
                            <i data-lucide="wrench" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black font-mono"
                              :class="(managerSummary?.unit_fix_required?.count || 0) > 0 ? 'text-rose-950' : 'text-slate-900'"
                              x-text="managerSummary?.unit_fix_required?.count || 0"></span>
                        <span class="text-xs font-bold"
                              :class="(managerSummary?.unit_fix_required?.count || 0) > 0 ? 'text-rose-800' : 'text-slate-500'">
                            products
                        </span>
                    </div>
                </div>
                <div class="mt-3 pt-2.5 border-t flex items-center justify-between text-xs"
                     :class="(managerSummary?.unit_fix_required?.count || 0) > 0 ? 'border-rose-200 text-rose-800 font-bold' : 'border-slate-100 text-slate-500'">
                    <span x-text="(managerSummary?.unit_fix_required?.count || 0) > 0 ? 'Click to filter unit mismatches' : 'All units matched'"></span>
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                </div>
            </div>
        </div>

        <!-- 3. ACTIONS ROW -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Action 1: Receive All Pending -->
                <button type="button"
                        @click="receiveAllPending()"
                        :disabled="receivingPending || pendingBillsCount === 0"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="package-check" class="w-4 h-4" :class="receivingPending ? 'animate-spin' : ''"></i>
                    <span x-text="receivingPending ? 'Receiving...' : ('Receive All Pending (' + pendingBillsCount + ')')"></span>
                </button>

                <!-- Action 2: Match All (Recovery Only) -->
                <button type="button"
                        @click="matchAll()"
                        :disabled="matchingAll"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50"
                        title="Re-run same-day auto match for all eligible products">
                    <i data-lucide="refresh-cw" class="w-4 h-4" :class="matchingAll ? 'animate-spin' : ''"></i>
                    <span x-text="matchingAll ? 'Matching...' : 'Match All (Recovery)'"></span>
                </button>

                <!-- Action 3: Share Pending -->
                <button type="button"
                        @click="sharePendingWhatsApp()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 text-xs font-bold transition shadow-xs cursor-pointer">
                    <i data-lucide="message-circle" class="w-4 h-4 text-emerald-600"></i>
                    <span>Share Pending (WhatsApp)</span>
                </button>

                <a :href="printPendingUrl"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer"
                   title="Print or PDF 2-column pending list">
                    <i data-lucide="printer" class="w-4 h-4 text-slate-600"></i>
                    <span>PDF / Print Pending</span>
                </a>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        @click="fetchOtherDates()"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold transition shadow-xs cursor-pointer">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span>Other Pending Dates</span>
                </button>

                <a :href="printUrl"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition shadow-xs cursor-pointer"
                   title="Print full comparison with discrepancies">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Print Discrepancies</span>
                </a>
            </div>
        </div>

        <!-- 4. SEARCH / FILTER BAR -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
                <!-- Search Box -->
                <div class="relative flex-1 sm:w-60 min-w-[200px]">
                    <input type="text"
                           x-model="search"
                           @input="currentPage = 1"
                           placeholder="Search product or code..."
                           class="w-full pl-8 pr-7 py-2 rounded-xl bg-white border border-slate-200 text-xs font-medium text-slate-900 shadow-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <button type="button"
                            x-show="search"
                            @click="search = ''; currentPage = 1"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold">✕</button>
                </div>

                <!-- Match Filter: [ All ] [ Pending Only ] [ Unit Fix ] -->
                <div class="inline-flex items-center rounded-xl bg-slate-100 p-0.5 border border-slate-200 text-xs font-bold">
                    <button type="button"
                            @click="matchFilter = 'all'; currentPage = 1"
                            class="px-3 py-1.5 rounded-lg transition"
                            :class="matchFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        All (<span x-text="(rows || []).length"></span>)
                    </button>
                    <button type="button"
                            @click="matchFilter = 'pending_only'; currentPage = 1"
                            class="px-3 py-1.5 rounded-lg transition flex items-center gap-1"
                            :class="matchFilter === 'pending_only' ? 'bg-amber-50 text-amber-900 font-black shadow-xs border border-amber-200' : 'text-slate-600 hover:text-slate-900'">
                        <span>Pending Only</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                              :class="matchFilter === 'pending_only' ? 'bg-amber-200 text-amber-950 font-black' : 'bg-slate-200 text-slate-700'"
                              x-text="pendingOnlyCount"></span>
                    </button>
                    <button type="button"
                            @click="matchFilter = 'unit_fix'; currentPage = 1"
                            class="px-3 py-1.5 rounded-lg transition flex items-center gap-1"
                            :class="matchFilter === 'unit_fix' ? 'bg-rose-50 text-rose-900 font-black shadow-xs border border-rose-200' : 'text-slate-600 hover:text-slate-900'">
                        <span>Unit Fix</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                              :class="matchFilter === 'unit_fix' ? 'bg-rose-200 text-rose-950 font-black' : 'bg-slate-200 text-slate-700'"
                              x-text="unitFixCount"></span>
                    </button>
                </div>

                <!-- Per Page Selector -->
                <div class="flex items-center gap-1.5 text-xs text-slate-600">
                    <span class="text-[11px] font-bold text-slate-500">Per page:</span>
                    <select x-model="perPage"
                            @change="currentPage = 1"
                            class="px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-800 shadow-xs focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 5. MAIN COMPARISON TABLE -->
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 select-none">
                            <th scope="col" class="py-3 px-3 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-center w-12">
                                #
                            </th>
                            <th scope="col" @click="sortBy('product_code')" class="py-3 px-3 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-left cursor-pointer hover:bg-slate-100/80 transition w-24">
                                <div class="flex items-center gap-1.5">
                                    <span>Code</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none">
                                        <span :class="sortColumn === 'product_code' && sortDirection === 'asc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'product_code' && sortDirection === 'desc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('product_name')" class="py-3 px-4 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-left cursor-pointer hover:bg-slate-100/80 transition">
                                <div class="flex items-center gap-1.5">
                                    <span>Product</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none">
                                        <span :class="sortColumn === 'product_name' && sortDirection === 'asc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'product_name' && sortDirection === 'desc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('advance_qty')" class="py-3 px-4 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-right cursor-pointer hover:bg-slate-100/80 transition">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Advance</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none">
                                        <span :class="sortColumn === 'advance_qty' && sortDirection === 'asc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'advance_qty' && sortDirection === 'desc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('bill_qty')" class="py-3 px-4 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-right cursor-pointer hover:bg-slate-100/80 transition">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Bill</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none">
                                        <span :class="sortColumn === 'bill_qty' && sortDirection === 'asc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'bill_qty' && sortDirection === 'desc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('diff')" class="py-3 px-4 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-right cursor-pointer hover:bg-slate-100/80 transition">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Diff</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none">
                                        <span :class="sortColumn === 'diff' && sortDirection === 'asc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'diff' && sortDirection === 'desc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('match_pct')" class="py-3 px-4 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-right cursor-pointer hover:bg-slate-100/80 transition">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Match %</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none">
                                        <span :class="sortColumn === 'match_pct' && sortDirection === 'asc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'match_pct' && sortDirection === 'desc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" @click="sortBy('stock_balance')" class="py-3 px-4 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-right cursor-pointer hover:bg-slate-100/80 transition">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Inv Balance</span>
                                    <span class="inline-flex flex-col text-[8px] leading-none">
                                        <span :class="sortColumn === 'stock_balance' && sortDirection === 'asc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▲</span>
                                        <span :class="sortColumn === 'stock_balance' && sortDirection === 'desc' ? 'text-emerald-600 font-black' : 'text-slate-300'">▼</span>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" class="py-3 px-4 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-center w-36">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        <template x-for="(row, index) in paginatedRows" :key="row.product_id + '-' + row.unit">
                            <tr class="hover:bg-slate-50/60 transition-colors" :class="row.unit_mismatch ? 'bg-amber-50/30' : ''">
                                <!-- Sl No -->
                                <td class="py-2.5 px-3 text-center font-mono text-slate-400 font-bold"
                                    x-text="(perPage === 'all' ? 0 : (currentPage - 1) * Number(perPage)) + index + 1"></td>

                                <!-- Code -->
                                <td class="py-2.5 px-3 font-mono text-[11px] font-bold text-slate-700 whitespace-nowrap">
                                    <template x-if="row.product_code || row.sku">
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200"
                                              x-text="row.product_code || row.sku"></span>
                                    </template>
                                    <template x-if="!row.product_code && !row.sku">
                                        <span class="text-slate-300">—</span>
                                    </template>
                                </td>

                                <!-- Product Name -->
                                <td class="py-2.5 px-4 font-semibold text-slate-900">
                                    <span x-text="row.product_name"></span>
                                </td>

                                <!-- Advance -->
                                <td class="py-2.5 px-4 text-right font-mono text-slate-700" x-text="row.formatted_advance"></td>

                                <!-- Bill -->
                                <td class="py-2.5 px-4 text-right font-mono text-slate-700" x-text="row.formatted_bill"></td>

                                <!-- Diff -->
                                <td class="py-2.5 px-4 text-right font-mono font-bold">
                                    <template x-if="row.unit_mismatch">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            Unit Mismatch
                                        </span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && row.diff > 0.0001">
                                        <span class="text-emerald-700" x-text="row.formatted_diff"></span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && row.diff < -0.0001">
                                        <span class="text-rose-700" x-text="row.formatted_diff"></span>
                                    </template>
                                    <template x-if="!row.unit_mismatch && Math.abs(row.diff || 0) <= 0.0001">
                                        <span class="text-slate-500">0</span>
                                    </template>
                                </td>

                                <!-- Match % -->
                                <td class="py-2.5 px-4 text-right font-mono font-bold">
                                    <template x-if="row.unit_mismatch">
                                        <span class="text-slate-400">--</span>
                                    </template>
                                    <template x-if="!row.unit_mismatch">
                                        <span :class="row.match_pct >= 100 ? 'text-emerald-700' : (row.match_pct > 0 ? 'text-amber-700' : 'text-slate-500')"
                                              x-text="row.formatted_match_pct"></span>
                                    </template>
                                </td>

                                <!-- Inventory Balance -->
                                <td class="py-2.5 px-4 text-right font-mono font-bold">
                                    <span :class="Number(row.stock_balance) < 0 ? 'text-rose-600' : (Number(row.stock_balance) > 0 ? 'text-slate-900' : 'text-slate-400')"
                                          x-text="row.formatted_stock_balance"></span>
                                </td>

                                <!-- Action -->
                                <td class="py-2.5 px-4 text-center">
                                    <template x-if="row.action_type === 'fix_unit'">
                                        <button type="button"
                                                 @click="openModal(row)"
                                                 class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold transition shadow-2xs cursor-pointer">
                                             <i data-lucide="wrench" class="w-3.5 h-3.5"></i>
                                             <span>Fix Unit</span>
                                         </button>
                                    </template>
                                    <template x-if="row.action_type === 'matched'">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                             <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i>
                                             <span>Matched</span>
                                         </span>
                                    </template>
                                    <template x-if="row.action_type === 'match'">
                                        <button type="button"
                                                @click="matchRow(row)"
                                                :disabled="matching"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-2xs cursor-pointer disabled:opacity-50">
                                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                            <span>Match</span>
                                        </button>
                                    </template>
                                    <template x-if="row.action_type === 'match_remaining'">
                                        <button type="button"
                                                @click="matchRow(row)"
                                                :disabled="matching"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-2xs cursor-pointer disabled:opacity-50">
                                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                            <span>Match Remaining</span>
                                        </button>
                                    </template>
                                    <template x-if="row.action_type === 'none'">
                                        <span class="text-slate-300 font-bold">-</span>
                                    </template>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="filteredRows.length === 0">
                            <td colspan="9" class="py-8 text-center text-slate-400 font-semibold">
                                <span x-show="search">No products matching "<span x-text="search"></span>".</span>
                                <span x-show="!search">No receipts or advance entries recorded for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div x-show="filteredRows.length > 0" class="border-t border-slate-200 bg-slate-50/50 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="text-slate-500">
                    Showing
                    <span class="font-bold text-slate-800" x-text="perPage === 'all' ? 1 : Math.min((currentPage - 1) * Number(perPage) + 1, filteredRows.length)"></span>
                    to
                    <span class="font-bold text-slate-800" x-text="perPage === 'all' ? filteredRows.length : Math.min(currentPage * Number(perPage), filteredRows.length)"></span>
                    of
                    <span class="font-bold text-slate-800" x-text="filteredRows.length"></span>
                    products
                </div>

                <div x-show="totalPages > 1" class="flex items-center gap-1">
                    <button type="button"
                            @click="currentPage = Math.max(1, currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">
                        Prev
                    </button>
                    <span class="px-2 text-slate-500 font-medium">
                        Page <span class="font-bold text-slate-800" x-text="currentPage"></span> of <span class="font-bold text-slate-800" x-text="totalPages"></span>
                    </span>
                    <button type="button"
                            @click="currentPage = Math.min(totalPages, currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Pending Bills Details (2-COLUMN ONLY: Product | Pending) -->
        <div x-show="pendingBillsModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="pendingBillsModalOpen = false" class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-slate-200 flex flex-col max-h-[85vh] overflow-hidden">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 bg-amber-50/70 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600"></i>
                            <span>Pending Bills After Match</span>
                        </h3>
                        <div class="flex items-center gap-2 mt-1 flex-wrap text-xs text-slate-600">
                            <span>Date: <strong class="text-slate-900" x-text="formattedDate"></strong></span>
                            <span>·</span>
                            <span>Warehouse: <strong class="text-slate-900" x-text="selectedWarehouseName"></strong></span>
                            <span>·</span>
                            <span>Pending Products: <strong class="text-amber-800 font-black" x-text="managerSummary?.pending_bills_after_match?.pending_products_count || 0"></strong></span>
                        </div>
                    </div>
                    <button type="button" @click="pendingBillsModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">✕</button>
                </div>

                <!-- 2-Column Table Content: Product | Pending -->
                <div class="p-4 overflow-y-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 select-none">
                                <th class="py-2.5 px-4 font-black text-slate-800 text-xs">Product</th>
                                <th class="py-2.5 px-4 font-black text-slate-800 text-xs text-right w-36">Pending</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                            <template x-for="p in (managerSummary?.pending_bills_after_match?.products || [])" :key="p.product_name + '-' + p.unit">
                                <tr class="hover:bg-slate-50/70 transition" :class="p.unit_mismatch ? 'bg-amber-50/30' : ''">
                                    <td class="py-2.5 px-4 font-semibold text-slate-900">
                                        <span x-text="p.product_name"></span>
                                        <template x-if="p.unit_mismatch">
                                            <span class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 font-bold border border-amber-200">Unit Fix Required</span>
                                        </template>
                                    </td>
                                    <td class="py-2.5 px-4 font-black font-mono text-slate-900 text-right" x-text="p.pending_qty"></td>
                                </tr>
                            </template>

                            <tr x-show="(managerSummary?.pending_bills_after_match?.products || []).length === 0">
                                <td colspan="2" class="py-8 text-center text-slate-500 font-bold">
                                    No pending bills. All advance stock has been matched! ✓
                                </td>
                            </tr>
                        </tbody>
                        <tfoot x-show="(managerSummary?.pending_bills_after_match?.products || []).length > 0">
                            <tr class="border-t-2 border-slate-200 bg-slate-50 font-black">
                                <td class="py-2.5 px-4 text-slate-900">
                                    Pending Products: <span x-text="managerSummary?.pending_bills_after_match?.pending_products_count || 0"></span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-slate-900" x-text="managerSummary?.pending_bills_after_match?.formatted_totals || '0'"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Modal Footer with Share Actions -->
                <div class="px-6 py-3.5 border-t border-slate-200 bg-slate-50/70 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="sharePendingWhatsApp()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                            <span>Share WhatsApp</span>
                        </button>
                        <a :href="printPendingUrl"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-800 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                            <i data-lucide="printer" class="w-3.5 h-3.5 text-slate-600"></i>
                            <span>Print / PDF</span>
                        </a>
                    </div>
                    <button type="button"
                            @click="pendingBillsModalOpen = false"
                            class="px-3.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Purchase Bills Details Popup -->
        <div x-show="purchaseBillsModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="purchaseBillsModalOpen = false" class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 flex flex-col max-h-[85vh] overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="receipt" class="w-4 h-4 text-emerald-600"></i>
                            <span>Today Purchase Bills</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Purchase Bills & POs for <strong class="text-slate-800" x-text="formattedDate"></strong> / <span x-text="selectedWarehouseName"></span>
                        </p>
                    </div>
                    <button type="button" @click="purchaseBillsModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">✕</button>
                </div>

                <div class="p-4 overflow-y-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 select-none">
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] w-32">Bill / PO</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px]">Supplier</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] text-center w-24">Products</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] text-right">Quantity Totals</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] text-center w-28">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                            <template x-for="item in (managerSummary?.purchase_bills?.items || [])" :key="item.type + '-' + item.id">
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-2.5 px-3 font-mono font-bold text-slate-900" x-text="item.bill_number"></td>
                                    <td class="py-2.5 px-3 font-medium text-slate-700" x-text="item.supplier_name"></td>
                                    <td class="py-2.5 px-3 text-center font-mono" x-text="item.products_count"></td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" x-text="item.quantity_totals"></td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black"
                                              :class="item.status === 'Received' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200'"
                                              x-text="item.status"></span>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="(managerSummary?.purchase_bills?.items || []).length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-400 font-semibold">
                                    No purchase bills recorded for this date and warehouse.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3 border-t border-slate-200 bg-slate-50/70 flex justify-end">
                    <button type="button" @click="purchaseBillsModalOpen = false" class="px-4 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Advance Receives Details Popup -->
        <div x-show="advanceReceivesModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="advanceReceivesModalOpen = false" class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 flex flex-col max-h-[85vh] overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-indigo-50/70 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="truck" class="w-4 h-4 text-indigo-600"></i>
                            <span>Today Advance Receives</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Advance GRNs for <strong class="text-slate-800" x-text="formattedDate"></strong> / <span x-text="selectedWarehouseName"></span>
                        </p>
                    </div>
                    <button type="button" @click="advanceReceivesModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">✕</button>
                </div>

                <div class="p-4 overflow-y-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 select-none">
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] w-36">Advance GRN</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] text-center w-24">Products</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] text-right">Quantity Totals</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] text-center w-36">Received Time</th>
                                <th class="py-2.5 px-3 font-bold text-slate-600 text-[11px] text-center w-24">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                            <template x-for="item in (managerSummary?.advance_receives?.items || [])" :key="item.id">
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-2.5 px-3 font-mono font-bold text-slate-900" x-text="item.grn_number"></td>
                                    <td class="py-2.5 px-3 text-center font-mono" x-text="item.products_count"></td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-indigo-950" x-text="item.quantity_totals"></td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-600" x-text="item.received_time || '—'"></td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black bg-indigo-100 text-indigo-800 border border-indigo-200"
                                              x-text="item.status"></span>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="(managerSummary?.advance_receives?.items || []).length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-400 font-semibold">
                                    No advance receipts recorded for this date and warehouse.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3 border-t border-slate-200 bg-slate-50/70 flex justify-end">
                    <button type="button" @click="advanceReceivesModalOpen = false" class="px-4 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Other Pending Dates Popup -->
        <div x-show="otherDatesModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="otherDatesModalOpen = false" class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 flex flex-col max-h-[85vh] overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4 text-slate-600"></i>
                            <span>Other Pending Dates</span>
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Dates with pending purchase bills for <span class="font-bold text-slate-700" x-text="selectedWarehouseName"></span>
                        </p>
                    </div>
                    <button type="button" @click="otherDatesModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">✕</button>
                </div>

                <div class="p-4 overflow-y-auto flex-1">
                    <div x-show="otherDatesLoading" class="py-8 text-center text-slate-400 font-semibold flex items-center justify-center gap-2">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-indigo-600"></i>
                        <span>Loading pending dates...</span>
                    </div>

                    <div x-show="!otherDatesLoading">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 select-none">
                                    <th class="py-2 px-3 font-bold text-slate-600 text-[11px]">Date</th>
                                    <th class="py-2 px-3 font-bold text-slate-600 text-[11px] text-center">Pending Bills</th>
                                    <th class="py-2 px-3 font-bold text-slate-600 text-[11px] text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                                <template x-for="d in otherDatesList" :key="d.date">
                                    <tr class="hover:bg-slate-50 transition" :class="d.date === date ? 'bg-indigo-50/40 font-bold' : ''">
                                        <td class="py-2.5 px-3 font-mono">
                                            <a :href="'{{ route('admin.cashbook.inventory') }}?date=' + d.date + (selectedWarehouseId ? '&warehouse_id=' + selectedWarehouseId : '')"
                                               class="text-indigo-600 hover:text-indigo-900 hover:underline font-bold inline-flex items-center gap-1"
                                               title="Open inventory for this date">
                                                <span x-text="d.formatted_date"></span>
                                                <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                            </a>
                                            <template x-if="d.date === date">
                                                <span class="ml-1.5 px-1.5 py-0.2 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800">Current</span>
                                            </template>
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-mono font-bold text-amber-700" x-text="d.pending_count"></td>
                                        <td class="py-2.5 px-3 text-right">
                                            <button type="button"
                                                    @click="receiveDateInModal(d.date, d.pending_count)"
                                                    :disabled="receivingDate === d.date"
                                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50">
                                                <i data-lucide="package-check" class="w-3.5 h-3.5" :class="receivingDate === d.date ? 'animate-spin' : ''"></i>
                                                <span x-text="receivingDate === d.date ? 'Receiving...' : 'Receive All'"></span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <tr x-show="otherDatesList.length === 0">
                                    <td colspan="3" class="py-8 text-center text-slate-400 font-semibold">
                                        No pending dates found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-3 border-t border-slate-200 bg-slate-50/70 flex justify-end">
                    <button type="button" @click="otherDatesModalOpen = false" class="px-4 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Fix Unit Modal -->
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="modalOpen = false" class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="wrench" class="w-4 h-4 text-indigo-600"></i>
                            <span>Fix Received Unit</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5" x-text="currentRow?.product_code ? `${currentRow.product_code} · ${currentRow.product_name}` : currentRow?.product_name"></p>
                    </div>
                    <button type="button" @click="modalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    <template x-for="item in currentRow?.editable_items || []" :key="item.id">
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="min-w-[140px]">
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                          :class="item.type === 'Advance' ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200'"
                                          x-text="item.type"></span>
                                    <span class="font-bold text-slate-900" x-text="item.grn_number"></span>
                                </div>
                                <div class="text-slate-500 font-mono mt-1">
                                    Qty: <span class="font-bold text-slate-800" x-text="item.qty"></span> <span class="font-black text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100" x-text="item.unit"></span>
                                </div>
                            </div>

                            <!-- Toggle Pill Buttons -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <template x-for="u in getUnitsForItem(item)" :key="u">
                                    <button type="button"
                                            @click="toggleItemUnit(item, u)"
                                            :disabled="saving"
                                            :title="'Set unit to ' + u"
                                            :class="item.unit.toLowerCase() === u.toLowerCase()
                                                ? 'bg-indigo-600 text-white font-bold shadow-xs ring-2 ring-indigo-300 ring-offset-1 cursor-default'
                                                : 'bg-white hover:bg-slate-100 hover:text-indigo-700 text-slate-700 border border-slate-200 hover:border-indigo-300 cursor-pointer'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition disabled:opacity-50 inline-flex items-center gap-1">
                                        <i data-lucide="loader-2" x-show="savingItemId === item.id && item.unit.toLowerCase() !== u.toLowerCase()" class="w-3 h-3 animate-spin text-indigo-500"></i>
                                        <i data-lucide="check" x-show="item.unit.toLowerCase() === u.toLowerCase() && savingItemId !== item.id" class="w-3 h-3 text-white"></i>
                                        <span x-text="u"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <p class="text-[11px] text-slate-400">Click any unit to instantly toggle and update.</p>
                    <button type="button" @click="modalOpen = false" class="px-4 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
