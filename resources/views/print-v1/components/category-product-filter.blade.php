@props([
    'section' => 'sort-sheet',
    'categoryInputName' => 'category_ids[]',
    'productInputName' => 'product_ids[]',
    'categoryFilterLabel' => 'Category Scope (Optional)',
    'productFilterLabel' => 'Product Scope (Optional)',
    'selectedCategoryIds' => [],
    'selectedProductIds' => [],
    'categories' => [],
    'products' => [],
])

<div x-data="printV1FilterPicker({
    section: '{{ $section }}',
    categoryInputName: '{{ $categoryInputName }}',
    productInputName: '{{ $productInputName }}',
    selectedCategoryIds: @json($selectedCategoryIds),
    selectedProductIds: @json($selectedProductIds),
    categories: @json($categories),
    products: @json($products),
})" class="space-y-2">

    {{-- Marker indicating category and product filters are present in this form submission --}}
    @if(str_starts_with($categoryInputName, 'settings['))
        <input type="hidden" name="settings[filter_scope_present]" value="1">
    @else
        <input type="hidden" name="filter_scope_present" value="1">
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- ─── 1. Category Filter ─── --}}
        <div class="relative" data-picker-root="categories-{{ $section }}">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                {{ $categoryFilterLabel }}
            </label>

            <button type="button"
                    @click="categoryOpen = !categoryOpen; productOpen = false"
                    class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <span x-text="categoryLabel">All Categories</span>
                <span class="text-slate-400">▾</span>
            </button>

            {{-- Category Popover Panel --}}
            <div x-show="categoryOpen"
                 @click.outside="categoryOpen = false"
                 x-cloak
                 class="absolute left-0 right-0 top-full z-40 mt-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                <input type="search"
                       x-model="categorySearch"
                       placeholder="Search categories"
                       class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">

                <div class="mt-3 flex gap-2">
                    <button type="button"
                            @click="selectAllCategories()"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50 transition">
                        Select all
                    </button>
                    <button type="button"
                            @click="clearCategories()"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50 transition">
                        Clear
                    </button>
                </div>

                <div class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                    <template x-for="cat in filteredCategories" :key="cat.id">
                        <button type="button"
                                @click="toggleCategory(cat.id)"
                                :class="isCategorySelected(cat.id) ? 'border-emerald-500 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                class="flex w-full items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-xs font-semibold transition">
                            <span x-text="cat.name"></span>
                            <template x-if="isCategorySelected(cat.id)">
                                <span class="flex min-w-8 h-5 px-1.5 shrink-0 items-center justify-center rounded-md border border-emerald-600 bg-emerald-600 text-white text-[10px] font-black gap-0.5"
                                      x-text="'#' + categoryOrderIndex(cat.id) + ' ✓'"></span>
                            </template>
                            <template x-if="!isCategorySelected(cat.id)">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white"></span>
                            </template>
                        </button>
                    </template>

                    @if(empty($categories))
                        <div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-500">
                            No active categories found
                        </div>
                    @else
                        <div x-show="filteredCategories.length === 0"
                             class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-500">
                            No categories match the search.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Hidden Inputs for Form Submission --}}
            <template x-for="catId in selectedCategories" :key="'cat-' + catId">
                <input type="hidden" :name="categoryInputName" :value="catId">
            </template>

            {{-- Live Print Order Badges Bar --}}
            <div x-show="selectedCategories.length > 0"
                 class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] font-bold text-slate-600">
                <span class="font-black uppercase tracking-wider text-slate-400 text-[9px] mr-1">Print Order:</span>
                <template x-for="(catId, idx) in selectedCategories" :key="'badge-' + catId">
                    <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-900 border border-emerald-200">
                        <span class="text-[9px] font-black text-emerald-600" x-text="'#' + (idx + 1)"></span>
                        <span x-text="getCategoryName(catId)"></span>
                    </span>
                </template>
            </div>
            <p class="text-[11px] text-slate-500 mt-1" x-show="selectedCategories.length === 0">
                Leave empty to include all categories.
            </p>
        </div>

        {{-- ─── 2. Product Filter ─── --}}
        <div class="relative" data-picker-root="products-{{ $section }}">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                {{ $productFilterLabel }}
            </label>

            <button type="button"
                    @click="productOpen = !productOpen; categoryOpen = false"
                    class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <span x-text="productLabel">All Products</span>
                <span class="text-slate-400">▾</span>
            </button>

            {{-- Product Popover Panel --}}
            <div x-show="productOpen"
                 @click.outside="productOpen = false"
                 x-cloak
                 class="absolute left-0 right-0 top-full z-40 mt-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl lg:w-[520px] max-w-[95vw]">
                <input type="search"
                       x-model="productSearch"
                       placeholder="Search item code or name"
                       class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">

                <div class="mt-3 flex gap-2">
                    <button type="button"
                            @click="selectVisibleProducts()"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50 transition">
                        Select visible
                    </button>
                    <button type="button"
                            @click="clearProducts()"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50 transition">
                        Clear products
                    </button>
                </div>

                <div class="mt-3 max-h-80 space-y-3 overflow-y-auto">
                    <template x-for="group in groupedVisibleProducts" :key="group.categoryName">
                        <section>
                            <div class="sticky top-0 z-10 bg-white py-1 text-[10px] font-black uppercase tracking-wider text-slate-500"
                                 x-text="group.categoryName"></div>
                            <div class="space-y-1">
                                <template x-for="prod in group.products" :key="prod.id">
                                    <button type="button"
                                            @click="toggleProduct(prod.id)"
                                            :class="isProductSelected(prod.id) ? 'border-emerald-500 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                            class="flex w-full items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-xs font-semibold transition">
                                        <span x-text="(prod.sku ? prod.sku + ' · ' : '') + prod.name"></span>
                                        <template x-if="isProductSelected(prod.id)">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-emerald-600 bg-emerald-600 text-white text-[10px] font-black">✓</span>
                                        </template>
                                        <template x-if="!isProductSelected(prod.id)">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white"></span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </section>
                    </template>

                    @if(empty($products))
                        <div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-500">
                            No active products found
                        </div>
                    @else
                        <div x-show="groupedVisibleProducts.length === 0"
                             class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-500">
                            No products match the current filters.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Hidden Inputs for Form Submission --}}
            <template x-for="prodId in selectedProducts" :key="'prod-' + prodId">
                <input type="hidden" :name="productInputName" :value="prodId">
            </template>
            <p class="text-[11px] text-slate-500 mt-1" x-show="selectedProducts.length === 0">
                Leave empty to include all products.
            </p>
        </div>

    </div>
