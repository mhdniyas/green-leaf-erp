<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Dues Settlement & Matching</span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">PAYMENT ALLOCATION SETTINGS</h2>
            <p class="text-xs text-slate-500 mt-0.5">Configure how verified payments to company are allocated against open payable ledger transactions using oldest-first FIFO matching.</p>
        </div>
        <div>
            <button type="button"
                    data-payment-modal-open="allocation"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-4 py-2.5 text-xs font-black text-white hover:bg-indigo-800 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="edit-3" class="h-4 w-4"></i>
                <span>Edit Settings</span>
            </button>
        </div>
    </div>

    {{-- 1. CURRENT SETUP --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Active Rules</span>
                <h3 class="text-sm font-black text-slate-900">Allocation Relations & Matching Engine</h3>
            </div>
            <button type="button"
                    data-payment-modal-open="allocation"
                    class="text-xs font-black text-indigo-700 hover:text-indigo-900 flex items-center gap-1 cursor-pointer">
                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                <span>Modify Rules</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                <span class="text-xs font-black text-slate-800 block uppercase tracking-wider">Settlement Relation Binding</span>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                        <span class="text-slate-600 font-medium">Default Payable Relation:</span>
                        <span class="font-black text-indigo-800">
                            {{ $allocationData['setup']['payable_relation']?->name ?? 'None selected' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                        <span class="text-slate-600 font-medium">Default Paid Relation:</span>
                        <span class="font-black text-indigo-800">
                            {{ $allocationData['setup']['paid_relation']?->name ?? 'None selected' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                        <span class="text-slate-600 font-medium">Auto-allocation upon Approval:</span>
                        <span class="font-black {{ ($allocationData['setup']['expense_allocation']['auto_allocate'] ?? false) ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ ($allocationData['setup']['expense_allocation']['auto_allocate'] ?? false) ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-slate-600 font-medium">Allocation Method:</span>
                        <span class="font-bold text-slate-800">Oldest first — Business Date → ID</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                <span class="text-xs font-black text-slate-800 block uppercase tracking-wider">Target Expense Categories</span>
                @php
                    $targetCatIds = (array) ($allocationData['setup']['expense_allocation']['category_ids'] ?? []);
                @endphp
                <div class="space-y-2 text-xs">
                    <span class="text-slate-500 block text-[10px] uppercase font-bold">Enabled Categories for Payment Allocation:</span>
                    <div class="flex flex-wrap gap-1 mt-1 max-h-24 overflow-y-auto">
                        @forelse($allocationData['setup']['all_expense_settings']->whereIn('id', $targetCatIds) as $setting)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200">
                                {{ $setting->entryType?->name ?? $setting->entry_name }}
                            </span>
                        @empty
                            <span class="text-slate-400 italic text-[11px]">All general expense categories</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. OUTPUT SECTION (Financial Results & Simulation) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Financial Impact</span>
                <h3 class="text-sm font-black text-slate-900">Allocation Status ({{ \Carbon\Carbon::parse($startDate)->format('M Y') }})</h3>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ ($allocationData['setup']['expense_allocation']['enabled'] ?? false) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                {{ ($allocationData['setup']['expense_allocation']['enabled'] ?? false) ? 'Allocation Active' : 'Allocation Inactive' }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Approved Payments</span>
                <div class="text-base font-black text-slate-900 mt-1">₹{{ number_format($allocationData['output']['total_approved_payments'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Allocated to Dues</span>
                <div class="text-base font-black text-emerald-800 mt-1">₹{{ number_format($allocationData['output']['allocated'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Unallocated Amount</span>
                <div class="text-base font-black text-amber-800 mt-1">₹{{ number_format($allocationData['output']['unallocated'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700">Open Payables ({{ $allocationData['output']['open_payables_count'] }})</span>
                <div class="text-base font-black text-indigo-800 mt-1">₹{{ number_format($allocationData['output']['open_payables_total'], 2) }}</div>
            </div>
        </div>

        {{-- Simulation Preview --}}
        <div class="border-t border-slate-100 pt-3 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-black text-slate-800">Oldest-First Allocation Preview (Simulating ₹{{ number_format($allocationData['preview']['sample_amount'], 2) }}):</span>
                <span class="text-[11px] text-slate-500 font-mono">Simulated Remaining: ₹{{ number_format($allocationData['preview']['remaining'], 2) }}</span>
            </div>
            @if(!empty($allocationData['preview']['allocations']))
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach($allocationData['preview']['allocations'] as $sim)
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 text-xs flex items-center justify-between">
                            <div>
                                <span class="font-black text-slate-800 block truncate">{{ $sim['name'] }}</span>
                                <span class="text-[10px] text-slate-400">{{ $sim['date'] }}</span>
                            </div>
                            <span class="font-black text-emerald-700">₹{{ number_format($sim['amount'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-lg border border-dashed border-slate-200 p-3 text-center text-xs text-slate-400">
                    No open payables available to simulate allocation.
                </div>
            @endif
        </div>
    </div>

    {{-- Bottom Edit Button --}}
    <div class="flex justify-end pt-2">
        <button type="button"
                data-payment-modal-open="allocation"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-5 py-2.5 text-xs font-black text-white hover:bg-indigo-800 transition cursor-pointer shadow-sm active:scale-98">
            <i data-lucide="edit-3" class="h-4 w-4"></i>
            <span>Edit Settings</span>
        </button>
    </div>
</div>

{{-- MODAL: ALLOCATION SETTINGS --}}
<x-cashbook.payment-modal
    modalId="allocation"
    title="Payment Allocation Settings"
    subtitle="FIFO Matching Rules"
    shopName="{{ $currentShop->name }}"
    period="{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}"
    maxWidth="max-w-3xl"
    saveFunction="saveAllocationModalSettings()"
    saveBtnId="modal-save-allocation-btn"
    saveBtnText="Save Allocation Rules">

    <div class="space-y-5">
        <form id="allocation-modal-form" class="space-y-4">
            {{-- Module Toggles --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-indigo-300 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/30 transition cursor-pointer">
                    <input type="checkbox"
                           name="enabled"
                           value="1"
                           @checked($allocationData['setup']['expense_allocation']['enabled'] ?? false)
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    <div>
                        <span class="text-xs font-black text-slate-900 block">Enable Payment Allocation</span>
                        <span class="text-[11px] text-slate-500">Track and link individual payments against open payable transactions.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-indigo-300 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/30 transition cursor-pointer">
                    <input type="checkbox"
                           name="auto_allocate"
                           value="1"
                           @checked($allocationData['setup']['expense_allocation']['auto_allocate'] ?? false)
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    <div>
                        <span class="text-xs font-black text-slate-900 block">Auto-Allocate on Approval</span>
                        <span class="text-[11px] text-slate-500">Automatically run FIFO allocation when an admin approves a payment request.</span>
                    </div>
                </label>
            </div>

            {{-- Relation Selectors --}}
            @php
                $relOptions = $allocationData['setup']['relations']->map(fn($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'subtitle' => 'Relation Type: ' . strtoupper($r->relation_type ?? 'standard'),
                ])->all();
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
                <label class="block text-xs font-black text-slate-900">Default Allocation Relations</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Default Payable Relation (Shop Owes)</label>
                        <x-cashbook.custom-select
                            name="payable_relation_id"
                            id="allocation_payable_relation"
                            :options="$relOptions"
                            :selected="$allocationData['setup']['payable_relation']?->id"
                            placeholder="Select Payable Relation..."
                            :searchable="true"
                        />
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Default Paid Relation (Payments Made)</label>
                        <x-cashbook.custom-select
                            name="paid_relation_id"
                            id="allocation_paid_relation"
                            :options="$relOptions"
                            :selected="$allocationData['setup']['paid_relation']?->id"
                            placeholder="Select Paid Relation..."
                            :searchable="true"
                        />
                    </div>
                </div>
            </div>

            {{-- Allocation Method Informational Note (Read-Only) --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Allocation Engine</span>
                    <span class="rounded bg-slate-200/80 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700">Read-Only Rule</span>
                </div>
                <div class="text-xs font-black text-slate-900">Oldest first — Business Date → ID</div>
                <p class="text-[11px] text-slate-500">Payments are matched against oldest open payables sequentially by business date and database transaction ID.</p>
            </div>

            {{-- Target Expense Categories Checkboxes --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-2.5">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-black text-slate-900">Target Expense Categories</label>
                    <span class="text-[10px] text-slate-400 font-bold">Categories eligible for allocation</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-48 overflow-y-auto p-2 border border-slate-200 rounded-xl bg-slate-50/40 scrollbar-thin">
                    @foreach($allocationData['setup']['all_expense_settings'] as $setting)
                        <label class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white cursor-pointer text-xs transition">
                            <input type="checkbox"
                                   name="category_ids[]"
                                   value="{{ $setting->id }}"
                                   @checked(in_array($setting->id, (array) ($allocationData['setup']['expense_allocation']['category_ids'] ?? []), true))
                                   class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                            <span class="truncate font-medium text-slate-800">{{ $setting->entryType?->name ?? $setting->entry_name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </form>
    </div>
</x-cashbook.payment-modal>

<script>
function saveAllocationModalSettings() {
    const btn = document.getElementById('modal-save-allocation-btn');
    const form = document.getElementById('allocation-modal-form');
    if (!form || !btn) return;

    const catCheckboxes = form.querySelectorAll('input[name="category_ids[]"]:checked');
    const catIds = Array.from(catCheckboxes).map(cb => parseInt(cb.value, 10));

    const payload = {
        enabled: form.querySelector('input[name="enabled"]').checked ? 1 : 0,
        auto_allocate: form.querySelector('input[name="auto_allocate"]').checked ? 1 : 0,
        payable_relation_id: form.querySelector('input[name="payable_relation_id"]').value ? parseInt(form.querySelector('input[name="payable_relation_id"]').value, 10) : null,
        paid_relation_id: form.querySelector('input[name="paid_relation_id"]').value ? parseInt(form.querySelector('input[name="paid_relation_id"]').value, 10) : null,
        category_ids: catIds,
        default_category_id: null,
    };

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
    if (window.lucide) window.lucide.createIcons();

    fetch('{{ route("admin.cashbook.settings.shop.payments.allocation.save", $shopKey) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePaymentModal('allocation');
            window.showPaymentToast(data.message || 'Payment allocation settings saved.', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alert(data.message || 'Error saving allocation settings.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to save allocation settings.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i><span>Save Allocation Rules</span>';
        if (window.lucide) window.lucide.createIcons();
    });
}
</script>
