<x-layouts.app title="Sales Destinations">

    <x-slot:actions>
        @can('sales.customer.create')
        <a href="{{ route('sales.customers.create') }}"
           id="add-customer-btn"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add External Customer
        </a>
        @endcan
    </x-slot:actions>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search shops, owners, or customers…"
               class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 w-56">
        <select name="type" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-400 focus:outline-none">
            <option value="">All Types</option>
            @foreach(['Retailer','Wholesaler','Restaurant','Supermarket'] as $t)
                <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition-colors">Filter</button>
        @if(request('search') || request('type'))
            <a href="{{ route('sales.customers.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Shop Deliveries</h2>
                    <p class="mt-1 text-xs text-gray-500">These shop owners are your real sales delivery points.</p>
                </div>
                <span class="text-xs text-gray-500">{{ $shopDestinations->count() }} shops</span>
            </div>

            @if($shopDestinations->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-medium text-gray-900">No shop destinations found</p>
                <p class="mt-1 text-xs text-gray-500">Create or assign shop owners to make sales delivery points visible here.</p>
            </div>
            @else
            <div class="divide-y divide-gray-100">
                @foreach($shopDestinations as $shop)
                    @php
                        $shopOwners = $shop->users->filter(fn ($user) => $user->hasRole('shop'));
                    @endphp
                    <article class="px-6 py-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-gray-900">{{ $shop->name }}</h3>
                                    <span class="inline-flex items-center rounded-full border border-cyan-200 bg-cyan-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-700">
                                        {{ $shop->code }}
                                    </span>
                                    @if($shop->warehouse_tag)
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                                            Tag {{ $shop->warehouse_tag }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse($shopOwners as $owner)
                                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                                            <p class="text-xs font-semibold text-gray-900">{{ $owner->name }}</p>
                                            <p class="mt-0.5 text-[11px] text-gray-500">{{ $owner->email }}</p>
                                        </div>
                                    @empty
                                        <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                                            No shop owner assigned
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:w-auto sm:grid-cols-3">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-center">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Orders</p>
                                    <p class="mt-1 text-lg font-bold text-gray-900">{{ $shop->orders_count }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-center">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Status</p>
                                    <p class="mt-1 text-sm font-bold text-gray-900">{{ $shop->status ?: 'Active' }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-center">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Type</p>
                                    <p class="mt-1 text-sm font-bold text-emerald-700">Shop Sale</p>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            @endif
        </div>

        {{-- External Customers --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">External Customers</h2>
                <p class="mt-1 text-xs text-gray-500">Optional non-shop customers remain here.</p>
            </div>
            <span class="text-xs text-gray-500">{{ $customers->total() }} customers</span>
        </div>

        @if($customers->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No external customers found</p>
            <p class="text-xs text-gray-500 mt-1">Your shop owners are shown in the delivery section. Add external customers only if needed.</p>
            @can('sales.customer.create')
            <a href="{{ route('sales.customers.create') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm text-brand-600 font-medium hover:underline">
                + Add External Customer
            </a>
            @endcan
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Payment Terms</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Credit</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($customers as $customer)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                                    <span class="text-emerald-700 text-xs font-bold">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $customer->name }}</p>
                                    @if($customer->email)
                                        <p class="text-xs text-gray-400">{{ $customer->email }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center bg-gray-100 text-gray-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                {{ $customer->type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 text-xs">{{ $customer->contact }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $customer->payment_terms }}</td>
                        <td class="px-6 py-4 text-gray-700 font-medium">
                            @if((float) $customer->credit_limit > 0)
                                INR {{ number_format((float) $customer->credit_limit, 0) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($customer->is_active)
                                <span class="inline-flex items-center gap-1 text-xs font-medium border px-2.5 py-0.5 rounded-full bg-green-50 text-green-700 border-green-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium border px-2.5 py-0.5 rounded-full bg-gray-50 text-gray-500 border-gray-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('sales.customer.update')
                                <a href="{{ route('sales.customers.edit', $customer) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </a>
                                @endcan

                                @can('delete', $customer)
                                <form method="POST" action="{{ route('sales.customers.destroy', $customer) }}"
                                      onsubmit="return confirm('Delete customer {{ $customer->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
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
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $customers->withQueryString()->links() }}
        </div>
        @endif
        @endif
        </div>
    </div>

</x-layouts.app>
