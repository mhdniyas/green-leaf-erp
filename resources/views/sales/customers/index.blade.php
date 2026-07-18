<x-layouts.admin title="Sales Destinations">

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search shop, code, owner, email…"
               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 sm:w-80">
        <button type="submit" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition-colors">Filter</button>
        @if(request('search'))
            <a href="{{ route('sales.customers.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>

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
