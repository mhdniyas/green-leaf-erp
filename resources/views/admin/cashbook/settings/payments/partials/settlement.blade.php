<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Settlement Calculation Engine</span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">SETTLEMENT SETTINGS</h2>
            <p class="text-xs text-slate-500 mt-0.5">Define periodic settlement relations, composition formulas (+ add, - subtract), and authoritative Shop ↔ Company net dues.</p>
        </div>
        <div>
            <button type="button"
                    data-payment-modal-open="settlement"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="edit-3" class="h-4 w-4"></i>
                <span>Edit Settings</span>
            </button>
        </div>
    </div>

    {{-- Duplicate Warnings if any --}}
    @if(!empty($settlementData['duplicate_warnings']))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 space-y-2 shadow-xs">
            <div class="flex items-center gap-2 text-amber-900 text-xs font-black">
                <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0"></i>
                <span>Potential Duplicate Calculation Detected:</span>
            </div>
            <ul class="list-disc list-inside text-xs text-amber-800 space-y-1 pl-2">
                @foreach($settlementData['duplicate_warnings'] as $relId => $warnings)
                    @foreach($warnings as $warn)
                        <li>{{ $warn }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 1. CURRENT SETUP --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Active Setup</span>
                <h3 class="text-sm font-black text-slate-900">Configured Settlement Relations & Formula Sources</h3>
            </div>
            <button type="button"
                    data-payment-modal-open="settlement"
                    class="text-xs font-black text-emerald-700 hover:text-emerald-900 flex items-center gap-1 cursor-pointer">
                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                <span>Modify Formulas</span>
            </button>
        </div>

        <div class="space-y-3">
            @forelse($settlementData['relations'] as $rel)
                <div class="rounded-xl border border-slate-200 p-4 bg-slate-50/40 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-sm font-black text-slate-900">{{ $rel->name }}</span>
                            <span class="rounded bg-slate-200/70 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700">
                                {{ strtoupper($rel->relation_type ?? 'Standard') }}
                            </span>
                            @if($rel->is_company_payable)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                    Company Payable
                                </span>
                            @endif
                            @if($rel->is_payment_paid)
                                <span class="rounded bg-violet-100 px-2 py-0.5 text-[10px] font-black uppercase text-violet-800">
                                    Payment Paid
                                </span>
                            @endif
                        </div>
                        <span class="text-xs text-slate-400 font-bold">{{ $rel->items->count() }} Formula {{ Str::plural('Source', $rel->items->count()) }}</span>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        @forelse($rel->items as $item)
                            @php
                                $isAdd = ($item->role ?? 'add') === 'add';
                                $label = 'Unknown';
                                if ($item->header_group_id && $item->headerGroup) {
                                    $label = '[Header] ' . $item->headerGroup->name;
                                } elseif ($item->source_settlement_id && $item->sourceSettlement) {
                                    $label = '[Settlement] ' . $item->sourceSettlement->name;
                                } elseif ($item->setting) {
                                    $label = '[Category] ' . ($item->setting->entryType?->name ?? $item->setting->entry_name);
                                }
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold {{ $isAdd ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                <span class="font-black text-[13px]">{{ $isAdd ? '+' : '–' }}</span>
                                <span>{{ $label }}</span>
                            </span>
                        @empty
                            <span class="text-xs text-slate-400 italic">No formula sources configured for this relation.</span>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="text-center py-6 text-xs text-slate-400">
                    No settlement relations configured for this shop.
                </div>
            @endforelse
        </div>
    </div>

    {{-- 2. OUTPUT SECTION (Financial Results) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Financial Impact</span>
                <h3 class="text-sm font-black text-slate-900">Calculated Settlement Position ({{ \Carbon\Carbon::parse($startDate)->format('M Y') }})</h3>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-black {{ $settlementData['net_payable_amount'] >= 0 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                {{ $settlementData['owes_text'] }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach($settlementData['relations'] as $rel)
                @php
                    $calc = $settlementData['settlement_summary']['relations'][$rel->id] ?? null;
                    $net = (float) ($calc['netSettlement'] ?? 0.0);
                    $addTotal = (float) ($calc['addTotal'] ?? 0.0);
                    $subTotal = (float) ($calc['subtractTotal'] ?? 0.0);
                @endphp
                <div class="rounded-xl border {{ $rel->is_company_payable ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200 bg-slate-50/40' }} p-4 space-y-2 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-slate-800 truncate">{{ $rel->name }}</span>
                            @if($rel->is_company_payable)
                                <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[9px] font-black uppercase">Payable</span>
                            @endif
                        </div>
                        <div class="text-lg font-black mt-2 {{ $net >= 0 ? 'text-slate-900' : 'text-rose-700' }}">
                            ₹{{ number_format($net, 2) }}
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 flex items-center justify-between border-t border-slate-100 pt-2 mt-2">
                        <span class="text-emerald-700 font-bold">+ ₹{{ number_format($addTotal, 2) }}</span>
                        <span class="text-rose-700 font-bold">- ₹{{ number_format($subTotal, 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Bottom Edit Button --}}
    <div class="flex justify-end pt-2">
        <button type="button"
                data-payment-modal-open="settlement"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-800 transition cursor-pointer shadow-sm active:scale-98">
            <i data-lucide="edit-3" class="h-4 w-4"></i>
            <span>Edit Settings</span>
        </button>
    </div>
</div>

{{-- MODAL: SETTLEMENT RELATIONS & FORMULAS --}}
<x-cashbook.payment-modal
    modalId="settlement"
    title="Settlement Relations & Formula Editor"
    subtitle="Settlement Configuration"
    shopName="{{ $currentShop->name }}"
    period="{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}"
    maxWidth="max-w-4xl"
    saveFunction="saveSettlementModalItems()"
    saveBtnId="modal-save-settlement-btn"
    saveBtnText="Save Formula Items">

    <div class="space-y-5">
        @php
            $relOptions = $settlementData['relations']->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'subtitle' => 'Type: ' . strtoupper($r->relation_type ?? 'standard') . ' • ' . $r->items->count() . ' items',
            ])->all();
            $defaultRelId = $settlementData['relations']->first()?->id ?? null;
        @endphp

        {{-- Relation Selector --}}
        <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Select Settlement Relation to Edit</label>
            <x-cashbook.custom-select
                name="modal_active_settlement_relation"
                id="modal_active_settlement_relation"
                :options="$relOptions"
                :selected="$defaultRelId"
                placeholder="Choose relation..."
                :searchable="true"
            />
        </div>

        {{-- Active Items List --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <span class="text-xs font-black text-slate-900">Current Formula Sources</span>
                <span id="settlement-modal-item-count" class="text-[10px] font-bold text-slate-400">0 sources</span>
            </div>

            {{-- Container for items rendered via JS --}}
            <div id="settlement-modal-items-list" class="space-y-2 min-h-[60px]">
                {{-- Dynamic list --}}
            </div>

            {{-- Duplicate Warning banner inside modal --}}
            <div id="settlement-modal-duplicate-warning" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 flex items-start gap-2">
                <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
                <div>
                    <strong class="font-black text-amber-950">Duplicate Source Warning:</strong>
                    <p id="settlement-modal-duplicate-msg" class="text-[11px] text-amber-800 mt-0.5"></p>
                </div>
            </div>
        </div>

        {{-- Add New Source Block --}}
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/30 p-4 space-y-3">
            <span class="text-xs font-black text-emerald-950 block">Add Source to Formula</span>
            
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                {{-- Role --}}
                <div class="sm:col-span-3">
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Role</label>
                    <x-cashbook.custom-select
                        name="new_item_role"
                        id="new_item_role"
                        :options="[
                            ['value' => 'add', 'label' => '+ Add (Credit)'],
                            ['value' => 'subtract', 'label' => '– Subtract (Debit)']
                        ]"
                        selected="add"
                        :searchable="false"
                    />
                </div>

                {{-- Source Type --}}
                <div class="sm:col-span-3">
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Source Type</label>
                    <x-cashbook.custom-select
                        name="new_item_type"
                        id="new_item_type"
                        :options="[
                            ['value' => 'header', 'label' => 'Header Group'],
                            ['value' => 'category', 'label' => 'Single Category'],
                            ['value' => 'settlement', 'label' => 'Settlement Relation']
                        ]"
                        selected="header"
                        :searchable="false"
                    />
                </div>

                {{-- Source Item --}}
                <div class="sm:col-span-4" id="new_item_id_container">
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Select Source</label>
                    {{-- Dynamically populated based on source type --}}
                    <div id="new_item_select_slot">
                        {{-- Injected by JS --}}
                    </div>
                </div>

                {{-- Add Button --}}
                <div class="sm:col-span-2">
                    <button type="button"
                            onclick="addSettlementSourceFromModal()"
                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-700 px-3 py-2.5 text-xs font-black text-white hover:bg-emerald-800 transition cursor-pointer shadow-sm active:scale-98">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span>Add</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-cashbook.payment-modal>

<script>
const settlementRelationsData = @json($settlementData['relations']);
const settlementAvailableHeaders = @json($settlementData['headers']);
const settlementAvailableSettings = @json($settlementData['settings']);

// In-memory working copy of items per relation: { [relId]: [{type, id, role, label}] }
let settlementWorkingItems = {};

function initSettlementWorkingState() {
    settlementWorkingItems = {};
    settlementRelationsData.forEach(rel => {
        settlementWorkingItems[rel.id] = (rel.items || []).map(item => {
            let itemType = 'category';
            let itemId = item.shop_ledger_entry_setting_id;
            let itemLabel = item.setting ? (item.setting.entry_type ? item.setting.entry_type.name : item.setting.entry_name) : 'Category #' + itemId;

            if (item.header_group_id) {
                itemType = 'header';
                itemId = item.header_group_id;
                itemLabel = item.header_group ? item.header_group.name : 'Header #' + itemId;
            } else if (item.source_settlement_id) {
                itemType = 'settlement';
                itemId = item.source_settlement_id;
                itemLabel = item.source_settlement ? item.source_settlement.name : 'Settlement #' + itemId;
            }

            return {
                type: itemType,
                id: parseInt(itemId, 10),
                role: item.role || 'add',
                label: itemLabel
            };
        });
    });
}

function getActiveSettlementRelationId() {
    const input = document.getElementById('modal_active_settlement_relation');
    return input && input.value ? parseInt(input.value, 10) : (settlementRelationsData[0]?.id || null);
}

function renderSettlementModalItems() {
    const relId = getActiveSettlementRelationId();
    const container = document.getElementById('settlement-modal-items-list');
    const countBadge = document.getElementById('settlement-modal-item-count');
    if (!container || !relId) return;

    const items = settlementWorkingItems[relId] || [];
    countBadge.textContent = items.length + ' source' + (items.length === 1 ? '' : 's');

    if (items.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-xs text-slate-400 italic">No sources in this formula yet. Use the form below to add sources.</div>';
        checkSettlementDuplicates(relId);
        return;
    }

    let html = '';
    items.forEach((item, index) => {
        const isAdd = item.role === 'add';
        const typeBadge = item.type === 'header' ? 'Header' : (item.type === 'settlement' ? 'Settlement' : 'Category');
        html += `
            <div class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 text-xs">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-lg font-black text-xs ${isAdd ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
                        ${isAdd ? '+' : '–'}
                    </span>
                    <div>
                        <div class="font-bold text-slate-900">${item.label}</div>
                        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-wider">${typeBadge}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            onclick="toggleSettlementItemRole(${relId}, ${index})"
                            class="px-2 py-1 rounded-lg text-[10px] font-bold border ${isAdd ? 'border-emerald-200 text-emerald-800 hover:bg-emerald-100' : 'border-rose-200 text-rose-800 hover:bg-rose-100'} transition cursor-pointer">
                        Switch to ${isAdd ? '– Subtract' : '+ Add'}
                    </button>
                    <button type="button"
                            onclick="removeSettlementModalItem(${relId}, ${index})"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-700 hover:bg-rose-50 transition cursor-pointer"
                            title="Remove Source">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    if (window.lucide) window.lucide.createIcons();
    checkSettlementDuplicates(relId);
}

function toggleSettlementItemRole(relId, index) {
    if (settlementWorkingItems[relId] && settlementWorkingItems[relId][index]) {
        const current = settlementWorkingItems[relId][index].role;
        settlementWorkingItems[relId][index].role = current === 'add' ? 'subtract' : 'add';
        renderSettlementModalItems();
    }
}

function removeSettlementModalItem(relId, index) {
    if (settlementWorkingItems[relId]) {
        settlementWorkingItems[relId].splice(index, 1);
        renderSettlementModalItems();
    }
}

function renderNewItemSelectSlot(type) {
    const slot = document.getElementById('new_item_select_slot');
    if (!slot) return;

    let options = [];
    const relId = getActiveSettlementRelationId();

    if (type === 'header') {
        options = settlementAvailableHeaders.map(h => ({
            id: h.id,
            name: h.name,
            subtitle: (h.code || '') + ' (Header Group)'
        }));
    } else if (type === 'settlement') {
        options = settlementRelationsData
            .filter(r => r.id !== relId)
            .map(r => ({
                id: r.id,
                name: r.name,
                subtitle: 'Relation (' + (r.relation_type || 'standard') + ')'
            }));
    } else {
        options = settlementAvailableSettings.map(s => ({
            id: s.id,
            name: s.entry_type ? s.entry_type.name : (s.display_name || s.entry_name),
            subtitle: (s.header_group ? s.header_group.name : 'No Header')
        }));
    }

    const defaultVal = options.length > 0 ? options[0].id : '';
    const defaultLabel = options.length > 0 ? options[0].name : 'Select item...';

    // Build custom-select markup dynamically
    slot.innerHTML = `
        <div class="custom-select-wrapper relative w-full" id="new_source_item_id_wrapper" data-custom-select>
            <input type="hidden" name="new_source_item_id" id="new_source_item_id" value="${defaultVal}">
            <button type="button" class="custom-select-trigger flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-800 shadow-2xs hover:border-slate-400 focus:border-violet-600 cursor-pointer">
                <span class="custom-select-label truncate text-slate-900 font-bold">${defaultLabel}</span>
                <i data-lucide="chevron-down" class="custom-select-chevron h-4 w-4 shrink-0 text-slate-400"></i>
            </button>
            <div class="custom-select-menu absolute left-0 right-0 z-50 mt-1.5 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                <div class="custom-select-options max-h-48 overflow-y-auto space-y-0.5 scrollbar-thin">
                    ${options.map(opt => `
                        <div class="custom-select-option flex items-center justify-between rounded-xl px-3 py-2 text-xs hover:bg-emerald-50/80 hover:text-emerald-900 text-slate-800 cursor-pointer ${String(opt.id) === String(defaultVal) ? 'bg-emerald-50 text-emerald-800 font-black' : 'font-semibold'}"
                             data-value="${opt.id}"
                             data-label="${opt.name}">
                            <div class="flex flex-col truncate pr-2">
                                <span class="custom-option-label truncate">${opt.name}</span>
                                <span class="custom-option-subtitle text-[10px] text-slate-400 font-normal truncate">${opt.subtitle || ''}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;

    if (window.lucide) window.lucide.createIcons();
}

function addSettlementSourceFromModal() {
    const relId = getActiveSettlementRelationId();
    const typeInput = document.getElementById('new_item_type');
    const roleInput = document.getElementById('new_item_role');
    const itemInput = document.getElementById('new_source_item_id');

    if (!relId || !typeInput || !roleInput || !itemInput || !itemInput.value) return;

    const type = typeInput.value;
    const role = roleInput.value;
    const id = parseInt(itemInput.value, 10);

    // Get item label
    let label = 'Item #' + id;
    if (type === 'header') {
        const found = settlementAvailableHeaders.find(h => h.id === id);
        if (found) label = found.name;
    } else if (type === 'settlement') {
        const found = settlementRelationsData.find(r => r.id === id);
        if (found) label = found.name;
    } else {
        const found = settlementAvailableSettings.find(s => s.id === id);
        if (found) label = found.entry_type ? found.entry_type.name : (found.display_name || found.entry_name);
    }

    if (!settlementWorkingItems[relId]) {
        settlementWorkingItems[relId] = [];
    }

    // Check duplicate within same relation
    const existing = settlementWorkingItems[relId].find(i => i.type === type && i.id === id);
    if (existing) {
        alert(label + ' is already added to this formula.');
        return;
    }

    settlementWorkingItems[relId].push({
        type: type,
        id: id,
        role: role,
        label: label
    });

    renderSettlementModalItems();
}

function checkSettlementDuplicates(relId) {
    const warnBox = document.getElementById('settlement-modal-duplicate-warning');
    const warnMsg = document.getElementById('settlement-modal-duplicate-msg');
    const saveBtn = document.getElementById('modal-save-settlement-btn');
    if (!warnBox || !warnMsg || !relId) return;

    const items = settlementWorkingItems[relId] || [];
    const headerIds = items.filter(i => i.type === 'header').map(i => i.id);
    const categoryIds = items.filter(i => i.type === 'category').map(i => i.id);

    let duplicates = [];
    categoryIds.forEach(catId => {
        const catSetting = settlementAvailableSettings.find(s => s.id === catId);
        if (catSetting && catSetting.header_group_id && headerIds.includes(catSetting.header_group_id)) {
            const header = settlementAvailableHeaders.find(h => h.id === catSetting.header_group_id);
            const catName = catSetting.entry_type ? catSetting.entry_type.name : (catSetting.display_name || catSetting.entry_name);
            const headerName = header ? header.name : 'its Header';
            duplicates.push(`"${catName}" is already included through Header "${headerName}".`);
        }
    });

    if (duplicates.length > 0) {
        warnMsg.textContent = duplicates.join(' ');
        warnBox.classList.remove('hidden');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            saveBtn.title = 'Resolve duplicates before saving';
        }
    } else {
        warnBox.classList.add('hidden');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            saveBtn.title = '';
        }
    }
}

function saveSettlementModalItems() {
    const relId = getActiveSettlementRelationId();
    const btn = document.getElementById('modal-save-settlement-btn');
    if (!relId || !btn) return;

    const items = settlementWorkingItems[relId] || [];

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
    if (window.lucide) window.lucide.createIcons();

    const payload = {
        relation_id: relId,
        items: items.map(i => ({
            type: i.type,
            id: i.id,
            role: i.role
        }))
    };

    fetch('{{ route("admin.cashbook.settings.shop.payments.settlement.save", $shopKey) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closePaymentModal('settlement');
            window.showPaymentToast(data.message || 'Settlement items saved.', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alert(data.message || 'Failed to save settlement items.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred while saving.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i><span>Save Formula Items</span>';
        if (window.lucide) window.lucide.createIcons();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSettlementWorkingState();
    renderNewItemSelectSlot('header');

    // Listen to source type changes
    const typeInput = document.getElementById('new_item_type');
    if (typeInput) {
        typeInput.addEventListener('change', () => {
            renderNewItemSelectSlot(typeInput.value);
        });
    }

    // Listen to active relation changes
    const relInput = document.getElementById('modal_active_settlement_relation');
    if (relInput) {
        relInput.addEventListener('change', () => {
            renderSettlementModalItems();
            renderNewItemSelectSlot(typeInput ? typeInput.value : 'header');
        });
    }

    renderSettlementModalItems();
});
</script>
