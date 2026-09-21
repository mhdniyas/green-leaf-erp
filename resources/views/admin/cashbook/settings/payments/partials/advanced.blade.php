<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700">Technical Category Tuning</span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">ADVANCED SETTINGS & AUDIT</h2>
            <p class="text-xs text-slate-500 mt-0.5">Category funding sources, payable flags, and legacy settlement field protections.</p>
        </div>
        <div>
            <button type="button"
                    data-payment-modal-open="advanced"
                    class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-700 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="sliders" class="h-4 w-4"></i>
                <span>Advanced Settings</span>
            </button>
        </div>
    </div>

    {{-- Architectural Protection Notice --}}
    <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 text-xs text-amber-900 space-y-1.5 shadow-xs">
        <div class="flex items-center gap-2 font-black">
            <i data-lucide="shield-alert" class="h-4 w-4 text-amber-600 shrink-0"></i>
            <span>Legacy Settlement Field Protection Active</span>
        </div>
        <p class="text-amber-800 leading-relaxed">
            The authoritative settlement engine uses <code>shop_cashbook_relations</code> and <code>shop_cashbook_relation_items</code>. Legacy <code>settlement_behavior</code> fields on categories are maintained read-only below to prevent database corruption. Any detected conflicts are flagged.
        </p>
    </div>

    {{-- 1. CONFLICT AUDIT (Output Summary) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Integrity Check</span>
                <h3 class="text-sm font-black text-slate-900">Settlement Architecture Integrity</h3>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $advancedData['conflict_count'] === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $advancedData['conflict_count'] === 0 ? 'No Formula Conflicts' : $advancedData['conflict_count'] . ' Legacy Conflicts' }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Active Categories</span>
                <div class="text-base font-black text-slate-900 mt-1">{{ count($advancedData['categories']) }}</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Authoritative Calculation</span>
                <div class="text-base font-black text-emerald-900 mt-1">shop_cashbook_relations</div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Legacy Status</span>
                <div class="text-base font-black text-amber-900 mt-1">Read-Only Locked</div>
            </div>
        </div>
    </div>

    {{-- 2. CURRENT SETUP (Read-only Summary Table) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Active Setup</span>
                <h3 class="text-sm font-black text-slate-900">Category Technical Controls Summary</h3>
            </div>
            <button type="button"
                    data-payment-modal-open="advanced"
                    class="text-xs font-black text-amber-700 hover:text-amber-900 flex items-center gap-1 cursor-pointer">
                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                <span>Configure Settings</span>
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 px-3">Category</th>
                        <th class="py-2.5 px-3">Funding Source</th>
                        <th class="py-2.5 px-3">Company Pending</th>
                        <th class="py-2.5 px-3 text-center">Include in Payable</th>
                        <th class="py-2.5 px-3">Legacy Settlement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($advancedData['categories'] as $cat)
                        @php
                            $hasConflict = !empty($cat['conflicts']);
                        @endphp
                        <tr class="hover:bg-slate-50/50 {{ $hasConflict ? 'bg-amber-50/30' : '' }}">
                            <td class="py-2.5 px-3">
                                <div class="font-bold text-slate-800">{{ $cat['name'] }}</div>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $cat['header_name'] }}</span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="font-semibold text-slate-700 capitalize">{{ str_replace('_', ' ', $cat['default_funding_source'] ?? 'sales') }}</span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="font-semibold text-slate-700 capitalize">{{ str_replace('_', ' ', $cat['company_pending_behavior'] ?? 'ignore') }}</span>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $cat['include_in_payable'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $cat['include_in_payable'] ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black uppercase bg-slate-100 text-slate-500 border border-slate-200">
                                    <i data-lucide="lock" class="h-3 w-3 text-slate-400"></i>
                                    <span>{{ $cat['settlement_behavior'] }}</span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bottom Edit Button --}}
    <div class="flex justify-end pt-2">
        <button type="button"
                data-payment-modal-open="advanced"
                class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-black text-white hover:bg-amber-700 transition cursor-pointer shadow-sm active:scale-98">
            <i data-lucide="sliders" class="h-4 w-4"></i>
            <span>Advanced Settings</span>
        </button>
    </div>
</div>

