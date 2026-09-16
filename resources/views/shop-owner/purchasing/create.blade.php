@extends('shop-owner.layouts.app')

@section('title', 'New Shop Purchase')
@section('page_title', 'New Purchase')
@section('page_description', 'Record bulk vegetable/fruit purchases directly for this shop.')
@php($breadcrumbs = [['label' => 'Purchasing', 'url' => route('shop-owner.purchasing.index')], ['label' => 'New Purchase']])

@section('content')
<div class="max-w-5xl space-y-6">
    {{-- Header Notice --}}
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-600 text-white font-bold text-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                </span>
                <div>
                    <h3 class="text-sm font-extrabold text-emerald-950">Shop Purchasing Engine</h3>
                    <p class="text-xs font-semibold text-emerald-700">Shop: <strong>{{ $activeShop->name }}</strong> | Active Business Day: <strong>{{ $businessDate }}</strong></p>
                </div>
            </div>
            <a href="{{ route('shop-owner.purchasing.index') }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition">
                Back to History
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 space-y-1">
            <p class="font-extrabold uppercase tracking-wider">Please fix the following errors:</p>
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('shop-owner.purchasing.store') }}" id="purchase-form" class="space-y-6">
        @csrf

        {{-- Supplier & Order Meta Card --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs space-y-5">
            <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-3">1. Vendor & Purchase Info</h3>
            
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{-- Supplier Selection / Creation --}}
                <div class="sm:col-span-1 space-y-1.5">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">Supplier / Vendor <span class="text-rose-500">*</span></label>
                    <select name="supplier_id" id="supplier-select" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <option value="">-- Select Existing Supplier --</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(old('supplier_id') == $s->id)>{{ $s->name }} ({{ $s->phone ?? 'No phone' }})</option>
                        @endforeach
                        <option value="NEW">+ Create New Supplier</option>
                    </select>
                </div>

                {{-- New Supplier Name (Hidden by default unless NEW selected) --}}
                <div id="new-supplier-fields" class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3 {{ old('supplier_id') === 'NEW' ? '' : 'hidden' }}">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-700">New Supplier Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" placeholder="Vendor business name" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-700">Phone (Optional)</label>
                        <input type="text" name="supplier_phone" value="{{ old('supplier_phone') }}" placeholder="Contact phone" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                    </div>
                </div>

                {{-- Business Date --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">Business Date <span class="text-rose-500">*</span></label>
                    <input type="date" name="business_date" value="{{ old('business_date', $businessDate) }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Payment Method --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">Payment Method <span class="text-rose-500">*</span></label>
                    <select name="payment_method" id="payment-method-select" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <option value="Cash" @selected(old('payment_method', 'Cash') === 'Cash')>Cash (Linked to Shop Cashbook)</option>
                        <option value="Credit" @selected(old('payment_method') === 'Credit')>Credit (Shop Vendor Liability)</option>
                    </select>
                </div>
            </div>

            {{-- Explanatory Banner for Payment Mode --}}
            <div id="cash-payment-info" class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-3 text-xs text-emerald-800 {{ old('payment_method', 'Cash') === 'Cash' ? '' : 'hidden' }}">
                <span class="font-extrabold">Cash Purchase Flow:</span> An official Shop Purchase is created and immediately linked to a Shop Cashbook cash_purchase entry. Zero vendor liability is created.
            </div>
            <div id="credit-payment-info" class="rounded-2xl border border-amber-100 bg-amber-50/50 p-3 text-xs text-amber-800 {{ old('payment_method') === 'Credit' ? '' : 'hidden' }}">
                <span class="font-extrabold">Credit Purchase Flow:</span> A Shop Purchase is created and tracked as a Shop Vendor Liability (`shop_vendor_payables`). No cash movement or company liability is created.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div class="space-y-1.5">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">Vendor Bill / Reference # (Optional)</label>
                    <input type="text" name="supplier_reference" value="{{ old('supplier_reference') }}" placeholder="Supplier invoice or receipt number" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">Notes / Remarks</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optional notes for this purchase" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>
            </div>
        </div>

        {{-- Purchase Items Table --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">2. Purchased Items</h3>
                <button type="button" id="add-item-btn" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800 transition">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="items-table">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-3 py-2.5 w-5/12">Product</th>
                            <th class="px-3 py-2.5 w-2/12">Quantity</th>
                            <th class="px-3 py-2.5 w-2/12">Unit</th>
                            <th class="px-3 py-2.5 w-2/12">Rate (₹)</th>
                            <th class="px-3 py-2.5 w-2/12 text-right">Amount (₹)</th>
                            <th class="px-3 py-2.5 w-1/12 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold" id="items-tbody">
                        {{-- Rows generated dynamically or seeded --}}
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-xs font-black">
                        <tr>
                            <td colspan="4" class="px-3 py-3 text-right uppercase tracking-wider text-slate-600">Total Purchase Value:</td>
                            <td class="px-3 py-3 text-right text-base font-black text-slate-950 font-mono" id="grand-total">₹0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('shop-owner.purchasing.index') }}" class="rounded-2xl border border-slate-200 bg-white px-6 py-3.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="rounded-2xl bg-emerald-600 px-8 py-3.5 text-xs font-black text-white shadow-md hover:bg-emerald-700 transition">
                Save Shop Purchase
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const products = @json($products);
    const supplierSelect = document.getElementById('supplier-select');
    const newSupplierFields = document.getElementById('new-supplier-fields');
    const paymentMethodSelect = document.getElementById('payment-method-select');
    const cashInfo = document.getElementById('cash-payment-info');
    const creditInfo = document.getElementById('credit-payment-info');
    const tbody = document.getElementById('items-tbody');
    const addItemBtn = document.getElementById('add-item-btn');
    const grandTotalEl = document.getElementById('grand-total');

    let rowIndex = 0;

    supplierSelect.addEventListener('change', () => {
        if (supplierSelect.value === 'NEW') {
            newSupplierFields.classList.remove('hidden');
        } else {
            newSupplierFields.classList.add('hidden');
        }
    });

    paymentMethodSelect.addEventListener('change', () => {
        if (paymentMethodSelect.value === 'Cash') {
            cashInfo.classList.remove('hidden');
            creditInfo.classList.add('hidden');
        } else {
            cashInfo.classList.add('hidden');
            creditInfo.classList.remove('hidden');
        }
    });

    function calculateRow(tr) {
        const qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
        const rate = parseFloat(tr.querySelector('.item-rate').value) || 0;
        const total = qty * rate;
        tr.querySelector('.item-total').textContent = '₹' + total.toFixed(2);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(tr.querySelector('.item-rate').value) || 0;
            total += (qty * rate);
        });
        grandTotalEl.textContent = '₹' + total.toFixed(2);
    }

    function addRow(productId = '', quantity = '', unit = 'kg', rate = '') {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50/50 transition';

        let optionsHtml = '<option value="">-- Select Product --</option>';
        products.forEach(p => {
            const selected = p.id == productId ? 'selected' : '';
            optionsHtml += `<option value="${p.id}" data-unit="${p.unit || 'kg'}" ${selected}>${p.name}</option>`;
        });

        tr.innerHTML = `
            <td class="px-3 py-2">
                <select name="items[${rowIndex}][product_id]" required class="item-product h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none">
                    ${optionsHtml}
                </select>
            </td>
            <td class="px-3 py-2">
                <input type="number" step="0.01" min="0.01" name="items[${rowIndex}][quantity]" value="${quantity}" required placeholder="0.00" class="item-qty h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 font-mono focus:bg-white focus:outline-none">
            </td>
            <td class="px-3 py-2">
                <select name="items[${rowIndex}][unit]" class="item-unit h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none">
                    <option value="kg" ${unit === 'kg' ? 'selected' : ''}>kg</option>
                    <option value="box" ${unit === 'box' ? 'selected' : ''}>box</option>
                    <option value="crate" ${unit === 'crate' ? 'selected' : ''}>crate</option>
                    <option value="bunch" ${unit === 'bunch' ? 'selected' : ''}>bunch</option>
                    <option value="pcs" ${unit === 'pcs' ? 'selected' : ''}>pcs</option>
                </select>
            </td>
            <td class="px-3 py-2">
                <input type="number" step="0.01" min="0" name="items[${rowIndex}][rate]" value="${rate}" required placeholder="0.00" class="item-rate h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 font-mono focus:bg-white focus:outline-none">
            </td>
            <td class="px-3 py-2 text-right font-mono font-bold text-slate-950 item-total">
                ₹0.00
            </td>
            <td class="px-3 py-2 text-center">
                <button type="button" class="remove-row-btn rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </td>
        `;

        const productSel = tr.querySelector('.item-product');
        const unitSel = tr.querySelector('.item-unit');
        const qtyInp = tr.querySelector('.item-qty');
        const rateInp = tr.querySelector('.item-rate');
        const removeBtn = tr.querySelector('.remove-row-btn');

        productSel.addEventListener('change', () => {
            const opt = productSel.options[productSel.selectedIndex];
            if (opt && opt.dataset.unit) {
                unitSel.value = opt.dataset.unit;
            }
        });

        qtyInp.addEventListener('input', () => calculateRow(tr));
        rateInp.addEventListener('input', () => calculateRow(tr));

        removeBtn.addEventListener('click', () => {
            if (tbody.querySelectorAll('tr').length > 1) {
                tr.remove();
                calculateGrandTotal();
            } else {
                alert('At least one item is required.');
            }
        });

        tbody.appendChild(tr);
        rowIndex++;
        calculateRow(tr);
    }

    addItemBtn.addEventListener('click', () => addRow());

    // Add initial row
    addRow();
});
</script>
@endsection
