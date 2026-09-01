@extends('admin.cashbook.layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900">Company Direct Sales</h1>
        <p class="text-sm text-slate-500">Itemized sales create one pending company receipt. Reconcile it before it appears in All Transactions.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-2xl bg-white p-5 shadow-sm" data-direct-sale-root>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-900">Confirm Direct Sale</h2>
                <p class="text-sm text-slate-500">
                    Pricing shop:
                    <span class="font-bold text-slate-800">{{ $directSaleShop?->name ?? 'Not configured' }}</span>
                </p>
            </div>
        </div>

        @if(! $directSaleShop)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">
                Configure Default Direct Sales Shop in Company Settings before creating direct sales.
            </div>
        @else
            <form method="POST" action="{{ route('admin.cashbook.finance.direct-sales.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="request_uuid" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                <div class="grid gap-3 md:grid-cols-5">
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Business date
                        <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" name="business_date" type="date" value="{{ old('business_date', today()->toDateString()) }}">
                    </label>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Customer
                        <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" name="customer_name" value="{{ old('customer_name') }}" placeholder="Optional">
                    </label>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Reference
                        <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" name="reference" value="{{ old('reference') }}" placeholder="Optional">
                    </label>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Note
                        <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" name="note" value="{{ old('note') }}" placeholder="Optional">
                    </label>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Payment
                        <select class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" name="payment_method" data-payment-method>
                            <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                            <option value="bank" @selected(old('payment_method') === 'bank')>Bank</option>
                        </select>
                    </label>
                </div>

                <label class="block max-w-md text-xs font-black uppercase tracking-wide text-slate-500" data-bank-account-field>
                    Company bank account
                    <select class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" name="company_account_uuid">
                        <option value="">Select enabled bank account</option>
                        @foreach($companyAccounts->where('account_type', 'bank') as $companyAccount)
                            <option value="{{ $companyAccount->public_uuid }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($companyAccount, old('company_account_uuid'), $companyAccounts->where('account_type', 'bank'), 'bank', 'public_uuid'))>{{ $companyAccount->name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="grid gap-3 md:grid-cols-[1fr_220px_160px_120px]">
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Search product
                        <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" data-product-search placeholder="Search name, SKU, category">
                    </label>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Product
                        <select class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" data-product-select>
                            <option value="">Select</option>
                        </select>
                    </label>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                        Quantity
                        <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" data-product-quantity type="number" min="0.001" step="0.001" value="1">
                    </label>
                    <button type="button" class="mt-5 h-11 rounded-xl bg-slate-900 px-4 text-sm font-black text-white" data-add-product>Add item</button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="p-3">Product</th>
                                <th class="p-3">Qty</th>
                                <th class="p-3">Unit</th>
                                <th class="p-3">Rate</th>
                                <th class="p-3">Line total</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody data-sale-items>
                            <tr data-empty-row>
                                <td class="p-3 text-slate-500" colspan="6">No products selected.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 font-black text-slate-900">
                            <tr>
                                <td class="p-3 text-right" colspan="4">Grand total</td>
                                <td class="p-3" data-grand-total>0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white">Confirm Sale</button>
            </form>
        @endif
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.cashbook.finance.direct-sales') }}" class="grid gap-3 md:grid-cols-[auto_200px_1fr_auto] md:items-end">
            <div class="flex items-end">
                <x-cashbook.previous-month-button mode="month" size="sm" class="h-11" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
            </div>
            <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                Month
                <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" type="month" name="month" value="{{ $month }}">
            </label>
            <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                Search
                <input class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm text-slate-900" name="search" value="{{ $search }}" placeholder="Buyer, reference, sale UUID">
            </label>
            <button class="h-11 rounded-xl border border-slate-200 px-4 text-sm font-black text-slate-700">Filter</button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="p-3">Date</th>
                    <th class="p-3">Sale #</th>
                    <th class="p-3">Items</th>
                    <th class="p-3">Payment</th>
                    <th class="p-3">Amount</th>
                    <th class="p-3">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr class="border-t border-slate-100">
                        <td class="p-3">{{ $sale->business_date->format('d M Y') }}</td>
                        <td class="p-3 font-mono text-xs">DIRECT-SALE-{{ $sale->id }}</td>
                        <td class="p-3">{{ $sale->items->count() ?: 'Legacy amount-only' }}</td>
                        <td class="p-3">{{ $sale->payment_method ? strtoupper($sale->payment_method) : 'Legacy' }}</td>
                        <td class="p-3">Rs {{ number_format((float) $sale->amount, 2) }}</td>
                        <td class="p-3">{{ strtoupper($sale->sale_status ?? 'legacy') }}</td>
                        <td class="p-3"><a class="font-bold text-emerald-700" href="{{ route('admin.cashbook.finance.direct-sales.show', $sale) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td class="p-3 text-slate-500" colspan="7">No direct sales.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sales->links() }}
</div>

<script>
(() => {
    const root = document.querySelector('[data-direct-sale-root]');
    if (! root) {
        return;
    }

    const products = @json($productOptions);
    const select = root.querySelector('[data-product-select]');
    const search = root.querySelector('[data-product-search]');
    const quantity = root.querySelector('[data-product-quantity]');
    const tbody = root.querySelector('[data-sale-items]');
    const total = root.querySelector('[data-grand-total]');
    const paymentMethod = root.querySelector('[data-payment-method]');
    const bankAccountField = root.querySelector('[data-bank-account-field]');
    let rowIndex = 0;

    const rateForUnit = (product, unit) => {
        const selectedUnit = product.units.find((candidate) => candidate.unit === unit);
        const priceUnit = product.units.find((candidate) => candidate.unit === product.price_unit);

        if (! selectedUnit || ! priceUnit) {
            return 0;
        }

        return Number(product.price) * (Number(selectedUnit.conversion_to_base) / Number(priceUnit.conversion_to_base));
    };

    const renderOptions = () => {
        const term = (search?.value || '').toLowerCase();
        select.innerHTML = '<option value="">Select</option>';

        products
            .filter((product) => `${product.name} ${product.sku || ''} ${product.category || ''}`.toLowerCase().includes(term))
            .forEach((product) => {
                const option = document.createElement('option');
                option.value = product.uuid;
                option.textContent = `${product.name} - Rs ${Number(product.price).toFixed(2)} / ${product.price_unit} (${product.price_source})`;
                select.appendChild(option);
            });
    };

    const recalc = () => {
        const sum = [...tbody.querySelectorAll('[data-line-total]')]
            .reduce((carry, cell) => carry + Number(cell.dataset.lineTotal || 0), 0);
        total.textContent = sum.toFixed(2);
    };

    root.querySelector('[data-add-product]')?.addEventListener('click', () => {
        const product = products.find((candidate) => candidate.uuid === select.value);
        const qty = Number(quantity.value || 0);

        if (! product || qty <= 0) {
            return;
        }

        tbody.querySelector('[data-empty-row]')?.remove();
        const unit = product.units[0].unit;
        const rate = rateForUnit(product, unit);
        const lineTotal = rate * qty;
        const tr = document.createElement('tr');
        tr.className = 'border-t border-slate-100';
        tr.innerHTML = `
            <td class="p-3 font-semibold text-slate-900">
                ${product.name}
                <input type="hidden" name="items[${rowIndex}][product_uuid]" value="${product.uuid}">
            </td>
            <td class="p-3">
                ${qty.toFixed(3)}
                <input type="hidden" name="items[${rowIndex}][quantity]" value="${qty}">
            </td>
            <td class="p-3">
                <select class="rounded-lg border border-slate-200 px-2 py-1 text-sm" name="items[${rowIndex}][unit]" data-unit-select>
                    ${product.units.map((candidate) => `<option value="${candidate.unit}">${candidate.label}</option>`).join('')}
                </select>
            </td>
            <td class="p-3" data-rate>${rate.toFixed(2)}</td>
            <td class="p-3" data-line-total data-line-total="${lineTotal.toFixed(2)}">${lineTotal.toFixed(2)}</td>
            <td class="p-3 text-right"><button type="button" class="font-black text-red-600" data-remove-row>Remove</button></td>
            <input type="hidden" name="items[${rowIndex}][unit_rate]" value="${rate.toFixed(2)}" data-rate-input>
        `;
        rowIndex += 1;
        tbody.appendChild(tr);
        recalc();
    });

    tbody.addEventListener('change', (event) => {
        if (! event.target.matches('[data-unit-select]')) {
            return;
        }

        const tr = event.target.closest('tr');
        const productUuid = tr.querySelector('input[name$="[product_uuid]"]').value;
        const product = products.find((candidate) => candidate.uuid === productUuid);
        const qty = Number(tr.querySelector('input[name$="[quantity]"]').value);
        const rate = rateForUnit(product, event.target.value);
        const lineTotal = rate * qty;
        tr.querySelector('[data-rate]').textContent = rate.toFixed(2);
        tr.querySelector('[data-rate-input]').value = rate.toFixed(2);
        tr.querySelector('[data-line-total]').dataset.lineTotal = lineTotal.toFixed(2);
        tr.querySelector('[data-line-total]').textContent = lineTotal.toFixed(2);
        recalc();
    });

    tbody.addEventListener('click', (event) => {
        if (event.target.matches('[data-remove-row]')) {
            event.target.closest('tr').remove();
            recalc();
        }
    });

    search?.addEventListener('input', renderOptions);
    const toggleBankAccount = () => {
        bankAccountField.hidden = paymentMethod?.value !== 'bank';
    };
    paymentMethod?.addEventListener('change', toggleBankAccount);
    renderOptions();
    toggleBankAccount();
})();
</script>
@endsection
