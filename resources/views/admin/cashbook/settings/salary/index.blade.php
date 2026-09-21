@extends('admin.cashbook.layouts.app')

@section('title', 'Salary Settings - '.$currentShop->name)

@section('content')
<div class="space-y-6 pb-12">
    {{-- Top Tabs Navigation --}}
    @include('admin.cashbook.settings.partials.tabs', [
        'activeTab' => 'salary',
        'currentShop' => $currentShop,
        'shopKey' => $shopKey
    ])

    {{-- Session Alerts --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm font-semibold text-emerald-900 shadow-xs flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <i data-lucide="check-circle-2" class="h-5 w-5"></i>
                </div>
                <div>{{ session('success') }}</div>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm font-semibold text-rose-900 shadow-xs">
            <div class="flex items-start gap-3">
                <div class="h-8 w-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                    <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                </div>
                <div>
                    <p class="font-bold text-rose-950">Please review the following errors:</p>
                    <ul class="list-disc pl-5 mt-1 space-y-0.5 text-xs text-rose-800">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Header Card --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 rounded-xl bg-purple-50 px-3 py-1 text-xs font-black text-purple-700 border border-purple-100">
                    <i data-lucide="wallet" class="h-3.5 w-3.5"></i>
                    HR ⇄ Cashbook Salary Bridge
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-950">
                    Salary Settings: <span class="text-purple-700">{{ $currentShop->name }}</span>
                </h1>
                <p class="text-sm font-medium text-slate-500 max-w-3xl leading-relaxed">
                    Configure how HR payroll transactions (Salary, Advance, Adjustments, and Recovery) bridge into this shop's Cashbook ledger, permitted payment modes, and connected settlement relations.
                </p>
            </div>

            {{-- Shop Switcher --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                <div class="relative">
                    <label for="shop-selector" class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Switch Shop</label>
                    <select id="shop-selector"
                            onchange="window.location.href = this.value"
                            class="w-full sm:w-56 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800 shadow-2xs focus:border-purple-500 focus:outline-none">
                        @foreach($shops as $shopProfile)
                            @php
                                $targetKey = $shopProfile->slug ?: $shopProfile->shop_id;
                            @endphp
                            <option value="{{ route('admin.cashbook.settings.shop.salary.index', $targetKey) }}"
                                    {{ (int)$shopProfile->shop_id === (int)$shop->id ? 'selected' : '' }}>
                                {{ $shopProfile->name }} ({{ $shopProfile->code ?: 'SHOP-'.$shopProfile->shop_id }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Configuration Hierarchy Tree Overview --}}
        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-950 p-5 sm:p-6 text-slate-100">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="h-2.5 w-2.5 rounded-full bg-purple-400 animate-pulse"></div>
                    <span class="text-xs font-black uppercase tracking-wider text-slate-400">Current Salary Bridge Hierarchy</span>
                </div>
                <span class="text-[11px] font-mono text-slate-500">{{ $currentShop->name }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($treeItems as $item)
                    <div class="rounded-xl border border-slate-800/80 bg-slate-900/90 p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-xs font-black text-purple-300">{{ $item['type_label'] }}</span>
                                @if($item['is_configured'])
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">Mapped</span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">Unmapped</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5 text-sm font-extrabold text-white mb-3 truncate" title="{{ $item['category_name'] }}">
                                <i data-lucide="corner-down-right" class="h-3.5 w-3.5 text-slate-500 shrink-0"></i>
                                <span>{{ $item['category_name'] }}</span>
                            </div>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-slate-800/80 text-[11px]">
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Default Mode:</span>
                                <span class="font-bold text-amber-300">{{ $item['default_mode_label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-slate-400">
                                <span>Allowed:</span>
                                <div class="flex items-center gap-1 font-mono text-[10px]">
                                    <span class="{{ $item['allowed_modes']['sales_cash'] ? 'text-emerald-400 font-bold' : 'text-slate-600 line-through' }}">Sales</span>
                                    <span class="text-slate-700">·</span>
                                    <span class="{{ $item['allowed_modes']['petty'] ? 'text-emerald-400 font-bold' : 'text-slate-600 line-through' }}">Petty</span>
                                    <span class="text-slate-700">·</span>
                                    <span class="{{ $item['allowed_modes']['company_payable'] ? 'text-emerald-400 font-bold' : 'text-slate-600 line-through' }}">Company</span>
                                </div>
                            </div>
                            @if($item['allowed_modes']['company_payable'] && $item['settlement_name'])
                                <div class="flex items-center justify-between text-slate-400 text-[10px] bg-slate-950/60 rounded px-2 py-1 border border-slate-800">
                                    <span class="text-slate-500">Settlement:</span>
                                    <span class="font-semibold text-purple-300 truncate max-w-[120px]" title="{{ $item['settlement_name'] }}">{{ $item['settlement_name'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Main Configuration Form --}}
    @if(!$canEdit)
        <div class="rounded-2xl border border-blue-200 bg-blue-50/80 p-4 text-xs font-semibold text-blue-950 flex items-center gap-3">
            <i data-lucide="info" class="h-4 w-4 text-blue-700 shrink-0"></i>
            <span>Viewing in <strong>Read-Only</strong> mode. You can inspect how salary entries will behave for this shop. Editing requires Cashbook Administrator privileges.</span>
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.cashbook.settings.shop.salary.update', $shopKey) }}"
          id="salary-settings-form-{{ $shopKey }}">
        @csrf

        <div class="space-y-6">
            @foreach($transactionTypes as $type)
                @php
                    $typeKey = $type->value;
                    /** @var \App\Models\Cashbook\ShopSalaryBridgeSetting $setting */
                    $setting = $salarySettings->get($typeKey);
                    $allowedModes = is_array($setting->allowed_payment_modes) ? $setting->allowed_payment_modes : ['sales_cash'];
                    $defaultMode = $setting->default_payment_mode ?: 'sales_cash';
                @endphp

                <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7 shadow-xs hover:border-slate-300 transition-colors">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 border-b border-slate-100 pb-5 mb-6">
                        <div class="flex items-start gap-4">
                            <div class="h-11 w-11 rounded-2xl bg-purple-50 text-purple-700 border border-purple-100 flex items-center justify-center shrink-0 mt-0.5">
                                @if($typeKey === 'salary')
                                    <i data-lucide="banknote" class="h-5 w-5"></i>
                                @elseif($typeKey === 'salary_advance')
                                    <i data-lucide="hand-coins" class="h-5 w-5"></i>
                                @elseif($typeKey === 'salary_adjustment')
                                    <i data-lucide="sliders" class="h-5 w-5"></i>
                                @else
                                    <i data-lucide="refresh-ccw" class="h-5 w-5"></i>
                                @endif
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-black text-slate-950">{{ $type->label() }}</h2>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-bold text-slate-500">{{ $typeKey }}</span>
                                </div>
                                <p class="text-xs font-semibold text-slate-500 mt-1 leading-relaxed">{{ $type->description() }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @if($setting->shop_ledger_entry_setting_id)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                                    <i data-lucide="check" class="h-3.5 w-3.5"></i> Mapped
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 border border-amber-200">
                                    <i data-lucide="help-circle" class="h-3.5 w-3.5"></i> Not configured
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        {{-- 1. Cashbook Category Mapping --}}
                        <div class="lg:col-span-4 space-y-2">
                            <label for="entry-setting-{{ $typeKey }}" class="block text-xs font-extrabold text-slate-800">
                                Cashbook Category <span class="text-slate-400 font-normal">(Entry Setting)</span>
                            </label>
                            <select id="entry-setting-{{ $typeKey }}"
                                    name="types[{{ $typeKey }}][shop_ledger_entry_setting_id]"
                                    {{ !$canEdit ? 'disabled' : '' }}
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-purple-500 focus:outline-none">
                                <option value="" {{ empty($setting->shop_ledger_entry_setting_id) ? 'selected' : '' }}>
                                    -- Not configured --
                                </option>
                                @foreach($entrySettings as $es)
                                    @php
                                        $label = ($es->display_name ?: $es->entryType?->name) . ($es->headerGroup ? ' ('.$es->headerGroup->name.')' : '');
                                        $isSelected = (int)$setting->shop_ledger_entry_setting_id === (int)$es->id;
                                    @endphp
                                    <option value="{{ $es->id }}" {{ $isSelected ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] font-medium text-slate-400">Ledger column where this HR transaction is posted.</p>
                        </div>

                        {{-- 2. Allowed Payment Modes --}}
                        <div class="lg:col-span-5 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-800">
                                Allowed Payment Modes
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                {{-- Sales Cash --}}
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100/80 cursor-pointer transition">
                                    <input type="checkbox"
                                           name="types[{{ $typeKey }}][allowed_payment_modes][]"
                                           value="sales_cash"
                                           {{ in_array('sales_cash', $allowedModes, true) ? 'checked' : '' }}
                                           {{ !$canEdit ? 'disabled' : '' }}
                                           class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Sales Cash</div>
                                        <div class="text-[10px] text-slate-500">Daily shop drawer</div>
                                    </div>
                                </label>

                                {{-- Petty Cash --}}
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100/80 cursor-pointer transition">
                                    <input type="checkbox"
                                           name="types[{{ $typeKey }}][allowed_payment_modes][]"
                                           value="petty"
                                           {{ in_array('petty', $allowedModes, true) ? 'checked' : '' }}
                                           {{ !$canEdit ? 'disabled' : '' }}
                                           class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Petty Cash</div>
                                        <div class="text-[10px] text-slate-500">Petty float pool</div>
                                    </div>
                                </label>

                                {{-- Company Payable --}}
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100/80 cursor-pointer transition">
                                    <input type="checkbox"
                                           id="company-payable-checkbox-{{ $typeKey }}"
                                           name="types[{{ $typeKey }}][allowed_payment_modes][]"
                                           value="company_payable"
                                           {{ in_array('company_payable', $allowedModes, true) ? 'checked' : '' }}
                                           {{ !$canEdit ? 'disabled' : '' }}
                                           onchange="toggleSettlementSection('{{ $typeKey }}')"
                                           class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Company</div>
                                        <div class="text-[10px] text-slate-500">Payable / Settlement</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- 3. Default Payment Mode --}}
                        <div class="lg:col-span-3 space-y-2">
                            <label for="default-mode-{{ $typeKey }}" class="block text-xs font-extrabold text-slate-800">
                                Default Mode
                            </label>
                            <select id="default-mode-{{ $typeKey }}"
                                    name="types[{{ $typeKey }}][default_payment_mode]"
                                    {{ !$canEdit ? 'disabled' : '' }}
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-purple-500 focus:outline-none">
                                <option value="sales_cash" {{ $defaultMode === 'sales_cash' ? 'selected' : '' }}>Sales Cash</option>
                                <option value="petty" {{ $defaultMode === 'petty' ? 'selected' : '' }}>Petty Cash</option>
                                <option value="company_payable" {{ $defaultMode === 'company_payable' ? 'selected' : '' }}>Company Payable</option>
                            </select>
                            <p class="text-[11px] font-medium text-slate-400">Pre-selected source in HR &amp; Shop payment forms.</p>
                        </div>
                    </div>

                    {{-- 4. Connected Settlement Relationship (Visible / Highlighted when Company Payable is enabled) --}}
                    <div id="settlement-container-{{ $typeKey }}"
                         class="mt-5 pt-4 border-t border-dashed border-slate-200 {{ in_array('company_payable', $allowedModes, true) ? '' : 'hidden' }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-purple-50/60 border border-purple-200/80 rounded-2xl p-4">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                                    <i data-lucide="git-fork" class="h-4 w-4"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-black text-purple-950">Connected Settlement (Company Payable)</div>
                                    <div class="text-[11px] text-purple-800 font-medium">When Company Payable mode is selected, entries route to this settlement.</div>
                                </div>
                            </div>

                            <div class="w-full sm:w-72 shrink-0">
                                <select name="types[{{ $typeKey }}][company_payable_settlement_id]"
                                        {{ !$canEdit ? 'disabled' : '' }}
                                        class="w-full rounded-xl border border-purple-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-purple-600 focus:outline-none">
                                    <option value="">-- Select Settlement --</option>
                                    @foreach($settlements as $relation)
                                        @php
                                            $isRelationSelected = (int)$setting->company_payable_settlement_id === (int)$relation->id;
                                        @endphp
                                        <option value="{{ $relation->id }}" {{ $isRelationSelected ? 'selected' : '' }}>
                                            {{ $relation->name }} {{ $relation->is_company_payable ? '★ (Payable)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Action Bar --}}
        @if($canEdit)
            <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs">
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                    <i data-lucide="shield-check" class="h-4 w-4 text-emerald-600"></i>
                    <span>Changes will take effect for future HR &amp; Cashbook entries. All updates are logged.</span>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-3 shrink-0">
                    {{-- Reset to Default (separate form, separate submit) --}}
                    <form method="POST"
                          action="{{ route('admin.cashbook.settings.shop.salary.reset', $shopKey) }}"
                          id="salary-reset-form"
                          onsubmit="return confirmSalaryReset()">
                        @csrf
                        <button type="submit"
                                id="salary-reset-btn"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white hover:bg-slate-50 px-5 py-3.5 text-xs sm:text-sm font-black text-slate-700 shadow-xs transition-all focus:outline-none">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                            Reset to Default
                        </button>
                    </form>
                    <button type="submit"
                            form="{{ 'salary-settings-form-'.$shopKey }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-purple-700 hover:bg-purple-800 px-6 py-3.5 text-xs sm:text-sm font-black text-white shadow-sm transition-all focus:outline-none">
                        <i data-lucide="save" class="h-4 w-4"></i>
                        Save Salary Settings
                    </button>
                </div>
            </div>
        @endif
    </form>

    {{-- Audit Log History Section --}}
    @if($recentAudits->isNotEmpty())
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <i data-lucide="history" class="h-4 w-4 text-slate-400"></i>
                    <h3 class="text-sm font-black text-slate-900">Salary Bridge Audit Trail</h3>
                </div>
                <span class="text-[11px] font-semibold text-slate-400">Last 10 Changes</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($recentAudits as $audit)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900">{{ $audit->causer?->name ?? 'System' }}</span>
                            <span class="text-slate-400">·</span>
                            <span class="text-slate-600">{{ $audit->description }}</span>
                        </div>
                        <div class="font-mono text-[11px] text-slate-400">
                            {{ $audit->created_at?->diffForHumans() }} ({{ $audit->created_at?->format('Y-m-d H:i:s') }})
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
function toggleSettlementSection(typeKey) {
    const checkbox = document.getElementById('company-payable-checkbox-' + typeKey);
    const container = document.getElementById('settlement-container-' + typeKey);
    if (!checkbox || !container) return;

    if (checkbox.checked) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

function confirmSalaryReset() {
    return window.confirm(
        'Reset salary settings to default?\n\n' +
        'Allowed modes will become: Sales Cash, Company Payable, Petty.\n' +
        'Default mode will become: Sales Cash.\n\n' +
        'Your Cashbook category mappings and settlement relations will be preserved.\n\n' +
        'This cannot be undone automatically. Proceed?'
    );
}
</script>
@endsection
