<x-layouts.admin title="Sales Destinations">

    <x-slot:actions>
        @can('sales.customer.create')
            <a href="{{ route('sales.customers.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add External Customer
            </a>
        @endcan
    </x-slot:actions>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search customer, shop, owner, email..."
               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 sm:w-80">
        <select name="type" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 sm:w-48">
            <option value="">All customer types</option>
            @foreach(['Retailer', 'Wholesaler', 'Restaurant', 'Supermarket'] as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition-colors">Filter</button>
        @if(request('search') || request('type'))
            <a href="{{ route('sales.customers.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-1 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">External Customers</h2>
                <p class="mt-1 text-xs text-gray-500">Customer records used for direct sales invoices and credit tracking.</p>
            </div>
            <span class="text-xs text-gray-500">{{ $customers->total() }} customers</span>
        </div>

        @if($customers->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-medium text-gray-900">No external customers found</p>
                <p class="mt-1 text-xs text-gray-500">Add a customer to use them on sales invoices.</p>
                @can('sales.customer.create')
                    <a href="{{ route('sales.customers.create') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:underline">
                        + Add External Customer
                    </a>
                @endcan
            </div>
        @else
            <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[980px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Payment</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Credit Limit</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($customers as $customer)
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="px-6 py-4 align-top">
                                    <div class="min-w-52">
                                        <p class="font-semibold text-gray-900">{{ $customer->name }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $customer->address ?: 'No address added' }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $customer->type }}</span>
                                </td>
                                <td class="px-6 py-4 align-top text-gray-600">
                                    <div class="min-w-48">
                                        <p>{{ $customer->contact }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $customer->email ?: 'No email' }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top text-gray-600">{{ $customer->payment_terms }}</td>
                                <td class="px-6 py-4 align-top font-semibold text-gray-900">{{ number_format((float) $customer->credit_limit, 2) }}</td>
                                <td class="px-6 py-4 align-top">
                                    @if($customer->is_active)
                                        <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('sales.customer.update')
                                            <a href="{{ route('sales.customers.edit', $customer) }}"
                                               class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-brand-50 hover:text-brand-600"
                                               title="Edit">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                            </a>
                                        @endcan

                                        @can('sales.customer.delete')
                                            <form method="POST" action="{{ route('sales.customers.destroy', $customer) }}"
                                                  onsubmit="return confirm('Delete customer {{ $customer->name }}? This will soft delete the customer record.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" title="Delete">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
                <div class="border-t border-gray-100 px-6 py-4">
                    {{ $customers->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-100 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Shop Deliveries</h2>
                <p class="mt-1 text-xs text-gray-500">These shops are the sales delivery customers.</p>
            </div>
            <span class="text-xs text-gray-500">{{ $shopDestinations->count() }} shops</span>
        </div>

        @if($shopDestinations->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-medium text-gray-900">No shop destinations found</p>
                <p class="mt-1 text-xs text-gray-500">Create or assign shop owners to make sales delivery points visible here.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Shop</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Owners</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Address</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Warehouse Tag</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Accounting</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($shopDestinations as $shop)
                    @php
                        $shopOwners = $shop->users->filter(fn ($user) => $user->hasRole('shop'));
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 align-top">
                            <div class="min-w-48">
                                <p class="font-semibold text-gray-900">{{ $shop->name }}</p>
                                <p class="mt-1 text-xs font-medium text-cyan-700">{{ $shop->code }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex min-w-56 flex-col gap-2">
                                @forelse($shopOwners as $owner)
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $owner->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $owner->email }}</p>
                                </div>
                                @empty
                                <span class="inline-flex w-fit rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                    No owner assigned
                                </span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top text-gray-600">
                            <div class="min-w-36">
                                <p>{{ $shop->contact_name ?: 'N/A' }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $shop->contact_phone ?: 'No phone' }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top text-gray-600">
                            <p class="min-w-56 whitespace-normal">{{ $shop->address ?: 'No address added' }}</p>
                        </td>
                        <td class="px-6 py-4 align-top">
                            @if($shop->warehouse_tag)
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                {{ $shop->warehouse_tag }}
                            </span>
                            @else
                            <span class="text-xs text-gray-400">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 align-top font-semibold text-gray-900">{{ $shop->orders_count }}</td>
                        <td class="px-6 py-4 align-top">
                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                {{ ucfirst($shop->status ?: 'active') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="min-w-36">
                                <p class="text-sm font-semibold text-gray-900">{{ ucwords(str_replace('_', ' ', (string) ($shop->accounting_mode ?: 'standard'))) }}</p>
                                <p class="mt-1 text-xs {{ $shop->accounting_enabled ? 'text-emerald-700' : 'text-gray-500' }}">
                                    {{ $shop->accounting_enabled ? 'Enabled' : 'Disabled' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</x-layouts.admin>
