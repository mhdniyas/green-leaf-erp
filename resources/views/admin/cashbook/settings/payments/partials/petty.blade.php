<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700">Imprest & Daily Cash</span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">PETTY CASH SETTINGS</h2>
            <p class="text-xs text-slate-500 mt-0.5">Manage petty cash fund rules, company imprest inflows, shop drawer top-ups, and petty expense controls.</p>
        </div>
        <div>
            <button type="button"
                    data-payment-modal-open="petty"
                    class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-700 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="edit-3" class="h-4 w-4"></i>
                <span>Edit Settings</span>
            </button>
        </div>
    </div>

    {{-- Discrepancy Alert if any --}}
    @if($pettyData['output']['has_discrepancy'])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 flex items-start gap-3 shadow-xs">
            <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600 shrink-0 mt-0.5"></i>
            <div class="text-xs">
                <p class="font-black text-amber-900">Petty Cash Discrepancy Detected</p>
                <p class="mt-0.5 text-amber-800 leading-relaxed">{{ $pettyData['output']['discrepancy_message'] }}</p>
            </div>
        </div>
    @endif

    {{-- 1. CURRENT SETUP --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Active Rules</span>
                <h3 class="text-sm font-black text-slate-900">Petty Cash Operational Configuration</h3>
            </div>
            <button type="button"
                    data-payment-modal-open="petty"
                    class="text-xs font-black text-amber-700 hover:text-amber-900 flex items-center gap-1 cursor-pointer">
                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                <span>Modify Rules</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                <span class="text-xs font-black text-slate-800 block uppercase tracking-wider">Operational Rules</span>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                        <span class="text-slate-600 font-medium">Petty Cash Tracking:</span>
                        <span class="font-black {{ ($pettyData['config']['enabled'] ?? false) ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ ($pettyData['config']['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                        <span class="text-slate-600 font-medium">Company → Petty Imprest:</span>
                        <span class="font-black {{ ($pettyData['config']['allow_company_to_petty'] ?? false) ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ ($pettyData['config']['allow_company_to_petty'] ?? false) ? 'Allowed' : 'Blocked' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                        <span class="text-slate-600 font-medium">Shop Owner Visibility:</span>
                        <span class="font-black {{ ($pettyData['config']['shop_owner_view_petty'] ?? true) ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ ($pettyData['config']['shop_owner_view_petty'] ?? true) ? 'Visible' : 'Hidden' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-slate-600 font-medium">Expenses Funded from Petty:</span>
                        <span class="font-black {{ ($pettyData['config']['allow_expenses_from_petty'] ?? true) ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ ($pettyData['config']['allow_expenses_from_petty'] ?? true) ? 'Allowed' : 'Restricted' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                <span class="text-xs font-black text-slate-800 block uppercase tracking-wider">Configured Category Bindings</span>
                <div class="space-y-2 text-xs">
                    <div>
                        <span class="text-slate-500 block text-[10px] uppercase font-bold">Imprest / Funding Categories:</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @forelse($pettyData['categories']['company_to_petty'] as $cat)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-sky-100 text-sky-800 border border-sky-200">
                                    {{ $cat->entryType?->name ?? $cat->entry_name }}
                                </span>
                            @empty
                                <span class="text-slate-400 italic text-[11px]">None explicitly bound</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="pt-2 border-t border-slate-200/60">
                        <span class="text-slate-500 block text-[10px] uppercase font-bold">Sales-to-Petty Top-up:</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @forelse($pettyData['categories']['sales_to_petty'] as $cat)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    {{ $cat->entryType?->name ?? $cat->entry_name }}
                                </span>
                            @empty
                                <span class="text-slate-400 italic text-[11px]">None</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="pt-2 border-t border-slate-200/60">
                        <span class="text-slate-500 block text-[10px] uppercase font-bold">Allowed Petty Expense Categories:</span>
                        <div class="flex flex-wrap gap-1 mt-1 max-h-20 overflow-y-auto">
                            @forelse($pettyData['categories']['petty_expenses'] as $cat)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ $cat->entryType?->name ?? $cat->entry_name }}
                                </span>
                            @empty
                                <span class="text-slate-400 italic text-[11px]">None defined</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. OUTPUT SECTION (Financial Results) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Financial Impact</span>
                <h3 class="text-sm font-black text-slate-900">Petty Cash Output ({{ \Carbon\Carbon::parse($startDate)->format('M Y') }})</h3>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ ($pettyData['config']['enabled'] ?? false) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                {{ ($pettyData['config']['enabled'] ?? false) ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Opening Balance</span>
                <div class="text-sm font-black text-slate-800 mt-1">₹{{ number_format($pettyData['output']['opening_petty'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-sky-100 bg-sky-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-sky-700">Company Imprest (+)</span>
                <div class="text-sm font-black text-sky-800 mt-1">₹{{ number_format($pettyData['output']['company_funding'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Sales Top-up (+)</span>
                <div class="text-sm font-black text-emerald-800 mt-1">₹{{ number_format($pettyData['output']['sales_funding'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-700">Petty Expenses (-)</span>
                <div class="text-sm font-black text-rose-800 mt-1">₹{{ number_format($pettyData['output']['petty_expenses'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-purple-100 bg-purple-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-700">Returned to Co (-)</span>
                <div class="text-sm font-black text-purple-800 mt-1">₹{{ number_format($pettyData['output']['petty_returned'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">Closing Balance</span>
                <div class="text-sm font-black text-amber-900 mt-1">₹{{ number_format($pettyData['output']['closing_petty'], 2) }}</div>
            </div>
        </div>

        <div class="text-[11px] text-slate-500 bg-slate-50 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span>Authoritative Balance Rule: <code>Closing Petty = Opening + Imprest + Sales Funding - Expenses - Returned</code></span>
            <span class="font-bold text-slate-700">Calculated via Ledger <code>petty_delta</code></span>
        </div>
    </div>

    {{-- Bottom Edit Button --}}
    <div class="flex justify-end pt-2">
        <button type="button"
                data-payment-modal-open="petty"
                class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-black text-white hover:bg-amber-700 transition cursor-pointer shadow-sm active:scale-98">
            <i data-lucide="edit-3" class="h-4 w-4"></i>
            <span>Edit Settings</span>
        </button>
    </div>
</div>

{{-- MODAL: PETTY CASH --}}
<x-cashbook.payment-modal
    modalId="petty"
    title="Petty Cash Settings"
    subtitle="Operational Rules"
    shopName="{{ $currentShop->name }}"
    period="{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}"
    maxWidth="max-w-2xl"
    saveFunction="savePettySettings()"
    saveBtnId="modal-save-petty-btn"
    saveBtnText="Save Petty Rules">

    <div class="space-y-4">
        <form id="petty-modal-form" class="space-y-3">
            <div class="space-y-2.5">
                <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-amber-300 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30 transition cursor-pointer">
                    <input type="checkbox"
                           name="enabled"
                           value="1"
                           @checked($pettyData['config']['enabled'] ?? false)
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                    <div>
                        <span class="text-xs font-black text-slate-900 block">Enable Petty Cash Module</span>
                        <span class="text-[11px] text-slate-500">Track a dedicated petty cash balance separate from shop cash drawer.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-amber-300 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30 transition cursor-pointer">
                    <input type="checkbox"
                           name="allow_company_to_petty"
                           value="1"
                           @checked($pettyData['config']['allow_company_to_petty'] ?? false)
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                    <div>
                        <span class="text-xs font-black text-slate-900 block">Allow Company → Petty Imprest</span>
                        <span class="text-[11px] text-slate-500">Allow company disbursements directly into the shop's petty cash fund.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-amber-300 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30 transition cursor-pointer">
                    <input type="checkbox"
                           name="shop_owner_view_petty"
                           value="1"
                           @checked($pettyData['config']['shop_owner_view_petty'] ?? true)
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                    <div>
                        <span class="text-xs font-black text-slate-900 block">Show Petty Cash to Shop Owner</span>
                        <span class="text-[11px] text-slate-500">Display petty cash balance and transaction summary in the shop manager dashboard.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-amber-300 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30 transition cursor-pointer">
                    <input type="checkbox"
                           name="allow_expenses_from_petty"
                           value="1"
                           @checked($pettyData['config']['allow_expenses_from_petty'] ?? true)
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                    <div>
                        <span class="text-xs font-black text-slate-900 block">Allow Expenses From Petty</span>
                        <span class="text-[11px] text-slate-500">Permit cashbook expense entries to select Petty Cash as the funding source.</span>
                    </div>
                </label>
            </div>
        </form>
    </div>
</x-cashbook.payment-modal>

<script>
function savePettySettings() {
    const btn = document.getElementById('modal-save-petty-btn');
    const form = document.getElementById('petty-modal-form');
    if (!form || !btn) return;

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
    if (window.lucide) window.lucide.createIcons();

    const payload = {
        enabled: form.querySelector('input[name="enabled"]').checked ? 1 : 0,
        allow_company_to_petty: form.querySelector('input[name="allow_company_to_petty"]').checked ? 1 : 0,
        shop_owner_view_petty: form.querySelector('input[name="shop_owner_view_petty"]').checked ? 1 : 0,
        allow_expenses_from_petty: form.querySelector('input[name="allow_expenses_from_petty"]').checked ? 1 : 0,
    };

    fetch('{{ route("admin.cashbook.settings.shop.payments.petty.save", $shopKey) }}', {
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
            closePaymentModal('petty');
            window.showPaymentToast(data.message || 'Petty cash settings saved.', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alert(data.message || 'Error saving petty settings.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to save petty settings.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i><span>Save Petty Rules</span>';
        if (window.lucide) window.lucide.createIcons();
    });
}
</script>
