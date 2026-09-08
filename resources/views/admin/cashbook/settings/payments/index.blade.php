@extends('admin.cashbook.layouts.app')

@section('title', $currentShop->name.' - Payments Settings')

@section('content')
@php
    $payableConfig = $paymentConfig['payable'] ?? ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => null];
    $salesConfig = $paymentConfig['sales_collections'] ?? $paymentConfig['direct_to_company'] ?? $paymentConfig['paid'] ?? ['source' => 'settlement', 'direct_category_ids' => [], 'cash_category_ids' => [], 'settlement_id' => null];
    $directConfig = $paymentConfig['direct_to_company'] ?? $paymentConfig['paid'] ?? ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => null];
    $payableSource = $payableConfig['source'] ?? 'settlement';
    $salesSource = $salesConfig['source'] ?? ($directConfig['source'] ?? 'settlement');
    $payableCategoryIds = array_map('intval', (array) ($payableConfig['category_ids'] ?? []));
    $salesDirectCategoryIds = array_map('intval', (array) ($salesConfig['direct_category_ids'] ?? ($directConfig['category_ids'] ?? [])));
    $salesCashCategoryIds = array_map('intval', (array) ($salesConfig['cash_category_ids'] ?? []));
    $payableSettlementId = $payableConfig['settlement_id'] ?? null;
    $salesSettlementId = $salesConfig['settlement_id'] ?? ($directConfig['settlement_id'] ?? null);
    $paymentSettlementId = $paymentConfig['payment_settlement_id'] ?? $payableSettlementId ?? null;
@endphp

