<x-layouts.admin title="Edit Customer">

    <x-slot:actions>
        <a href="{{ route('sales.customers.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to Customers
        </a>
    </x-slot:actions>

    @include('sales.customers.partials.form', [
        'formAction' => route('sales.customers.update', $customer),
        'formMethod' => 'PUT',
        'cancelHref' => route('sales.customers.index'),
        'submitLabel' => 'Save Customer',
        'customer' => $customer,
    ])

</x-layouts.admin>
