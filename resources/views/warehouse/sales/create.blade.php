<x-layouts.app title="New Warehouse Sale">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-4 py-3 lg:max-w-3xl lg:px-4 lg:py-4"
         x-data="{
            warehouseId: '{{ $selectedWarehouseId }}',
            businessDate: '{{ $date }}',
            customerId: '',
            customerName: 'Walk-in Customer',
            customerPhone: '',
            customerSearch: '',
            customerDropdownOpen: false,
            newCustomerModalOpen: false,
            newCustomerName: '',
            newCustomerPhone: '',
            newCustomerAddress: '',
            newCustomerSaving: false,
            customersList: @js($customers),
            productsList: @js($productsWithStock),
            items: [
                { product_id: '', grade: 'A', qty: 1, unit: 'kg', unit_price: 0, available_stock: 0 }
            ],
            discount: 0,
            paymentMethod: 'cash',
            moneyHolderType: 'user',
            moneyHolderUserId: '{{ auth()->id() }}',
            companyAccountId: '',
            reference: '',
            notes: '',
            submitting: false,
            errorMsg: '',

            get subtotal() {
                return this.items.reduce((sum, item) => {
                    const q = parseFloat(item.qty) || 0;
                    const p = parseFloat(item.unit_price) || 0;
                    return sum + (q * p);
                }, 0);
            },

            get totalAmount() {
                const sub = this.subtotal;
                const disc = parseFloat(this.discount) || 0;
                return Math.max(0, sub - disc);
            },

            get filteredCustomers() {
                const q = (this.customerSearch || '').trim().toLowerCase();
                if (!q) return this.customersList;
                return this.customersList.filter(c =>
                    (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q)
                );
            },

            selectCustomer(c) {
                if (c) {
                    this.customerId = c.id;
                    this.customerName = c.name;
                    this.customerPhone = c.phone || '';
                } else {
                    this.customerId = '';
                    this.customerName = 'Walk-in Customer';
                    this.customerPhone = '';
                }
                this.customerDropdownOpen = false;
                this.customerSearch = '';
            },

            async saveNewCustomer() {
                if (!this.newCustomerName.trim()) {
                    alert('Please enter a customer name.');
                    return;
                }
                this.newCustomerSaving = true;
                try {
                    const res = await fetch('{{ route('warehouse.sales.customers.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            name: this.newCustomerName,
                            phone: this.newCustomerPhone,
                            address: this.newCustomerAddress
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success' && data.customer) {
                        this.customersList.push(data.customer);
                        this.selectCustomer(data.customer);
                        this.newCustomerModalOpen = false;
                        this.newCustomerName = '';
                        this.newCustomerPhone = '';
                        this.newCustomerAddress = '';
                    } else {
                        alert(data.message || 'Failed to save customer');
                    }
                } catch (e) {
                    alert('Error saving customer: ' + e.message);
                } finally {
                    this.newCustomerSaving = false;
                }
            },

            async onWarehouseChange() {
                try {
                    const res = await fetch('{{ route('warehouse.sales.search-products') }}?warehouse_id=' + this.warehouseId, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        this.productsList = data.data || [];
                        // Update stock on existing selected items
                        this.items.forEach(item => {
                            if (item.product_id) {
                                const p = this.productsList.find(x => x.id == item.product_id);
                                if (p) {
                                    item.available_stock = p.available_stock;
                                }
                            }
                        });
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            onProductSelect(index) {
                const item = this.items[index];
                const p = this.productsList.find(x => x.id == item.product_id);
                if (p) {
                    item.unit = p.unit || 'kg';
                    item.unit_price = p.price || 0;
                    item.available_stock = p.available_stock || 0;
                } else {
                    item.available_stock = 0;
                }
            },

            addItem() {
                this.items.push({
                    product_id: '',
                    grade: 'A',
                    qty: 1,
                    unit: 'kg',
                    unit_price: 0,
                    available_stock: 0
                });
            },

            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },

            async submitSale() {
                this.errorMsg = '';
                // 1. Basic validation
                if (!this.warehouseId) {
                    this.errorMsg = 'Please select a selling warehouse.';
                    return;
                }

                if (this.items.length === 0) {
                    this.errorMsg = 'Please add at least one item.';
                    return;
                }

                for (let i = 0; i < this.items.length; i++) {
                    const it = this.items[i];
                    if (!it.product_id) {
                        this.errorMsg = `Please select a product for line #${i + 1}.`;
                        return;
                    }
                    const q = parseFloat(it.qty) || 0;
                    if (q <= 0) {
                        this.errorMsg = `Quantity must be greater than zero on line #${i + 1}.`;
                        return;
                    }
                    if (q > (it.available_stock + 0.001)) {
                        const pName = (this.productsList.find(x => x.id == it.product_id) || {}).name || 'Product';
                        this.errorMsg = `Requested ${q} ${it.unit} of ${pName} exceeds available stock (${it.available_stock} ${it.unit}). Negative inventory is blocked!`;
                        return;
                    }
                }

                this.submitting = true;
                try {
                    const payload = {
                        warehouse_id: parseInt(this.warehouseId),
                        customer_id: this.customerId ? parseInt(this.customerId) : null,
                        customer_name: this.customerName,
                        customer_phone: this.customerPhone,
                        business_date: this.businessDate,
                        discount: parseFloat(this.discount) || 0,
                        notes: this.notes,
                        payment_method: this.paymentMethod,
                        money_holder_type: this.paymentMethod === 'cash' ? this.moneyHolderType : 'company',
                        money_holder_user_id: (this.paymentMethod === 'cash' && this.moneyHolderType === 'user') ? parseInt(this.moneyHolderUserId) : null,
                        company_account_id: this.companyAccountId ? parseInt(this.companyAccountId) : null,
                        reference: this.reference,
                        items: this.items.map(it => ({
                            product_id: parseInt(it.product_id),
                            grade: it.grade || 'A',
                            qty: parseFloat(it.qty),
                            unit: it.unit,
                            unit_price: parseFloat(it.unit_price)
                        }))
                    };

                    const res = await fetch('{{ route('warehouse.sales.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await res.json();
                    if (res.ok && data.status === 'success' && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        let msg = data.message || 'Failed to create sale.';
                        if (data.errors) {
                            const firstErrKey = Object.keys(data.errors)[0];
                            if (firstErrKey && data.errors[firstErrKey][0]) {
                                msg = data.errors[firstErrKey][0];
                            }
                        }
                        this.errorMsg = msg;
                        this.submitting = false;
                    }
                } catch (e) {
                    this.errorMsg = 'Network error: ' + e.message;
                    this.submitting = false;
                }
            }
         }">

        <!-- Header -->
        <div class="flex items-center justify-between gap-3 px-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('warehouse.sales.index', ['warehouse_id' => $selectedWarehouseId, 'date' => $date]) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </a>
                <div>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">New Warehouse Sale</h1>
                    <p class="text-xs text-slate-500 font-semibold">Direct sale &amp; instant stock deduction.</p>
                </div>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-mono font-bold text-slate-600">
                {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
            </span>
        </div>

        <!-- Error Alert -->
        <template x-if="errorMsg">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 flex items-start justify-between gap-2 shadow-xs">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    <span x-text="errorMsg"></span>
                </div>
                <button type="button" @click="errorMsg = ''" class="text-rose-500 hover:text-rose-700 font-black">✕</button>
            </div>
        </template>

        <!-- Form Card -->
        <div class="rounded-3xl border border-slate-200 bg-white p-4 sm:p-6 shadow-xs space-y-6">

            <!-- 1. Warehouse & Business Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1">Selling Warehouse *</label>
                    <select x-model="warehouseId" @change="onWarehouseChange()"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                        @foreach($allowedWarehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1">Business Date *</label>
                    <input type="date" x-model="businessDate"
                           class="w-full h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                </div>
            </div>

            <!-- 2. Customer Section -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Customer</label>
                    <button type="button" @click="newCustomerModalOpen = true"
                            class="text-xs font-bold text-emerald-600 hover:text-emerald-800 transition">
                        + New Customer
                    </button>
                </div>

                <div class="relative">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input type="text"
                                   x-model="customerName"
                                   @focus="customerDropdownOpen = true"
                                   placeholder="Search or enter customer name..."
                                   class="w-full h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                            <template x-if="customerId">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-100 text-emerald-800">
                                    Saved
                                </span>
                            </template>
                        </div>

                        <button type="button"
                                @click="selectCustomer(null)"
                                class="h-11 px-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 transition"
                                title="Set as Walk-in Customer">
                            Walk-in
                        </button>
                    </div>

                    <!-- Customer Dropdown -->
                    <div x-show="customerDropdownOpen"
                         @click.away="customerDropdownOpen = false"
                         x-cloak
                         class="absolute left-0 right-0 top-full mt-1.5 max-h-56 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-lg z-30 space-y-1">
                        <input type="text"
                               x-model="customerSearch"
                               placeholder="Filter customer list..."
                               class="w-full h-9 rounded-lg border border-slate-200 px-2.5 text-xs font-medium focus:outline-none focus:border-emerald-500 mb-1">

                        <button type="button"
                                @click="selectCustomer(null)"
                                class="w-full text-left p-2 rounded-xl text-xs font-bold hover:bg-slate-50 text-slate-800 transition flex items-center justify-between">
                            <span>Walk-in Customer</span>
                            <span class="text-[10px] text-slate-400">Default</span>
                        </button>

                        <template x-for="c in filteredCustomers" :key="c.id">
                            <button type="button"
                                    @click="selectCustomer(c)"
                                    class="w-full text-left p-2 rounded-xl text-xs font-semibold hover:bg-emerald-50 text-slate-900 transition flex items-center justify-between">
                                <span x-text="c.name" class="font-bold"></span>
                                <span x-text="c.phone || ''" class="font-mono text-[11px] text-slate-400"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- 3. Line Items Section -->
            <div class="space-y-3 pt-4 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-600">Sale Items</h3>
                    <button type="button" @click="addItem()"
                            class="inline-flex items-center gap-1 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-3 py-1.5 text-xs font-bold transition">
                        <span>+ Add Item</span>
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-3 relative">
                            <!-- Top: Product select & available stock badge -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">
                                        Product <span x-text="'#' + (index + 1)"></span> *
                                    </label>
                                    <select x-model="item.product_id" @change="onProductSelect(index)"
                                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none cursor-pointer">
                                        <option value="">-- Choose Product --</option>
                                        <template x-for="p in productsList" :key="p.id">
                                            <option :value="p.id" x-text="p.name + (p.sku ? ' (' + p.sku + ')' : '')"></option>
                                        </template>
                                    </select>
                                </div>

                                <!-- Live Available Stock Badge -->
                                <div class="flex items-center gap-2 self-end sm:self-center">
                                    <template x-if="item.product_id">
                                        <div class="px-2.5 py-1 rounded-lg border text-xs font-mono font-bold"
                                             :class="item.available_stock > 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'">
                                            <span>Available: </span>
                                            <span x-text="item.available_stock + ' ' + item.unit"></span>
                                        </div>
                                    </template>

                                    <template x-if="items.length > 1">
                                        <button type="button" @click="removeItem(index)"
                                                class="h-9 w-9 flex items-center justify-center rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition"
                                                title="Remove Item">
                                            ✕
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Bottom: Qty, Unit, Price, Line Total -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-1">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Quantity</label>
                                    <input type="number" step="0.001" min="0.001" x-model.number="item.qty"
                                           class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black font-mono text-slate-900 focus:border-emerald-500 focus:outline-none">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Unit</label>
                                    <input type="text" x-model="item.unit" readonly
                                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-bold text-slate-600 cursor-not-allowed">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Price (₹)</label>
                                    <input type="number" step="0.01" min="0" x-model.number="item.unit_price"
                                           class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black font-mono text-slate-900 focus:border-emerald-500 focus:outline-none">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Line Total</label>
                                    <div class="w-full h-10 rounded-xl border border-slate-200 bg-slate-100 px-3 flex items-center font-mono font-black text-xs text-slate-950">
                                        ₹<span x-text="((parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0)).toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 4. Pricing Totals -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                    <span>Subtotal</span>
                    <span class="font-mono font-bold text-slate-900">₹<span x-text="subtotal.toFixed(2)"></span></span>
                </div>

                <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                    <label for="discount" class="cursor-pointer">Discount (₹)</label>
                    <input id="discount" type="number" step="0.01" min="0" x-model.number="discount"
                           class="w-28 h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-right text-xs font-mono font-black text-slate-900 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="pt-2 border-t border-slate-200 flex items-center justify-between text-sm sm:text-base font-black text-slate-950">
                    <span>TOTAL AMOUNT</span>
                    <span class="font-mono text-emerald-800 text-lg sm:text-xl">₹<span x-text="totalAmount.toFixed(2)"></span></span>
                </div>
            </div>

            <!-- 5. Payment & Money Holder Section -->
            <div class="space-y-4 pt-4 border-t border-slate-100">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-600">Payment &amp; Cash Location</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Payment Method -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Payment Method *</label>
                        <select x-model="paymentMethod"
                                class="w-full h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI / QR</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="credit">Credit (Due)</option>
                        </select>
                    </div>

                    <!-- Cash Money Holder (Critical Requirement) -->
                    <template x-if="paymentMethod === 'cash'">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Cash Money Holder *</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs font-bold transition"
                                       :class="moneyHolderType === 'user' ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-600'">
                                    <input type="radio" name="money_holder_type_choice" value="user" x-model="moneyHolderType" class="text-emerald-600">
                                    <span>Held by User</span>
                                </label>

                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs font-bold transition"
                                       :class="moneyHolderType === 'company' ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-600'">
                                    <input type="radio" name="money_holder_type_choice" value="company" x-model="moneyHolderType" class="text-emerald-600">
                                    <span>Company Direct</span>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- If Held by User, select which user -->
                <template x-if="paymentMethod === 'cash' && moneyHolderType === 'user'">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">User Holding Cash *</label>
                        <select x-model="moneyHolderUserId"
                                class="w-full h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                            @foreach($activeUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} {{ (int)$u->id === auth()->id() ? '(Me)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </template>

                <!-- Optional Note -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Notes / Remarks (Optional)</label>
                    <input type="text" x-model="notes" placeholder="e.g. Room service, special packaging, etc."
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>
            </div>

            <!-- Submit Action -->
            <div class="pt-4 border-t border-slate-100">
                <button type="button"
                        @click="submitSale()"
                        :disabled="submitting || totalAmount <= 0"
                        class="w-full h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm sm:text-base font-black shadow-lg shadow-emerald-600/25 transition active:scale-[0.99] disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer">
                    <template x-if="!submitting">
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            <span>CONFIRM SALE (₹<span x-text="totalAmount.toFixed(2)"></span>)</span>
                        </span>
                    </template>
                    <template x-if="submitting">
                        <span class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>Processing Sale &amp; Deducting Stock...</span>
                        </span>
                    </template>
                </button>
            </div>

        </div>

        <!-- Inline New Customer Modal -->
        <div x-show="newCustomerModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white rounded-3xl p-6 shadow-xl space-y-4"
                 @click.away="newCustomerModalOpen = false">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-black text-slate-900">Add New Customer</h3>
                    <button type="button" @click="newCustomerModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Customer Name *</label>
                        <input type="text" x-model="newCustomerName" placeholder="e.g. Royal Palace Hotel"
                               class="w-full h-10 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Phone Number</label>
                        <input type="text" x-model="newCustomerPhone" placeholder="e.g. 9876543210"
                               class="w-full h-10 rounded-xl border border-slate-200 px-3 text-xs font-mono font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Address (Optional)</label>
                        <textarea x-model="newCustomerAddress" rows="2" placeholder="Street, City..."
                                  class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-medium text-slate-900 focus:border-emerald-500 focus:outline-none"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="newCustomerModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="button" @click="saveNewCustomer()" :disabled="newCustomerSaving"
                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-xs transition disabled:opacity-50">
                        <span x-text="newCustomerSaving ? 'Saving...' : 'Save Customer'"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</x-layouts.app>