<div class="mx-auto max-w-6xl space-y-6">
    {{-- Navigation Tabs --}}
    @include('admin.cashbook.settings.partials.tabs', [
        'activeTab' => 'payments',
        'shopKey' => $shopKey,
        'currentShop' => $currentShop
    ])

    {{-- Page Header --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-100 text-violet-800 border border-violet-200 shadow-2xs shrink-0">
                <i data-lucide="wallet" class="h-6 w-6"></i>
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-violet-700">Dedicated Settings</span>
                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700 border border-emerald-200">Official Source</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight mt-0.5">SHOP PAYMENTS</h1>
                <p class="text-xs text-slate-500 font-medium">Configure Payable targets (Auto Allocation), and Sales Collections for {{ $currentShop->name }}.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button" onclick="savePaymentsSettings(event)" id="save-payments-settings-btn"
                class="inline-flex items-center gap-2 rounded-xl bg-violet-700 px-5 py-3 text-xs font-black text-white hover:bg-violet-800 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="check" class="h-4 w-4"></i>
                <span>Save Payments Configuration</span>
            </button>
        </div>
    </div>

    {{-- Three Pillars Grid: PAYABLE, SALES COLLECTIONS, MANUAL PAYMENTS --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- PILLAR 1: PAYABLE --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-5">
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Auto Allocation Targets</span>
                        <h2 class="text-base font-black text-slate-950">PAYABLE</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Expenses / obligations configured here define the exact Auto Allocation targets for this shop.</p>
                    </div>
                    <span class="rounded-lg bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-extrabold text-indigo-700 shrink-0">
                        Allocation Source
                    </span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Source:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-2.5 text-xs font-bold text-slate-800 cursor-pointer hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/60 transition">
                            <input type="radio" name="payable_source" value="categories" @checked($payableSource === 'categories') onchange="togglePaymentsSource('payable', 'categories')" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-[11px] truncate">Selected Categories</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-2.5 text-xs font-bold text-slate-800 cursor-pointer hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/60 transition">
                            <input type="radio" name="payable_source" value="settlement" @checked($payableSource === 'settlement') onchange="togglePaymentsSource('payable', 'settlement')" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-[11px] truncate">Existing Settlement</span>
                        </label>
                    </div>
                </div>

                {{-- Payable: Categories Panel --}}
                <div id="payable-categories-panel" class="{{ $payableSource === 'categories' ? '' : 'hidden' }} space-y-3 pt-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">Select Categories (mainly expenses):</span>
                        <button type="button" onclick="selectAllPayableExpenseCategories()" class="text-[11px] font-bold text-indigo-700 hover:underline cursor-pointer">Select All Expenses</button>
                    </div>
                    <div class="max-h-72 overflow-y-auto space-y-1.5 rounded-xl border border-slate-200 bg-slate-50/40 p-2.5">
                        @foreach($entrySettings as $setting)
                            @php
                                $isExpense = strtolower((string) ($setting->entryType?->category ?? '')) === 'expense' || (bool) $setting->include_in_expense;
                            @endphp
                            <label class="flex items-center justify-between gap-2 rounded-lg p-2 text-xs font-medium text-slate-800 hover:bg-white cursor-pointer {{ $isExpense ? 'bg-rose-50/40' : '' }}">
                                <div class="flex items-center gap-2 min-w-0">
                                    <input type="checkbox" name="payable_category_ids[]" value="{{ $setting->id }}" @checked(in_array((int) $setting->id, $payableCategoryIds, true)) data-is-expense="{{ $isExpense ? '1' : '0' }}" class="payable-category-checkbox rounded border-slate-300 text-indigo-600">
                                    <span class="truncate font-bold">{{ $setting->displayName() }}</span>
                                </div>
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $isExpense ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $setting->entryType?->category ?? 'other' }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Payable: Settlement Panel --}}
                <div id="payable-settlement-panel" class="{{ $payableSource === 'settlement' ? '' : 'hidden' }} space-y-2 pt-1">
                    <label class="block text-xs font-bold text-slate-700">Select Existing Settlement:</label>
                    <select id="payable_settlement_id" name="payable_settlement_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-800 focus:border-indigo-500 focus:outline-none">
                        <option value="">-- Choose Settlement --</option>
                        @foreach($relations as $rel)
                            <option value="{{ $rel->id }}" @selected($payableSettlementId == $rel->id || (! $payableSettlementId && $rel->is_payment_payable))>
                                {{ $rel->name }} ({{ $rel->items->count() }} items)
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 font-medium">Reuses the settlement calculation engine configured in Settlements.</p>
                </div>
            </div>

            <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 flex items-center justify-between text-xs">
                <span class="font-bold text-indigo-900">Current Month Payable:</span>
                <span class="font-mono font-black text-indigo-950">₹{{ number_format($paymentsSummary['payable'] ?? 0) }}</span>
            </div>
        </div>

        {{-- PILLAR 2: SALES COLLECTIONS --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-5">
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-teal-700">Total Sales Split</span>
                        <h2 class="text-base font-black text-slate-950">SALES COLLECTIONS</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Split sales into Direct to Company vs Cash retained at shop.</p>
                    </div>
                    <span class="rounded-lg bg-teal-50 border border-teal-200 px-2 py-0.5 text-[10px] font-extrabold text-teal-700 shrink-0">
                        Direct + Cash
                    </span>
                </div>

                {{-- Source Radio --}}
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Source:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-2.5 text-xs font-bold text-slate-800 cursor-pointer hover:border-teal-300 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 transition">
                            <input type="radio" name="sales_source" value="categories" @checked($salesSource === 'categories') onchange="togglePaymentsSource('sales', 'categories')" class="text-teal-600 focus:ring-teal-500">
                            <span class="text-[11px] truncate">Selected Categories</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-2.5 text-xs font-bold text-slate-800 cursor-pointer hover:border-teal-300 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 transition">
                            <input type="radio" name="sales_source" value="settlement" @checked($salesSource === 'settlement') onchange="togglePaymentsSource('sales', 'settlement')" class="text-teal-600 focus:ring-teal-500">
                            <span class="text-[11px] truncate">Existing Settlement</span>
                        </label>
                    </div>
                </div>

                {{-- Sales: Categories Panel with Direct vs Cash groupings --}}
                <div id="sales-categories-panel" class="{{ $salesSource === 'categories' ? '' : 'hidden' }} space-y-4 pt-1">
                    
                    {{-- 1. Direct to Company Sub-section --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-teal-900 uppercase tracking-tight">Direct to Company (Bank / Digital)</span>
                        </div>
                        <p class="text-[11px] text-teal-700 font-medium">Card, Paytm, UPI etc. auto-received directly by company bank accounts.</p>
                        
                        <div class="max-h-44 overflow-y-auto space-y-1.5 rounded-xl border border-teal-200/80 bg-teal-50/30 p-2">
                            @foreach($entrySettings as $setting)
                                @php
                                    $code = strtolower((string) ($setting->entryType?->code ?? ''));
                                    $dispName = strtolower($setting->displayName());
                                    $companyAccount = $setting->companyAccount;
                                    $bankName = $companyAccount ? ($companyAccount->bank_name ?: $companyAccount->name) : null;
                                    $isCash = $setting->company_account_id === null && (str_contains($code, 'cash') || str_contains($dispName, 'cash') || in_array($setting->default_funding_source, ['shop_cash', 'cash', 'sales'], true));
                                @endphp
                                @if(! $isCash)
                                    <label class="flex items-center justify-between gap-2 rounded-lg p-1.5 text-xs font-medium text-slate-800 hover:bg-white cursor-pointer {{ $companyAccount ? 'bg-emerald-50/60' : '' }}">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <input type="checkbox" name="sales_direct_category_ids[]" value="{{ $setting->id }}" @checked(in_array((int) $setting->id, $salesDirectCategoryIds, true)) class="sales-direct-checkbox rounded border-slate-300 text-teal-600">
                                            <span class="truncate font-bold">{{ $setting->displayName() }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            @if($companyAccount)
                                                <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[9px] font-black uppercase bg-emerald-100 text-emerald-800">
                                                    <i data-lucide="landmark" class="h-3 w-3"></i>
                                                    {{ $bankName }}
                                                </span>
                                            @else
                                                <span class="rounded px-1.5 py-0.5 text-[9px] font-bold uppercase bg-slate-100 text-slate-600">
                                                    {{ $setting->entryType?->category ?? 'other' }}
                                                </span>
                                            @endif
                                        </div>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- 2. Cash / Shop Collections Sub-section --}}
                    <div class="space-y-1.5 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-amber-900 uppercase tracking-tight">Cash / Shop Collections</span>
                        </div>
                        <p class="text-[11px] text-amber-700 font-medium">Stays with the shop and contributes directly to Shop Balance.</p>

                        <div class="max-h-44 overflow-y-auto space-y-1.5 rounded-xl border border-amber-200/80 bg-amber-50/30 p-2">
                            @foreach($entrySettings as $setting)
                                @php
                                    $code = strtolower((string) ($setting->entryType?->code ?? ''));
                                    $dispName = strtolower($setting->displayName());
                                    $isCash = $setting->company_account_id === null && (str_contains($code, 'cash') || str_contains($dispName, 'cash') || in_array($setting->default_funding_source, ['shop_cash', 'cash', 'sales'], true));
                                @endphp
                                @if($isCash || in_array((int) $setting->id, $salesCashCategoryIds, true))
                                    <label class="flex items-center justify-between gap-2 rounded-lg p-1.5 text-xs font-medium text-slate-800 hover:bg-white cursor-pointer bg-amber-50/60">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <input type="checkbox" name="sales_cash_category_ids[]" value="{{ $setting->id }}" @checked(in_array((int) $setting->id, $salesCashCategoryIds, true) || (empty($salesCashCategoryIds) && $isCash)) class="sales-cash-checkbox rounded border-slate-300 text-amber-600">
                                            <span class="truncate font-bold">{{ $setting->displayName() }}</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase bg-amber-100 text-amber-800">
                                            <i data-lucide="banknote" class="h-3 w-3"></i>
                                            Stays with Shop
                                        </span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>

                </div>

                {{-- Sales: Settlement Panel --}}
                <div id="sales-settlement-panel" class="{{ $salesSource === 'settlement' ? '' : 'hidden' }} space-y-2 pt-1">
                    <label class="block text-xs font-bold text-slate-700">Select Existing Settlement:</label>
                    <select id="sales_settlement_id" name="sales_settlement_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-800 focus:border-teal-500 focus:outline-none">
                        <option value="">-- Choose Settlement --</option>
                        @foreach($relations as $rel)
                            <option value="{{ $rel->id }}" @selected($salesSettlementId == $rel->id || (! $salesSettlementId && $rel->is_payment_paid))>
                                {{ $rel->name }} ({{ $rel->items->count() }} items)
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 font-medium">Reuses the settlement calculation engine configured in Settlements.</p>
                </div>
            </div>

            <div class="rounded-xl border border-teal-100 bg-teal-50/50 p-3 space-y-1 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-extrabold uppercase text-slate-900">Total Sales:</span>
                    <span class="font-mono font-black text-slate-950">₹{{ number_format($paymentsSummary['total_sales'] ?? ($paymentsSummary['direct_to_company'] ?? 0)) }}</span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500">
                    <span>Direct to Company: ₹{{ number_format($paymentsSummary['direct_to_company'] ?? 0) }}</span>
                    <span>Cash in Shop: ₹{{ number_format($paymentsSummary['cash_in_shop'] ?? 0) }}</span>
                </div>
            </div>
        </div>

        {{-- PILLAR 3: MANUAL PAYMENTS --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-5">
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Shop &rarr; Company Remittances</span>
                        <h2 class="text-base font-black text-slate-950">MANUAL PAYMENTS</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Shop Balance / Cash manually sent later to company.</p>
                    </div>
                    <span class="rounded-lg bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-extrabold text-amber-800 shrink-0">
                        Existing Flow
                    </span>
                </div>

                {{-- Explanation Box --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 space-y-2 text-xs text-slate-600 leading-relaxed font-medium">
                    <div class="flex items-center gap-1.5 font-bold text-slate-900">
                        <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600"></i>
                        No Duplicate Configuration Needed
                    </div>
                    <p>Uses the existing <code class="rounded bg-slate-200 px-1 py-0.5 font-mono text-[11px] text-slate-800">ShopInvoicePaymentRequest</code> flow.</p>
                    <div class="rounded-xl border border-slate-200/80 bg-white p-2.5 space-y-1 font-mono text-[11px] text-slate-700">
                        <div>Cash Sale &rarr; Stays with Shop</div>
                        <div>&rarr; Becomes Shop Balance</div>
                        <div>&rarr; Shop uses for Cash Purchase / Expenses</div>
                        <div>&rarr; Remaining cash is manually paid to company</div>
                    </div>
                </div>

                {{-- Current Month Breakdown --}}
                <div class="space-y-2 pt-1">
                    <span class="text-xs font-bold text-slate-700 block">Current Month Remittance Status:</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-2.5 text-center">
                            <span class="text-[10px] font-black uppercase text-emerald-800 block">Received (Approved)</span>
                            <span class="font-mono text-sm font-black text-emerald-950">₹{{ number_format($paymentsSummary['manual_received'] ?? 0) }}</span>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-2.5 text-center">
                            <span class="text-[10px] font-black uppercase text-amber-800 block">Pending Review</span>
                            <span class="font-mono text-sm font-black text-amber-950">₹{{ number_format($paymentsSummary['manual_pending'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100">
                <a href="{{ route('admin.cashbook.shop.accept-payment', $shopKey) }}"
                   class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-slate-50 hover:bg-slate-100 px-3.5 py-2.5 text-xs font-bold text-slate-800 transition">
                    <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                    <span>Review Shop Payment Requests</span>
                </a>
            </div>
        </div>
    </div>

    {{-- LIVE FORMULA PREVIEW BANNER --}}
    <div class="rounded-3xl border border-slate-900/10 bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-950 p-6 text-white shadow-md">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-400">LIVE EVALUATION PREVIEW</span>
                <h3 class="text-base font-extrabold text-white">How This Evaluates on Shop Owner Payments</h3>
                <p class="text-xs text-slate-300 font-medium">Both Shop Owner Payments and Admin Cashbook calculate using this exact formula.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 font-mono text-xs">
                <div class="rounded-xl bg-white/10 px-3 py-2 text-center border border-white/10">
                    <span class="block text-[10px] uppercase font-bold text-indigo-300">Payable</span>
                    <span class="font-black text-white">₹{{ number_format($paymentsSummary['payable'] ?? 0) }}</span>
                </div>
                <span class="text-slate-400 font-black text-sm">−</span>
                <div class="rounded-xl bg-white/10 px-3 py-2 text-center border border-white/10">
                    <span class="block text-[10px] uppercase font-bold text-teal-300">Direct</span>
                    <span class="font-black text-white">₹{{ number_format($paymentsSummary['direct_to_company'] ?? 0) }}</span>
                </div>
                <span class="text-slate-400 font-black text-sm">−</span>
                <div class="rounded-xl bg-white/10 px-3 py-2 text-center border border-white/10">
                    <span class="block text-[10px] uppercase font-bold text-emerald-300">Manual Rec.</span>
                    <span class="font-black text-white">₹{{ number_format($paymentsSummary['manual_received'] ?? 0) }}</span>
                </div>
                <span class="text-slate-400 font-black text-sm">=</span>
                <div class="rounded-xl bg-emerald-500/20 px-3.5 py-2 text-center border border-emerald-400/30">
                    <span class="block text-[10px] uppercase font-bold text-emerald-300">Shop Balance</span>
                    <span class="font-black text-emerald-200 text-sm">₹{{ number_format($paymentsSummary['shop_balance'] ?? 0) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePaymentsSource(type, source) {
    const categoriesPanel = document.getElementById(`${type}-categories-panel`);
    const settlementPanel = document.getElementById(`${type}-settlement-panel`);
    if (source === 'categories') {
        categoriesPanel?.classList.remove('hidden');
        settlementPanel?.classList.add('hidden');
    } else {
        categoriesPanel?.classList.add('hidden');
        settlementPanel?.classList.remove('hidden');
    }
}

function selectAllPayableExpenseCategories() {
    document.querySelectorAll('.payable-category-checkbox[data-is-expense="1"]').forEach(cb => {
        cb.checked = true;
    });
}

async function savePaymentsSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('save-payments-settings-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>`;
    if (window.lucide) lucide.createIcons();

    const payableSource = document.querySelector('input[name="payable_source"]:checked')?.value || 'settlement';
    const salesSource = document.querySelector('input[name="sales_source"]:checked')?.value || 'settlement';

    const payableCategoryIds = Array.from(document.querySelectorAll('input[name="payable_category_ids[]"]:checked')).map(cb => parseInt(cb.value));
    const salesDirectCategoryIds = Array.from(document.querySelectorAll('input[name="sales_direct_category_ids[]"]:checked')).map(cb => parseInt(cb.value));
    const salesCashCategoryIds = Array.from(document.querySelectorAll('input[name="sales_cash_category_ids[]"]:checked')).map(cb => parseInt(cb.value));

    const payableSettlementId = document.getElementById('payable_settlement_id')?.value || null;
    const salesSettlementId = document.getElementById('sales_settlement_id')?.value || null;

    const salesPayload = {
        source: salesSource,
        direct_category_ids: salesDirectCategoryIds,
        cash_category_ids: salesCashCategoryIds,
        category_ids: salesDirectCategoryIds,
        settlement_id: salesSettlementId
    };

    const payload = {
        payable: {
            source: payableSource,
            category_ids: payableCategoryIds,
            settlement_id: payableSettlementId
        },
        sales_collections: salesPayload,
        direct_to_company: salesPayload,
        paid: salesPayload
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    const url = '{{ route('admin.cashbook.settings.shop.payments-configuration.save', $shopKey) }}';

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (response.ok && data.success) {
            if (window.showToast) {
                showToast(data.message || 'Payments configuration saved successfully.', 'success');
            } else {
                alert(data.message || 'Payments configuration saved successfully.');
            }
        } else {
            alert(data.message || 'Failed to save payments configuration.');
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred while saving payments configuration.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (window.lucide) lucide.createIcons();
    }
}
</script>
@endsection
