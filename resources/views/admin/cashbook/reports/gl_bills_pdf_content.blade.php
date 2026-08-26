<header>
    <h1>{{ $title }}</h1>
    <p class="muted">
        {{ $selectedShop?->name ?: 'No outlet selected' }} · {{ $startDate }} to {{ $endDate }} ·
        {{ $selectedProductFilter?->name ?: 'All Products' }}
    </p>
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
        @forelse ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->business_date?->format('Y-m-d') }}</td>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->shop?->name ?: 'Shop #'.$invoice->shop_id }}</td>
                <td class="right">{{ $invoice->filtered_display_total === null ? '' : number_format((float) $invoice->filtered_display_total, 2) }}</td>
                <td class="right">{{ number_format((float) $invoice->final_total, 2) }}</td>
                <td class="right">{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                <td class="right">{{ number_format((float) $invoice->balance_amount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7">No GL invoices found for selected filters.</td>
            </tr>
        @endforelse
    </tbody>
</table>