</div>

<script>
if (typeof window.printV1FilterPicker === 'undefined') {
    window.printV1FilterPicker = function(config) {
        return {
            section: config.section || '',
            categoryInputName: config.categoryInputName || 'category_ids[]',
            productInputName: config.productInputName || 'product_ids[]',
            categories: config.categories || [],
            products: config.products || [],

            selectedCategories: Array.isArray(config.selectedCategoryIds)
                ? config.selectedCategoryIds.map(Number).filter(id => id > 0)
                : [],
            selectedProducts: Array.isArray(config.selectedProductIds)
                ? config.selectedProductIds.map(Number).filter(id => id > 0)
                : [],

            categoryOpen: false,
            productOpen: false,
            categorySearch: '',
            productSearch: '',

            get categoryLabel() {
                if (this.selectedCategories.length === 0) {
                    return 'All Categories';
                }
                return this.selectedCategories.length === 1
                    ? '1 category selected'
                    : `${this.selectedCategories.length} categories selected`;
            },

            get productLabel() {
                if (this.selectedProducts.length === 0) {
                    return 'All Products';
                }
                return this.selectedProducts.length === 1
                    ? '1 product selected'
                    : `${this.selectedProducts.length} products selected`;
            },

            getCategoryName(id) {
                const cat = this.categories.find(c => Number(c.id) === Number(id));
                return cat ? cat.name : `Category #${id}`;
            },

            isCategorySelected(id) {
                return this.selectedCategories.includes(Number(id));
            },

            categoryOrderIndex(id) {
                const idx = this.selectedCategories.indexOf(Number(id));
                return idx !== -1 ? idx + 1 : 0;
            },

            toggleCategory(id) {
                const numId = Number(id);
                const idx = this.selectedCategories.indexOf(numId);
                if (idx !== -1) {
                    this.selectedCategories.splice(idx, 1);
                } else {
                    this.selectedCategories.push(numId);
                }
            },

            selectAllCategories() {
                const visible = this.filteredCategories;
                visible.forEach(cat => {
                    const numId = Number(cat.id);
                    if (!this.selectedCategories.includes(numId)) {
                        this.selectedCategories.push(numId);
                    }
                });
            },

            clearCategories() {
                this.selectedCategories = [];
            },

            get filteredCategories() {
                const q = (this.categorySearch || '').trim().toLowerCase();
                if (!q) {
                    return this.categories;
                }
                return this.categories.filter(cat => (cat.name || '').toLowerCase().includes(q));
            },

            isProductSelected(id) {
                return this.selectedProducts.includes(Number(id));
            },

            toggleProduct(id) {
                const numId = Number(id);
                const idx = this.selectedProducts.indexOf(numId);
                if (idx !== -1) {
                    this.selectedProducts.splice(idx, 1);
                } else {
                    this.selectedProducts.push(numId);
                }
            },

            selectVisibleProducts() {
                const groups = this.groupedVisibleProducts;
                groups.forEach(group => {
                    group.products.forEach(prod => {
                        const numId = Number(prod.id);
                        if (!this.selectedProducts.includes(numId)) {
                            this.selectedProducts.push(numId);
                        }
                    });
                });
            },

            clearProducts() {
                this.selectedProducts = [];
            },

            get visibleProducts() {
                const selectedCatSet = new Set(this.selectedCategories);
                const q = (this.productSearch || '').trim().toLowerCase();

                return this.products.filter(prod => {
                    // Category scoping:
                    // If categories are selected, show products belonging to those categories OR products that are already selected
                    if (selectedCatSet.size > 0) {
                        const matchesCategory = selectedCatSet.has(Number(prod.category_id));
                        const isAlreadySelected = this.selectedProducts.includes(Number(prod.id));
                        if (!matchesCategory && !isAlreadySelected) {
                            return false;
                        }
                    }

                    // Search query filtering:
                    if (q) {
                        const text = `${prod.sku || ''} ${prod.name || ''} ${prod.category_name || ''}`.toLowerCase();
                        if (!text.includes(q)) {
                            return false;
                        }
                    }

                    return true;
                });
            },

            get groupedVisibleProducts() {
                const grouped = new Map();
                const list = this.visibleProducts;

                list.forEach(prod => {
                    const catName = prod.category_name || 'Uncategorized';
                    if (!grouped.has(catName)) {
                        grouped.set(catName, []);
                    }
                    grouped.get(catName).push(prod);
                });

                const result = [];
                grouped.forEach((products, categoryName) => {
                    result.push({ categoryName, products });
                });

                return result;
            }
        };
    };
}
</script>
