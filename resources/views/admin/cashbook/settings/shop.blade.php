@extends('admin.cashbook.layouts.app')

@section('title', $currentShop->name.' Settings - Cashbook')

@section('content')
@php
    $sections = [
        'income' => ['title' => 'Income', 'icon' => 'trending-up', 'icon_class' => 'bg-emerald-50 text-emerald-700'],
        'expense' => ['title' => 'Expense', 'icon' => 'trending-down', 'icon_class' => 'bg-rose-50 text-rose-700'],
        'transfer' => ['title' => 'Transfer', 'icon' => 'repeat-2', 'icon_class' => 'bg-sky-50 text-sky-700'],
    ];
    $fundingSources = [
        'none' => 'None',
        'sales' => 'Sales',
        'petty' => 'Petty',
        'company' => 'Company',
        'company_later' => 'Company Later',
        'bank' => 'Bank',
    ];
    $effects = [
        'none' => 'No change',
        'increase' => 'Increase',
        'decrease' => 'Decrease',
    ];
    $allShopRows = $settingsByCategory
        ->flatten(1)
        ->sortBy(fn ($setting) => $setting->entryType?->name)
        ->values();
    $collectionIncomeIds = $collectionGroup
        ? $collectionGroup->entryTypes->where('role', 'income')->pluck('entry_type_id')->map(fn ($id) => (int) $id)->all()
        : [];
    $collectionExpenseIds = $collectionGroup
        ? $collectionGroup->entryTypes->where('role', 'expense')->pluck('entry_type_id')->map(fn ($id) => (int) $id)->all()
        : [];
@endphp

