@extends('admin.cashbook.layouts.app')

@section('title', $entryType->name . ' — Category Details')

@section('content')
<div class="space-y-6">
    <!-- Success / Error Notifications -->
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-sm flex items-center gap-3">
            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
            <div class="flex items-center gap-2 text-rose-800 font-bold text-sm mb-1">
                <i data-lucide="alert-circle" class="h-4 w-4 shrink-0 text-rose-600"></i>
                <span>Please fix the validation errors:</span>
            </div>
            <ul class="list-disc list-inside text-xs font-semibold text-rose-700 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Category Header Card -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <a href="{{ route('admin.cashbook.categories.index') }}" class="hover:text-slate-600 transition">Categories</a>
                    <span>/</span>
                    <span class="text-indigo-600">{{ $entryType->name }}</span>
                </div>
                <div class="flex items-center gap-3 mt-1">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $entryType->name }}</h1>
                    <span class="px-2.5 py-1 rounded-lg border text-[10px] font-extrabold uppercase bg-indigo-50 text-indigo-700 border-indigo-200">
                        {{ $entryType->category }}
                    </span>
                    @if($entryType->active)
                        <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-black flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Global Active
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 border border-slate-200 text-[10px] font-black flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Global Inactive
                        </span>
                    @endif
                </div>
                <p class="text-xs font-mono text-slate-400 mt-0.5">Code: {{ $entryType->code }}</p>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center gap-2">
                <button type="button" onclick="document.getElementById('assignShopsModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-black hover:bg-indigo-100 transition flex items-center gap-2">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span>Manage Shop Assignments ({{ count($assignedShopIds) }} / {{ count($allShops) }})</span>
                </button>
            </div>
        </div>

        <!-- Edit Global Details Form -->
        <form method="POST" action="{{ route('admin.cashbook.categories.update-global', $entryType->code) }}" class="flex flex-wrap items-center gap-4 bg-slate-50/70 p-3.5 rounded-2xl border border-slate-200/60">
            @csrf
            <div class="flex items-center gap-2 flex-1 min-w-[200px]">
                <label class="text-xs font-extrabold text-slate-700 shrink-0">Category Name:</label>
                <input type="text" name="name" value="{{ old('name', $entryType->name) }}" required class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:outline-hidden focus:border-indigo-500 w-full">
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', $entryType->active) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                    <span class="text-xs font-extrabold text-slate-800">Global Active Status</span>
                </label>
            </div>
            <button type="submit" class="px-4 py-1.5 rounded-xl bg-slate-800 text-white text-xs font-extrabold hover:bg-slate-900 transition">Save Global Info</button>
        </form>
    </div>

    <!-- Shop Configuration Cards Header -->
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-2">
            <i data-lucide="sliders" class="w-5 h-5 text-indigo-600"></i>
            <span>Shop Configuration Rules</span>
        </h2>
        <span class="text-xs font-bold text-slate-500">Each shop behaves independently</span>
    </div>

    <!-- Shop Cards Loop -->
    <div class="space-y-6">
        @foreach($shopConfigs as $cfg)
            @php
                $s = $cfg['shop'];
                $setting = $cfg['setting'];
                $isEnabled = (bool)($setting?->enabled ?? false);
            @endphp
            <div class="bg-white rounded-3xl border {{ $isEnabled ? 'border-slate-200/90' : 'border-slate-200/50 opacity-75' }} shadow-xs overflow-hidden" id="shop-card-{{ $s->id }}">
                <!-- Shop Header Bar -->
                <div class="bg-slate-50/90 px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full {{ $isEnabled ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                        <h3 class="text-base font-black text-slate-900">{{ $s->name }}</h3>
                        <span class="text-xs font-semibold text-slate-400">({{ $s->code ?: 'Shop #'.$s->id }})</span>
                        @if($isEnabled)
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase">Enabled</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[10px] font-black uppercase">Disabled</span>
                        @endif
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Read-Only Explanation Summary -->
                    @if($setting && !empty($cfg['explanation']))
                        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs font-semibold text-indigo-950 flex items-start gap-3">
                            <i data-lucide="info" class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5"></i>
                            <div>
                                <span class="font-extrabold text-indigo-900 block mb-0.5 uppercase tracking-wider text-[10px]">Actual Behavioral Summary:</span>
                                <p class="leading-relaxed">{{ $cfg['explanation'] }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Conflict Warning Alert -->
                    @if($cfg['has_conflict'])
                        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs font-bold text-amber-900 flex items-start gap-3">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                            <div>
                                <span class="font-black block uppercase text-[11px] tracking-wider text-amber-800 mb-1">Conflicting Settlement Settings Warning</span>
                                <p>The `settlement_behavior` setting on this category conflicts with the explicit calculation role defined in the Settlement engine (`shop_cashbook_relation_items`). Please review and save the Settlement section below to align them.</p>
                            </div>
                        </div>
                    @endif

                    <!-- SECTION 1: Basic & Header -->
                    <form method="POST" action="{{ route('admin.cashbook.categories.shop.basic-header', [$entryType->code, $s->id]) }}" class="bg-slate-50/50 p-4 rounded-2xl border border-slate-200/60 space-y-3">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                <i data-lucide="layout" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span>1. Basic & Header</span>
                            </h4>
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-extrabold transition">Save Header</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Status in Shop</label>
                                <select name="enabled" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="1" {{ $isEnabled ? 'selected' : '' }}>Enabled</option>
                                    <option value="0" {{ !$isEnabled ? 'selected' : '' }}>Disabled</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Custom Display Name</label>
                                <input type="text" name="display_name" value="{{ old('display_name', $setting?->display_name) }}" placeholder="{{ $entryType->name }}" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Cashbook Header Group</label>
                                <select name="header_group_id" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="">-- No Header --</option>
                                    @foreach($cfg['headers'] as $hdr)
                                        <option value="{{ $hdr->id }}" {{ ($setting?->header_group_id == $hdr->id) ? 'selected' : '' }}>{{ $hdr->name }} ({{ $hdr->type }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </form>

                    <!-- SECTION 2: Settlement -->
                    <form method="POST" action="{{ route('admin.cashbook.categories.shop.settlement', [$entryType->code, $s->id]) }}" class="bg-slate-50/50 p-4 rounded-2xl border border-slate-200/60 space-y-3">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                <i data-lucide="calculator" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span>2. Settlement Engine Relation</span>
                            </h4>
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-extrabold transition">Save Settlement</button>
                        </div>

                        <!-- Calculation Flow Diagram -->
                        <div class="bg-white p-3 rounded-xl border border-slate-200/70 text-xs font-bold text-slate-700 flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-800 border border-indigo-200 font-extrabold">{{ $setting?->displayName() ?: $entryType->name }}</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 border border-slate-200 font-bold">
                                {{ $cfg['settlement_item']?->relation?->name ?: $setting?->vendorSettlementRelation?->name ?: 'No Settlement' }}
                            </span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span class="px-2.5 py-1 rounded-lg {{ strtolower((string)($cfg['settlement_item']?->role ?? $setting?->settlement_behavior)) === 'subtract' || strtolower((string)$setting?->settlement_behavior) === 'decrease' ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200' }} border font-black uppercase">
                                {{ strtolower((string)($cfg['settlement_item']?->role ?? $setting?->settlement_behavior)) === 'subtract' || strtolower((string)$setting?->settlement_behavior) === 'decrease' ? 'SUBTRACT' : 'ADD' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Target Settlement Relation</label>
                                <select name="relation_id" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="">-- None --</option>
                                    @foreach($cfg['settlements'] as $rel)
                                        <option value="{{ $rel->id }}" {{ ($cfg['settlement_item']?->relation_id == $rel->id || $setting?->vendor_settlement_relation_id == $rel->id) ? 'selected' : '' }}>{{ $rel->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Engine Formula Role</label>
                                <select name="role" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="add" {{ ($cfg['settlement_item']?->role === 'add') ? 'selected' : '' }}>ADD (Increases Net Balance)</option>
                                    <option value="subtract" {{ ($cfg['settlement_item']?->role === 'subtract') ? 'selected' : '' }}>SUBTRACT (Reduces Net Balance)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Legacy Settlement Behavior</label>
                                <select name="settlement_behavior" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="none" {{ ($setting?->settlement_behavior === 'none') ? 'selected' : '' }}>None</option>
                                    <option value="increase" {{ ($setting?->settlement_behavior === 'increase') ? 'selected' : '' }}>Increase</option>
                                    <option value="decrease" {{ ($setting?->settlement_behavior === 'decrease') ? 'selected' : '' }}>Decrease</option>
                                </select>
                            </div>
                        </div>
                    </form>

                    <!-- SECTION 3: Company Relation -->
                    <form method="POST" action="{{ route('admin.cashbook.categories.shop.company-relation', [$entryType->code, $s->id]) }}" class="bg-slate-50/50 p-4 rounded-2xl border border-slate-200/60 space-y-3">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                <i data-lucide="building-2" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span>3. Company Relation & Banking</span>
                            </h4>
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-extrabold transition">Save Company Relation</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Company Bank Account</label>
                                <select name="company_account_id" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="">-- No Bank Account Direct Mapping --</option>
                                    @foreach($companyAccounts as $acc)
                                        <option value="{{ $acc->id }}" {{ ($setting?->company_account_id == $acc->id) ? 'selected' : '' }}>{{ $acc->name ?: $acc->bank_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Default Funding Source</label>
                                <select name="default_funding_source" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="sales" {{ ($setting?->default_funding_source === 'sales' || $setting?->default_funding_source === 'shop_balance') ? 'selected' : '' }}>Shop Cash / Sales</option>
                                    <option value="petty" {{ ($setting?->default_funding_source === 'petty') ? 'selected' : '' }}>Petty Cash</option>
                                    <option value="company" {{ ($setting?->default_funding_source === 'company') ? 'selected' : '' }}>Company</option>
                                    <option value="none" {{ ($setting?->default_funding_source === 'none') ? 'selected' : '' }}>None</option>
                                </select>
                            </div>
                        </div>
                    </form>

                    <!-- SECTION 4: Vendor Relation -->
                    <form method="POST" action="{{ route('admin.cashbook.categories.shop.vendor-relation', [$entryType->code, $s->id]) }}" class="bg-slate-50/50 p-4 rounded-2xl border border-slate-200/60 space-y-3">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                <i data-lucide="truck" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span>4. Vendor Relation</span>
                            </h4>
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-extrabold transition">Save Vendor</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Is Vendor Purchase Category?</label>
                                <select name="is_vendor_purchase" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="0" {{ !($setting?->is_vendor_purchase) ? 'selected' : '' }}>No</option>
                                    <option value="1" {{ ($setting?->is_vendor_purchase) ? 'selected' : '' }}>Yes</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Purchase Payment Mode</label>
                                <select name="vendor_purchase_payment_type" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="cash" {{ ($setting?->vendor_purchase_payment_type === 'cash') ? 'selected' : '' }}>Cash Purchase</option>
                                    <option value="credit" {{ ($setting?->vendor_purchase_payment_type === 'credit') ? 'selected' : '' }}>Credit Purchase</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Vendor Access Mode</label>
                                <select name="vendor_access_mode" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                    <option value="all" {{ ($setting?->vendor_access_mode === 'all') ? 'selected' : '' }}>All Vendors Allowed</option>
                                    <option value="pinned" {{ ($setting?->vendor_access_mode === 'pinned') ? 'selected' : '' }}>Pinned Vendors Only</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Pinned Shop Suppliers (for {{ $s->name }})</label>
                            @php
                                $pinnedIds = $setting?->definedShopSuppliers->pluck('id')->all() ?? [];
                            @endphp
                            <select name="pinned_supplier_ids[]" multiple class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 max-h-24">
                                @foreach($cfg['suppliers'] as $sup)
                                    <option value="{{ $sup->id }}" {{ in_array($sup->id, $pinnedIds) ? 'selected' : '' }}>{{ $sup->supplier?->name ?? ('Supplier #'.$sup->id) }}</option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1 font-semibold">Hold Ctrl/Cmd to select multiple vendors strictly for this shop.</p>
                        </div>
                    </form>

                    <!-- SECTION 5: Reports & Accounting (Collapsible) -->
                    <details class="bg-slate-50/50 rounded-2xl border border-slate-200/60 overflow-hidden">
                        <summary class="p-4 text-xs font-black uppercase tracking-wider text-slate-800 flex items-center justify-between cursor-pointer hover:bg-slate-100/60">
                            <span class="flex items-center gap-1.5">
                                <i data-lucide="bar-chart-3" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span>5. Reports & Accounting Flags</span>
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                        </summary>
                        <form method="POST" action="{{ route('admin.cashbook.categories.shop.reports', [$entryType->code, $s->id]) }}" class="p-4 border-t border-slate-200/60 space-y-4">
                            @csrf
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer bg-white p-2.5 rounded-xl border border-slate-200">
                                    <input type="checkbox" name="include_in_sales" value="1" {{ ($setting?->include_in_sales) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 h-4 w-4">
                                    <span class="text-xs font-bold text-slate-800">Gross Sales</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer bg-white p-2.5 rounded-xl border border-slate-200">
                                    <input type="checkbox" name="include_in_income" value="1" {{ ($setting?->include_in_income) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 h-4 w-4">
                                    <span class="text-xs font-bold text-slate-800">Total Income</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer bg-white p-2.5 rounded-xl border border-slate-200">
                                    <input type="checkbox" name="include_in_expense" value="1" {{ ($setting?->include_in_expense) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 h-4 w-4">
                                    <span class="text-xs font-bold text-slate-800">Total Expense</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer bg-white p-2.5 rounded-xl border border-slate-200">
                                    <input type="checkbox" name="include_in_pl" value="1" {{ ($setting?->include_in_pl) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 h-4 w-4">
                                    <span class="text-xs font-bold text-slate-800">P&L Statement</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer bg-white p-2.5 rounded-xl border border-slate-200">
                                    <input type="checkbox" name="include_in_payable" value="1" {{ ($setting?->include_in_payable) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 h-4 w-4">
                                    <span class="text-xs font-bold text-slate-800">Payables</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                <div>
                                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Payable Direction</label>
                                    <select name="payable_direction" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                        <option value="">-- None --</option>
                                        <option value="vendor_payable" {{ ($setting?->payable_direction === 'vendor_payable') ? 'selected' : '' }}>Vendor Payable</option>
                                        <option value="company_payable" {{ ($setting?->payable_direction === 'company_payable') ? 'selected' : '' }}>Company Payable</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Sales Report Bucket Override</label>
                                    <select name="sales_report_bucket" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                        <option value="default" {{ ($setting?->sales_report_bucket === 'default') ? 'selected' : '' }}>Default (Auto)</option>
                                        <option value="sales" {{ ($setting?->sales_report_bucket === 'sales') ? 'selected' : '' }}>Sales</option>
                                        <option value="rent" {{ ($setting?->sales_report_bucket === 'rent') ? 'selected' : '' }}>Rent</option>
                                        <option value="purchase" {{ ($setting?->sales_report_bucket === 'purchase') ? 'selected' : '' }}>Purchase</option>
                                        <option value="other_expense" {{ ($setting?->sales_report_bucket === 'other_expense') ? 'selected' : '' }}>Other Expense</option>
                                        <option value="ignore" {{ ($setting?->sales_report_bucket === 'ignore') ? 'selected' : '' }}>Ignore in Sales Report</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-slate-800 text-white text-xs font-extrabold hover:bg-slate-900 transition">Save Reports</button>
                            </div>
                        </form>
                    </details>

                    <!-- SECTION 6: Advanced Settings (Collapsible) -->
                    <details class="bg-slate-50/50 rounded-2xl border border-slate-200/60 overflow-hidden">
                        <summary class="p-4 text-xs font-black uppercase tracking-wider text-slate-800 flex items-center justify-between cursor-pointer hover:bg-slate-100/60">
                            <span class="flex items-center gap-1.5">
                                <i data-lucide="settings-2" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span>6. Advanced Settings</span>
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                        </summary>
                        <form method="POST" action="{{ route('admin.cashbook.categories.shop.advanced', [$entryType->code, $s->id]) }}" class="p-4 border-t border-slate-200/60 space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Note Required</label>
                                    <select name="note_enabled" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                        <option value="0" {{ !($setting?->note_enabled) ? 'selected' : '' }}>Optional Note</option>
                                        <option value="1" {{ ($setting?->note_enabled) ? 'selected' : '' }}>Mandatory Note</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Edit Policy</label>
                                    <select name="edit_policy" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                        <option value="past_days_allowed" {{ ($setting?->edit_policy !== 'today_only') ? 'selected' : '' }}>Past Days Allowed</option>
                                        <option value="today_only" {{ ($setting?->edit_policy === 'today_only') ? 'selected' : '' }}>Today Only</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Mirror to Cashbook</label>
                                    <select name="mirror_to_cashbook" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                        <option value="1" {{ ($setting?->mirror_to_cashbook ?? true) ? 'selected' : '' }}>Yes (Mirror Entries)</option>
                                        <option value="0" {{ !($setting?->mirror_to_cashbook ?? true) ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                <div>
                                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Petty Cash Direct Behavior</label>
                                    <select name="petty_behavior" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                        <option value="">-- None --</option>
                                        <option value="increase" {{ ($setting?->petty_behavior === 'increase') ? 'selected' : '' }}>Increase Petty</option>
                                        <option value="decrease" {{ ($setting?->petty_behavior === 'decrease') ? 'selected' : '' }}>Decrease Petty</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">Company Pending Behavior</label>
                                    <select name="company_pending_behavior" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800">
                                        <option value="">-- None --</option>
                                        <option value="increase" {{ ($setting?->company_pending_behavior === 'increase') ? 'selected' : '' }}>Increase Pending</option>
                                        <option value="decrease" {{ ($setting?->company_pending_behavior === 'decrease') ? 'selected' : '' }}>Decrease Pending</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-slate-800 text-white text-xs font-extrabold hover:bg-slate-900 transition">Save Advanced</button>
                            </div>
                        </form>
                    </details>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Assign Shops Modal -->
<div id="assignShopsModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-xl max-w-lg w-full p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-black text-slate-900">Manage Shop Assignments</h3>
            <button type="button" onclick="document.getElementById('assignShopsModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.cashbook.categories.assign-shops', $entryType->code) }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-600">Select Shops to Assign Category:</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="document.querySelectorAll('.modal-shop-cb').forEach(c => c.checked = true)" class="text-[11px] font-bold text-indigo-600 hover:underline">Select All</button>
                    <button type="button" onclick="document.querySelectorAll('.modal-shop-cb').forEach(c => c.checked = false)" class="text-[11px] font-bold text-indigo-600 hover:underline">Clear All</button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-1">
                @foreach($allShops as $shop)
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-slate-50/50 cursor-pointer">
                        <input type="checkbox" name="shop_ids[]" value="{{ $shop->id }}" {{ in_array($shop->id, $assignedShopIds) ? 'checked' : '' }} class="modal-shop-cb rounded border-slate-300 text-indigo-600 h-4 w-4">
                        <span class="text-xs font-bold text-slate-800">{{ $shop->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('assignShopsModal').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-black shadow-sm hover:bg-indigo-700">Save Assignments</button>
            </div>
        </form>
    </div>
</div>
@endsection
