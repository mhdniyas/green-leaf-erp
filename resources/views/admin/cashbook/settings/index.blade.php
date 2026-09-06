@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Settings - Cashbook')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-sm flex items-center gap-3">
            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm" role="alert" tabindex="-1">
            <div class="flex items-center gap-2 text-rose-800 font-bold text-sm mb-1">
                <i data-lucide="alert-circle" class="h-4 w-4 shrink-0 text-rose-600"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside text-xs font-semibold text-rose-700 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Staff Salary & Advances Configuration --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 mb-5">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight text-slate-950">Staff Salary & Advances</h2>
                <p class="text-xs font-medium text-slate-500 mt-0.5">Configure default expense categories, funding channels, and limits for future staff payments.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                Applies to Future Postings
            </span>
        </div>

        <form action="{{ route('admin.cashbook.settings.staff') }}" method="POST" id="staff-settings-form" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Salary Category --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <label for="salary_category_name" class="block text-xs font-black uppercase tracking-wider text-slate-700">Salary Category Name</label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="salary_category_active" value="1" id="salary_category_active"
                                   class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                   {{ old('salary_category_active', $salaryCategory?->is_active ?? true) ? 'checked' : '' }}>
                            <span class="text-xs font-bold text-slate-600">Active</span>
                        </label>
                    </div>
                    <input type="text" name="salary_category_name" id="salary_category_name"
                           value="{{ old('salary_category_name', $salaryCategory?->name ?? 'Salary') }}"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 {{ $errors->has('salary_category_name') ? 'border-rose-400' : '' }}"
                           required>
                    @error('salary_category_name')
                        <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Advance Category --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <label for="advance_category_name" class="block text-xs font-black uppercase tracking-wider text-slate-700">Advance Category Name</label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="advance_category_active" value="1" id="advance_category_active"
                                   class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                   {{ old('advance_category_active', $advanceCategory?->is_active ?? true) ? 'checked' : '' }}>
                            <span class="text-xs font-bold text-slate-600">Active</span>
                        </label>
                    </div>
                    <input type="text" name="advance_category_name" id="advance_category_name"
                           value="{{ old('advance_category_name', $advanceCategory?->name ?? 'Salary Advance') }}"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 {{ $errors->has('advance_category_name') ? 'border-rose-400' : '' }}"
                           required>
                    @error('advance_category_name')
                        <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                {{-- Default Manager Source --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                    <label for="default_fund_source" class="block text-xs font-black uppercase tracking-wider text-slate-700">Default Shop Source</label>
                    <select name="default_fund_source" id="default_fund_source"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="petty_cash" {{ old('default_fund_source', ($advanceRule?->default_from_petty_cash ?? true) ? 'petty_cash' : 'sales_income') === 'petty_cash' ? 'selected' : '' }}>Petty Cash</option>
                        <option value="sales_income" {{ old('default_fund_source', ($advanceRule?->default_from_petty_cash ?? true) ? 'petty_cash' : 'sales_income') === 'sales_income' ? 'selected' : '' }}>Sales Cash</option>
                    </select>
                    <p class="text-[11px] font-medium text-slate-500">Default channel preselected on shop payment forms.</p>
                </div>

                {{-- Advance Ceiling Percentage (System Frozen) --}}
                <div class="rounded-xl border border-slate-200 bg-slate-100/70 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="block text-xs font-black uppercase tracking-wider text-slate-700">Advance Percentage</span>
                        <span class="rounded-md bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">Frozen Rule</span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-bold text-slate-700">
                        50.00%
                    </div>
                    <p class="text-[11px] font-medium text-slate-500">Authoritative advance ceiling ratio locked to 50% of earned salary.</p>
                </div>

                {{-- Minimum Attendance Units (System Frozen) --}}
                <div class="rounded-xl border border-slate-200 bg-slate-100/70 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="block text-xs font-black uppercase tracking-wider text-slate-700">Min Payable Units</span>
                        <span class="rounded-md bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">Frozen Rule</span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-bold text-slate-700">
                        0 Units (None)
                    </div>
                    <p class="text-[11px] font-medium text-slate-500">Authoritative calculation imposes no artificial attendance minimum.</p>
                </div>
            </div>

            {{-- Optional Shop Scope Override --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                <label for="shop_id" class="block text-xs font-black uppercase tracking-wider text-slate-700">Configuration Scope</label>
                <select name="shop_id" id="shop_id"
                        class="w-full md:w-1/2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">Global Setting (Applies to all shops without custom override)</option>
                    @foreach($shops as $shopOption)
                        <option value="{{ $shopOption->id }}" {{ old('shop_id') == $shopOption->id ? 'selected' : '' }}>
                            Override for Shop: {{ $shopOption->name }} ({{ $shopOption->code ?: 'SHOP-'.$shopOption->shop_id }})
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] font-medium text-slate-500">Leave as Global to configure standard categories across all eligible stores.</p>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <p class="text-xs font-semibold text-slate-500">Historical cashbook lines retain their original category and source.</p>
                <button type="submit" id="staff-settings-submit-btn"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    <span>Save Staff Settings</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('staff-settings-form')?.addEventListener('submit', function() {
            var btn = document.getElementById('staff-settings-submit-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="inline-block animate-spin mr-1">↻</span> Saving...';
            }
        });
    </script>

    {{-- Existing Shop Settings List --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Shop Settings</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Select one shop and edit only that shop's cashbook rows.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $shops->count() }} shops</span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($shops as $shop)
            <div class="space-y-2"><a href="{{ route('admin.cashbook.settings.shop', $shop->slug ?: $shop->shop_id) }}"
               class="block group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-black text-slate-950 group-hover:text-emerald-700">{{ $shop->name }}</h2>
                        <p class="mt-1 font-mono text-xs font-bold text-slate-400">{{ $shop->code ?: 'SHOP-'.$shop->shop_id }}</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                        <i data-lucide="store" class="h-5 w-5"></i>
                    </span>
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                    <span class="text-xs font-bold text-slate-500">Income, Expense, Transfer, Collection</span>
                    <span class="inline-flex items-center gap-1 text-xs font-black text-emerald-700">
                        Open Settings
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </span>
                </div>
            </a>
            <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $shop->slug ?: $shop->shop_id) }}" class="block rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-center text-sm font-bold text-indigo-800 hover:bg-indigo-100">Settlements</a>
            </div>
        @endforeach
    </div>

    <!-- INSTRUCTIONS / HOW-TO GUIDE: CONFIGURING CATEGORIES & SALES DEDUCTIONS -->
    <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7 shadow-xs space-y-5 mt-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 shrink-0">
                    <i data-lucide="help-circle" class="h-5 w-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-black tracking-tight text-slate-950">How to Configure Categories &amp; Sales Deductions</h3>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Step-by-step instructions to set up sales deductions (e.g. Casio Delivery, Shop to Supermarket) or custom categories.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 w-fit">
                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                Standardized Rule Engine
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Step 1 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">1</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">1. Open Shop Settings</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Select a shop from above, then navigate to the <strong class="text-slate-900">Transfers &amp; Movements</strong> or <strong class="text-slate-900">Sales</strong> tab.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">2</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">2. Assign to SALES</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Toggle <strong class="text-emerald-700">Enabled: ON</strong> and set <strong class="text-slate-900">Header Group: SALES</strong> so it appears on the shop's sales entry card.
                </p>
            </div>

            <!-- Step 3 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">3</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">3. Set Minus (−)</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Check <strong class="text-slate-900">Include in Sales: ON</strong> with <strong class="text-rose-700">Direction: Minus (−)</strong>. Keep <strong class="text-slate-900">Include in Expense: OFF</strong>.
                </p>
            </div>

            <!-- Step 4 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">4</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">4. Save Changes</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Set <strong class="text-slate-900">Default Funding: Sales Cash</strong> and <strong class="text-slate-900">Settlement: Decrease</strong>, then save.
                </p>
            </div>
        </div>

        <!-- Formula & Visual Preview Banner -->
        <div class="rounded-2xl border border-slate-200 bg-slate-900 text-white p-4 sm:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-rose-500/20 text-rose-300 font-mono font-black text-xs">−</span>
                    <span class="text-xs font-black uppercase tracking-wider text-slate-300">Sales Deduction Rule</span>
                </div>
                <div class="font-mono text-xs sm:text-sm font-bold text-slate-100">
                    Total Sales = Cash + Card + Paytm + Delivery &minus; <span class="text-rose-300 font-black">(Casio Delivery + Deductions)</span>
                </div>
            </div>
            <div class="text-xs text-slate-400 max-w-md font-medium">
                Deductions display with an explicit <span class="text-rose-400 font-bold">− (rose)</span> badge in the owner cashbook and subtract cleanly without inflating total expenses.
            </div>
        </div>
    </div>
</div>
@endsection
