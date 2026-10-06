@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Settings - Cashbook')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs flex items-center gap-3 animate-in fade-in duration-150">
            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-xs" role="alert" tabindex="-1">
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

    <!-- Top Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-xs">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-xl font-black text-slate-950 tracking-tight">Shop Settings</h1>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                    {{ $shops->count() }} active client {{ \Illuminate\Support\Str::plural('shop', $shops->count()) }}
                </span>
            </div>
            <p class="mt-1 text-xs font-semibold text-slate-500">Select an active client shop to view and configure its categories, rules, and settings.</p>
        </div>

        <div class="flex items-center flex-wrap gap-2.5 shrink-0">
            <!-- Button: Staff Salary & Advances -->
            <button type="button"
                    onclick="openStaffModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-emerald-50/50 hover:border-emerald-300 text-xs font-black text-slate-800 hover:text-emerald-800 transition shadow-2xs">
                <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                <span>Staff Salary &amp; Advances</span>
            </button>

            <!-- Button: Vendor Purchase Edit Window -->
            <button type="button"
                    onclick="openVendorWindowModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-indigo-50/50 hover:border-indigo-300 text-xs font-black text-slate-800 hover:text-indigo-800 transition shadow-2xs">
                <i data-lucide="clock" class="w-4 h-4 text-indigo-600"></i>
                <span>Vendor Purchase Edit Window</span>
            </button>
        </div>
    </div>

    <!-- Active Client Shop Cards Grouped Under Client Name -->
    <div class="space-y-8">
        @forelse(($clientGroups ?? collect(['Client Shops' => $shops])) as $clientName => $clientShops)
            <div class="space-y-3">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-200/80">
                    <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">
                        {{ $clientName }}
                    </h2>
                    <span class="rounded-full bg-emerald-50 border border-emerald-200/60 px-2.5 py-0.5 text-xs font-black text-emerald-800">
                        {{ $clientShops->count() }} {{ \Illuminate\Support\Str::plural('shop', $clientShops->count()) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($clientShops as $shop)
                        <a href="{{ route('admin.cashbook.settings.shop', $shop->slug ?: $shop->shop_id) }}"
                           class="group flex items-center justify-between p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs hover:shadow-md hover:border-emerald-400 hover:bg-emerald-50/20 transition-all duration-150">
                            <span class="text-sm font-black text-slate-900 group-hover:text-emerald-950 truncate transition">
                                {{ $shop->name }}
                            </span>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition shrink-0 ml-2"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center space-y-3">
                <i data-lucide="store" class="w-12 h-12 text-slate-300 mx-auto"></i>
                <h3 class="text-base font-black text-slate-800">No Active Client Shops Found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">There are currently no active client shops eligible for cashbook configuration.</p>
            </div>
        @endforelse
    </div>

    <!-- INSTRUCTIONS / HOW-TO GUIDE -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-7 shadow-xs space-y-5 mt-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 shrink-0">
                    <i data-lucide="help-circle" class="h-5 w-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-black tracking-tight text-slate-950">How to Configure Categories &amp; Sales Deductions</h3>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Step-by-step instructions to set up sales deductions or custom categories.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 w-fit">
                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                Standardized Rule Engine
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">1</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">1. Open Shop Settings</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Select a shop from above to navigate to its configuration, categories, and rules.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">2</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">2. Assign Header</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Attach categories under appropriate headers (e.g. Daily Cash Expenditure, Petty Cash, or Sales).
                </p>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">3</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">3. Set Minus (−)</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    For deductions like delivery charges, check <strong class="text-slate-900">Include in Sales: ON</strong> with <strong class="text-rose-700">Direction: Minus (−)</strong>.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">4</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">4. Save Changes</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Set default funding and settlement options, then save configuration.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ── POPUP MODAL: STAFF SALARY & ADVANCES ──────────────────────── -->
<div id="staff-salary-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 sm:p-7 space-y-5 animate-in fade-in zoom-in-95 duration-150 my-8 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700 shrink-0">
                    <i data-lucide="users" class="h-5 w-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-950">Staff Salary &amp; Advances</h3>
                    <p class="text-xs font-medium text-slate-500">Configure expense categories, funding channels, and limits for staff payments.</p>
                </div>
            </div>
            <button type="button" onclick="closeStaffModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form action="{{ route('admin.cashbook.settings.staff') }}" method="POST" id="staff-settings-form" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Salary Category --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
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
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500 {{ $errors->has('salary_category_name') ? 'border-rose-400' : '' }}"
                           required>
                    @error('salary_category_name')
                        <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Advance Category --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
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
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500 {{ $errors->has('advance_category_name') ? 'border-rose-400' : '' }}"
                           required>
                    @error('advance_category_name')
                        <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Default Shop Source --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                    <label for="default_fund_source" class="block text-xs font-black uppercase tracking-wider text-slate-700">Default Source</label>
                    <select name="default_fund_source" id="default_fund_source"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-hidden">
                        <option value="petty_cash" {{ old('default_fund_source', ($advanceRule?->default_from_petty_cash ?? true) ? 'petty_cash' : 'sales_income') === 'petty_cash' ? 'selected' : '' }}>Petty Cash</option>
                        <option value="sales_income" {{ old('default_fund_source', ($advanceRule?->default_from_petty_cash ?? true) ? 'petty_cash' : 'sales_income') === 'sales_income' ? 'selected' : '' }}>Sales Cash</option>
                    </select>
                    <p class="text-[10px] font-medium text-slate-400">Preselected on shop payment forms.</p>
                </div>

                {{-- Advance Ceiling Percentage --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-100/70 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="block text-xs font-black uppercase tracking-wider text-slate-700">Advance %</span>
                        <span class="rounded-md bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">Locked</span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black text-slate-700">
                        50.00%
                    </div>
                    <p class="text-[10px] font-medium text-slate-400">Locked to 50% of earned salary.</p>
                </div>

                {{-- Minimum Attendance Units --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-100/70 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="block text-xs font-black uppercase tracking-wider text-slate-700">Min Units</span>
                        <span class="rounded-md bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">Locked</span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black text-slate-700">
                        0 Units (None)
                    </div>
                    <p class="text-[10px] font-medium text-slate-400">No artificial attendance minimum.</p>
                </div>
            </div>

            {{-- Optional Shop Scope Override --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                <label for="shop_id" class="block text-xs font-black uppercase tracking-wider text-slate-700">Configuration Scope</label>
                <select name="shop_id" id="shop_id"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-hidden">
                    <option value="">Global Setting (Applies across all shops)</option>
                    @foreach($shops as $shopOption)
                        <option value="{{ $shopOption->id }}" {{ old('shop_id') == $shopOption->id ? 'selected' : '' }}>
                            Override for Shop: {{ $shopOption->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeStaffModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" id="staff-settings-submit-btn"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white shadow-xs hover:bg-emerald-700 transition">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    <span>Save Staff Settings</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── POPUP MODAL: VENDOR PURCHASE EDIT WINDOW ─────────────────── -->
<div id="vendor-purchase-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 sm:p-7 space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 shrink-0">
                    <i data-lucide="clock" class="h-5 w-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-950">Vendor Purchase Edit Window</h3>
                    <p class="text-xs font-medium text-slate-500">How long Shop Owners can create or edit vendor purchases.</p>
                </div>
            </div>
            <button type="button" onclick="closeVendorWindowModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form action="{{ route('admin.cashbook.settings.vendor-purchase-edit-window') }}" method="POST" id="vendor-purchase-window-form" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                    <label for="vendor_purchase_edit_window_value" class="block text-xs font-black uppercase tracking-wider text-slate-700">Window Value</label>
                    <input type="number" min="1" max="720" name="vendor_purchase_edit_window_value" id="vendor_purchase_edit_window_value"
                           value="{{ old('vendor_purchase_edit_window_value', $vendorPurchaseEditWindow['value'] ?? 3) }}"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-hidden {{ $errors->has('vendor_purchase_edit_window_value') ? 'border-rose-400' : '' }}"
                           required>
                    @error('vendor_purchase_edit_window_value')
                        <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                    <label for="vendor_purchase_edit_window_unit" class="block text-xs font-black uppercase tracking-wider text-slate-700">Window Unit</label>
                    <select name="vendor_purchase_edit_window_unit" id="vendor_purchase_edit_window_unit"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-hidden">
                        <option value="hours" {{ old('vendor_purchase_edit_window_unit', $vendorPurchaseEditWindow['unit'] ?? 'days') === 'hours' ? 'selected' : '' }}>Hours</option>
                        <option value="days" {{ old('vendor_purchase_edit_window_unit', $vendorPurchaseEditWindow['unit'] ?? 'days') === 'days' ? 'selected' : '' }}>Days</option>
                    </select>
                </div>
            </div>

            <p class="text-xs font-medium text-slate-500">Purchases older than this window become Read Only for Shop Owners. Admin remains unrestricted.</p>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeVendorWindowModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" id="vendor-purchase-window-submit-btn"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-black text-white shadow-xs hover:bg-slate-800 transition">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    <span>Save Window Setting</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStaffModal() {
        const modal = document.getElementById('staff-salary-modal');
        if (modal) modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeStaffModal() {
        const modal = document.getElementById('staff-salary-modal');
        if (modal) modal.classList.add('hidden');
    }

    function openVendorWindowModal() {
        const modal = document.getElementById('vendor-purchase-modal');
        if (modal) modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeVendorWindowModal() {
        const modal = document.getElementById('vendor-purchase-modal');
        if (modal) modal.classList.add('hidden');
    }

    // Auto-open modal if validation errors exist for that form
    @if($errors->has('salary_category_name') || $errors->has('advance_category_name') || $errors->has('default_fund_source'))
        openStaffModal();
    @endif

    @if($errors->has('vendor_purchase_edit_window_value') || $errors->has('vendor_purchase_edit_window_unit'))
        openVendorWindowModal();
    @endif

    document.getElementById('staff-settings-form')?.addEventListener('submit', function() {
        var btn = document.getElementById('staff-settings-submit-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="inline-block animate-spin mr-1">↻</span> Saving...';
        }
    });

    document.getElementById('vendor-purchase-window-form')?.addEventListener('submit', function() {
        var btn = document.getElementById('vendor-purchase-window-submit-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="inline-block animate-spin mr-1">↻</span> Saving...';
        }
    });
</script>
@endsection