{{-- MODAL: ADVANCED PAYMENTS SETTINGS --}}
<x-cashbook.payment-modal
    modalId="advanced"
    title="Advanced Payments Settings"
    subtitle="Category Technical Controls"
    shopName="{{ $currentShop->name }}"
    period="{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}"
    maxWidth="max-w-5xl"
    saveFunction="saveAdvancedModalSettings()"
    saveBtnId="modal-save-advanced-btn"
    saveBtnText="Save Advanced Settings">

    <div class="space-y-4">
        {{-- Warning banner --}}
        <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs text-amber-900 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
            <div>
                <strong class="font-black text-amber-950">Caution: Technical Configuration Matrix</strong>
                <p class="mt-0.5 text-amber-800 leading-relaxed">
                    Legacy <code>settlement_behavior</code> values remain <strong>locked read-only</strong> because settlement is authoritatively calculated via Settlement Relations.
                </p>
            </div>
        </div>

        <form id="advanced-modal-form" class="space-y-3">
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">Category</th>
                            <th class="py-3 px-3 w-44">Default Funding Source</th>
                            <th class="py-3 px-3 w-44">Company Pending</th>
                            <th class="py-3 px-3 text-center w-24">Payable</th>
                            <th class="py-3 px-3 w-36">Legacy (Locked)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $fundingSourceOptions = [
                                ['value' => 'sales', 'label' => 'Sales / Drawer'],
                                ['value' => 'shop_cash', 'label' => 'Shop Cash'],
                                ['value' => 'petty', 'label' => 'Petty Cash'],
                                ['value' => 'company_bank', 'label' => 'Company Bank'],
                            ];

                            $pendingOptions = [
                                ['value' => 'ignore', 'label' => 'Ignore'],
                                ['value' => 'deduct_from_balance', 'label' => 'Deduct from Balance'],
                                ['value' => 'show_in_settlement', 'label' => 'Show in Settlement'],
                            ];
                        @endphp

                        @foreach($advancedData['categories'] as $cat)
                            @php
                                $setting = $cat['setting'];
                                $hasConflict = !empty($cat['conflicts']);
                            @endphp
                            <tr class="hover:bg-slate-50/50 {{ $hasConflict ? 'bg-amber-50/30' : '' }}">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900">{{ $cat['name'] }}</div>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $cat['header_name'] }}</span>
                                    @if($hasConflict)
                                        <div class="mt-0.5 text-[10px] text-amber-700 font-bold">
                                            @foreach($cat['conflicts'] as $cnf)
                                                <div>⚠️ {{ $cnf }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <x-cashbook.custom-select
                                        name="settings[{{ $setting->id }}][default_funding_source]"
                                        id="adv_funding_{{ $setting->id }}"
                                        :options="$fundingSourceOptions"
                                        :selected="$cat['default_funding_source'] ?? 'sales'"
                                        placeholder="Select source..."
                                        :searchable="false"
                                    />
                                </td>
                                <td class="py-2.5 px-3">
                                    <x-cashbook.custom-select
                                        name="settings[{{ $setting->id }}][company_pending_behavior]"
                                        id="adv_pending_{{ $setting->id }}"
                                        :options="$pendingOptions"
                                        :selected="$cat['company_pending_behavior'] ?? 'ignore'"
                                        placeholder="Select behavior..."
                                        :searchable="false"
                                    />
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <input type="checkbox"
                                           name="settings[{{ $setting->id }}][include_in_payable]"
                                           value="1"
                                           @checked($cat['include_in_payable'])
                                           class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-black uppercase bg-slate-100 text-slate-500 border border-slate-200 cursor-not-allowed"
                                          title="Legacy settlement behavior is locked read-only. Current settlement is controlled by Settlement Relations.">
                                        <i data-lucide="lock" class="h-3 w-3 text-slate-400"></i>
                                        <span>{{ $cat['settlement_behavior'] }}</span>
                                        <span class="text-[8px] font-normal text-slate-400 lowercase">(read-only)</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</x-cashbook.payment-modal>

<script>
function saveAdvancedModalSettings() {
    const btn = document.getElementById('modal-save-advanced-btn');
    const form = document.getElementById('advanced-modal-form');
    if (!form || !btn) return;

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
    if (window.lucide) window.lucide.createIcons();

    const formData = new FormData(form);
    const settings = {};

    for (const [key, value] of formData.entries()) {
        const match = key.match(/settings\[(\d+)\]\[(\w+)\]/);
        if (match) {
            const id = match[1];
            const field = match[2];
            if (!settings[id]) settings[id] = {};
            settings[id][field] = value;
        }
    }

    // Check include_in_payable checkboxes
    form.querySelectorAll('input[type="checkbox"][name*="include_in_payable"]').forEach(cb => {
        const match = cb.name.match(/settings\[(\d+)\]\[include_in_payable\]/);
        if (match) {
            const id = match[1];
            if (!settings[id]) settings[id] = {};
            settings[id]['include_in_payable'] = cb.checked ? 1 : 0;
        }
    });

    fetch('{{ route("admin.cashbook.settings.shop.payments.advanced.save", $shopKey) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ settings: settings })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePaymentModal('advanced');
            window.showPaymentToast(data.message || 'Advanced settings saved successfully.', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alert(data.message || 'Error saving advanced settings.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to save advanced settings.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i><span>Save Advanced Settings</span>';
        if (window.lucide) window.lucide.createIcons();
    });
}
</script>
