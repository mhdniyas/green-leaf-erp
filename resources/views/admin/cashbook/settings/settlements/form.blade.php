@extends('admin.cashbook.layouts.app')
@section('title', ($relation ? 'Edit' : 'Create').' Settlement - '.$currentShop->name)
@section('content')
@php
    $shopKey = $currentShop->slug ?: $currentShop->shop_id;
    $initialItems = old('items', $relation?->items->map(fn ($item) => [
        'setting_id' => $item->shop_ledger_entry_setting_id ? (string) $item->shop_ledger_entry_setting_id : '',
        'header_group_id' => $item->header_group_id ? (string) $item->header_group_id : '',
        'header_mode' => $item->header_mode ?? 'all_categories',
        'role' => $item->role,
    ])->all() ?: [['setting_id' => '', 'header_group_id' => '', 'header_mode' => 'all_categories', 'role' => 'add']]);
    $categoryNames = $settings->mapWithKeys(fn ($setting) => [(string) $setting->id => $setting->displayName()])->all();
    $headerGroupNames = $headerGroups->mapWithKeys(fn ($hg) => [(string) $hg->id => $hg->name])->all();
    $headerGroupDetails = $headerGroups->mapWithKeys(fn ($hg) => [(string) $hg->id => [
        'id' => $hg->id,
        'name' => $hg->name,
        'product_tagging_enabled' => (bool) $hg->product_tagging_enabled,
        'products' => $hg->allowedProducts->map(fn ($p) => $p->name)->all(),
    ]])->all();
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $shopKey) }}" class="inline-flex py-2 text-sm font-bold text-slate-600 hover:text-indigo-700">&larr; Settlements</a>
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">{{ $currentShop->name }}</p>
        <h1 class="mt-1 text-3xl font-extrabold text-slate-950">{{ $relation ? 'Edit Settlement' : 'Create Settlement' }}</h1>
        <p class="mt-2 text-sm text-slate-600">Add or subtract any categories or header groups to calculate one settlement result.</p>
    </div>
    <form method="POST" action="{{ $relation ? route('admin.cashbook.settings.shop.settlements.update', [$shopKey, $relation->public_uuid]) : route('admin.cashbook.settings.shop.settlements.store', $shopKey) }}"
          class="space-y-6" x-data="{
              rows: @js(array_values($initialItems)),
              categoryNames: @js($categoryNames),
              headerNames: @js($headerGroupNames),
              headerDetails: @js($headerGroupDetails),
              settlementName: @js(old('name', $relation?->name ?? '')),
              templates: @js($importableSettlements ?? []),
              selectedTemplateId: '',
              saving: false,
              errors: @js($errors->messages()),
              getSelectionKey(row) {
                  if (row.header_group_id) return 'header:' + row.header_group_id;
                  if (row.setting_id) return 'setting:' + row.setting_id;
                  return '';
              },
              onSelectionChange(row, val) {
                  if (val.startsWith('header:')) {
                      row.header_group_id = val.replace('header:', '');
                      row.setting_id = '';
                  } else if (val.startsWith('setting:')) {
                      row.setting_id = val.replace('setting:', '');
                      row.header_group_id = '';
                  } else {
                      row.setting_id = '';
                      row.header_group_id = '';
                  }
              },
              getRowName(row) {
                  if (row.header_group_id && this.headerNames[row.header_group_id]) {
                      const hName = 'Header: ' + this.headerNames[row.header_group_id];
                      return row.header_mode === 'tagged_products_only' ? hName + ' (Tagged Products Only)' : hName + ' (All Categories)';
                  }
                  if (row.setting_id && this.categoryNames[row.setting_id]) {
                      return this.categoryNames[row.setting_id];
                  }
                  return '';
              },
              importTemplate() {
                  const t = this.templates.find(item => String(item.id) === String(this.selectedTemplateId));
                  if (!t || !t.items || t.items.length === 0) return;
                  if (this.rows.length > 0 && this.rows.some(r => r.setting_id || r.header_group_id) && !confirm('Replace current formula rows with items from ' + t.name + '?')) return;
                  this.rows = t.items.map(i => ({ setting_id: String(i.setting_id || ''), header_group_id: String(i.header_group_id || ''), header_mode: i.header_mode || 'all_categories', role: i.role || 'add' }));
                  if (!this.settlementName) {
                      this.settlementName = t.name;
                  }
                  this.selectedTemplateId = '';
              }
          }" @submit="saving = true">
        @csrf
        @if($relation) @method('PUT') @endif
        @if($errors->any())
            <div role="alert" tabindex="-1" x-init="$el.focus()" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Please correct the settlement:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->messages() as $field => $messages)
                        @php($errorTarget = $field === 'name' ? 'settlement-name' : (preg_match('/^items\.(\d+)\.(setting_id|header_group_id|role)$/', $field, $matches) ? ($matches[2] === 'role' ? 'role-' : 'target-').$matches[1] : 'formula-heading'))
                        @foreach($messages as $message)<li><a class="underline" href="#{{ $errorTarget }}">{{ $message }}</a></li>@endforeach
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div>
                <label for="settlement-name" class="block text-sm font-bold text-slate-800">Settlement name</label>
                <input id="settlement-name" name="name" x-model="settlementName" required maxlength="80" placeholder="e.g. Company Payable" aria-describedby="name-error" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name')<p id="name-error" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <input type="hidden" name="enabled" value="0">
                <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-800">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $relation?->enabled ?? true)) class="h-5 w-5 rounded border-slate-300 text-indigo-700">
                    Show this settlement in the summary
                </label>
            </div>
            <div class="border-t border-slate-100 pt-4">
                <input type="hidden" name="is_company_payable" value="0">
                <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-900">
                    <input type="checkbox" name="is_company_payable" value="1" @checked(old('is_company_payable', $relation?->is_company_payable ?? false)) class="h-5 w-5 rounded border-slate-300 text-indigo-700">
                    <span>Mark as this shop's <strong>Company Payable</strong> settlement</span>
                </label>
                <p class="mt-1 text-xs text-slate-500 ml-8">When selected, this settlement dynamically drives the shop's payable balance and verified payments deduction.</p>
            </div>
        </div>
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="formula-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="formula-heading" class="text-lg font-extrabold text-slate-950">Calculation</h2>
                    <p class="mt-1 text-sm text-slate-600">Select an entire Header Group or an individual Category to add or subtract in the formula.</p>
                </div>
                <template x-if="templates.length > 0">
                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-indigo-100 bg-indigo-50/50 p-2">
                        <select x-model="selectedTemplateId" @change="importTemplate()" class="rounded-lg border border-indigo-200 bg-white px-2.5 py-1.5 text-xs font-bold text-indigo-900 focus:border-indigo-500">
                            <option value="">Import formula from existing settlement...</option>
                            <template x-for="t in templates" :key="t.id">
                                <option :value="t.id" x-text="t.name + (t.is_same_shop ? ' (This shop)' : ' (' + t.shop_name + ')')"></option>
                            </template>
                        </select>
                    </div>
                </template>
            </div>
            <div class="space-y-3">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 sm:grid-cols-[130px_1fr_auto] sm:items-start">
                        <div>
                            <label :for="'role-' + index" class="block text-xs font-bold text-slate-700">Operation</label>
                            <select :aria-describedby="'role-error-' + index" :id="'role-' + index" :name="'items[' + index + '][role]'" x-model="row.role" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm font-semibold">
                                <option value="add">+ Add</option><option value="subtract">− Subtract</option>
                            </select>
                            <p :id="'role-error-' + index" x-show="errors['items.' + index + '.role']" x-text="(errors['items.' + index + '.role'] || []).join(' ')" class="mt-1 text-xs text-rose-700"></p>
                        </div>
                        <div class="space-y-2">
                            <label :for="'target-' + index" class="block text-xs font-bold text-slate-700">Header Group or Category</label>
                            <input type="hidden" :name="'items[' + index + '][setting_id]'" :value="row.setting_id">
                            <input type="hidden" :name="'items[' + index + '][header_group_id]'" :value="row.header_group_id">
                            <select :id="'target-' + index" :value="getSelectionKey(row)" @change="onSelectionChange(row, $event.target.value)" required class="w-full rounded-lg border border-slate-300 bg-white p-3 text-sm font-medium focus:border-indigo-500">
                                <option value="">Choose a Header Group or Category...</option>
                                @if($headerGroups->isNotEmpty())
                                    <optgroup label="📁 ENTIRE HEADER GROUPS">
                                        @foreach($headerGroups as $hg)
                                            <option value="header:{{ $hg->id }}">Header: {{ $hg->name }}{{ $hg->product_tagging_enabled ? ' 🏷️ (Product Tagged)' : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @foreach($settings->groupBy(fn ($setting) => $setting->headerGroup?->name ?? ucfirst($setting->entryType?->category ?? 'Other')) as $group => $groupSettings)
                                    <optgroup label="Category: {{ $group }}">
                                        @foreach($groupSettings as $setting)
                                            <option value="setting:{{ $setting->id }}">{{ $setting->displayName() }}{{ $setting->enabled ? '' : ' (entry disabled)' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>

                            <!-- HEADER CALCULATION MODE SELECTION -->
                            <template x-if="row.header_group_id">
                                <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3 text-xs space-y-2">
                                    <p class="font-bold text-indigo-900 flex items-center gap-1.5">
                                        <span>⚙️ Header Calculation Mode for <span x-text="headerNames[row.header_group_id]"></span>:</span>
                                    </p>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="inline-flex items-center gap-1.5 font-semibold text-slate-800 cursor-pointer">
                                            <input type="radio" :name="'items[' + index + '][header_mode]'" value="all_categories" x-model="row.header_mode" class="text-indigo-700">
                                            <span>All Categories under Header</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1.5 font-semibold text-slate-800 cursor-pointer">
                                            <input type="radio" :name="'items[' + index + '][header_mode]'" value="tagged_products_only" x-model="row.header_mode" class="text-indigo-700">
                                            <span>Tagged Products Total Only</span>
                                        </label>
                                    </div>
                                    <template x-if="headerDetails[row.header_group_id] && headerDetails[row.header_group_id].product_tagging_enabled">
                                        <div class="pt-1 text-[11px] text-indigo-800">
                                            <span class="font-bold">🏷️ Tagged Products:</span>
                                            <span x-text="headerDetails[row.header_group_id].products.length > 0 ? headerDetails[row.header_group_id].products.join(', ') : 'All catalog products enabled'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="rows.splice(index, 1)" :aria-label="'Remove item ' + (index + 1)" class="mt-6 rounded-lg border border-slate-300 px-3 py-3 text-sm font-bold text-rose-700 hover:bg-rose-50">Remove</button>
                    </div>
                </template>
            </div>
            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="button" @click="rows.push({setting_id: '', header_group_id: '', header_mode: 'all_categories', role: 'add'})" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-bold text-indigo-800 hover:bg-indigo-100">+ Add Formula Row</button>
                <a href="{{ route('admin.cashbook.settings.shop', $shopKey) }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-1.5">
                    <span>⚙️ Manage Headers & Product Tagging</span> ↗
                </a>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4" aria-live="polite">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-800">Formula preview</p>
                <p class="mt-2 break-words text-sm font-semibold text-slate-900" x-text="rows.filter(row => getRowName(row)).map((row, i) => (row.role === 'subtract' ? '− ' : (i ? '+ ' : '')) + getRowName(row)).join(' ') || 'Choose headers or categories to build the formula'"></p>
                <p class="mt-2 text-xs text-indigo-800">The calculated result contributes to <span class="font-bold" x-text="settlementName || 'this settlement'"></span>.</p>
            </div>
        </section>
        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $shopKey) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-100">Cancel</a>
            <button type="submit" :disabled="saving || rows.length === 0" class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-bold text-white hover:bg-indigo-800 disabled:opacity-50" x-text="saving ? 'Saving…' : '{{ $relation ? 'Save Changes' : 'Create Settlement' }}'"></button>
        </div>
    </form>
</div>
@endsection
