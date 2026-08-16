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
                <table class="w-full min-w-[78rem] lg:table-fixed text-[11px] lg:text-[10px]">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="w-[16%] px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Row</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">On</th>
                            <th class="w-[9%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Paid From</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Sales</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Income</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Expense</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">P&L</th>
                            <th class="w-[9%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Settlement</th>
                            <th class="w-[8%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Petty</th>
                            <th class="w-[9%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Company</th>
                            <th class="w-[8%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Payable</th>
                            <th class="w-[4%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Auto Child</th>
                            <th class="w-[11%] px-2 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Child Row</th>
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
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
</script>
@endpush
