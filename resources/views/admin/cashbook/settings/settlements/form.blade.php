@extends('admin.cashbook.layouts.app')
@section('title', ($relation ? 'Edit' : 'Create').' Settlement - '.$currentShop->name)
@section('content')
@php
    $shopKey = $currentShop->slug ?: $currentShop->shop_id;
    $initialItems = old('items', $relation?->items->map(fn ($item) => ['setting_id' => (string) $item->shop_ledger_entry_setting_id, 'role' => $item->role])->all() ?: [['setting_id' => '', 'role' => 'add']]);
    $categoryNames = $settings->mapWithKeys(fn ($setting) => [$setting->id => $setting->displayName()])->all();
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $shopKey) }}" class="inline-flex py-2 text-sm font-bold text-slate-600 hover:text-indigo-700">&larr; Settlements</a>
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">{{ $currentShop->name }}</p>
        <h1 class="mt-1 text-3xl font-extrabold text-slate-950">{{ $relation ? 'Edit Settlement' : 'Create Settlement' }}</h1>
        <p class="mt-2 text-sm text-slate-600">Add or subtract any categories to calculate one settlement result.</p>
    </div>
    <form method="POST" action="{{ $relation ? route('admin.cashbook.settings.shop.settlements.update', [$shopKey, $relation->public_uuid]) : route('admin.cashbook.settings.shop.settlements.store', $shopKey) }}"
          class="space-y-6" x-data="{
              rows: @js(array_values($initialItems)),
              names: @js($categoryNames),
              settlementName: @js(old('name', $relation?->name ?? '')),
              templates: @js($importableSettlements ?? []),
              selectedTemplateId: '',
              saving: false,
              errors: @js($errors->messages()),
              importTemplate() {
                  const t = this.templates.find(item => String(item.id) === String(this.selectedTemplateId));
                  if (!t || !t.items || t.items.length === 0) return;
                  if (this.rows.length > 0 && this.rows.some(r => r.setting_id) && !confirm('Replace current formula rows with items from ' + t.name + '?')) return;
                  this.rows = t.items.map(i => ({ setting_id: String(i.setting_id), role: i.role || 'add' }));
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
                        @php($errorTarget = $field === 'name' ? 'settlement-name' : (preg_match('/^items\.(\d+)\.(setting_id|role)$/', $field, $matches) ? ($matches[2] === 'role' ? 'role-' : 'category-').$matches[1] : 'formula-heading'))
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
        </div>
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="formula-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="formula-heading" class="text-lg font-extrabold text-slate-950">Calculation</h2>
                    <p class="mt-1 text-sm text-slate-600">For Category A − Category B + Category C, add A, subtract B, then add C. Categories are grouped below to help you find them.</p>
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
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[130px_1fr_auto] sm:items-end">
                        <div>
                            <label :for="'role-' + index" class="block text-xs font-bold text-slate-700">Operation</label>
                            <select :aria-describedby="'role-error-' + index" :aria-invalid="Boolean(errors['items.' + index + '.role'])" :id="'role-' + index" :name="'items[' + index + '][role]'" x-model="row.role" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm">
                                <option value="add">+ Add</option><option value="subtract">− Subtract</option>
                            </select>
                            <p :id="'role-error-' + index" x-show="errors['items.' + index + '.role']" x-text="(errors['items.' + index + '.role'] || []).join(' ')" class="mt-1 text-xs text-rose-700"></p>
                        </div>
                        <div>
                            <label :for="'category-' + index" class="block text-xs font-bold text-slate-700">Category</label>
                            <select :aria-describedby="'category-error-' + index" :aria-invalid="Boolean(errors['items.' + index + '.setting_id'])" :id="'category-' + index" :name="'items[' + index + '][setting_id]'" x-model="row.setting_id" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm">
                                <option value="">Choose a category</option>
                                @foreach($settings->groupBy(fn ($setting) => $setting->headerGroup?->name ?? ucfirst($setting->entryType?->category ?? 'Other')) as $group => $groupSettings)
                                    <optgroup label="{{ $group }}">
                                        @foreach($groupSettings as $setting)
                                            <option value="{{ $setting->id }}">{{ $setting->displayName() }}{{ $setting->enabled ? '' : ' (entry disabled)' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p :id="'category-error-' + index" x-show="errors['items.' + index + '.setting_id']" x-text="(errors['items.' + index + '.setting_id'] || []).join(' ')" class="mt-1 text-xs text-rose-700"></p>
                        </div>
                        <button type="button" @click="rows.splice(index, 1)" :aria-label="'Remove category ' + (index + 1)" class="rounded-lg border border-slate-300 px-3 py-3 text-sm font-bold text-rose-700 hover:bg-rose-50">Remove</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="rows.push({setting_id: '', role: 'add'})" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-bold text-indigo-800 hover:bg-indigo-100">+ Add Category</button>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4" aria-live="polite">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-800">Formula preview</p>
                <p class="mt-2 break-words text-sm font-semibold text-slate-900" x-text="rows.filter(row => names[row.setting_id]).map((row, i) => (row.role === 'subtract' ? '− ' : (i ? '+ ' : '')) + names[row.setting_id]).join(' ') || 'Choose categories to build the formula'"></p>
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
