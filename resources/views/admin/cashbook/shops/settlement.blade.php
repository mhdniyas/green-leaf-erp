@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop #' . $currentShop->shop_id) . ' — Payment & Bill Clearing')

@section('header_title')
    <i data-lucide="wallet" class="w-5 h-5 text-slate-900"></i> Accept Payment & Itemized Bill Clearing for {{ $currentShop->name }}
@endsection

@section('header_subtitle')
    Record payments received from shop into company buffer, select itemized Green Leaf bills, and execute clearing.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.shop.show', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-4 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Shop Details
    </a>
@endsection

@section('content')
<div x-data="settlementPageApp()" x-init="init()" class="space-y-6">

    <!-- Shop Overview & Payable Context Banner -->
    <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl border-l-4 border-l-slate-900">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-4">
                <div class="h-14 w-14 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-900 shadow-sm">
                    <i data-lucide="store" class="w-7 h-7"></i>
                </div>
                <div>
                    <h2 class="text-xl font-extrabold text-slate-900">{{ $currentShop->name }} ({{ $currentShop->code }})</h2>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">
                        Client: <span class="font-bold text-slate-800">Aiswarya Veg</span> • 
                        Slug: <code class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">{{ $currentShop->slug }}</code>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold text-xs rounded-xl flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> Settlement Active
                </span>
            </div>
        </div>

        <!-- 4-Vector Balances to Clean Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 pt-1">
            <div class="p-3 sm:p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[9px] sm:text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">1. Company Payable</span>
                <strong id="set-shop-position" class="text-base sm:text-xl font-mono font-extrabold text-amber-600 block truncate">₹0.00</strong>
                <span class="text-[9px] sm:text-[10px] text-slate-500 block truncate">Collections Owed</span>
            </div>

            <div class="p-3 sm:p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[9px] sm:text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">2. GL Bills Due</span>
                <strong id="set-gl-bills-total" class="text-base sm:text-xl font-mono font-extrabold text-slate-900 block truncate">₹0.00</strong>
                <span class="text-[9px] sm:text-[10px] text-slate-500 block truncate">Invoices to Clear</span>
            </div>

            <div class="p-3 sm:p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[9px] sm:text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">3. Company Pending</span>
                <strong id="set-company-pending" class="text-base sm:text-xl font-mono font-extrabold text-purple-600 block truncate">₹0.00</strong>
                <span class="text-[9px] sm:text-[10px] text-slate-500 block truncate">Expenses Paid</span>
            </div>

            <div class="p-3 sm:p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <span class="text-[9px] sm:text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">4. Net Due Position</span>
                <strong id="set-net-due" class="text-base sm:text-xl font-mono font-extrabold text-emerald-600 block truncate">₹0.00</strong>
                <span class="text-[9px] sm:text-[10px] text-slate-500 block truncate">Final Settlement</span>
            </div>
        </div>
    </div>


    <!-- Main Settlement Working Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Itemized Green Leaf Bills Selection & Clearing Table -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Category Totals Summary Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3">
                <div class="p-3 bg-white rounded-2xl border border-slate-200 space-y-0.5 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Green Leaf Bills Category</span>
                    <strong class="font-mono text-sm font-extrabold text-slate-900" x-text="'₹' + totalGlBillsAmount.toFixed(2)"></strong>
                </div>

                <div class="p-3 bg-white rounded-2xl border border-slate-200 space-y-0.5 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Vehicle Transport Category</span>
                    <strong class="font-mono text-sm font-extrabold text-purple-600" x-text="'₹' + totalVehicleAmount.toFixed(2)"></strong>
                </div>

                <div class="p-3 bg-white rounded-2xl border border-slate-200 space-y-0.5 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Selected Items to Clear</span>
                    <strong class="font-mono text-sm font-extrabold text-emerald-600" x-text="'₹' + selectedTotal.toFixed(2)"></strong>
                </div>
            </div>

            <!-- Green Leaf Daily Bills & Expenses Selector Table -->
            <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i data-lucide="check-square" class="w-4 h-4 text-slate-900"></i> Select Bills & Expenses to Clear Later
                        </h3>
                        <p class="text-xs text-slate-500">Check specific Green Leaf bills or vehicle expenses below to clear against shop payments.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="toggleSelectAll()" class="px-2.5 py-1 text-[11px] font-extrabold bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg">
                            <span x-text="selectedIds.length === pendingItems.length ? 'Deselect All' : 'Select All'"></span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs min-w-[550px]">
                        <thead>
                            <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                <th class="py-3 px-3 text-center">Select</th>
                                <th class="py-3 px-3">Date</th>
                                <th class="py-3 px-3">Category / Entry Type</th>
                                <th class="py-3 px-3">Notes</th>
                                <th class="py-3 px-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                            <template x-for="item in pendingItems" :key="item.id">
                                <tr class="hover:bg-slate-50 transition-all cursor-pointer" @click="toggleItem(item.id)">
                                    <td class="py-3 px-3 text-center" @click.stop>
                                        <input type="checkbox" :value="item.id" x-model="selectedIds" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900 cursor-pointer">
                                    </td>
                                    <td class="py-3 px-3 font-bold text-slate-900" x-text="item.business_date"></td>
                                    <td class="py-3 px-3 font-sans font-semibold text-slate-900">
                                        <span x-text="item.entry_type ? item.entry_type.name : item.entry_type_id"></span>
                                        <span x-show="item.entry_type && item.entry_type.code === 'purchase_bill'" class="ml-1 px-1.5 py-0.5 text-[9px] bg-amber-100 text-amber-800 border border-amber-200 rounded font-bold">GL Bill</span>
                                        <span x-show="item.entry_type && item.entry_type.code === 'vehicle'" class="ml-1 px-1.5 py-0.5 text-[9px] bg-purple-100 text-purple-800 border border-purple-200 rounded font-bold">Vehicle</span>
                                    </td>
                                    <td class="py-3 px-3 font-sans text-slate-600 text-[11px]" x-text="item.notes || '-'"></td>
                                    <td class="py-3 px-3 text-right font-bold text-slate-900" x-text="'₹' + parseFloat(item.amount).toFixed(2)"></td>
                                </tr>
                            </template>
                            <tr x-show="pendingItems.length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-400 font-sans">No pending bills or expenses for this shop.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                    <span class="text-xs text-slate-500 font-medium">Selected <strong class="font-mono text-slate-900" x-text="selectedIds.length"></strong> items totaling <strong class="font-mono text-emerald-600" x-text="'₹' + selectedTotal.toFixed(2)"></strong></span>
                    <button @click="submitClearSelectedBills()" :disabled="selectedIds.length === 0" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white font-extrabold text-xs shadow-md transition-all flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Clear Selected Bills & Items
                    </button>
                </div>
            </div>

            <!-- Recent Settlement Logs for Shop -->
            <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-slate-900"></i> Recent Payment & Settlement Logs
                    </h3>
                    <span class="text-xs text-slate-500 font-mono font-medium" x-text="settlementHistory.length + ' history logs'"></span>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Entry Type</th>
                                <th class="py-2.5 px-3 text-right">Settled Amount</th>
                                <th class="py-2.5 px-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                            <template x-for="h in settlementHistory" :key="h.id">
                                <tr class="hover:bg-slate-50">
                                    <td class="py-2.5 px-3 font-bold text-slate-900" x-text="h.business_date"></td>
                                    <td class="py-2.5 px-3 font-sans font-semibold text-slate-900" x-text="h.entry_type ? h.entry_type.name : h.entry_type_id"></td>
                                    <td class="py-2.5 px-3 text-right font-bold text-emerald-600" x-text="'₹' + parseFloat(h.amount).toFixed(2)"></td>
                                    <td class="py-2.5 px-3 font-sans text-slate-500 text-[11px]" x-text="h.notes || '-'"></td>
                                </tr>
                            </template>
                            <tr x-show="settlementHistory.length === 0">
                                <td colspan="4" class="py-6 text-center text-slate-400 font-sans">No settlement history recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right Column: Step 1 - Accept Payment Only Form -->
        <div class="space-y-6">
            <div class="white-card p-6 rounded-3xl space-y-5 shadow-xl border border-slate-200">
                <div class="border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i data-lucide="wallet" class="w-5 h-5 text-emerald-600"></i> Step 1: Accept Payment Only
                    </h3>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">Records money received from {{ $currentShop->name }} into company buffer without clearing bills immediately.</p>
                </div>

                <form @submit.prevent="submitAcceptPaymentOnly()" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Business Date</label>
                        <input type="date" id="set-form-date" value="{{ today()->toDateString() }}" @change="onDateChange($event.target.value)" class="w-full bg-slate-50 text-xs font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border border-slate-300">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Total Payment Received from Shop (₹)</label>
                        <input type="number" step="0.01" id="set-form-total-amount" placeholder="0.00" required class="w-full bg-white text-base font-extrabold font-mono text-slate-900 px-4 py-3 rounded-2xl border-2 border-slate-300 focus:border-slate-900 focus:outline-none shadow-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">1. Settle Payable Buffer (₹)</label>
                            <input type="number" step="0.01" id="set-form-settle-amount" placeholder="0.00" class="w-full bg-slate-50 text-xs font-mono font-bold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">2. Top-up Petty Float (₹)</label>
                            <input type="number" step="0.01" id="set-form-petty-amount" placeholder="0.00" class="w-full bg-slate-50 text-xs font-mono font-bold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Credited Company Account (Bank / Cash)</label>
                        <select id="set-form-company-account" class="w-full bg-white text-xs font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border-2 border-slate-300 focus:border-slate-900">
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_default ? 'selected' : '' }}>
                                    {{ $acc->name }} ({{ strtoupper($acc->account_type) }}) {{ $acc->is_default ? '— Default Account' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Memo / Notes</label>
                        <input type="text" id="set-form-notes" placeholder="e.g. Collection received via Cheque / Cash" class="w-full bg-slate-50 text-xs font-medium text-slate-900 px-3.5 py-2.5 rounded-xl border border-slate-300">
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs shadow-lg transition-all flex items-center justify-center gap-2">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Accept Payment Only (Credit Account)
                    </button>
                </form>
            </div>

            <!-- Company Accounts Balances Matrix Card -->
            <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i data-lucide="landmark" class="w-4 h-4 text-emerald-600"></i> Company Bank & Cash Balances
                    </h3>
                </div>

                <div class="space-y-3">
                    @foreach($companyAccounts as $acc)
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="h-9 w-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-700 shadow-sm">
                                    @if($acc->account_type == 'bank')
                                        <i data-lucide="landmark" class="w-4 h-4"></i>
                                    @elseif($acc->account_type == 'cash')
                                        <i data-lucide="wallet" class="w-4 h-4"></i>
                                    @else
                                        <i data-lucide="smartphone" class="w-4 h-4"></i>
                                    @endif
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">{{ $acc->name }}</h4>
                                    <span class="text-[10px] text-slate-500 font-mono">{{ $acc->bank_name ?: strtoupper($acc->account_type) }} • {{ $acc->account_number ?: 'DEFAULT' }}</span>
                                </div>
                            </div>
                            <strong class="font-mono text-xs font-extrabold text-emerald-600">₹{{ number_format($acc->current_balance, 2) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
    const currentShopId = {{ $currentShop->shop_id }};
    let currentDate = '{{ today()->toDateString() }}';

    function settlementPageApp() {
        return {
            pendingItems: [],
            settlementHistory: [],
            selectedIds: [],

            init() {
                this.loadData();
            },

            onDateChange(newDate) {
                currentDate = newDate;
                this.loadData();
            },

            get totalGlBillsAmount() {
                return (this.pendingItems || [])
                    .filter(i => i.entry_type && i.entry_type.code === 'purchase_bill')
                    .reduce((acc, i) => acc + parseFloat(i.amount), 0);
            },

            get totalVehicleAmount() {
                return (this.pendingItems || [])
                    .filter(i => i.entry_type && i.entry_type.code === 'vehicle')
                    .reduce((acc, i) => acc + parseFloat(i.amount), 0);
            },

            get selectedTotal() {
                return (this.pendingItems || [])
                    .filter(i => this.selectedIds.includes(i.id))
                    .reduce((acc, i) => acc + parseFloat(i.amount), 0);
            },

            toggleItem(id) {
                if (this.selectedIds.includes(id)) {
                    this.selectedIds = this.selectedIds.filter(i => i !== id);
                } else {
                    this.selectedIds.push(id);
                }
            },

            toggleSelectAll() {
                if (this.selectedIds.length === this.pendingItems.length) {
                    this.selectedIds = [];
                } else {
                    this.selectedIds = this.pendingItems.map(i => i.id);
                }
            },

            async loadData() {
                try {
                    const res = await fetch(`/admin/cashbook/api/shop-data?shop_id=${currentShopId}&business_date=${currentDate}&timeframe=monthly`);
                    const data = await res.json();

                    if (data.success) {
                        // All pending Green Leaf bills & vehicle expenses to clear
                        this.pendingItems = (data.month_transactions || []).filter(t => 
                            t.entry_type && (t.entry_type.code === 'purchase_bill' || t.entry_type.code === 'vehicle')
                        );
                        
                        // Settlement History
                        this.settlementHistory = (data.month_transactions || []).filter(t => t.entry_type && t.entry_type.category === 'settlement');

                        const snapshot = data.snapshot;
                        if (snapshot) {
                            document.getElementById('set-shop-position').innerText = `₹${parseFloat(snapshot.closing_shop_position).toFixed(2)}`;
                            document.getElementById('set-company-pending').innerText = `₹${parseFloat(snapshot.closing_company_pending).toFixed(2)}`;
                            const companyMainBal = (data.month_transactions || [])
                                .filter(t => t.entry_type && (t.entry_type.code === 'shop_paid_company' || t.entry_type.code === 'sales_to_company' || t.entry_type.category === 'settlement' || (t.notes && t.notes.toLowerCase().includes('accept'))))
                                .reduce((acc, t) => acc + parseFloat(t.amount), 0);
                            document.getElementById('set-company-main-balance').innerText = `₹${companyMainBal.toFixed(2)}`;

                            // Prefill settle amount input with full shop position
                            document.getElementById('set-form-total-amount').value = parseFloat(snapshot.closing_shop_position) > 0 ? parseFloat(snapshot.closing_shop_position).toFixed(2) : '';
                            document.getElementById('set-form-settle-amount').value = parseFloat(snapshot.closing_shop_position) > 0 ? parseFloat(snapshot.closing_shop_position).toFixed(2) : '';
                        }

                        // Sum outstanding Green Leaf Bills
                        const totalBills = this.totalGlBillsAmount;
                        document.getElementById('set-gl-bills-total').innerText = `₹${totalBills.toFixed(2)}`;
                    }
                } catch (err) {
                    showToast('Failed to load shop settlement data', 'error');
                }
            },

            async submitAcceptPaymentOnly() {
                const date = document.getElementById('set-form-date').value;
                const companyAccountId = document.getElementById('set-form-company-account').value;
                const settleAmount = document.getElementById('set-form-settle-amount').value;
                const pettyAmount = document.getElementById('set-form-petty-amount').value;
                const notes = document.getElementById('set-form-notes').value;

                try {
                    const res = await fetch('/admin/cashbook/api/accept-payment', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            shop_id: currentShopId,
                            business_date: date,
                            company_account_id: companyAccountId,
                            settle_amount: settleAmount,
                            petty_amount: pettyAmount,
                            notes: notes || 'Payment accepted into company account'
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast('Payment accepted into company buffer!', 'success');
                        this.loadData();
                    } else {
                        showToast(data.message || 'Failed to accept payment', 'error');
                    }
                } catch (err) {
                    showToast('Server error accepting payment', 'error');
                }
            },

            async submitClearSelectedBills() {
                if (this.selectedIds.length === 0) return;

                try {
                    const date = document.getElementById('set-form-date').value;
                    const amountToClear = this.selectedTotal;

                    const res = await fetch('/admin/cashbook/api/record-entry', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            shop_id: currentShopId,
                            business_date: date,
                            entry_type_code: 'sales_to_company',
                            amount: amountToClear,
                            funding_source: 'sales',
                            notes: `Cleared ${this.selectedIds.length} selected bills totaling ₹${amountToClear.toFixed(2)}`
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast(`Successfully cleared ${this.selectedIds.length} selected bills (₹${amountToClear.toFixed(2)})!`, 'success');
                        this.selectedIds = [];
                        this.loadData();
                    } else {
                        showToast(data.message || 'Failed to clear selected bills', 'error');
                    }
                } catch (err) {
                    showToast('Server error clearing bills', 'error');
                }
            }
        };
    }
</script>
@endpush
