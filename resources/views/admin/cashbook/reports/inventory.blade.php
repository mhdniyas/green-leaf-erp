@extends('admin.cashbook.layouts.app')

@section('title', 'Daily Inventory Comparison — ' . \Carbon\Carbon::parse($date)->format('d M Y'))

@section('header_title')
    <i data-lucide="scale" class="w-5 h-5 text-emerald-600"></i> Daily Inventory Comparison
@endsection

@section('header_subtitle')
    Daily warehouse Advance vs Purchase Bill received comparison by product and unit.
@endsection

@section('content')
    <div class="mx-auto max-w-6xl space-y-4"
         x-data="{
            modalOpen: false,
            currentRow: null,
            saving: false,
            matching: false,
            matchingAll: false,
            pendingDetailsOpen: false,
            otherDatesModalOpen: false,
            otherDatesLoading: false,
            otherDatesList: [],
            receivingSingleId: null,
            receivingPending: false,
            receivingDate: null,
            search: '',
            perPage: 50,
            currentPage: 1,
            sortColumn: 'product_name',
            sortDirection: 'asc',
            date: '{{ $date }}',
            formattedDate: '{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}',
            selectedWarehouseId: '{{ $selectedWarehouseId }}' || null,
            selectedWarehouseName: '{{ $selectedWarehouse?->name ?? 'All Warehouses' }}',
            pendingBillsCount: {{ (int) $pendingBillsCount }},
            pendingBillsList: @js($pendingBillsList),
            rows: @js($rows),
            summary: @js($summary),
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
                if (this.sortColumn) {
                    list = [...list].sort((a, b) => {
                        let res = 0;
                        if (this.sortColumn === 'product_name') {
                            const nameA = ((a.product_code || a.sku || '') + ' ' + (a.product_name || '')).toLowerCase();
                            const nameB = ((b.product_code || b.sku || '') + ' ' + (b.product_name || '')).toLowerCase();
                            res = nameA.localeCompare(nameB);
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
                        // Remove from pending list
                        this.pendingBillsList = this.pendingBillsList.filter(b => !(b.type === bill.type && b.id === bill.id));
                        this.pendingBillsCount = this.pendingBillsList.length;
                        if (this.pendingBillsList.length === 0) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Bill received successfully');
                            window.location.reload();
                        }
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
                        alert(data.message || 'Bills received successfully');
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
                        alert(data.message || 'Bills received successfully');
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
                const lines = [
                    'Green Leaf - Bill Match Pending',
                    'Date: ' + this.formattedDate,
                    'Warehouse: ' + this.selectedWarehouseName,
                    ''
                ];

                let pendingCount = 0;

                for (const row of this.rows) {
                    if (row.unit_mismatch) {
                        lines.push(row.product_code ? `${row.product_code} · ${row.product_name}` : row.product_name);
                        lines.push('Advance: ' + row.formatted_advance);
                        lines.push('Bill: ' + row.formatted_bill);
                        lines.push('Status: UNIT FIX REQUIRED');
                        lines.push('');
                        pendingCount++;
                    } else if (row.bill_qty > 0 && row.unmatched_bill_qty > 0.0001) {
                        lines.push(row.product_code ? `${row.product_code} · ${row.product_name}` : row.product_name);
                        lines.push('Bill: ' + row.formatted_bill);
                        lines.push('Matched: ' + this.formatNumber(row.matched_bill_qty) + ' ' + row.unit);
                        lines.push('Pending: ' + this.formatNumber(row.unmatched_bill_qty) + ' ' + row.unit);
                        lines.push('');
                        pendingCount++;
                    }
                }

                if (pendingCount === 0) {
                    alert('All bills are fully matched for this date and warehouse!');
                    return;
                }

                lines.push('Total Pending Products: ' + pendingCount);

                const message = lines.join('\n');
                window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(message), '_blank', 'noopener');
            },
            async saveUnit(itemId, newUnit) {
                this.saving = true;
                try {
                    const res = await fetch('{{ route('admin.cashbook.inventory.update-item-unit') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            goods_received_item_id: itemId,
                            new_unit: newUnit
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to update unit');
                        this.saving = false;
                    }
                } catch (e) {
                    alert('Error updating unit: ' + e.message);
                    this.saving = false;
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

        <!-- 2. Pending Receive Details Banner -->
        <div class="rounded-2xl p-4 transition shadow-xs border"
             :class="pendingBillsCount > 0 ? 'bg-amber-50/70 border-amber-200/80' : 'bg-emerald-50/70 border-emerald-200/80'">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                         :class="pendingBillsCount > 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                        <i :data-lucide="pendingBillsCount > 0 ? 'inbox' : 'check-circle-2'" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-extrabold text-slate-900">
                                Pending Purchase Bills:
                                <span :class="pendingBillsCount > 0 ? 'text-amber-800 font-black text-sm' : 'text-emerald-700 font-bold'"
                                      x-text="pendingBillsCount"></span>
                            </span>
                            <span class="text-[11px] font-semibold text-slate-500 bg-white/80 px-2 py-0.5 rounded-md border border-slate-200/60 font-mono">
                                Selected Date: {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}
                            </span>
                        </div>
                        <template x-if="pendingBillsCount === 0">
                            <p class="text-[11px] font-bold text-emerald-700 mt-0.5 flex items-center gap-1">
                                All purchase bills received ✓
                            </p>
                        </template>
                        <template x-if="pendingBillsCount > 0">
                            <p class="text-[11px] font-medium text-amber-700 mt-0.5">
                                Pending warehouse receive confirmation for selected date and warehouse.
                            </p>
                        </template>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <template x-if="pendingBillsCount > 0">
                        <button type="button"
                                @click="pendingDetailsOpen = true; $nextTick(() => { if (window.lucide) lucide.createIcons(); });"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                            <i data-lucide="eye" class="w-3.5 h-3.5 text-slate-600"></i>
                            <span>View Pending Details</span>
                        </button>
                    </template>

                    <template x-if="pendingBillsCount > 0">
                        <button type="button"
                                @click="receiveAllPending()"
                                :disabled="receivingPending"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50">
                            <i data-lucide="package-check" class="w-3.5 h-3.5" :class="receivingPending ? 'animate-spin' : ''"></i>
                            <span x-text="receivingPending ? 'Receiving...' : ('Receive All ' + pendingBillsCount)"></span>
                        </button>
                    </template>

                    <button type="button"
                            @click="fetchOtherDates()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold transition shadow-xs cursor-pointer">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-500"></i>
                        <span>Other Pending Dates</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. Full-day Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            <!-- Advance -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Advance Qty</div>
                <div class="mt-1 text-base font-black font-mono text-slate-900" x-text="formatNumber(summary.total_advance_qty)"></div>
                <div class="text-[10px] text-slate-400">Total advance items</div>
            </div>

            <!-- Bill -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Bill Qty</div>
                <div class="mt-1 text-base font-black font-mono text-slate-900" x-text="formatNumber(summary.total_bill_qty)"></div>
                <div class="text-[10px] text-slate-400">Received bills</div>
            </div>

            <!-- Matched -->
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3 shadow-xs">
                <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Matched Qty</div>
                <div class="mt-1 text-base font-black font-mono text-emerald-900" x-text="formatNumber(summary.total_matched_qty)"></div>
                <div class="text-[10px] text-emerald-700">Stock reconciled</div>
            </div>

            <!-- Bill Pending -->
            <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3 shadow-xs">
                <div class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Bill Pending</div>
                <div class="mt-1 text-base font-black font-mono text-amber-900" x-text="formatNumber(summary.total_unmatched_bill_qty)"></div>
                <div class="text-[10px] text-amber-700">Unmatched bill qty</div>
            </div>

            <!-- Match % -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Match %</div>
                <div class="mt-1 text-base font-black font-mono"
                     :class="summary.overall_match_pct >= 100 ? 'text-emerald-700' : (summary.overall_match_pct > 0 ? 'text-amber-700' : 'text-slate-700')"
                     x-text="summary.overall_match_pct + '%'"></div>
                <div class="text-[10px] text-slate-400">Day coverage</div>
            </div>

            <!-- Unit Fix Required -->
            <div class="rounded-xl border p-3 shadow-xs"
                 :class="summary.unit_fix_count > 0 ? 'border-rose-200 bg-rose-50/50' : 'border-slate-200 bg-white'">
                <div class="text-[10px] font-bold uppercase tracking-wider"
                     :class="summary.unit_fix_count > 0 ? 'text-rose-800' : 'text-slate-500'">Unit Fix Required</div>
                <div class="mt-1 text-base font-black font-mono"
                     :class="summary.unit_fix_count > 0 ? 'text-rose-700' : 'text-slate-900'"
                     x-text="summary.unit_fix_count"></div>
                <div class="text-[10px]" :class="summary.unit_fix_count > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'">
                    <span x-text="summary.unit_fix_count > 0 ? 'Mismatch detected' : 'Units consistent'"></span>
                </div>
            </div>
        </div>

        <!-- 4 & 5. Actions Bar + Search & Per Page -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
                <!-- Search Box -->
                <div class="relative flex-1 sm:w-64 min-w-[200px]">
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

            <!-- Top Actions: Match All & Share Pending WhatsApp -->
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <button type="button"
                        @click="matchAll()"
                        :disabled="matchingAll"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50">
                    <i data-lucide="refresh-cw" class="w-4 h-4" :class="matchingAll ? 'animate-spin' : ''"></i>
                    <span x-text="matchingAll ? 'Matching...' : 'Match All'"></span>
                </button>

                <button type="button"
                        @click="sharePendingWhatsApp()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 text-xs font-bold transition shadow-xs cursor-pointer">
                    <i data-lucide="message-circle" class="w-4 h-4 text-emerald-600"></i>
                    <span>Share Pending WhatsApp</span>
                </button>
            </div>
        </div>

        <!-- 6. Single Compact Comparison Table with Sl No, Product Code, Sorting -->
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 select-none">
                            <th scope="col" class="py-3 px-3 font-bold text-slate-700 uppercase tracking-wider text-[11px] text-center w-14">
                                Sl No
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

                                <!-- Product Code + Name -->
                                <td class="py-2.5 px-4 font-semibold text-slate-900">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <template x-if="row.product_code || row.sku">
                                            <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[10px] font-black border border-slate-200"
                                                  x-text="row.product_code || row.sku"></span>
                                        </template>
                                        <span x-text="row.product_name"></span>
                                    </div>
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
                            <td colspan="7" class="py-8 text-center text-slate-400 font-semibold">
                                <span x-show="search">No products matching "<span x-text="search"></span>".</span>
                                <span x-show="!search">No receipts or advance entries recorded for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 7. Pagination Footer -->
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

        <!-- MODAL 1: Pending Details Popup -->
        <div x-show="pendingDetailsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="pendingDetailsOpen = false" class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 flex flex-col max-h-[90vh] overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="package-check" class="w-4 h-4 text-indigo-600"></i>
                            <span>Pending Purchase Bills</span>
                        </h3>
                        <div class="flex items-center gap-2 mt-1 flex-wrap text-xs text-slate-500">
                            <span class="font-bold text-slate-700">Date:</span>
                            <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-800">{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</span>
                            <span class="text-slate-300">·</span>
                            <span class="font-bold text-slate-700">Warehouse:</span>
                            <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-800">{{ $selectedWarehouse?->name ?? 'All Warehouses' }}</span>
                            <span class="text-slate-300">·</span>
                            <span class="font-bold text-slate-700">Total Pending:</span>
                            <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-black text-[11px]" x-text="pendingBillsList.length"></span>
                        </div>
                    </div>
                    <button type="button" @click="pendingDetailsOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">✕</button>
                </div>

                <!-- Table Content -->
                <div class="p-4 overflow-y-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 select-none">
                                <th class="py-2 px-3 font-bold text-slate-600 text-[11px] text-center w-12">Sl</th>
                                <th class="py-2 px-3 font-bold text-slate-600 text-[11px] w-28">PO / Bill</th>
                                <th class="py-2 px-3 font-bold text-slate-600 text-[11px] w-36">Supplier</th>
                                <th class="py-2 px-3 font-bold text-slate-600 text-[11px]">Items</th>
                                <th class="py-2 px-3 font-bold text-slate-600 text-[11px] text-center w-28">Status</th>
                                <th class="py-2 px-3 font-bold text-slate-600 text-[11px] text-center w-24">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                            <template x-for="(bill, bIndex) in pendingBillsList" :key="bill.type + '-' + bill.id">
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-400 font-bold" x-text="bIndex + 1"></td>
                                    <td class="py-2.5 px-3 font-mono font-bold text-slate-900" x-text="bill.bill_number"></td>
                                    <td class="py-2.5 px-3 font-medium text-slate-700" x-text="bill.supplier_name"></td>
                                    <td class="py-2.5 px-3 text-slate-600 text-[11px]">
                                        <span class="font-bold text-slate-800" x-text="bill.items_count + ' items · '"></span>
                                        <span x-text="bill.items_summary"></span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200">
                                            Pending Receive
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <button type="button"
                                                @click="receiveSingle(bill)"
                                                :disabled="receivingSingleId === (bill.type + '_' + bill.id)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50">
                                            <i data-lucide="package-check" class="w-3 h-3" :class="receivingSingleId === (bill.type + '_' + bill.id) ? 'animate-spin' : ''"></i>
                                            <span x-text="receivingSingleId === (bill.type + '_' + bill.id) ? 'Receiving...' : 'Receive'"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="pendingBillsList.length === 0">
                                <td colspan="6" class="py-8 text-center text-slate-400 font-semibold">
                                    No pending bills found for this date and warehouse.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="px-6 py-3.5 border-t border-slate-200 bg-slate-50/70 flex items-center justify-between gap-3">
                    <div class="text-xs font-bold text-slate-700">
                        <span x-text="pendingBillsList.length"></span> Bills Pending
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="pendingDetailsOpen = false"
                                class="px-3.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                            Close
                        </button>

                        <button type="button"
                                x-show="pendingBillsList.length > 0"
                                @click="receiveAllPending()"
                                :disabled="receivingPending"
                                class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50">
                            <span x-text="receivingPending ? 'Receiving...' : ('Receive All ' + pendingBillsList.length)"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 2: Other Pending Dates Popup -->
        <div x-show="otherDatesModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="otherDatesModalOpen = false" class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 flex flex-col max-h-[85vh] overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4 text-slate-600"></i>
                            <span>Other Pending Dates</span>
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Dates with pending purchase bills for <span class="font-bold text-slate-700">{{ $selectedWarehouse?->name ?? 'All Warehouses' }}</span>
                        </p>
                    </div>
                    <button type="button" @click="otherDatesModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">✕</button>
                </div>

                <!-- Content -->
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

                <!-- Footer -->
                <div class="px-6 py-3 border-t border-slate-200 bg-slate-50/70 flex justify-end">
                    <button type="button" @click="otherDatesModalOpen = false" class="px-4 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition shadow-xs cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Fix Unit Modal -->
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.outside="modalOpen = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="wrench" class="w-4 h-4 text-indigo-600"></i>
                            <span>Fix Received Unit</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5" x-text="currentRow?.product_code ? `${currentRow.product_code} · ${currentRow.product_name}` : currentRow?.product_name"></p>
                    </div>
                    <button type="button" @click="modalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-base">✕</button>
                </div>

                <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    <template x-for="item in currentRow?.editable_items || []" :key="item.id">
                        <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/70 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                          :class="item.type === 'Advance' ? 'bg-indigo-100 text-indigo-800' : 'bg-emerald-100 text-emerald-800'"
                                          x-text="item.type"></span>
                                    <span class="font-bold text-slate-800" x-text="item.grn_number"></span>
                                </div>
                                <div class="text-slate-500 font-mono mt-1">
                                    Qty: <span class="font-bold text-slate-800" x-text="item.qty"></span> <span x-text="item.unit"></span>
                                </div>
                            </div>

                            <form @submit.prevent="saveUnit(item.id, $event.target.new_unit.value)" class="flex items-center gap-1.5">
                                <select name="new_unit" class="h-8 px-2 py-1 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500 cursor-pointer">
                                    <option value="kg" :selected="item.unit.toLowerCase() === 'kg'">kg</option>
                                    <option value="piece" :selected="item.unit.toLowerCase() === 'piece'">piece</option>
                                    <option value="box" :selected="item.unit.toLowerCase() === 'box'">box</option>
                                    <option value="bunch" :selected="item.unit.toLowerCase() === 'bunch'">bunch</option>
                                    <option value="bag" :selected="item.unit.toLowerCase() === 'bag'">bag</option>
                                    <option value="packet" :selected="item.unit.toLowerCase() === 'packet'">packet</option>
                                    <option value="crate" :selected="item.unit.toLowerCase() === 'crate'">crate</option>
                                </select>
                                <button type="submit"
                                        :disabled="saving"
                                        class="h-8 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50">
                                    Save
                                </button>
                            </form>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
