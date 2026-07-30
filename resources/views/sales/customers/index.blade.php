<x-layouts.admin title="Shop Deliveries">
    @php
        $formContext = old('_form_context');
        $showCreateForm = $formContext === 'create-shop' || ($errors->any() && ! str_starts_with((string) $formContext, 'edit-shop-'));
        $destinationTypes = [
            'client' => 'Client',
            'direct' => 'Direct sale',
        ];
    @endphp

    <x-slot:actions>
        @can('sales.customer.create')
            <button type="button"
                    onclick="document.getElementById('shop-create-panel')?.classList.toggle('hidden')"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Shop
            </button>
        @endcan
    </x-slot:actions>

    @can('sales.customer.create')
        <div id="shop-create-panel" class="{{ $showCreateForm ? '' : 'hidden ' }}mb-4 overflow-hidden rounded-lg border border-brand-100 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Create Shop</h2>
                    <p class="mt-1 text-xs text-gray-500">Choose whether this shop belongs to a client or is a direct-sale shop.</p>
                </div>
                <button type="button"
                        onclick="document.getElementById('shop-create-panel')?.classList.add('hidden')"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-800"
                        title="Close create form">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('sales.customers.shops.store') }}" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                @csrf
                <input type="hidden" name="_form_context" value="create-shop">

                <div>
                    <label for="create-shop-name" class="mb-1.5 block text-xs font-semibold text-gray-700">Shop Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="create-shop-name" value="{{ $showCreateForm ? old('name') : '' }}" required
                           class="w-full rounded-lg border @error('name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @if($showCreateForm) @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-code" class="mb-1.5 block text-xs font-semibold text-gray-700">Shop Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" id="create-shop-code" value="{{ $showCreateForm ? old('code') : '' }}" required maxlength="20"
                           class="w-full rounded-lg border @error('code') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm font-semibold uppercase text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @if($showCreateForm) @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-destination-type" class="mb-1.5 block text-xs font-semibold text-gray-700">Sale Type <span class="text-red-500">*</span></label>
                    <select name="destination_type" id="create-shop-destination-type" required
                            class="w-full rounded-lg border @error('destination_type') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        @foreach($destinationTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($showCreateForm ? old('destination_type', 'client') : 'client') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($showCreateForm) @error('destination_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-status" class="mb-1.5 block text-xs font-semibold text-gray-700">Status <span class="text-red-500">*</span></label>
                    <select name="status" id="create-shop-status" required
                            class="w-full rounded-lg border @error('status') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="active" @selected(($showCreateForm ? old('status', 'active') : 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(($showCreateForm ? old('status') : '') === 'inactive')>Inactive</option>
                    </select>
                    @if($showCreateForm) @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-client-id" class="mb-1.5 block text-xs font-semibold text-gray-700">Client</label>
                    <select name="client_id" id="create-shop-client-id"
                            class="w-full rounded-lg border @error('client_id') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">Select client</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @if($showCreateForm) @error('client_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-client-name" class="mb-1.5 block text-xs font-semibold text-gray-700">New Client</label>
                    <input type="text" name="client_name" id="create-shop-client-name" value="{{ $showCreateForm ? old('client_name') : '' }}"
                           class="w-full rounded-lg border @error('client_name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @if($showCreateForm) @error('client_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-warehouse-tag" class="mb-1.5 block text-xs font-semibold text-gray-700">Warehouse Tag</label>
                    <input type="text" name="warehouse_tag" id="create-shop-warehouse-tag" value="{{ $showCreateForm ? old('warehouse_tag') : '' }}" maxlength="12"
                           class="w-full rounded-lg border @error('warehouse_tag') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm font-semibold uppercase text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @if($showCreateForm) @error('warehouse_tag') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-price-group" class="mb-1.5 block text-xs font-semibold text-gray-700">Price Category</label>
                    <select name="shop_price_group_id" id="create-shop-price-group"
                            class="w-full rounded-lg border @error('shop_price_group_id') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">No category</option>
                        @foreach($priceGroups as $priceGroup)
                            <option value="{{ $priceGroup->id }}" @selected((string) old('shop_price_group_id') === (string) $priceGroup->id)>{{ $priceGroup->display_name }}</option>
                        @endforeach
                    </select>
                    @if($showCreateForm) @error('shop_price_group_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-contact-name" class="mb-1.5 block text-xs font-semibold text-gray-700">Contact Name</label>
                    <input type="text" name="contact_name" id="create-shop-contact-name" value="{{ $showCreateForm ? old('contact_name') : '' }}"
                           class="w-full rounded-lg border @error('contact_name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @if($showCreateForm) @error('contact_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="create-shop-contact-phone" class="mb-1.5 block text-xs font-semibold text-gray-700">Contact Phone</label>
                    <input type="text" name="contact_phone" id="create-shop-contact-phone" value="{{ $showCreateForm ? old('contact_phone') : '' }}"
                           class="w-full rounded-lg border @error('contact_phone') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @if($showCreateForm) @error('contact_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div class="md:col-span-2">
                    <label for="create-shop-address" class="mb-1.5 block text-xs font-semibold text-gray-700">Address</label>
                    <input type="text" name="address" id="create-shop-address" value="{{ $showCreateForm ? old('address') : '' }}"
                           class="w-full rounded-lg border @error('address') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @if($showCreateForm) @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div class="flex flex-col-reverse gap-2 md:col-span-2 xl:col-span-4 sm:flex-row sm:justify-end">
                    <button type="button"
                            onclick="document.getElementById('shop-create-panel')?.classList.add('hidden')"
                            class="inline-flex justify-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700">
                        Create Shop
                    </button>
                </div>
            </form>
        </div>
    @endcan

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search shop, code, owner, email..."
               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 sm:w-80">
        <button type="submit" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-200">Filter</button>
        @if(request('search'))
            <a href="{{ route('sales.customers.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-1 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Shop Deliveries</h2>
                <p class="mt-1 text-xs text-gray-500">Create and maintain shops used as sales delivery destinations.</p>
            </div>
            <span class="text-xs text-gray-500">{{ $shopDestinations->count() }} shops</span>
        </div>

        @if($shopDestinations->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-medium text-gray-900">No shop destinations found</p>
                <p class="mt-1 text-xs text-gray-500">Create a shop to make it available for sales delivery.</p>
            </div>
        @else
            <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[1220px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Shop</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Sale Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Owners</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Address</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Warehouse Tag</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Orders</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($shopDestinations as $shop)
                            @php
                                $shopOwners = $shop->users->filter(fn ($user) => $user->hasRole('shop'));
                                $editContext = 'edit-shop-'.$shop->id;
                                $showEditForm = $formContext === $editContext;
                                $editPrefix = 'edit-shop-'.$shop->id;
                                $destinationType = $shop->client_id ? 'client' : 'direct';
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="px-6 py-4 align-top">
                                    <div class="min-w-48">
                                        <p class="font-semibold text-gray-900">{{ $shop->name }}</p>
                                        <p class="mt-1 text-xs font-medium text-cyan-700">{{ $shop->code }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    @if($shop->client)
                                        <div class="min-w-40">
                                            <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">Client</span>
                                            <p class="mt-2 text-xs font-semibold text-gray-600">{{ $shop->client->name }}</p>
                                        </div>
                                    @else
                                        <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">Direct sale</span>
                                    @endif
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
                                    @if($shop->status === 'active')
                                        <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('sales.customer.update')
                                            <button type="button"
                                                    onclick="document.getElementById('shop-edit-row-{{ $shop->id }}')?.classList.toggle('hidden')"
                                                    class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-brand-50 hover:text-brand-600"
                                                    title="Edit shop">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                            </button>
                                        @endcan

                                        @if($shop->isOwnedAccountingEnabled())
                                            <a href="{{ route('admin.accounting.owned-shops.show', $shop) }}"
                                               class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                                               title="Open shop accounting">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6 12-12m0 0H15m4.5 0V9" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            @can('sales.customer.update')
                                <tr id="shop-edit-row-{{ $shop->id }}" class="{{ $showEditForm ? '' : 'hidden ' }}bg-brand-50/40">
                                    <td colspan="9" class="px-6 py-5">
                                        <form method="POST" action="{{ route('sales.customers.shops.update', $shop) }}" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="_form_context" value="{{ $editContext }}">

                                            <div>
                                                <label for="{{ $editPrefix }}-name" class="mb-1.5 block text-xs font-semibold text-gray-700">Shop Name <span class="text-red-500">*</span></label>
                                                <input type="text" name="name" id="{{ $editPrefix }}-name" value="{{ $showEditForm ? old('name') : $shop->name }}" required
                                                       class="w-full rounded-lg border @if($showEditForm && $errors->has('name')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                @if($showEditForm) @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-code" class="mb-1.5 block text-xs font-semibold text-gray-700">Shop Code <span class="text-red-500">*</span></label>
                                                <input type="text" name="code" id="{{ $editPrefix }}-code" value="{{ $showEditForm ? old('code') : $shop->code }}" required maxlength="20"
                                                       class="w-full rounded-lg border @if($showEditForm && $errors->has('code')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm font-semibold uppercase text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                @if($showEditForm) @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-destination-type" class="mb-1.5 block text-xs font-semibold text-gray-700">Sale Type <span class="text-red-500">*</span></label>
                                                <select name="destination_type" id="{{ $editPrefix }}-destination-type" required
                                                        class="w-full rounded-lg border @if($showEditForm && $errors->has('destination_type')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                    @foreach($destinationTypes as $value => $label)
                                                        <option value="{{ $value }}" @selected(($showEditForm ? old('destination_type', $destinationType) : $destinationType) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @if($showEditForm) @error('destination_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-status" class="mb-1.5 block text-xs font-semibold text-gray-700">Status <span class="text-red-500">*</span></label>
                                                <select name="status" id="{{ $editPrefix }}-status" required
                                                        class="w-full rounded-lg border @if($showEditForm && $errors->has('status')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                    <option value="active" @selected(($showEditForm ? old('status', $shop->status) : $shop->status) === 'active')>Active</option>
                                                    <option value="inactive" @selected(($showEditForm ? old('status', $shop->status) : $shop->status) === 'inactive')>Inactive</option>
                                                </select>
                                                @if($showEditForm) @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-client-id" class="mb-1.5 block text-xs font-semibold text-gray-700">Client</label>
                                                <select name="client_id" id="{{ $editPrefix }}-client-id"
                                                        class="w-full rounded-lg border @if($showEditForm && $errors->has('client_id')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                    <option value="">Select client</option>
                                                    @foreach($clients as $client)
                                                        <option value="{{ $client->id }}" @selected((string) ($showEditForm ? old('client_id', $shop->client_id) : $shop->client_id) === (string) $client->id)>{{ $client->name }}</option>
                                                    @endforeach
                                                </select>
                                                @if($showEditForm) @error('client_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-client-name" class="mb-1.5 block text-xs font-semibold text-gray-700">New Client</label>
                                                <input type="text" name="client_name" id="{{ $editPrefix }}-client-name" value="{{ $showEditForm ? old('client_name') : '' }}"
                                                       class="w-full rounded-lg border @if($showEditForm && $errors->has('client_name')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                @if($showEditForm) @error('client_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-warehouse-tag" class="mb-1.5 block text-xs font-semibold text-gray-700">Warehouse Tag</label>
                                                <input type="text" name="warehouse_tag" id="{{ $editPrefix }}-warehouse-tag" value="{{ $showEditForm ? old('warehouse_tag') : $shop->warehouse_tag }}" maxlength="12"
                                                       class="w-full rounded-lg border @if($showEditForm && $errors->has('warehouse_tag')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm font-semibold uppercase text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                @if($showEditForm) @error('warehouse_tag') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-price-group" class="mb-1.5 block text-xs font-semibold text-gray-700">Price Category</label>
                                                <select name="shop_price_group_id" id="{{ $editPrefix }}-price-group"
                                                        class="w-full rounded-lg border @if($showEditForm && $errors->has('shop_price_group_id')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                    <option value="">No category</option>
                                                    @foreach($priceGroups as $priceGroup)
                                                        <option value="{{ $priceGroup->id }}" @selected((string) ($showEditForm ? old('shop_price_group_id', $shop->shop_price_group_id) : $shop->shop_price_group_id) === (string) $priceGroup->id)>{{ $priceGroup->display_name }}</option>
                                                    @endforeach
                                                </select>
                                                @if($showEditForm) @error('shop_price_group_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-contact-name" class="mb-1.5 block text-xs font-semibold text-gray-700">Contact Name</label>
                                                <input type="text" name="contact_name" id="{{ $editPrefix }}-contact-name" value="{{ $showEditForm ? old('contact_name') : $shop->contact_name }}"
                                                       class="w-full rounded-lg border @if($showEditForm && $errors->has('contact_name')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                @if($showEditForm) @error('contact_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div>
                                                <label for="{{ $editPrefix }}-contact-phone" class="mb-1.5 block text-xs font-semibold text-gray-700">Contact Phone</label>
                                                <input type="text" name="contact_phone" id="{{ $editPrefix }}-contact-phone" value="{{ $showEditForm ? old('contact_phone') : $shop->contact_phone }}"
                                                       class="w-full rounded-lg border @if($showEditForm && $errors->has('contact_phone')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                @if($showEditForm) @error('contact_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div class="md:col-span-2">
                                                <label for="{{ $editPrefix }}-address" class="mb-1.5 block text-xs font-semibold text-gray-700">Address</label>
                                                <input type="text" name="address" id="{{ $editPrefix }}-address" value="{{ $showEditForm ? old('address') : $shop->address }}"
                                                       class="w-full rounded-lg border @if($showEditForm && $errors->has('address')) border-red-400 @else border-gray-200 @endif bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                @if($showEditForm) @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                                            </div>

                                            <div class="flex flex-col-reverse gap-2 md:col-span-2 xl:col-span-4 sm:flex-row sm:justify-end">
                                                <button type="button"
                                                        onclick="document.getElementById('shop-edit-row-{{ $shop->id }}')?.classList.add('hidden')"
                                                        class="inline-flex justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700">
                                                    Save Shop
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endcan
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.admin>
