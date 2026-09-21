@extends('admin.cashbook.layouts.app')

@section('title', 'Add Category — Cashbook Categories')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header / Breadcrumb -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <a href="{{ route('admin.cashbook.categories.index') }}" class="hover:text-slate-600 transition">Categories</a>
                <span>/</span>
                <span class="text-indigo-600">Add Category</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">Create New Category</h1>
        </div>
        <a href="{{ route('admin.cashbook.categories.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-extrabold text-slate-700 hover:bg-slate-50 transition">
            Cancel
        </a>
    </div>

    <!-- Error Messages -->
    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
            <div class="flex items-center gap-2 text-rose-800 font-bold text-sm mb-1">
                <i data-lucide="alert-circle" class="h-4 w-4 shrink-0 text-rose-600"></i>
                <span>Please fix the errors below:</span>
            </div>
            <ul class="list-disc list-inside text-xs font-semibold text-rose-700 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.cashbook.categories.store') }}" class="space-y-6">
        @csrf

        <!-- Basic Details Section -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                <i data-lucide="tag" class="w-4 h-4 text-indigo-600"></i>
                <span>Basic Details</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Utility Bills, Packaging..." required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category Type <span class="text-rose-500">*</span></label>
                    <select name="category" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                        <option value="expense" {{ old('category') === 'expense' ? 'selected' : '' }}>Expense</option>
                        <option value="income" {{ old('category') === 'income' ? 'selected' : '' }}>Income</option>
                        <option value="transfer" {{ old('category') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1 font-semibold">Note: Settlement groups are configured under the Settlements section.</p>
                </div>
            </div>

            <div class="pt-2">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                    <span class="text-xs font-bold text-slate-800">Global Category Active</span>
                </label>
            </div>
        </div>

        <!-- Shop Assignment Section -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="store" class="w-4 h-4 text-indigo-600"></i>
                    <span>Assign Shops</span>
                </h2>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="selectAllShops(true)" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 hover:bg-slate-100">Select All</button>
                    <button type="button" onclick="selectAllShops(false)" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 hover:bg-slate-100">Clear All</button>
                </div>
            </div>

            <!-- Live Shop Filter -->
            <div>
                <input type="text" id="shopSearchInput" placeholder="Filter shop name..." onkeyup="filterShopList()" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
            </div>

            <!-- Shop Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-60 overflow-y-auto p-1" id="shopGrid">
                @foreach($shops as $shop)
                    <label class="shop-item flex items-center gap-2.5 p-3 rounded-2xl border border-slate-200/80 bg-slate-50/50 hover:bg-indigo-50/50 hover:border-indigo-200 cursor-pointer transition">
                        <input type="checkbox" name="shop_ids[]" value="{{ $shop->id }}" {{ in_array($shop->id, old('shop_ids', [])) ? 'checked' : '' }} class="shop-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                        <span class="shop-name text-xs font-extrabold text-slate-800">{{ $shop->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.cashbook.categories.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-xs font-extrabold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-black shadow-md hover:bg-indigo-700 transition">Create Category</button>
        </div>
    </form>
</div>

<script>
function selectAllShops(select) {
    document.querySelectorAll('.shop-checkbox').forEach(cb => cb.checked = select);
}
function filterShopList() {
    const q = document.getElementById('shopSearchInput').value.toLowerCase();
    document.querySelectorAll('#shopGrid .shop-item').forEach(item => {
        const name = item.querySelector('.shop-name').textContent.toLowerCase();
        item.style.display = name.includes(q) ? 'flex' : 'none';
    });
}
</script>
@endsection
