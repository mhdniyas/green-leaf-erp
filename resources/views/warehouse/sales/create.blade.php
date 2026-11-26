<x-layouts.app title="Warehouse Sale - New Sale">
    @include('warehouse.sales.partials._create_form', [
        'backUrl' => route('warehouse.sales.index', ['warehouse_id' => $selectedWarehouseId, 'date' => $date ?? \Carbon\Carbon::today()->toDateString()]),
        'storeUrl' => route('warehouse.sales.store'),
        'storeCustomerUrl' => route('warehouse.sales.customers.store'),
        'searchProductsUrl' => route('warehouse.sales.search-products'),
    ])
</x-layouts.app>
