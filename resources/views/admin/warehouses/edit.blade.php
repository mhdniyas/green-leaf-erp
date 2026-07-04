<x-layouts.admin title="Edit Warehouse">

    <div class="max-w-xl">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs">
            <h2 class="text-sm font-semibold text-gray-900 mb-6">Modify Warehouse Details</h2>

            <form method="POST" action="{{ route('admin.warehouses.update', $warehouse) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Warehouse Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $warehouse->name) }}" required
                           class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    @error('name')
                        <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="code" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Warehouse Code</label>
                    <input type="text" name="code" id="code" value="{{ old('code', $warehouse->code) }}" required placeholder="e.g. VEG-WH"
                           class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    @error('code')
                        <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-2.5 pt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $warehouse->is_active) ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <label for="is_active" class="text-xs font-bold text-gray-700 uppercase tracking-wide">Is Active</label>
                </div>

                <div class="flex items-center gap-3 pt-6 border-t border-gray-100">
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer">
                        Update Warehouse
                    </button>
                    <a href="{{ route('admin.warehouses.index') }}"
                       class="inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.app>
