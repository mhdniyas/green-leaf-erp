<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-violet-700">Direct-to-Bank Collections</span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">COMPANY COLLECTIONS</h2>
            <p class="text-xs text-slate-500 mt-0.5">Configure which income categories deposit directly into Company bank accounts (e.g. Paytm, Card, UPI) rather than shop cash drawer.</p>
        </div>
        <div>
            <button type="button"
                    data-payment-modal-open="company-collections"
                    class="inline-flex items-center gap-2 rounded-xl bg-violet-700 px-4 py-2.5 text-xs font-black text-white hover:bg-violet-800 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="edit-3" class="h-4 w-4"></i>
                <span>Edit Settings</span>
            </button>
        </div>
    </div>

    {{-- 1. CURRENT SETUP --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Active Setup</span>
                <h3 class="text-sm font-black text-slate-900">Configured Direct Collection Categories</h3>
            </div>
            <button type="button"
                    data-payment-modal-open="company-collections"
                    class="text-xs font-black text-violet-700 hover:text-violet-900 flex items-center gap-1">
                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                <span>Modify Mappings</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($collectionsData['categories'] as $cat)
                @php
                    $isDirect = $cat['is_direct'];
                    $acc = $cat['company_account'] ?? null;
                @endphp
                <div class="rounded-xl border {{ $isDirect ? 'border-violet-200 bg-violet-50/20' : 'border-slate-200 bg-slate-50/40' }} p-3.5 space-y-2.5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="text-xs font-black text-slate-900">{{ $cat['name'] }}</h4>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $cat['header_name'] }}</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $isDirect ? 'bg-violet-100 text-violet-800 border border-violet-200' : 'bg-slate-100 text-slate-600' }}">
                                {{ $isDirect ? 'Direct Bank' : 'Cash Drawer' }}
                            </span>
                        </div>

                        <div class="mt-2 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between py-1 border-b border-slate-100">
                                <span class="text-[11px] text-slate-500 font-medium">Receiving Account:</span>
                                <span class="font-bold {{ $acc ? 'text-violet-800' : 'text-slate-400 italic' }}">
                                    {{ $acc ? $acc->name : 'Shop Drawer' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-slate-100">
                                <span class="text-[11px] text-slate-500 font-medium">Settlement:</span>
                                <span class="font-bold text-slate-700 truncate max-w-[140px]" title="{{ $cat['settlement_label'] }}">
                                    {{ $cat['settlement_label'] }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-[11px] text-slate-500 font-medium">Report Heading:</span>
                                <span class="font-bold text-slate-700">
                                    {{ $cat['report_label'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-400 font-medium">This Month:</span>
                        <span class="font-black text-slate-900">₹{{ number_format($cat['month_amount'], 2) }}</span>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-6 text-center text-xs text-slate-400">
                    No categories configured for this shop.
                </div>
            @endforelse
        </div>
    </div>

    {{-- 2. OUTPUT SECTION (Financial Results) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Financial Impact</span>
                <h3 class="text-sm font-black text-slate-900">Direct Collections Output ({{ \Carbon\Carbon::parse($startDate)->format('M Y') }})</h3>
            </div>
            <span class="text-xs font-bold text-slate-500">{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Direct Collections</span>
                <div class="text-base font-black text-slate-900 mt-1">₹{{ number_format($collectionsData['output']['total_direct_collections'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Verified</span>
                <div class="text-base font-black text-emerald-800 mt-1">₹{{ number_format($collectionsData['output']['verified'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Pending Verification</span>
                <div class="text-base font-black text-amber-800 mt-1">₹{{ number_format($collectionsData['output']['pending_verification'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700">Reconciled in Bank</span>
                <div class="text-base font-black text-indigo-800 mt-1">₹{{ number_format($collectionsData['output']['reconciled'], 2) }}</div>
            </div>
        </div>

        {{-- Company Account Split --}}
        @if(!empty($collectionsData['output']['account_split']))
            <div class="border-t border-slate-100 pt-3">
                <span class="text-xs font-bold text-slate-700 block mb-2">Company Bank Split:</span>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($collectionsData['output']['account_split'] as $acc)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2.5 text-xs">
                            <span class="font-bold text-slate-800 truncate">{{ $acc['name'] }}</span>
                            <span class="font-black text-violet-700 ml-2">₹{{ number_format($acc['total'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Bottom Edit Button --}}
    <div class="flex justify-end pt-2">
        <button type="button"
                data-payment-modal-open="company-collections"
                class="inline-flex items-center gap-2 rounded-xl bg-violet-700 px-5 py-2.5 text-xs font-black text-white hover:bg-violet-800 transition cursor-pointer shadow-sm active:scale-98">
            <i data-lucide="edit-3" class="h-4 w-4"></i>
            <span>Edit Settings</span>
        </button>
    </div>
</div>

{{-- MODAL: COMPANY COLLECTIONS --}}
<x-cashbook.payment-modal
    modalId="company-collections"
    title="Company Collections Settings"
    subtitle="Direct-to-Bank Mappings"
    shopName="{{ $currentShop->name }}"
    period="{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}"
    maxWidth="max-w-4xl"
    saveFunction="saveCompanyCollectionsSettings()"
    saveBtnId="modal-save-company-collections-btn"
    saveBtnText="Save Mappings">

    <div class="space-y-4">
        <div class="rounded-2xl border border-violet-100 bg-violet-50/50 p-4 text-xs text-violet-900 leading-relaxed">
            <p class="font-black text-violet-950">Direct Bank Collections Mapping</p>
            <p class="mt-0.5 text-violet-800">
                Map each category to its receiving Company Account. Transactions in these categories will flow directly into company bank balances instead of the shop's physical cash drawer.
            </p>
        </div>

        <form id="company-collections-modal-form" class="space-y-3">
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
                @foreach($collectionsData['categories'] as $cat)
                    @php
                        $setting = $cat['setting'];
                        $accOptions = $collectionsData['company_accounts']->map(fn($a) => [
                            'id' => $a->id,
                            'name' => $a->name,
                            'subtitle' => $a->bank_name ? $a->bank_name . ($a->account_number ? ' ('.$a->account_number.')' : '') : null,
                        ])->all();
                    @endphp
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/40 transition">
                        <div class="space-y-1 sm:w-1/3">
                            <div class="font-black text-slate-900 text-xs flex items-center gap-2">
                                <span>{{ $cat['name'] }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">({{ $cat['header_name'] }})</span>
                            </div>
                            <p class="text-[11px] text-slate-500 line-clamp-1 italic">{{ $cat['explanation'] }}</p>

                            {{-- Linked Read-Only References --}}
                            <div class="flex items-center gap-2 pt-1 text-[10px]">
                                <span class="text-slate-400">Settlement: <strong class="text-slate-600">{{ $cat['settlement_label'] }}</strong></span>
                                <span>•</span>
                                <span class="text-slate-400">Report: <strong class="text-slate-600">{{ $cat['report_label'] }}</strong></span>
                            </div>
                        </div>

                        <div class="sm:w-1/2">
                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Receiving Company Account</label>
                            <x-cashbook.custom-select
                                name="mappings[{{ $setting->id }}][company_account_id]"
                                id="mapping_account_{{ $setting->id }}"
                                :options="$accOptions"
                                :selected="$setting->company_account_id"
                                placeholder="(None - Remains Shop Cash Drawer)"
                                :searchable="true"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </form>
    </div>
</x-cashbook.payment-modal>

<script>
function saveCompanyCollectionsSettings() {
    const btn = document.getElementById('modal-save-company-collections-btn');
    const form = document.getElementById('company-collections-modal-form');
    if (!form || !btn) return;

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
    if (window.lucide) window.lucide.createIcons();

    const formData = new FormData(form);
    const mappings = {};

    for (const [key, value] of formData.entries()) {
        const match = key.match(/mappings\[(\d+)\]\[(\w+)\]/);
        if (match) {
            const id = match[1];
            const field = match[2];
            if (!mappings[id]) mappings[id] = {};
            mappings[id][field] = value || null;
            mappings[id]['is_direct'] = value ? 1 : 0;
        }
    }

    fetch('{{ route("admin.cashbook.settings.shop.payments.company-collections.save", $shopKey) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ mappings: mappings })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePaymentModal('company-collections');
            window.showPaymentToast(data.message || 'Company Collections updated.', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alert(data.message || 'Error saving mappings.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to save company collections.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i><span>Save Mappings</span>';
        if (window.lucide) window.lucide.createIcons();
    });
}
</script>
