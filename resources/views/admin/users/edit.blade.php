<x-layouts.admin title="Edit User">

    <x-slot:actions>
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Users
        </a>
    </x-slot:actions>

    <div class="max-w-4xl mx-auto">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- 1. Profile --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">User Profile — {{ $user->name }}</h2>
                </div>

                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="name" class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                                   class="w-full rounded-lg border @error('name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-xs font-semibold text-gray-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                                   class="w-full rounded-lg border @error('email') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="shop_id" class="block text-xs font-semibold text-gray-700 mb-1.5">Shop Assignment</label>
                            <select name="shop_id" id="shop_id"
                                class="w-full rounded-lg border @error('shop_id') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                <option value="">No shop linked</option>
                                @foreach($shops as $shop)
                                    <option value="{{ $shop->id }}" @selected((string) old('shop_id', $user->shop_id) === (string) $shop->id)>{{ $shop->name }}</option>
                                @endforeach
                            </select>
                            @error('shop_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-gray-700 mb-1.5">Password</label>
                        <input type="password" name="password" id="password" placeholder="Leave blank to keep current password"
                               class="w-full rounded-lg border @error('password') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 2. Roles --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Assign Roles</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Assign one or more roles that define default sets of permissions.</p>
                </div>

                <div class="p-6">
                    @error('roles') <p class="mb-4 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-3 gap-4">
                        @foreach($roles as $role)
                        <label class="relative flex items-start rounded-lg border border-gray-200 p-4 hover:bg-gray-50 cursor-pointer select-none">
                            <div class="flex h-5 items-center">
                                <input type="checkbox" name="roles[]" value="{{ $role->name }}" 
                                       @checked(in_array($role->name, old('roles', $user->roles->pluck('name')->toArray())))
                                       class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-medium text-gray-900">{{ $role->name }}</span>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 3. Direct Permissions --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Direct Permission Overrides</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Define custom permissions directly for this user on top of their roles.</p>
                </div>

                <div class="p-6 space-y-6">
                    @error('permissions') <p class="text-xs text-red-600 mb-4">{{ $message }}</p> @enderror

                    @php
                        // Group permissions by their module namespace (first segment before dot)
                        $grouped = $permissions->groupBy(function($item) {
                            return explode('.', $item->name)[0] ?? 'Other';
                        });
                        $directPermissions = $user->permissions->pluck('name')->toArray();
                    @endphp

                    <div class="grid grid-cols-2 gap-6">
                        @foreach($grouped as $module => $modulePerms)
                        <div class="border border-gray-100 rounded-xl p-4 bg-gray-50/50">
                            <h3 class="text-xs font-bold text-brand-700 uppercase tracking-wide mb-3 border-b border-gray-100 pb-1.5">{{ ucfirst($module) }}</h3>
                            <div class="space-y-2">
                                @foreach($modulePerms as $perm)
                                <label class="flex items-start gap-2.5 cursor-pointer text-xs select-none">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                                           @checked(in_array($perm->name, old('permissions', $directPermissions)))
                                           class="mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-gray-700 font-medium font-mono text-[11px]">{{ $perm->name }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

</x-layouts.app>
