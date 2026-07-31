<x-layouts.inventory title="Edit Category">

    <div class="max-w-2xl">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Edit Category: {{ $category->name }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">Update product category information.</p>
            </div>

            <form method="POST" action="{{ route('inventory.categories.update', $category) }}" class="p-6 space-y-5">
                @csrf
                @method('PUT')

                {{-- Name input --}}
                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-gray-700">Category Name <span class="text-red-500">*</span></label>
                    <input id="name" name="name" type="text" required
                           value="{{ old('name', $category->name) }}"
                           placeholder="e.g. Vegetables"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('name') border-red-300 @enderror">
                    @error('name') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>

                {{-- Description input --}}
                <div class="space-y-1.5">
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="4"
                              placeholder="Describe the category…"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('description') border-red-300 @enderror">{{ old('description', $category->description) }}</textarea>
                    @error('description') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>

                {{-- Is Active checkbox --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" name="is_active" type="checkbox" value="1"
                               @checked(old('is_active', $category->is_active))
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 focus:ring-offset-0 cursor-pointer">
                        <label for="is_active" class="text-sm font-semibold text-slate-800 cursor-pointer">Active status</label>
                    </div>
                    <p class="text-xs text-gray-400 mt-1 pl-7">Inactive categories cannot be assigned to new products.</p>
                </div>

                {{-- Form Actions --}}
                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer">
                        Update Category
                    </button>
                    <a href="{{ route('inventory.categories.index') }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.inventory>
