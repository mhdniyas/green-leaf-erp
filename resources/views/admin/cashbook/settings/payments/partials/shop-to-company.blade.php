<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Manual Remittances & Verification</span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">SHOP → COMPANY PAYMENTS</h2>
            <p class="text-xs text-slate-500 mt-0.5">Configure how cash and cheques from shop cash drawers are remitted to company bank accounts, verified by admin, and allocated to clear ledger dues.</p>
        </div>
        <div>
            <button type="button"
                    data-payment-modal-open="shop-to-company"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="edit-3" class="h-4 w-4"></i>
                <span>Edit Settings</span>
            </button>
        </div>
    </div>

    {{-- 1. CURRENT SETUP --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Active Configuration</span>
                <h3 class="text-sm font-black text-slate-900">Current Remittance Rules & Account Routing</h3>
            </div>
            <button type="button"
                    data-payment-modal-open="shop-to-company"
                    class="text-xs font-black text-emerald-700 hover:text-emerald-900 flex items-center gap-1 cursor-pointer">
                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                <span>Modify Rules</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            {{-- Allowed Payment Methods Card --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Allowed Methods</span>
                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-800 uppercase">
                        {{ count($shopToCompanyData['setup']['allowed_methods']) }} Enabled
                    </span>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    @php
                        $allMethodLabels = [
                            'cash' => 'Cash in Hand',
                            'online_upi' => 'UPI / Online Transfer',
                            'cheque' => 'Bank Cheque',
                            'bank_transfer' => 'CDM / Bank Deposit',
                            'other' => 'Other Payment',
                        ];
                    @endphp
                    @forelse($shopToCompanyData['setup']['allowed_methods'] as $m)
                        <span class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-900">
                            <i data-lucide="check" class="h-3 w-3 text-emerald-700"></i>
                            {{ $allMethodLabels[$m] ?? ucfirst(str_replace('_', ' ', $m)) }}
                        </span>
                    @empty
                        <span class="text-slate-400 italic">No methods enabled</span>
                    @endforelse
                </div>
            </div>

            {{-- Destination Account Card --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Default Destination</span>
                <div class="space-y-1">
                    <div class="font-black text-slate-900 text-sm">
                        {{ $shopToCompanyData['setup']['default_account'] ? $shopToCompanyData['setup']['default_account']->name : 'Default Company Bank Account' }}
                    </div>
                    @if($shopToCompanyData['setup']['default_account'])
                        <p class="text-[11px] text-slate-500 font-mono">
                            {{ $shopToCompanyData['setup']['default_account']->bank_name }} • {{ strtoupper($shopToCompanyData['setup']['default_account']->account_type) }}
                        </p>
                    @endif
                </div>
                <p class="text-[11px] text-slate-400">Preselected bank account on payment verification screens.</p>
            </div>

            {{-- Paid Settlement Relation Card --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Payment-Paid Settlement</span>
                <div class="space-y-1">
                    <div class="font-black text-slate-900 text-sm">
                        {{ $shopToCompanyData['setup']['paid_relation'] ? $shopToCompanyData['setup']['paid_relation']->name : 'None Assigned' }}
                    </div>
                    <span class="inline-flex items-center rounded-md bg-violet-100 px-2 py-0.5 text-[10px] font-black text-violet-800 uppercase">
                        is_payment_paid
                    </span>
                </div>
                <p class="text-[11px] text-slate-400">Settlement relation tracking verified remittances from this shop.</p>
            </div>
        </div>
    </div>

    {{-- 2. OUTPUT SECTION (Financial Results) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Financial Impact</span>
                <h3 class="text-sm font-black text-slate-900">Current Output ({{ \Carbon\Carbon::parse($startDate)->format('M Y') }})</h3>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.shop.accept-payment', $shopKey) }}" class="text-xs font-bold text-emerald-700 hover:underline inline-flex items-center gap-1">
                    <span>Accept Payment Screen</span>
                    <i data-lucide="external-link" class="h-3 w-3"></i>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Requested</span>
                <div class="text-base font-black text-slate-900 mt-1">₹{{ number_format($shopToCompanyData['output']['requested'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Admin Approved</span>
                <div class="text-base font-black text-emerald-800 mt-1">₹{{ number_format($shopToCompanyData['output']['approved'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-teal-100 bg-teal-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-teal-700">Total Received</span>
                <div class="text-base font-black text-teal-800 mt-1">₹{{ number_format($shopToCompanyData['output']['received'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-700">Allocated to Dues</span>
                <div class="text-base font-black text-blue-800 mt-1">₹{{ number_format($shopToCompanyData['output']['allocated'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Unallocated Float</span>
                <div class="text-base font-black text-amber-800 mt-1">₹{{ number_format($shopToCompanyData['output']['unallocated'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-700">Pending Reconciliation</span>
                <div class="text-base font-black text-rose-800 mt-1">₹{{ number_format($shopToCompanyData['output']['pending_reconciliation'], 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Bottom Edit Button --}}
    <div class="flex justify-end pt-2">
        <button type="button"
                data-payment-modal-open="shop-to-company"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-800 transition cursor-pointer shadow-sm active:scale-98">
            <i data-lucide="edit-3" class="h-4 w-4"></i>
            <span>Edit Settings</span>
        </button>
    </div>
</div>

{{-- MODAL: SHOP → COMPANY PAYMENTS --}}
<x-cashbook.payment-modal
    modalId="shop-to-company"
    title="Shop → Company Payment Settings"
    subtitle="Remittance Configuration"
    shopName="{{ $currentShop->name }}"
    period="{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}"
    maxWidth="max-w-3xl"
    saveFunction="saveShopToCompanySettings()"
    saveBtnId="modal-save-shop-to-company-btn"
    saveBtnText="Save Settings">

    <div class="space-y-5">
        <form id="shop-to-company-modal-form" class="space-y-5">
            {{-- Allowed Payment Methods --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
                <div>
                    <label class="block text-xs font-black text-slate-900">Allowed Payment Methods</label>
                    <p class="text-[11px] text-slate-500">Select payment modes permitted for shop owners when submitting remittances.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @php
                        $modalMethods = [
                            'cash' => ['label' => 'Cash in Hand', 'desc' => 'Physical currency handed to collector'],
                            'online_upi' => ['label' => 'Online Transfer / UPI', 'desc' => 'Direct IMPS/NEFT/UPI transfer'],
                            'cheque' => ['label' => 'Cheque Payment', 'desc' => 'Deposited bank cheque'],
                            'bank_transfer' => ['label' => 'Bank CDM / Branch Deposit', 'desc' => 'Direct CDM/counter deposit'],
                            'other' => ['label' => 'Other Payment', 'desc' => 'Miscellaneous approved payment mode'],
                        ];
                    @endphp

                    @foreach($modalMethods as $mKey => $mInfo)
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 hover:border-emerald-300 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 transition cursor-pointer">
                            <input type="checkbox"
                                   name="allowed_methods[]"
                                   value="{{ $mKey }}"
                                   @checked(in_array($mKey, $shopToCompanyData['setup']['allowed_methods'], true))
                                   class="mt-0.5 text-emerald-600 focus:ring-emerald-500 rounded cursor-pointer">
                            <div>
                                <span class="text-xs font-black text-slate-900">{{ $mInfo['label'] }}</span>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ $mInfo['desc'] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Default Bank Account & Relations --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-4">
                <div>
                    <label class="block text-xs font-black text-slate-900">Destination Account & Paid Relation</label>
                    <p class="text-[11px] text-slate-500">Default receiving bank account and the settlement relation tracking payments paid.</p>
                </div>

                @php
                    $accOptions = $shopToCompanyData['company_accounts']->map(fn($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'subtitle' => $a->bank_name ? $a->bank_name . ($a->account_number ? ' ('.$a->account_number.')' : '') : strtoupper($a->account_type),
                    ])->all();

                    $relOptions = $shopToCompanyData['relations']->map(fn($r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'subtitle' => 'Relation Type: ' . strtoupper($r->relation_type),
                    ])->all();
                @endphp

                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Default Receiving Company Account</label>
                        <x-cashbook.custom-select
                            name="default_account_id"
                            id="shop_to_company_default_account"
                            :options="$accOptions"
                            :selected="$shopToCompanyData['setup']['default_account']?->id"
                            placeholder="(Default Company Bank Account)"
                            :searchable="true"
                        />
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Payment-Paid Settlement Relation</label>
                        <x-cashbook.custom-select
                            name="paid_relation_id"
                            id="shop_to_company_paid_relation"
                            :options="$relOptions"
                            :selected="$shopToCompanyData['setup']['paid_relation']?->id"
                            placeholder="Select Payment-Paid Relation..."
                            :searchable="true"
                        />
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-cashbook.payment-modal>

<script>
function saveShopToCompanySettings() {
    const btn = document.getElementById('modal-save-shop-to-company-btn');
    const form = document.getElementById('shop-to-company-modal-form');
    if (!form || !btn) return;

    const formData = new FormData(form);
    const allowedMethods = [];
    for (let [key, value] of formData.entries()) {
        if (key === 'allowed_methods[]') {
            allowedMethods.push(value);
        }
    }

    const payload = {
        allowed_methods: allowedMethods,
        default_account_id: formData.get('default_account_id') ? parseInt(formData.get('default_account_id')) : null,
        paid_relation_id: formData.get('paid_relation_id') ? parseInt(formData.get('paid_relation_id')) : null,
    };

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
    if (window.lucide) window.lucide.createIcons();

    fetch('{{ route("admin.cashbook.settings.shop.payments.shop-to-company.save", $shopKey) }}', {
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
            closePaymentModal('shop-to-company');
            window.showPaymentToast(data.message || 'Shop to Company settings saved.', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alert(data.message || 'Failed to save settings.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred while saving.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i><span>Save Settings</span>';
        if (window.lucide) window.lucide.createIcons();
    });
}
</script>