<div class="space-y-5">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <a href="{{ route('admin.cashbook.settings') }}" class="mb-2 inline-flex items-center gap-1 text-xs font-black text-slate-400 hover:text-slate-700">
                    <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                    All Shops
                </a>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">{{ $currentShop->name }}</h1>
                <p class="mt-1 font-mono text-xs font-bold text-slate-400">{{ $currentShop->code ?: 'SHOP-'.$currentShop->shop_id }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="#income" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700">Income</a>
                <a href="#expense" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700">Expense</a>
                <a href="#transfer" class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-black text-sky-700">Transfer</a>
                <a href="#collection" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-700">Collection</a>
            </div>
        </div>
    </div>

    @foreach($sections as $category => $section)
        @php $rows = $settingsByCategory->get($category, collect()); @endphp
        <section id="{{ $category }}" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $section['icon_class'] }}">
                        <i data-lucide="{{ $section['icon'] }}" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-950">{{ $section['title'] }}</h2>
                        <p class="text-xs font-semibold text-slate-500">{{ $rows->count() }} rows</p>
                    </div>
                </div>
                <form onsubmit="createCustomRow(event, '{{ $category }}')" class="flex w-full gap-2 sm:w-auto">
                    <input type="hidden" name="shop_id" value="{{ $currentShop->shop_id }}">
                    <input type="text" name="name" placeholder="New {{ strtolower($section['title']) }} row"
                           class="h-10 min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900 focus:border-slate-400 focus:outline-none sm:w-64">
                    <button type="submit" class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Add Row
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto lg:overflow-visible">
                <table class="w-full min-w-[84rem] lg:table-fixed text-[11px] lg:text-[10px]">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="w-[15%] px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Row</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">On</th>
                            <th class="w-[8%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Paid From</th>
                            <th class="w-[10%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Bank Dest</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Sales</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Income</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Expense</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">P&L</th>
                            <th class="w-[8%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Settlement</th>
                            <th class="w-[7%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Petty</th>
                            <th class="w-[8%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Company</th>
                            <th class="w-[8%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Payable</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Auto Child</th>
                            <th class="w-[9%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Child Row</th>
                            <th class="w-[6%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Child Amount</th>
                            <th class="w-[4%] px-2 py-2 text-right text-[10px] font-black uppercase tracking-wider text-slate-400">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $setting)
                            <tr>
                                <td class="px-3 py-2">
                                    <div class="font-black text-slate-950">{{ $setting->entryType->name }}</div>
                                    <div class="font-mono text-[10px] font-bold text-slate-400">{{ $setting->entryType->code }}</div>
                                </td>
                                <td class="px-2 py-2">
                                    <input form="setting-{{ $setting->id }}" type="hidden" name="enabled" value="0">
                                    <input form="setting-{{ $setting->id }}" type="checkbox" name="enabled" value="1" @checked($setting->enabled) class="rounded border-slate-300 text-emerald-600">
                                </td>
                                <td class="px-2 py-2">
                                    <select form="setting-{{ $setting->id }}" name="default_funding_source" class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700">
                                        @foreach($fundingSources as $value => $label)
                                            <option value="{{ $value }}" @selected($setting->default_funding_source === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <select form="setting-{{ $setting->id }}" name="company_account_id" class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700">
                                        <option value="">None</option>
                                        @foreach($companyAccounts as $account)
                                            <option value="{{ $account->id }}" @selected((int) $setting->company_account_id === (int) $account->id)>
                                                {{ $account->name }} ({{ $account->bank_name ?: $account->account_type }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @if($setting->companyAccount && $setting->companyAccount->account_type === 'cash' && in_array($setting->entryType?->code, ['paytm', 'card', 'upi', 'gpay', 'online']))
                                        <div class="mt-1 text-[9px] font-bold text-amber-700 leading-tight">
                                            ⚠️ Cash account selected
                                        </div>
                                    @endif
                                    @php
                                        $rulesForThisSetting = isset($bankAdjustmentRules) ? ($bankAdjustmentRules->get($setting->entry_type_id) ?? collect()) : collect();
                                    @endphp
                                    <div class="mt-1 flex items-center justify-between">
                                        <button type="button"
                                                id="btn-adj-rules-{{ $setting->entry_type_id }}"
                                                onclick="openBankAdjModal({{ $currentShop->shop_id }}, {{ $setting->entry_type_id }}, '{{ addslashes($setting->entryType?->name) }}')"
                                                class="inline-flex items-center gap-1 text-[9px] font-extrabold px-1.5 py-0.5 rounded border transition {{ $rulesForThisSetting->isNotEmpty() ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200' }}">
                                            <span>⚖️ Adj Rules ({{ $rulesForThisSetting->count() }})</span>
                                        </button>
                                    </div>
                                </td>
                                @foreach(['include_in_sales', 'include_in_income', 'include_in_expense', 'include_in_pl'] as $field)
                                    <td class="px-2 py-2">
                                        <input form="setting-{{ $setting->id }}" type="hidden" name="{{ $field }}" value="0">
                                        <input form="setting-{{ $setting->id }}" type="checkbox" name="{{ $field }}" value="1" {{ $setting->{$field} ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900">
                                    </td>
                                @endforeach
                                @foreach(['settlement_behavior', 'petty_behavior', 'company_pending_behavior'] as $field)
                                    <td class="px-2 py-2">
                                        <select form="setting-{{ $setting->id }}" name="{{ $field }}" class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700">
                                            @foreach($effects as $value => $label)
                                                <option value="{{ $value }}" {{ (($setting->{$field} ?: 'none') === $value) ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td class="px-2 py-2">
                                    @if($category === 'income')
                                        <input form="setting-{{ $setting->id }}" type="hidden" name="include_in_payable" value="0">
                                        <input form="setting-{{ $setting->id }}" type="hidden" name="payable_direction" value="add">
                                        <label class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-700 cursor-pointer">
                                            <input form="setting-{{ $setting->id }}" type="checkbox" name="include_in_payable" value="1" {{ $setting->include_in_payable ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600">
                                            Add
                                        </label>
                                    @elseif($category === 'expense')
                                        <input form="setting-{{ $setting->id }}" type="hidden" name="include_in_payable" value="0">
                                        <input form="setting-{{ $setting->id }}" type="hidden" name="payable_direction" value="minus">
                                        <label class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-700 cursor-pointer">
                                            <input form="setting-{{ $setting->id }}" type="checkbox" name="include_in_payable" value="1" {{ $setting->include_in_payable ? 'checked' : '' }} class="rounded border-slate-300 text-rose-600">
                                            Minus
                                        </label>
                                    @else
                                        @php
                                            $currentDirection = $setting->payable_direction ?: (($setting->entryType && in_array($setting->entryType->code, ['company_to_petty', 'company_paid_shop', 'company_paid_vendor'])) ? 'minus' : 'add');
                                        @endphp
                                        <input form="setting-{{ $setting->id }}" type="hidden" id="inc-pay-{{ $setting->id }}" name="include_in_payable" value="{{ $setting->include_in_payable ? 1 : 0 }}">
                                        <input form="setting-{{ $setting->id }}" type="hidden" id="pay-dir-{{ $setting->id }}" name="payable_direction" value="{{ $currentDirection }}">
                                        <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                                            <label class="inline-flex items-center gap-1 font-bold text-emerald-700 cursor-pointer" title="Add to Payable">
                                                <input type="radio" name="pay_choice_{{ $setting->id }}" value="add"
                                                       {{ ($setting->include_in_payable && $currentDirection === 'add') ? 'checked' : '' }}
                                                       onchange="setPayableChoice({{ $setting->id }}, 'add')"
                                                       class="h-3 w-3 text-emerald-600 focus:ring-emerald-500">
                                                Add
                                            </label>
                                            <label class="inline-flex items-center gap-1 font-bold text-rose-700 cursor-pointer" title="Minus from Payable">
                                                <input type="radio" name="pay_choice_{{ $setting->id }}" value="minus"
                                                       {{ ($setting->include_in_payable && $currentDirection === 'minus') ? 'checked' : '' }}
                                                       onchange="setPayableChoice({{ $setting->id }}, 'minus')"
                                                       class="h-3 w-3 text-rose-600 focus:ring-rose-500">
                                                Minus
                                            </label>
                                            <label class="inline-flex items-center gap-1 font-bold text-slate-400 cursor-pointer" title="Not in Payable">
                                                <input type="radio" name="pay_choice_{{ $setting->id }}" value="none"
                                                       {{ !$setting->include_in_payable ? 'checked' : '' }}
                                                       onchange="setPayableChoice({{ $setting->id }}, 'none')"
                                                       class="h-3 w-3 text-slate-400 focus:ring-slate-400">
                                                Off
                                            </label>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-2 py-2">
                                    <input form="setting-{{ $setting->id }}" type="hidden" name="generates_secondary_entry" value="0">
                                    <label class="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-700">
                                        <input form="setting-{{ $setting->id }}" type="checkbox" name="generates_secondary_entry" value="1" @checked($setting->generates_secondary_entry) class="rounded border-slate-300 text-emerald-600">
                                        Create
                                    </label>
                                </td>
                                <td class="px-2 py-2">
                                    <select form="setting-{{ $setting->id }}" name="secondary_entry_type_id" class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700">
                                        <option value="">Select child row</option>
                                        @foreach($allShopRows as $childSetting)
                                            @if($childSetting->entry_type_id !== $setting->entry_type_id)
                                                <option value="{{ $childSetting->entry_type_id }}" @selected((int) $setting->secondary_entry_type_id === (int) $childSetting->entry_type_id)>
                                                    {{ $childSetting->entryType->name }} ({{ $childSetting->entryType->category }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex items-center gap-2">
                                        <select form="setting-{{ $setting->id }}" name="secondary_amount_mode" class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700">
                                            <option value="same_amount" @selected($setting->secondary_amount_mode === 'same_amount')>Same</option>
                                            <option value="percentage" @selected($setting->secondary_amount_mode === 'percentage')>Percent</option>
                                        </select>
                                        <input form="setting-{{ $setting->id }}" type="number" min="0" step="0.01" name="secondary_amount_value" value="{{ $setting->secondary_amount_value }}"
                                               class="h-8 w-16 rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700" placeholder="%">
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <form id="setting-{{ $setting->id }}" onsubmit="saveShopSetting(event, {{ $setting->id }})">
                                        <button type="submit" class="rounded-lg bg-slate-950 px-2.5 py-1.5 text-[10px] font-black text-white hover:bg-slate-800">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-5 py-8 text-center text-sm font-bold text-slate-400">No rows found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section id="collection" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form onsubmit="saveCollectionSettings(event)">
            <input type="hidden" name="shop_id" value="{{ $currentShop->shop_id }}">
            <input type="hidden" name="enabled" value="0">

            <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                        <i data-lucide="list-checks" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-950">Collection</h2>
                        <p class="text-xs font-semibold text-slate-500">Enable this to show one combined collection form for this shop.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-700">
                        <input type="checkbox" name="enabled" value="1" @checked($collectionGroup?->enabled) class="rounded border-slate-300 text-emerald-600">
                        Enable Collection
                    </label>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-xs font-black text-white hover:bg-slate-800">Save Collection</button>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach(['income' => 'Income Rows', 'expense' => 'Expense Rows'] as $category => $title)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $title }}</h3>
                        <div class="flex gap-2">
                            <input type="text"
                                   data-collection-custom-row="{{ $category }}"
                                   placeholder="New {{ strtolower($category) }} row"
                                   class="h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none sm:w-44">
                            <button type="button"
                                    onclick="createCollectionCustomRow(event, '{{ $category }}')"
                                    class="h-9 rounded-lg bg-slate-950 px-3 text-[10px] font-black text-white hover:bg-slate-800">
                                Add
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-2">
                        @foreach($settingsByCategory->get($category, collect())->where('enabled', true) as $setting)
                            <label class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm">
                                <span>{{ $setting->entryType->name }}</span>
                                <input type="checkbox"
                                       name="{{ $category === 'income' ? 'income_entry_type_ids[]' : 'expense_entry_type_ids[]' }}"
                                       value="{{ $setting->entry_type_id }}"
                                       @checked(in_array((int) $setting->entry_type_id, $category === 'income' ? $collectionIncomeIds : $collectionExpenseIds, true))
                                       class="rounded border-slate-300 text-emerald-600">
                            </label>
                        @endforeach
                        @if($settingsByCategory->get($category, collect())->where('enabled', true)->isEmpty())
                            <div class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-center text-xs font-bold text-slate-400">
                                Enable {{ strtolower($title) }} above first.
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>
        </form>
    </section>
    <section id="historical-fetch" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                    <i data-lucide="history" class="h-5 w-5"></i>
                </span>
                <div>
                    <h2 class="text-lg font-extrabold text-slate-950">Historical Bank Collection Fetch</h2>
                    <p class="text-xs font-semibold text-slate-500">Fetch past shop online/digital collections (e.g. Paytm, Card) into a linked bank account for reconciliation.</p>
                </div>
            </div>
        </div>

        <form id="historical-fetch-form" onsubmit="previewHistoricalFetch(event)" class="mt-5 space-y-4">
            <input type="hidden" name="shop_id" value="{{ $currentShop->shop_id }}">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Category / Row</label>
                    <select id="hist-entry-type-id" name="entry_type_id" onchange="onHistoricalCategoryChange()" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        @foreach($settingsByCategory->get('income', collect())->where('enabled', true) as $setting)
                            <option value="{{ $setting->entry_type_id }}" data-bank-id="{{ $setting->company_account_id ?: '' }}">
                                {{ $setting->entryType->name }} ({{ $setting->entryType->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Destination Bank</label>
                    <select id="hist-company-account-id" name="company_account_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->name }} ({{ $account->bank_name ?: $account->account_type }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Period Preset</label>
                    <select id="hist-preset" onchange="applyPeriodPreset(this.value)" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="custom">Custom Period</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">From Date</label>
                        <input type="date" id="hist-from-date" name="from_date" value="{{ now()->startOfMonth()->toDateString() }}" required class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">To Date</label>
                        <input type="date" id="hist-to-date" name="to_date" value="{{ now()->toDateString() }}" required class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[11px] font-semibold text-slate-500">Preview calculates eligibility using shop sales date without changing live data.</p>
                <button type="submit" id="btn-preview-hist" class="rounded-xl bg-slate-950 px-5 py-2.5 text-xs font-black text-white hover:bg-slate-800">
                    Preview Historical Entries
                </button>
            </div>
        </form>

        <!-- Preview Results Container -->
        <div id="hist-preview-container" class="mt-6 hidden border-t border-slate-100 pt-5"></div>
    </section>

    <!-- Bank Settlement Adjustment Rules Modal -->
    <div id="bank-adj-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 flex items-center gap-2">
                        <span>⚖️ Bank Settlement Adjustments</span>
                    </h3>
                    <p class="text-xs font-semibold text-slate-500" id="bank-adj-modal-subtitle">Configure optional rules for expected bank calculations.</p>
                </div>
                <button type="button" onclick="closeBankAdjModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <!-- Rules list -->
            <div class="mt-4">
                <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Configured Rules</h4>
                <div id="bank-adj-rules-list" class="space-y-2 max-h-60 overflow-y-auto">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Add new rule form -->
            <div class="mt-5 border-t border-slate-100 pt-4">
                <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Add New Rule</h4>
                <form id="add-bank-adj-rule-form" onsubmit="submitAddBankAdjRule(event)" class="space-y-3">
                    <input type="hidden" id="bank-adj-shop-id" name="shop_id">
                    <input type="hidden" id="bank-adj-entry-type-id" name="entry_type_id">

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-500">Label (e.g. Rent, Other Addition)</label>
                            <input type="text" id="bank-adj-label" name="label" required placeholder="e.g. Rent"
                                   class="mt-1 h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-500">Direction</label>
                            <select id="bank-adj-direction" name="direction" required
                                    class="mt-1 h-9 w-full rounded-xl border border-slate-200 px-2 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                                <option value="minus">MINUS (-)</option>
                                <option value="plus">PLUS (+)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" id="btn-save-adj-rule"
                                class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800 disabled:bg-slate-300">
                            + Add Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setPayableChoice(settingId, choice) {
    const incField = document.getElementById('inc-pay-' + settingId);
    const dirField = document.getElementById('pay-dir-' + settingId);
    if (!incField || !dirField) return;
    if (choice === 'none') {
        incField.value = '0';
    } else {
        incField.value = '1';
        dirField.value = choice;
    }
}

async function saveShopSetting(event, settingId) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.setting_id = settingId;

    button.disabled = true;
    button.textContent = 'Saving';

    try {
        const response = await fetch('/admin/cashbook/api/shop-settings/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        showToast(data.message || (data.success ? 'Saved' : 'Save failed'), data.success ? 'success' : 'error');
    } catch (error) {
        showToast('Save failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Save';
    }
}

async function createCustomRow(event, category) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const name = form.name.value.trim();

    if (!name) {
        showToast('Enter a row name', 'error');
        return;
    }

    button.disabled = true;
    button.textContent = 'Adding';

    try {
        const response = await fetch('/admin/cashbook/api/shop-settings/custom-row', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: form.shop_id.value,
                name,
                category,
            }),
        });
        const data = await response.json();
        if (data.success) {
            showToast(data.message || 'Row added', 'success');
            window.location.reload();
            return;
        }
        showToast(data.message || 'Add row failed', 'error');
    } catch (error) {
        showToast('Add row failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Add Row';
    }
}

async function createCollectionCustomRow(event, category) {
    const button = event.target;
    const input = document.querySelector(`input[data-collection-custom-row="${category}"]`);
    const name = input.value.trim();

    if (!name) {
        showToast('Enter a row name', 'error');
        return;
    }

    button.disabled = true;
    button.textContent = 'Adding';

    try {
        const response = await fetch('/admin/cashbook/api/shop-settings/custom-row', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: '{{ $currentShop->shop_id }}',
                name,
                category,
            }),
        });
        const data = await response.json();
        if (data.success) {
            showToast(data.message || 'Row added', 'success');
            window.location.reload();
            return;
        }
        showToast(data.message || 'Add row failed', 'error');
    } catch (error) {
        showToast('Add row failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Add';
    }
}

async function saveCollectionSettings(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const payload = {
        shop_id: form.shop_id.value,
        enabled: form.querySelector('input[name="enabled"][value="1"]').checked ? 1 : 0,
        income_entry_type_ids: Array.from(form.querySelectorAll('input[name="income_entry_type_ids[]"]:checked')).map((input) => input.value),
        expense_entry_type_ids: Array.from(form.querySelectorAll('input[name="expense_entry_type_ids[]"]:checked')).map((input) => input.value),
    };

    button.disabled = true;
    button.textContent = 'Saving';

    try {
        const response = await fetch('/admin/cashbook/api/shop-settings/collection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        showToast(data.message || (data.success ? 'Saved' : 'Save failed'), data.success ? 'success' : 'error');
    } catch (error) {
        showToast('Save failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Save Collection';
    }
}

// ─── Historical Bank Collection Fetch JS ─────────────────────────────────────

let currentHistoricalPreview = null;

function applyPeriodPreset(preset) {
    const today = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    const fromInput = document.getElementById('hist-from-date');
    const toInput = document.getElementById('hist-to-date');

    if (preset === 'today') {
        fromInput.value = formatDate(today);
        toInput.value = formatDate(today);
    } else if (preset === 'yesterday') {
        const y = new Date();
        y.setDate(y.getDate() - 1);
        fromInput.value = formatDate(y);
        toInput.value = formatDate(y);
    } else if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        fromInput.value = formatDate(firstDay);
        toInput.value = formatDate(today);
    } else if (preset === 'last_month') {
        const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        fromInput.value = formatDate(firstDayLastMonth);
        toInput.value = formatDate(lastDayLastMonth);
    }
}

function onHistoricalCategoryChange() {
    const select = document.getElementById('hist-entry-type-id');
    const selectedOption = select.options[select.selectedIndex];
    const bankId = selectedOption.getAttribute('data-bank-id');
    if (bankId) {
        document.getElementById('hist-company-account-id').value = bankId;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    applyPeriodPreset('this_month');
    onHistoricalCategoryChange();
});

async function previewHistoricalFetch(event) {
    event.preventDefault();
    const form = event.target;
    const button = document.getElementById('btn-preview-hist');
    const container = document.getElementById('hist-preview-container');

    const shopId = form.shop_id?.value;
    const entryTypeId = form.entry_type_id?.value;
    const companyAccountId = form.company_account_id?.value;
    const fromDate = form.from_date?.value;
    const toDate = form.to_date?.value;

    if (!entryTypeId) {
        showToast('Please select a Category / Row first.', 'error');
        return;
    }
    if (!companyAccountId) {
        showToast('Please select a Destination Bank.', 'error');
        return;
    }
    if (!fromDate || !toDate) {
        showToast('Please select both From and To dates.', 'error');
        return;
    }

    const payload = {
        shop_id: parseInt(shopId, 10),
        entry_type_id: parseInt(entryTypeId, 10),
        company_account_id: parseInt(companyAccountId, 10),
        from_date: fromDate,
        to_date: toDate,
    };

    button.disabled = true;
    button.textContent = 'Calculating Preview...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.historical-bank-collections.preview') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            data = { message: 'Server returned an invalid response (status ' + response.status + ').' };
        }

        if (!response.ok || !data.success) {
            const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Preview calculation failed');
            showToast(errorMsg, 'error');
            return;
        }

        currentHistoricalPreview = data.preview;
        renderHistoricalPreview(data.preview);
    } catch (error) {
        console.error('Historical fetch preview error:', error);
        showToast(error.message || 'Preview request failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Preview Historical Entries';
    }
}

function renderHistoricalPreview(p) {
    const container = document.getElementById('hist-preview-container');
    if (!container) return;
    container.classList.remove('hidden');

    const formatCurrency = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const shopName = p.shop?.name || 'Selected Shop';
    const entryTypeName = p.entry_type?.name || 'Selected Category';
    const bankName = p.company_account?.name || 'Selected Bank';
    const fromDate = p.from_date || '';
    const toDate = p.to_date || '';

    const sourceCount = Number(p.source_count || 0);
    const sourceAmount = Number(p.source_amount || 0);
    const eligibleCount = Number(p.eligible_count || 0);
    const eligibleAmount = Number(p.eligible_amount || 0);
    const alreadyLinkedCount = Number(p.already_linked_count || 0);
    const alreadyLinkedAmount = Number(p.already_linked_amount || 0);
    const differentBankCount = Number(p.different_bank_count || 0);
    const differentBankAmount = Number(p.different_bank_amount || 0);
    const reconciledCount = Number(p.reconciled_count || 0);
    const reconciledAmount = Number(p.reconciled_amount || 0);
    const voidCount = Number(p.void_count || 0);
    const voidAmount = Number(p.void_amount || 0);

    const sourceBaseAmount = Number(p.source_base_amount ?? p.source_amount ?? 0);
    const sourceAdjAmount = Number(p.source_adjustment_amount ?? 0);
    const sourceExpAmount = Number(p.source_expected_amount ?? p.source_amount ?? 0);
    const hasSourceAdj = sourceAdjAmount !== 0;

    const eligibleBaseAmount = Number(p.eligible_base_amount ?? p.eligible_amount ?? 0);
    const eligibleAdjAmount = Number(p.eligible_adjustment_amount ?? 0);
    const eligibleExpAmount = Number(p.eligible_expected_amount ?? p.eligible_amount ?? 0);
    const hasEligibleAdj = eligibleAdjAmount !== 0;

    let differentBankHtml = '';
    if (Array.isArray(p.different_banks_detail) && p.different_banks_detail.length > 0) {
        differentBankHtml = `<div class="mt-2 text-[11px] text-amber-700 font-semibold space-y-1">` +
            p.different_banks_detail.map(d => `<div>• ${d.bank_name || 'Bank'}: ${Number(d.count || 0)} txs (${formatCurrency(d.amount)})</div>`).join('') +
            `</div>`;
    }

    let duplicateWarningHtml = '';
    if (Number(p.duplicate_source_warnings_count || 0) > 0 && Array.isArray(p.duplicate_source_warnings_detail)) {
        duplicateWarningHtml = `
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4">
                <div class="flex items-center gap-2 text-rose-900 font-black text-xs">
                    <i data-lucide="alert-triangle" class="h-4 w-4 text-rose-600"></i>
                    <span>Duplicate Source Warnings (${p.duplicate_source_warnings_count} dates)</span>
                </div>
                <p class="mt-1 text-[11px] text-rose-700 font-semibold">Multiple active entries exist for the same date. These are never automatically combined.</p>
                <div class="mt-2 space-y-1 text-xs text-rose-950 font-bold">
                    ${p.duplicate_source_warnings_detail.map(d => `<div>• <strong>${d.business_date}</strong>: ${d.count} active entries (Total ${formatCurrency(d.total_amount)}) &mdash; IDs: ${(d.transaction_ids || []).join(', ')}</div>`).join('')}
                </div>
            </div>
        `;
    }

    let sameDateDiffHtml = '';
    if (Number(p.same_date_amount_differences_count || 0) > 0 && Array.isArray(p.same_date_amount_differences_detail)) {
        sameDateDiffHtml = `
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-center gap-2 text-amber-900 font-black text-xs">
                    <i data-lucide="alert-circle" class="h-4 w-4 text-amber-600"></i>
                    <span>Potential Same-Date Amount Differences (${p.same_date_amount_differences_count})</span>
                </div>
                <p class="mt-1 text-[11px] text-amber-700 font-semibold">Unmatched bank statement exists on the same date with a different amount (requires manual review in Reconciliation).</p>
                <div class="mt-2 space-y-1 text-xs text-amber-950 font-bold">
                    ${p.same_date_amount_differences_detail.map(d => `<div>• <strong>${d.business_date}</strong>: Expected ${formatCurrency(d.expected_amount)} vs Statement ${formatCurrency(d.statement_amount)} (Diff: ${formatCurrency(d.difference)}) &mdash; Ref: ${d.statement_reference || '—'}</div>`).join('')}
                </div>
            </div>
        `;
    }

    container.innerHTML = `
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Historical Fetch Preview</h3>
                    <p class="text-xs font-semibold text-slate-500">
                        ${shopName} • ${entryTypeName} &rarr; <span class="font-bold text-slate-900">${bankName}</span>
                        (${fromDate} &rarr; ${toDate})
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold text-slate-500">Total Found:</span>
                    <span class="text-sm font-black text-slate-950">${sourceCount} txs (${formatCurrency(sourceAmount)})</span>
                    ${hasSourceAdj ? `<div class="text-[10px] font-bold text-slate-500">Base: ${formatCurrency(sourceBaseAmount)} | Adj: ${sourceAdjAmount > 0 ? '+' : ''}${formatCurrency(sourceAdjAmount)} &rarr; Exp: ${formatCurrency(sourceExpAmount)}</div>` : ''}
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Eligible to Fetch</p>
                    <p class="mt-1 text-lg font-black text-emerald-950">${eligibleCount} <span class="text-xs font-bold text-emerald-800">txs</span></p>
                    <p class="text-xs font-bold text-emerald-700">${formatCurrency(eligibleAmount)}</p>
                    ${hasEligibleAdj ? `<p class="text-[9px] font-bold text-emerald-800">Base: ${formatCurrency(eligibleBaseAmount)} | Adj: ${eligibleAdjAmount > 0 ? '+' : ''}${formatCurrency(eligibleAdjAmount)} &rarr; Exp: ${formatCurrency(eligibleExpAmount)}</p>` : ''}
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Already ${bankName}</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${alreadyLinkedCount} <span class="text-xs font-bold text-slate-400">txs</span></p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(alreadyLinkedAmount)}</p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-800">Different Bank</p>
                    <p class="mt-1 text-lg font-black text-amber-950">${differentBankCount} <span class="text-xs font-bold text-amber-800">txs</span></p>
                    <p class="text-xs font-bold text-amber-700">${formatCurrency(differentBankAmount)}</p>
                    ${differentBankHtml}
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Reconciled (Locked)</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${reconciledCount} <span class="text-xs font-bold text-slate-400">txs</span></p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(reconciledAmount)}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Void / Excluded</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${voidCount} <span class="text-xs font-bold text-slate-400">txs</span></p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(voidAmount)}</p>
                </div>
            </div>

            ${duplicateWarningHtml}
            ${sameDateDiffHtml}

            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-slate-200 pt-4">
                <p class="text-xs font-bold text-slate-600">
                    ${eligibleCount > 0 ? `Ready to assign ${eligibleCount} entries to ${bankName}. Original sales dates will be retained.` : `No unassigned eligible transactions found for this period.`}
                </p>
                <button type="button"
                        id="btn-execute-hist"
                        onclick="executeHistoricalFetch()"
                        ${eligibleCount === 0 ? 'disabled' : ''}
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                    Fetch ${eligibleCount} Eligible Entries
                </button>
            </div>
        </div>
    `;

    if (window.lucide) { lucide.createIcons(); }
}

async function executeHistoricalFetch() {
    if (!currentHistoricalPreview || currentHistoricalPreview.eligible_count <= 0) return;

    const button = document.getElementById('btn-execute-hist');
    const container = document.getElementById('hist-preview-container');

    button.disabled = true;
    button.textContent = 'Fetching Entries...';

    const payload = {
        shop_id: currentHistoricalPreview.shop.id,
        entry_type_id: currentHistoricalPreview.entry_type.id,
        company_account_id: currentHistoricalPreview.company_account.id,
        from_date: currentHistoricalPreview.from_date,
        to_date: currentHistoricalPreview.to_date,
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.historical-bank-collections.fetch') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            data = { message: 'Server returned an invalid response (status ' + response.status + ').' };
        }

        if (!response.ok || !data.success) {
            const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Fetch execution failed');
            showToast(errorMsg, 'error');
            button.disabled = false;
            button.textContent = `Fetch ${currentHistoricalPreview.eligible_count} Eligible Entries`;
            return;
        }

        showToast(data.message, 'success');
        renderHistoricalFetchComplete(data.result);
    } catch (error) {
        console.error('Historical fetch execution error:', error);
        showToast(error.message || 'Fetch execution failed', 'error');
        button.disabled = false;
        button.textContent = `Fetch ${currentHistoricalPreview.eligible_count} Eligible Entries`;
    }
}

function renderHistoricalFetchComplete(res) {
    const container = document.getElementById('hist-preview-container');
    const formatCurrency = (n) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const reconUrl = `/admin/cashbook/finance/reconciliation?company_account_uuid=${res.company_account.public_uuid}`;

    container.innerHTML = `
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white">
                    <i data-lucide="check-circle" class="h-6 w-6"></i>
                </span>
                <div>
                    <h3 class="text-sm font-black text-slate-950">Historical Bank Collection Fetch Complete</h3>
                    <p class="text-xs font-semibold text-slate-600">
                        ${res.shop.name} • ${res.entry_type.name} &rarr; <span class="font-bold text-slate-950">${res.company_account.name}</span>
                        (${res.from_date} &rarr; ${res.to_date})
                    </p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-emerald-200 bg-white p-3 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Successfully Updated</p>
                    <p class="mt-1 text-lg font-black text-emerald-950">${res.updated_count} txs</p>
                    <p class="text-xs font-bold text-emerald-700">${formatCurrency(res.updated_amount)}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Already Linked</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${res.skipped.already_linked_count} txs</p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(res.skipped.already_linked_amount)}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Different Bank (Skipped)</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${res.skipped.different_bank_count} txs</p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(res.skipped.different_bank_amount)}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Reconciled / Locked</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${res.skipped.reconciled_count} txs</p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(res.skipped.reconciled_amount)}</p>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-emerald-100 pt-4">
                <a href="${reconUrl}" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-xs font-black text-white hover:bg-slate-800">
                    <i data-lucide="external-link" class="h-4 w-4"></i>
                    Open ${res.company_account.name} Reconciliation
                </a>
                <a href="/admin/cashbook/finance/reconciliation?company_account_uuid=${res.company_account.public_uuid}&month=${res.from_date.substring(0, 7)}&direction=in" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-700">
                    <i data-lucide="sparkles" class="h-4 w-4"></i>
                    Preview Auto Match
                </a>
                <button type="button" onclick="document.getElementById('hist-preview-container').classList.add('hidden')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Preview Another Period
                </button>
            </div>
        </div>
    `;

    if (window.lucide) { lucide.createIcons(); }
}

// ─── Bank Settlement Adjustment Rules JS ───────────────────────────────────

const allBankAdjustmentRules = @json($bankAdjustmentRules ?? []);
let activeAdjShopId = null;
let activeAdjEntryTypeId = null;

function openBankAdjModal(shopId, entryTypeId, entryTypeName) {
    activeAdjShopId = shopId;
    activeAdjEntryTypeId = entryTypeId;

    document.getElementById('bank-adj-shop-id').value = shopId;
    document.getElementById('bank-adj-entry-type-id').value = entryTypeId;
    document.getElementById('bank-adj-modal-subtitle').textContent = `Shop: {{ $currentShop->name }} • Category: ${entryTypeName}`;
    document.getElementById('bank-adj-label').value = '';

    renderBankAdjRulesList();
    document.getElementById('bank-adj-modal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeBankAdjModal() {
    document.getElementById('bank-adj-modal').classList.add('hidden');
}

function renderBankAdjRulesList() {
    const listContainer = document.getElementById('bank-adj-rules-list');
    const rules = (allBankAdjustmentRules[activeAdjEntryTypeId] || []);

    if (rules.length === 0) {
        listContainer.innerHTML = `<div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-400">No adjustment rules configured yet. Default is direct collection amount.</div>`;
        return;
    }

    listContainer.innerHTML = rules.map(r => `
        <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-900">${r.label}</span>
                <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase ${r.direction === 'plus' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">${r.direction}</span>
                ${!r.enabled ? '<span class="rounded bg-slate-200 px-1.5 py-0.5 text-[9px] font-bold text-slate-600">Disabled</span>' : ''}
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleBankAdjRule(${r.id}, ${r.enabled ? 0 : 1})" class="text-[11px] font-bold ${r.enabled ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900'}">
                    ${r.enabled ? 'Disable' : 'Enable'}
                </button>
                <button type="button" onclick="deleteBankAdjRule(${r.id})" class="text-[11px] font-bold text-rose-600 hover:text-rose-800">
                    Delete
                </button>
            </div>
        </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

async function submitAddBankAdjRule(event) {
    event.preventDefault();
    const btn = document.getElementById('btn-save-adj-rule');
    const label = document.getElementById('bank-adj-label').value.trim();
    const direction = document.getElementById('bank-adj-direction').value;

    if (!label) return;

    btn.disabled = true;
    btn.textContent = 'Saving...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.bank-adjustment-rules.save') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: activeAdjShopId,
                entry_type_id: activeAdjEntryTypeId,
                label,
                direction,
                enabled: 1,
            }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            showToast(data.message || 'Failed to save rule', 'error');
            return;
        }

        allBankAdjustmentRules[activeAdjEntryTypeId] = data.rules;
        renderBankAdjRulesList();
        document.getElementById('bank-adj-label').value = '';
        showToast('Rule saved successfully.', 'success');
        updateAdjRuleButtonCount(activeAdjEntryTypeId, data.rules.length);
    } catch (err) {
        showToast(err.message || 'Failed to save rule', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '+ Add Rule';
    }
}

async function toggleBankAdjRule(ruleId, newEnabledState) {
    const rules = allBankAdjustmentRules[activeAdjEntryTypeId] || [];
    const rule = rules.find(r => r.id === ruleId);
    if (!rule) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.bank-adjustment-rules.save') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                id: rule.id,
                shop_id: activeAdjShopId,
                entry_type_id: activeAdjEntryTypeId,
                label: rule.label,
                direction: rule.direction,
                enabled: newEnabledState,
            }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            showToast(data.message || 'Failed to update rule', 'error');
            return;
        }

        allBankAdjustmentRules[activeAdjEntryTypeId] = data.rules;
        renderBankAdjRulesList();
        showToast('Rule updated.', 'success');
    } catch (err) {
        showToast(err.message || 'Failed to update rule', 'error');
    }
}

async function deleteBankAdjRule(ruleId) {
    if (!confirm('Are you sure you want to delete this adjustment rule?')) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch(`/admin/cashbook/api/shop-settings/bank-adjustment-rules/${ruleId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            showToast(data.message || 'Failed to delete rule', 'error');
            return;
        }

        allBankAdjustmentRules[activeAdjEntryTypeId] = data.rules;
        renderBankAdjRulesList();
        showToast('Rule deleted.', 'success');
        updateAdjRuleButtonCount(activeAdjEntryTypeId, data.rules.length);
    } catch (err) {
        showToast(err.message || 'Failed to delete rule', 'error');
    }
}

function updateAdjRuleButtonCount(entryTypeId, count) {
    const btn = document.getElementById(`btn-adj-rules-${entryTypeId}`);
    if (btn) {
        btn.textContent = `⚖️ Adj Rules (${count})`;
        if (count > 0) {
            btn.className = 'inline-flex items-center gap-1 text-[9px] font-extrabold px-1.5 py-0.5 rounded border transition bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100';
        } else {
            btn.className = 'inline-flex items-center gap-1 text-[9px] font-extrabold px-1.5 py-0.5 rounded border transition bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200';
        }
    }
}
</script>
@endpush
