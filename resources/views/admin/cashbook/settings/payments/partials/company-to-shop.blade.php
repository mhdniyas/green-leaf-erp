<div class="space-y-6">
    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-blue-700">Company Disbursements & Funding</span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">COMPANY → SHOP</h2>
            <p class="text-xs text-slate-500 mt-0.5">Overview of existing company-to-shop funding mechanisms including direct reimbursements, petty cash injections, and company-settled vendor bills.</p>
        </div>
        <div>
            <a href="{{ route('admin.cashbook.shop.overview', $shopKey) }}"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition cursor-pointer shadow-2xs">
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                <span>View Shop Ledger Transactions</span>
            </a>
        </div>
    </div>

    {{-- 1. OUTPUT SECTION (Financial Results) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Financial Output</span>
                <h3 class="text-sm font-black text-slate-900">Company Disbursements This Month</h3>
            </div>
            <span class="text-xs font-bold text-slate-500">{{ \Carbon\Carbon::parse($startDate)->format('M Y') }}</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-700">Company Paid Shop</span>
                <div class="text-base font-black text-blue-900 mt-1">₹{{ number_format($companyToShopData['output']['company_paid_shop_month'], 2) }}</div>
                <p class="text-[10px] text-slate-400 mt-0.5">Direct shop reimbursement</p>
            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Petty Injected</span>
                <div class="text-base font-black text-amber-900 mt-1">₹{{ number_format($companyToShopData['output']['petty_funded_month'], 2) }}</div>
                <p class="text-[10px] text-slate-400 mt-0.5">Company to petty drawer</p>
            </div>

            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Pending Match</span>
                <div class="text-base font-black text-slate-800 mt-1">₹{{ number_format($companyToShopData['output']['pending'], 2) }}</div>
                <p class="text-[10px] text-slate-400 mt-0.5">Awaiting statement match</p>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Bank Verified</span>
                <div class="text-base font-black text-emerald-900 mt-1">₹{{ number_format($companyToShopData['output']['verified'], 2) }}</div>
                <p class="text-[10px] text-slate-400 mt-0.5">Matched with statement</p>
            </div>

            <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700">Reconciled</span>
                <div class="text-base font-black text-indigo-900 mt-1">₹{{ number_format($companyToShopData['output']['reconciled'], 2) }}</div>
                <p class="text-[10px] text-slate-400 mt-0.5">Finalized in accounting</p>
            </div>
        </div>
    </div>

    {{-- 2. CURRENT SETUP (Supported Flow Explanations) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">System Architecture</span>
            <h3 class="text-sm font-black text-slate-900">Supported Company → Shop Flows</h3>
            <p class="text-xs text-slate-500 mt-0.5">Existing ledger pathways where company funds flow to or on behalf of this shop.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($companyToShopData['flows'] as $flow)
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="font-black text-xs text-slate-900">{{ $flow['name'] }}</span>
                            <span class="font-mono text-[10px] font-bold text-slate-500 bg-white px-1.5 py-0.5 rounded border border-slate-200">{{ $flow['code'] }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">{{ $flow['description'] }}</p>
                    </div>

                    <div class="border-t border-slate-200/80 pt-2.5 space-y-1.5 text-[11px]">
                        <div class="flex items-center justify-between text-slate-600">
                            <span class="font-medium text-slate-400">Source:</span>
                            <span class="font-bold text-slate-800">{{ $flow['source'] }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span class="font-medium text-slate-400">Destination:</span>
                            <span class="font-bold text-slate-800">{{ $flow['destination'] }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span class="font-medium text-slate-400">Shop Balance:</span>
                            <span class="font-bold text-blue-700">{{ $flow['shop_balance_effect'] }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span class="font-medium text-slate-400">Petty Balance:</span>
                            <span class="font-bold text-amber-700">{{ $flow['petty_effect'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs text-blue-900 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="info" class="h-4 w-4 text-blue-600 shrink-0"></i>
                <span>These flows are operational and recorded through banking statements and journal entries. No artificial settings are required here.</span>
            </div>
            <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="font-bold text-blue-800 hover:underline shrink-0 ml-2">
                Go to Bank Reconciliation →
            </a>
        </div>
    </div>
</div>
