@extends('admin.cashbook.layouts.app')

@section('title', 'Create Product Filter - Purchase Cashbook')
@section('header_title')
    <i data-lucide="plus-circle" class="h-5 w-5 text-emerald-600"></i> Create Product Filter
@endsection

@section('header_subtitle')
    Define a reusable set of products for cashbook reporting
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5" x-data="productFilterManager()">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-xl font-black text-slate-950">New Product Filter</h1>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Select categories to quickly check products, then add or remove individual products as needed.</p>
        </div>
        <a href="{{ route('admin.cashbook.finance.purchase.product-filters.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Filters
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 space-y-1">
            <div class="flex items-center gap-2 text-rose-900 font-black">
                <i data-lucide="alert-triangle" class="h-4 w-4"></i> Please correct the following errors:
            </div>
            <ul class="list-disc pl-5 font-semibold space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.cashbook.finance.purchase.product-filters.store') }}" @submit="validateForm($event)" class="space-y-6">
        @csrf

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <div>
                <label for="name" class="block text-xs font-black uppercase text-slate-700">Filter Name <span class="text-rose-600">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. Local Vegetables, Morning Market, Fruit Selection..." required class="mt-1.5 min-h-10 w-full max-w-lg rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                <p class="mt-1 text-[11px] font-semibold text-slate-500">Give this filter a descriptive name so it is easy to recognize on the purchase cashbook dashboard.</p>
            </div>
        </div>

        <!-- Category Helper & Search Toolbar -->
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Select Products</h2>
                    <p class="text-[11px] font-semibold text-slate-500">Click a category button to select all its products, or pick individual items below.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-800 border border-emerald-200">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                        <span x-text="selectedCount">0</span> selected
                    </span>
                    <button type="button" @click="selectAll()" class="text-xs font-bold text-emerald-700 hover:underline">Select All</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" @click="clearAll()" class="text-xs font-bold text-slate-500 hover:underline">Clear All</button>
                </div>
            </div>

            <!-- Quick Category Selection Pills -->
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1.5">Quick Select by Category</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($categories as $category)
                        <button type="button"
                                @click="toggleCategory({{ $category->id }}, {{ $category->products->pluck('id')->toJson() }})"
                                :class="isCategoryFullySelected({{ $category->products->pluck('id')->toJson() }}) ? 'bg-emerald-700 text-white border-emerald-700' : (isCategoryPartiallySelected({{ $category->products->pluck('id')->toJson() }}) ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100')"
                                class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition">
                            <span>{{ $category->name }}</span>
                            <span class="text-[10px] opacity-75">({{ $category->products->count() }})</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Live Product Search -->
            <div class="pt-2">
                <div class="relative max-w-md">
                    <i data-lucide="search" class="absolute left-3 top-3 h-4 w-4 text-slate-400"></i>
                    <input type="text" x-model="searchQuery" placeholder="Filter products by name or SKU..." class="min-h-10 w-full rounded-lg border border-slate-300 pl-9 pr-3 text-xs font-semibold text-slate-800 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                </div>
            </div>
        </div>

        <!-- Product Categories and Checkboxes -->
        <div class="space-y-4">
            @foreach($categories as $category)
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                     x-show="categoryMatchesSearch('{{ addslashes($category->name) }}', {{ $category->products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])->toJson() }})">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-black uppercase text-slate-800">{{ $category->name }}</h3>
                            <span class="text-[10px] font-bold text-slate-400">({{ $category->products->count() }} items)</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <button type="button" @click="selectCategoryProducts({{ $category->products->pluck('id')->toJson() }})" class="font-bold text-emerald-700 hover:underline">Select All</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" @click="deselectCategoryProducts({{ $category->products->pluck('id')->toJson() }})" class="font-bold text-slate-400 hover:underline">None</button>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                        @foreach($category->products as $product)
                            <label class="flex items-start gap-2.5 rounded-lg border border-slate-100 p-2 hover:bg-slate-50 cursor-pointer transition"
                                   x-show="productMatchesSearch('{{ addslashes($product->name) }}', '{{ addslashes($product->sku ?? '') }}')">
                                <input type="checkbox"
                                       name="product_ids[]"
                                       value="{{ $product->id }}"
                                       @change="toggleProduct({{ $product->id }})"
                                       :checked="selectedProducts.includes({{ $product->id }})"
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <div class="min-w-0 text-xs">
                                    <div class="font-bold text-slate-900 truncate">{{ $product->name }}</div>
                                    <div class="text-[10px] font-semibold text-slate-400">
                                        @if($product->sku) SKU: {{ $product->sku }} · @endif
                                        {{ $product->unit ?? 'unit' }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if($uncategorizedProducts->isNotEmpty())
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                     x-show="categoryMatchesSearch('Uncategorized', {{ $uncategorizedProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])->toJson() }})">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-black uppercase text-slate-800">Uncategorized</h3>
                            <span class="text-[10px] font-bold text-slate-400">({{ $uncategorizedProducts->count() }} items)</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <button type="button" @click="selectCategoryProducts({{ $uncategorizedProducts->pluck('id')->toJson() }})" class="font-bold text-emerald-700 hover:underline">Select All</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" @click="deselectCategoryProducts({{ $uncategorizedProducts->pluck('id')->toJson() }})" class="font-bold text-slate-400 hover:underline">None</button>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                        @foreach($uncategorizedProducts as $product)
                            <label class="flex items-start gap-2.5 rounded-lg border border-slate-100 p-2 hover:bg-slate-50 cursor-pointer transition"
                                   x-show="productMatchesSearch('{{ addslashes($product->name) }}', '{{ addslashes($product->sku ?? '') }}')">
                                <input type="checkbox"
                                       name="product_ids[]"
                                       value="{{ $product->id }}"
                                       @change="toggleProduct({{ $product->id }})"
                                       :checked="selectedProducts.includes({{ $product->id }})"
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <div class="min-w-0 text-xs">
                                    <div class="font-bold text-slate-900 truncate">{{ $product->name }}</div>
                                    <div class="text-[10px] font-semibold text-slate-400">
                                        @if($product->sku) SKU: {{ $product->sku }} · @endif
                                        {{ $product->unit ?? 'unit' }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Sticky Footer / Action Bar -->
        <div class="sticky bottom-0 rounded-lg border border-slate-200 bg-white/95 backdrop-blur p-4 shadow-lg flex items-center justify-between z-10">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-700">Selected Products:</span>
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-black text-emerald-800" x-text="selectedCount">0</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.finance.purchase.product-filters.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-5 py-2 text-xs font-black text-white hover:bg-emerald-800 shadow-sm">
                    <i data-lucide="check" class="h-4 w-4"></i> Save Product Filter
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function productFilterManager() {
    return {
        selectedProducts: @json(old('product_ids', [])),
        searchQuery: '',

        get selectedCount() {
            return this.selectedProducts.length;
        },

        toggleProduct(id) {
            id = parseInt(id);
            const index = this.selectedProducts.indexOf(id);
            if (index > -1) {
                this.selectedProducts.splice(index, 1);
            } else {
                this.selectedProducts.push(id);
            }
        },

        selectCategoryProducts(productIds) {
            productIds.forEach(id => {
                id = parseInt(id);
                if (!this.selectedProducts.includes(id)) {
                    this.selectedProducts.push(id);
                }
            });
        },

        deselectCategoryProducts(productIds) {
            const intIds = productIds.map(id => parseInt(id));
            this.selectedProducts = this.selectedProducts.filter(id => !intIds.includes(id));
        },

        toggleCategory(categoryId, productIds) {
            if (this.isCategoryFullySelected(productIds)) {
                this.deselectCategoryProducts(productIds);
            } else {
                this.selectCategoryProducts(productIds);
            }
        },

        isCategoryFullySelected(productIds) {
            if (!productIds || productIds.length === 0) return false;
            return productIds.every(id => this.selectedProducts.includes(parseInt(id)));
        },

        isCategoryPartiallySelected(productIds) {
            if (!productIds || productIds.length === 0) return false;
            const hasSome = productIds.some(id => this.selectedProducts.includes(parseInt(id)));
            return hasSome && !this.isCategoryFullySelected(productIds);
        },

        selectAll() {
            const allBoxes = document.querySelectorAll('input[name="product_ids[]"]');
            const allIds = Array.from(allBoxes).map(b => parseInt(b.value));
            this.selectedProducts = Array.from(new Set(allIds));
        },

        clearAll() {
            this.selectedProducts = [];
        },

        productMatchesSearch(name, sku) {
            if (!this.searchQuery) return true;
            const query = this.searchQuery.toLowerCase();
            return name.toLowerCase().includes(query) || sku.toLowerCase().includes(query);
        },

        categoryMatchesSearch(categoryName, products) {
            if (!this.searchQuery) return true;
            const query = this.searchQuery.toLowerCase();
            if (categoryName.toLowerCase().includes(query)) return true;
            return products.some(p => p.name.toLowerCase().includes(query) || (p.sku && p.sku.toLowerCase().includes(query)));
        },

        validateForm(event) {
            if (this.selectedProducts.length === 0) {
                event.preventDefault();
                alert('Please select at least one product for this filter.');
            }
        }
    };
}
</script>
@endsection
