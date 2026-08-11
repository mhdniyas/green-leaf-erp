<x-layouts.admin title="Add User">

    <x-slot:actions>
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to Users
        </a>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.users.store') }}" class="mx-auto max-w-5xl space-y-5">
        @csrf

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Account Details</h2>
                <p class="mt-1 text-xs text-gray-500">Create the login and optional shop link for this user.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-1.5 block text-xs font-semibold text-gray-700">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autocomplete="name"
                           class="w-full rounded-lg border @error('name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold text-gray-700">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email"
                           class="w-full rounded-lg border @error('email') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-xs font-semibold text-gray-700">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" id="password" required autocomplete="new-password" placeholder="Minimum 8 characters"
                           class="w-full rounded-lg border @error('password') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="shop_id" class="mb-1.5 block text-xs font-semibold text-gray-700">Shop Assignment</label>
                    <select name="shop_id" id="shop_id"
                            class="w-full rounded-lg border @error('shop_id') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">No shop linked</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}" @selected((string) old('shop_id') === (string) $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                    @error('shop_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Warehouse Assignments</h2>
                <p class="mt-1 text-xs text-gray-500">Choose the warehouses this user may access and their default warehouse.</p>
            </div>

            <div class="space-y-4 p-5">
                @error('warehouse_ids') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($warehouses as $warehouse)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-4 hover:border-brand-200 hover:bg-brand-50/40">
                            <input type="checkbox" name="warehouse_ids[]" value="{{ $warehouse->id }}"
                                   @checked(in_array($warehouse->id, array_map('intval', old('warehouse_ids', [])), true))
                                   class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $warehouse->name }}</span>
                                <span class="block text-xs text-gray-500">{{ $warehouse->code }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="max-w-md">
                    <label for="default_warehouse_id" class="mb-1.5 block text-xs font-semibold text-gray-700">Default Warehouse</label>
                    <select name="default_warehouse_id" id="default_warehouse_id"
                            class="w-full rounded-lg border @error('default_warehouse_id') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800">
                        <option value="">Use first assigned warehouse</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('default_warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('default_warehouse_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Access Roles</h2>
                <p class="mt-1 text-xs text-gray-500">Choose roles only. Each role already includes the correct access for that job.</p>
            </div>

            <div class="p-5">
                @error('roles') <p class="mb-4 text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($roles as $role)
                        <label class="group flex min-h-24 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 transition-colors hover:border-brand-200 hover:bg-brand-50/40">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, old('roles', []), true))
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold capitalize text-gray-900">{{ str_replace('_', ' ', $role->name) }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ $role->permissions_count }} included permissions</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.users.index') }}" class="inline-flex justify-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Cancel</a>
            <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700">
                Create User
            </button>
        </div>
    </form>

</x-layouts.admin>
