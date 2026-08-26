<header>
    <h1>{{ $title }}</h1>
    <div class="filter-card">
        <div>
            <span class="filter-label">Outlet scope</span>
            <strong>{{ $exportScopeLabel }}</strong>
        </div>
        <div>
            <span class="filter-label">Product filter</span>
            <strong>{{ $selectedProductFilter?->name ?: 'All Products' }}</strong>
        </div>
        <div>
            <span class="filter-label">Period</span>
            <strong>{{ $startDate }} to {{ $endDate }}</strong>
        </div>
    </div>
</header>

<table class="summary">
    <tbody>
        <tr>
            <td><strong>Total Billed</strong><br>{{ number_format((float) $totals['total_billed'], 2) }}</td>
            <td><strong>Paid Amount</strong><br>{{ number_format((float) $totals['total_paid'], 2) }}</td>
            <td><strong>Balance Due</strong><br>{{ number_format((float) $totals['total_balance'], 2) }}</td>
            <td><strong>Invoices</strong><br>{{ $totals['count'] }}</td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Invoice</th>
            <th>Shop</th>
            <th class="right">Filter Total</th>
            <th class="right">Invoice Total</th>
            <th class="right">Paid</th>
            <th class="right">Balance</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoices->groupBy('shop_id') as $shopInvoices)
            @php
                $firstInvoice = $shopInvoices->first();
            @endphp
            <tr class="shop-spacer">
                <td colspan="7"></td>
            </tr>
            <tr class="shop-heading">
                <td colspan="7">
                    <strong>{{ $firstInvoice?->shop?->name ?: 'Shop #'.$firstInvoice?->shop_id }}</strong>
                    <span>{{ $firstInvoice?->shop?->code }} · {{ $shopInvoices->count() }} invoices · {{ number_format((float) $shopInvoices->sum(fn ($invoice): float => $invoice->filtered_display_total === null ? (float) $invoice->final_total : (float) $invoice->filtered_display_total), 2) }}</span>
                </td>
            </tr>
            @foreach ($shopInvoices as $invoice)
                <tr>
                    <td>{{ $invoice->business_date?->format('Y-m-d') }}</td>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->shop?->name ?: 'Shop #'.$invoice->shop_id }}</td>
                    <td class="right">{{ $invoice->filtered_display_total === null ? '' : number_format((float) $invoice->filtered_display_total, 2) }}</td>
                    <td class="right">{{ number_format((float) $invoice->final_total, 2) }}</td>
                    <td class="right">{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $invoice->balance_amount, 2) }}</td>
                </tr>
            @endforeach
        @empty
            <tr>
                <td colspan="7">No GL invoices found for selected filters.</td>
            </tr>
        @endforelse
    </tbody>
</table>
