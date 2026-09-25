@php
    $hasVendorCashPurchase = $entrySettings->contains(fn ($setting) => $setting->isVendorPurchaseCash());
    $hasVendorCreditPurchase = $entrySettings->contains(fn ($setting) => $setting->isVendorPurchaseCredit());
@endphp

<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-700">Financial Reports</span>
                <span class="text-slate-300">•</span>
                <span class="text-[10px] font-bold text-slate-500">Period: {{ \Carbon\Carbon::parse($startDate)->format('F Y') }}</span>
            </div>
            <h2 class="text-xl font-black text-slate-900 tracking-tight mt-0.5">SHOP SALES REPORT</h2>
            <p class="text-xs text-slate-500 mt-0.5">Configure heading sources (Headers, Categories, Product Totals) for the Shop Sales Report.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.cashbook.shop.sales-report', ['shop' => $shopKey, 'month' => $month]) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer shadow-2xs">
                <span>View Report</span>
                <i data-lucide="external-link" class="h-3.5 w-3.5 text-slate-400"></i>
            </a>
            <button type="button"
                    data-payment-modal-open="reports"
                    class="inline-flex items-center gap-2 rounded-xl bg-rose-700 px-4 py-2.5 text-xs font-black text-white hover:bg-rose-800 transition cursor-pointer shadow-sm active:scale-98">
                <i data-lucide="edit-3" class="h-4 w-4"></i>
                <span>Edit Report Settings</span>
            </button>
        </div>
    </div>

    {{-- Current Period Banner --}}
    <div class="rounded-2xl border border-rose-100 bg-rose-50/40 p-4 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 text-rose-700 font-black">
                <i data-lucide="calendar" class="h-5 w-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-700">Active Reporting Period</span>
                <h3 class="text-sm font-black text-slate-900">{{ \Carbon\Carbon::parse($startDate)->format('F Y') }}</h3>
            </div>
        </div>
        <div class="text-right">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Single Engine Rule</span>
            <span class="text-xs font-black text-rose-800 font-mono">Monthly Total ≡ ∑(Daily Rows)</span>
        </div>
    </div>

    {{-- 1. CURRENT SETUP --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Current Setup</span>
                <h3 class="text-sm font-black text-slate-900">Configured Headings & Source Mappings</h3>
            </div>
            <button type="button"
                    data-payment-modal-open="reports"
                    class="text-xs font-black text-rose-700 hover:text-rose-900 flex items-center gap-1 cursor-pointer">
                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                <span>Edit Sources</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @php
                $allowedHeadingKeys = ['total_sales', 'rent_expense', 'cash_purchase', 'other_expense', 'net_operating_balance', 'gl_bills_ref'];
            @endphp
            @foreach($reportHeadingsData['headings'] as $key => $heading)
                @if(in_array($key, $allowedHeadingKeys, true))
                    <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-2.5 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black text-slate-900 uppercase tracking-wider">{{ $heading['title'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase {{ $heading['role'] === 'add' ? 'bg-emerald-100 text-emerald-800' : ($heading['role'] === 'subtract' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200/70 text-slate-700') }}">
                                    {{ $heading['role'] }}
                                </span>
                            </div>

                            <div class="text-xs text-slate-500 mt-2">
                                @if($heading['is_computed'])
                                    @php
                                        $balanceTerms = [
                                            'total_sales' => 'Total Sales',
                                            'rent_expense' => 'Rent',
                                            'cash_purchase' => 'Purchase',
                                            'other_expense' => 'Other Expenses',
                                        ];
                                        $balanceFormula = $heading['formula'] ?? ['total_sales' => 'add', 'rent_expense' => 'subtract', 'cash_purchase' => 'subtract', 'other_expense' => 'subtract'];
                                    @endphp
                                    <span class="italic text-slate-400 text-[11px]">Formula: {{ collect($balanceTerms)->map(fn ($label, $key) => match ($balanceFormula[$key] ?? 'ignore') { 'add' => '+ '.$label, 'subtract' => '− '.$label, default => null })->filter()->implode(' ') }}</span>
                                @elseif($heading['is_informational'])
                                    <span class="italic text-slate-400 text-[11px]">Reference: Green Leaf ERP Invoices final total</span>
                                @else
                                    <div class="flex flex-wrap gap-1 pt-1">
                                        @forelse($heading['sources'] as $src)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white border border-slate-200 text-slate-700 shadow-2xs">
                                                <span class="font-black text-[9px] uppercase text-slate-400">[{{ $src['type'] }}]</span>
                                                <span>{{ $src['name'] }}</span>
                                            </span>
                                        @empty
                                            <span class="text-slate-400 italic text-[11px]">No sources mapped</span>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if(isset($heading['total']))
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                                <span class="text-slate-400 font-medium">Month Total:</span>
                                <span class="font-black text-slate-900">₹{{ number_format($heading['total'], 2) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- 2. OUTPUT SECTION (Monthly Totals) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Output</span>
                <h3 class="text-sm font-black text-slate-900">Shop Sales Report Totals ({{ \Carbon\Carbon::parse($startDate)->format('F Y') }})</h3>
            </div>
            <a href="{{ route('admin.cashbook.shop.sales-report', ['shop' => $shopKey, 'month' => $month]) }}"
               target="_blank"
               class="text-xs font-black text-rose-700 hover:underline flex items-center gap-1">
                <span>Open Full Sheet</span>
                <i data-lucide="external-link" class="h-3 w-3"></i>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Total Sales</span>
                <div class="text-sm font-black text-emerald-800 mt-1">₹{{ number_format($reportHeadingsData['summary']['total_sales'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600">Rent</span>
                <div class="text-sm font-black text-slate-800 mt-1">₹{{ number_format($reportHeadingsData['summary']['total_rent'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600">Purchase</span>
                <div class="text-sm font-black text-slate-800 mt-1">₹{{ number_format($reportHeadingsData['summary']['total_purchase'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600">Other Expenses</span>
                <div class="text-sm font-black text-slate-800 mt-1">₹{{ number_format($reportHeadingsData['summary']['total_other_expense'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-800">Net Operating Balance</span>
                <div class="text-sm font-black text-rose-900 mt-1">₹{{ number_format($reportHeadingsData['summary']['net_total'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700">GL Bills Ref</span>
                <div class="text-sm font-black text-indigo-800 mt-1">₹{{ number_format($reportHeadingsData['summary']['gl_bills_total'], 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Bottom Action Buttons --}}
    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('admin.cashbook.shop.sales-report', ['shop' => $shopKey, 'month' => $month]) }}"
           target="_blank"
           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer shadow-2xs">
            <span>View Report</span>
            <i data-lucide="external-link" class="h-3.5 w-3.5 text-slate-400"></i>
        </a>
        <button type="button"
                data-payment-modal-open="reports"
                class="inline-flex items-center gap-2 rounded-xl bg-rose-700 px-5 py-2.5 text-xs font-black text-white hover:bg-rose-800 transition cursor-pointer shadow-sm active:scale-98">
            <i data-lucide="edit-3" class="h-4 w-4"></i>
            <span>Edit Report Settings</span>
        </button>
    </div>
</div>

{{-- MODAL: SHOP SALES REPORT SETTINGS --}}
<x-cashbook.payment-modal
    modalId="reports"
    title="Shop Sales Report Settings"
    subtitle="Heading & Source Mappings"
    shopName="{{ $currentShop->name }}"
    period="{{ \Carbon\Carbon::parse($startDate)->format('F Y') }}"
    maxWidth="max-w-4xl"
    saveFunction="saveReportModalConfig()"
    saveBtnId="modal-save-reports-btn"
    saveBtnText="Save Report Settings">

    <div class="space-y-5">
        {{-- Heading Selector for Shop Sales Report --}}
        @php
            $editableHeadings = [
                'total_sales' => ['title' => '1. Total Sales', 'role' => 'add', 'desc' => 'Gross income and sales revenues'],
                'rent_expense' => ['title' => '2. Rent', 'role' => 'subtract', 'desc' => 'Shop premises rent outflows'],
                'cash_purchase' => ['title' => '3. Purchase', 'role' => 'subtract', 'desc' => 'Direct cash procurement & purchases'],
                'other_expense' => ['title' => '4. Other Expenses', 'role' => 'subtract', 'desc' => 'Operational and sundry expenses'],
                'net_operating_balance' => ['title' => '5. Net Operating Balance', 'role' => 'balance', 'desc' => 'Choose the formula terms and signs'],
            ];

            $headingSelectOptions = collect($editableHeadings)->map(fn($h, $k) => [
                'id' => $k,
                'name' => $h['title'],
                'subtitle' => $h['desc'],
            ])->values()->all();
        @endphp

        <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Select Heading to Configure</label>
            <x-cashbook.custom-select
                name="modal_active_report_heading"
                id="modal_active_report_heading"
                :options="$headingSelectOptions"
                selected="total_sales"
                placeholder="Choose heading..."
                :searchable="false"
            />
        </div>

        {{-- Active Sources List for Selected Heading --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <span class="text-xs font-black text-slate-900">Mapped Sources for Active Heading</span>
                <span id="reports-modal-source-count" class="text-[10px] font-bold text-slate-400">0 sources</span>
            </div>

            <div id="reports-modal-sources-list" class="space-y-2 min-h-[60px]">
                {{-- Dynamic items list --}}
            </div>

            {{-- Duplicate Warning banner inside modal --}}
            <div id="reports-modal-duplicate-warning" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 flex items-start gap-2">
                <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
                <div>
                    <strong class="font-black text-amber-950">Duplicate Source Warning:</strong>
                    <p id="reports-modal-duplicate-msg" class="text-[11px] text-amber-800 mt-0.5"></p>
                </div>
            </div>
        </div>

        <div id="reports-modal-balance-formula" class="hidden rounded-2xl border border-indigo-200 bg-indigo-50/40 p-4 space-y-3">
            <div>
                <span class="text-xs font-black text-indigo-950 block">Net Operating Balance Formula</span>
                <span class="text-[11px] text-indigo-700">Choose whether each heading is added, subtracted, or ignored.</span>
            </div>
            <div id="reports-modal-balance-formula-terms" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
        </div>

        {{-- Add New Source to Heading --}}
        <div id="report-source-editor" class="rounded-2xl border border-rose-200 bg-rose-50/30 p-4 space-y-3">
            <span class="text-xs font-black text-rose-950 block">Add Source to Heading</span>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                {{-- Source Type --}}
                <div class="sm:col-span-4">
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Source Type</label>
                    <x-cashbook.custom-select
                        name="new_report_source_type"
                        id="new_report_source_type"
                        :options="[
                            ['value' => 'header', 'label' => 'Header Group'],
                            ['value' => 'category', 'label' => 'Single Category'],
                            ...($hasVendorCashPurchase ? [['value' => 'vendor_purchase_cash', 'label' => 'Vendor Cash / Debit Total']] : []),
                            ...($hasVendorCreditPurchase ? [['value' => 'vendor_purchase_credit', 'label' => 'Vendor Credit Total']] : [])
                        ]"
                        selected="header"
                        :searchable="false"
                    />
                </div>

                {{-- Source Item --}}
                <div class="sm:col-span-6" id="new_report_source_id_container">
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Select Source</label>
                    <div id="new_report_source_select_slot">
                        {{-- Injected dynamically by JS --}}
                    </div>
                </div>

                {{-- Add Button --}}
                <div class="sm:col-span-2">
                    <button type="button"
                            onclick="addReportSourceFromModal()"
                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-700 px-3 py-2.5 text-xs font-black text-white hover:bg-rose-800 transition cursor-pointer shadow-sm active:scale-98">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span>Add</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Special Headings Note --}}
        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-2 text-xs">
            <span class="font-black text-slate-800 uppercase tracking-wider text-[10px] block">Shop Sales Report Calculated & Reference Headings</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-slate-600">
                <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                    <span class="font-bold text-slate-900 block">5. Net Operating Balance</span>
                    <span class="text-[11px] text-slate-500">Editable formula: choose Add, Subtract, or Ignore for each report heading.</span>
                </div>
                <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                    <span class="font-bold text-slate-900 block">6. GL Bills Ref</span>
                    <span class="text-[11px] text-slate-500">System informational reference displaying Green Leaf ERP invoice final totals.</span>
                </div>
            </div>
        </div>
    </div>
</x-cashbook.payment-modal>

<script>
let reportsWorkingHeadings = @json($reportHeadingsData['headings']);
const reportsAvailableHeaders = @json($settlementData['headers'] ?? []);
const reportsAvailableSettings = @json($entrySettings ?? []);
const balanceFormulaTerms = {
    total_sales: 'Total Sales',
    rent_expense: 'Rent',
    cash_purchase: 'Purchase',
    other_expense: 'Other Expenses'
};
const defaultBalanceFormula = {
    total_sales: 'add',
    rent_expense: 'subtract',
    cash_purchase: 'subtract',
    other_expense: 'subtract'
};

function getActiveReportHeadingKey() {
    const input = document.getElementById('modal_active_report_heading');
    return input && input.value ? input.value : 'total_sales';
}

function renderReportsModalSources() {
    const headingKey = getActiveReportHeadingKey();
    const container = document.getElementById('reports-modal-sources-list');
    const countBadge = document.getElementById('reports-modal-source-count');
    if (!container || !headingKey) return;

    const heading = reportsWorkingHeadings[headingKey] || { sources: [] };
    const sources = heading.sources || [];
    const formulaEditor = document.getElementById('reports-modal-balance-formula');
    const sourceEditor = document.getElementById('report-source-editor');

    if (headingKey === 'net_operating_balance') {
        if (!reportsWorkingHeadings[headingKey]) {
            reportsWorkingHeadings[headingKey] = { formula: { ...defaultBalanceFormula }, sources: [] };
        }
        reportsWorkingHeadings[headingKey].formula = { ...defaultBalanceFormula, ...(reportsWorkingHeadings[headingKey].formula || {}) };
        countBadge.textContent = 'Formula';
        container.innerHTML = '<div class="py-4 text-center text-xs text-slate-500">This calculated heading has no sources. Configure its formula below.</div>';
        formulaEditor?.classList.remove('hidden');
        sourceEditor?.classList.add('hidden');
        renderBalanceFormulaTerms();
        checkReportsDuplicates(headingKey);
        return;
    }

    formulaEditor?.classList.add('hidden');
    sourceEditor?.classList.remove('hidden');

    countBadge.textContent = sources.length + ' source' + (sources.length === 1 ? '' : 's');

    if (sources.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-xs text-slate-400 italic">No sources mapped to this heading. Add a source below.</div>';
        checkReportsDuplicates(headingKey);
        return;
    }

    let html = '';
    sources.forEach((src, index) => {
        const typeBadge = src.type === 'header' ? 'Header' : (src.type === 'category' ? 'Category' : (src.type === 'header_with_product_total' ? 'Header + Products' : 'Product Total'));
        html += `
            <div class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 text-xs">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-100 text-rose-800">
                        ${typeBadge}
                    </span>
                    <div class="font-bold text-slate-900">${src.name}</div>
                </div>
                <button type="button"
                        onclick="removeReportModalSource('${headingKey}', ${index})"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-700 hover:bg-rose-50 transition cursor-pointer"
                        title="Remove Source">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                </button>
            </div>
        `;
    });

    container.innerHTML = html;
    if (window.lucide) window.lucide.createIcons();
    checkReportsDuplicates(headingKey);
}

function renderBalanceFormulaTerms() {
    const container = document.getElementById('reports-modal-balance-formula-terms');
    const formula = reportsWorkingHeadings.net_operating_balance?.formula || defaultBalanceFormula;
    if (!container) return;

    container.innerHTML = Object.entries(balanceFormulaTerms).map(([key, label]) => `
        <label class="flex items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-white px-3 py-2.5 text-xs font-bold text-slate-800">
            <span>${label}</span>
            <select class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-indigo-500 focus:outline-none" onchange="setBalanceFormulaOperation('${key}', this.value)">
                <option value="add" ${formula[key] === 'add' ? 'selected' : ''}>Add (+)</option>
                <option value="subtract" ${formula[key] === 'subtract' ? 'selected' : ''}>Subtract (−)</option>
                <option value="ignore" ${formula[key] === 'ignore' ? 'selected' : ''}>Ignore</option>
            </select>
        </label>
    `).join('');
}

function setBalanceFormulaOperation(key, operation) {
    reportsWorkingHeadings.net_operating_balance = reportsWorkingHeadings.net_operating_balance || { sources: [], formula: { ...defaultBalanceFormula } };
    reportsWorkingHeadings.net_operating_balance.formula = {
        ...defaultBalanceFormula,
        ...(reportsWorkingHeadings.net_operating_balance.formula || {}),
        [key]: operation
    };
}

function removeReportModalSource(headingKey, index) {
    if (reportsWorkingHeadings[headingKey] && reportsWorkingHeadings[headingKey].sources) {
        reportsWorkingHeadings[headingKey].sources.splice(index, 1);
        renderReportsModalSources();
    }
}

function renderNewReportSourceSelectSlot(type) {
    const slot = document.getElementById('new_report_source_select_slot');
    if (!slot) return;

    let options = [];

    if (type === 'header') {
        options = reportsAvailableHeaders.flatMap(h => {
            if (!h.product_tagging_enabled) {
                return [{ id: `header:${h.id}`, name: h.name, subtitle: 'Header total' }];
            }

            return [
                { id: `product_total:${h.id}`, name: `${h.name} — Product Total Only`, subtitle: 'Count product-tagged rows only' },
                { id: `header_with_product_total:${h.id}`, name: `${h.name} + Product Total (Full)`, subtitle: 'Count header categories and product rows' },
                { id: `header:${h.id}`, name: `${h.name} — Other Totals (Skip Products)`, subtitle: 'Count header categories without product rows' }
            ];
        });
    } else if (type === 'vendor_purchase_cash' || type === 'vendor_purchase_credit') {
        const isCash = type === 'vendor_purchase_cash';
        options = [{
            id: isCash ? 'cash' : 'credit',
            name: isCash ? 'Vendor Cash / Debit Purchases' : 'Vendor Credit Purchases',
            subtitle: 'Shop purchase invoices with product breakdown'
        }];
    } else {
        // Individual Categories (both with and without headers)
        options = reportsAvailableSettings.filter(s => !(s.header_group && s.header_group.product_tagging_enabled)).map(s => ({
            id: s.id,
            name: s.entry_type ? s.entry_type.name : (s.display_name || s.entry_name),
            subtitle: (s.header_group ? s.header_group.name : 'Non-Header Category')
        }));
    }

    const defaultVal = options.length > 0 ? options[0].id : '';
    const defaultLabel = options.length > 0 ? options[0].name : 'Select source...';

    slot.innerHTML = `
        <div class="custom-select-wrapper relative w-full" id="new_report_source_item_id_wrapper" data-custom-select>
            <input type="hidden" name="new_report_source_item_id" id="new_report_source_item_id" value="${defaultVal}">
            <button type="button" class="custom-select-trigger flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-800 shadow-2xs hover:border-slate-400 focus:border-rose-600 cursor-pointer">
                <span class="custom-select-label truncate text-slate-900 font-bold">${defaultLabel}</span>
                <i data-lucide="chevron-down" class="custom-select-chevron h-4 w-4 shrink-0 text-slate-400"></i>
            </button>
            <div class="custom-select-menu absolute left-0 right-0 z-50 mt-1.5 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                <div class="custom-select-options max-h-48 overflow-y-auto space-y-0.5 scrollbar-thin">
                    ${options.map(opt => `
                        <div class="custom-select-option flex items-center justify-between rounded-xl px-3 py-2 text-xs hover:bg-rose-50/80 hover:text-rose-900 text-slate-800 cursor-pointer ${String(opt.id) === String(defaultVal) ? 'bg-rose-50 text-rose-800 font-black' : 'font-semibold'}"
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

function addReportSourceFromModal() {
    const headingKey = getActiveReportHeadingKey();
    const typeInput = document.getElementById('new_report_source_type');
    const itemInput = document.getElementById('new_report_source_item_id');

    if (!headingKey || !typeInput || !itemInput || !itemInput.value) return;

    let type = typeInput.value;
    let id = itemInput.value;

    if (type === 'header' && String(id).includes(':')) {
        [type, id] = String(id).split(':', 2);
    }

    let name = 'Source #' + id;
    if (type === 'header') {
        const found = reportsAvailableHeaders.find(h => String(h.id) === String(id));
        if (found) name = found.product_tagging_enabled ? found.name + ' — Other Totals (Skip Products)' : found.name;
    } else if (type === 'product_total') {
        const found = reportsAvailableHeaders.find(h => String(h.id) === String(id));
        name = found ? found.name + ' — Product Total Only' : 'Tagged Header Product Total';
    } else if (type === 'header_with_product_total') {
        const found = reportsAvailableHeaders.find(h => String(h.id) === String(id));
        name = found ? found.name + ' + Product Total (Full)' : 'Header + Product Total (Full)';
    } else if (type === 'vendor_purchase_cash') {
        name = 'Vendor Cash / Debit Purchases';
    } else if (type === 'vendor_purchase_credit') {
        name = 'Vendor Credit Purchases';
    } else {
        const found = reportsAvailableSettings.find(s => String(s.id) === String(id));
        if (found) name = found.entry_type ? found.entry_type.name : (found.display_name || found.entry_name);
    }

    if (!reportsWorkingHeadings[headingKey]) {
        reportsWorkingHeadings[headingKey] = { sources: [] };
    }
    if (!reportsWorkingHeadings[headingKey].sources) {
        reportsWorkingHeadings[headingKey].sources = [];
    }

    // Check duplicate within same heading
    const existing = reportsWorkingHeadings[headingKey].sources.find(s => s.type === type && String(s.id) === String(id));
    if (existing) {
        alert(name + ' is already added to this heading.');
        return;
    }

    reportsWorkingHeadings[headingKey].sources.push({
        type: type,
        id: type === 'vendor_purchase_cash' || type === 'vendor_purchase_credit' ? id : parseInt(id, 10),
        name: name
    });

    renderReportsModalSources();
}

function checkReportsDuplicates(headingKey) {
    const warnBox = document.getElementById('reports-modal-duplicate-warning');
    const warnMsg = document.getElementById('reports-modal-duplicate-msg');
    const saveBtn = document.getElementById('modal-save-reports-btn');
    if (!warnBox || !warnMsg || !headingKey) return;

    const sources = reportsWorkingHeadings[headingKey]?.sources || [];
    const headerIds = sources.filter(s => s.type === 'header' || s.type === 'header_with_product_total').map(s => parseInt(s.id, 10));
    const categoryIds = sources.filter(s => s.type === 'category').map(s => parseInt(s.id, 10));

    let duplicates = [];
    categoryIds.forEach(catId => {
        const catSetting = reportsAvailableSettings.find(s => s.id === catId);
        if (catSetting && catSetting.header_group_id && headerIds.includes(catSetting.header_group_id)) {
            const header = reportsAvailableHeaders.find(h => h.id === catSetting.header_group_id);
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

function saveReportModalConfig() {
    const btn = document.getElementById('modal-save-reports-btn');
    if (!btn) return;

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
    if (window.lucide) window.lucide.createIcons();

    const payload = {
        month: '{{ $month }}',
        headings: reportsWorkingHeadings
    };

    fetch('{{ route("admin.cashbook.settings.shop.payments.report-headings.save", $shopKey) }}', {
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
            closePaymentModal('reports');
            window.showPaymentToast(data.message || 'Shop Sales Report settings saved successfully.', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alert(data.message || 'Failed to save report configuration.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred while saving.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i><span>Save Report Settings</span>';
        if (window.lucide) window.lucide.createIcons();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    renderNewReportSourceSelectSlot('header');

    const typeInput = document.getElementById('new_report_source_type');
    if (typeInput) {
        typeInput.addEventListener('change', () => {
            renderNewReportSourceSelectSlot(typeInput.value);
        });
    }

    const headingInput = document.getElementById('modal_active_report_heading');
    if (headingInput) {
        headingInput.addEventListener('change', () => {
            renderReportsModalSources();
        });
    }

    renderReportsModalSources();
});
</script>
