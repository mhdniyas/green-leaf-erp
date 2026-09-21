<div class="space-y-6">
    {{-- Header Banner --}}
    <div class="rounded-2xl border border-violet-100 bg-gradient-to-r from-violet-50 via-indigo-50/50 to-white p-5 sm:p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-violet-100/80 px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase text-violet-800">
                    <i data-lucide="compass" class="h-3 w-3"></i> Central Bridge
                </span>
                <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight mt-1">Shop ↔ Company Payments Overview</h2>
                <p class="text-xs text-slate-500 mt-1 max-w-2xl font-medium">
                    Payments coordinates money movement between shop cash drawers, direct company bank collections, petty reserves, daily settlement obligations, and reconciliation.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Period: {{ \Carbon\Carbon::parse($startDate)->format('M Y') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Financial Output Metrics (9 Core Cards) --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Current Period Financial Results</h3>
            <span class="text-[11px] font-medium text-slate-400">Live authoritative calculation</span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3.5">
            {{-- Card 1: Direct Company Collections --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Direct Collections</span>
                    <i data-lucide="credit-card" class="h-4 w-4 text-violet-500"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-slate-900 tracking-tight">₹{{ number_format($overviewData['cards']['direct_collections'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Paytm / Card / UPI directly into Company Bank</p>
                </div>
            </div>

            {{-- Card 2: Shop Paid Company --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Shop Paid Company</span>
                    <i data-lucide="arrow-up-right" class="h-4 w-4 text-emerald-500"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-emerald-700 tracking-tight">₹{{ number_format($overviewData['cards']['shop_paid_company'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Approved manual shop remittances</p>
                </div>
            </div>

            {{-- Card 3: Company Paid Shop --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Company Paid Shop</span>
                    <i data-lucide="arrow-down-left" class="h-4 w-4 text-blue-500"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-blue-700 tracking-tight">₹{{ number_format($overviewData['cards']['company_paid_shop'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Reimbursements & company funding</p>
                </div>
            </div>

            {{-- Card 4: Petty Balance --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Petty Balance</span>
                    <i data-lucide="coins" class="h-4 w-4 text-amber-500"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-slate-900 tracking-tight">₹{{ number_format($overviewData['cards']['petty_balance'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Current operational petty drawer balance</p>
                </div>
            </div>

            {{-- Card 5: Settlement Position --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Settlement Due</span>
                    <i data-lucide="scale" class="h-4 w-4 text-indigo-500"></i>
                </div>
                <div>
                    <div class="text-lg font-black {{ $overviewData['cards']['settlement_position'] >= 0 ? 'text-amber-700' : 'text-emerald-700' }} tracking-tight">
                        ₹{{ number_format(abs($overviewData['cards']['settlement_position']), 2) }}
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">{{ $overviewData['cards']['settlement_position'] >= 0 ? 'Shop owes Company' : 'Company owes Shop' }}</p>
                </div>
            </div>

            {{-- Card 6: Allocated --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Allocated</span>
                    <i data-lucide="check-check" class="h-4 w-4 text-emerald-600"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-slate-900 tracking-tight">₹{{ number_format($overviewData['cards']['allocated_amount'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Payments cleared against ledger dues</p>
                </div>
            </div>

            {{-- Card 7: Unallocated --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Unallocated</span>
                    <i data-lucide="clock" class="h-4 w-4 text-amber-600"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-amber-800 tracking-tight">₹{{ number_format($overviewData['cards']['unallocated_amount'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Received payments awaiting clearance</p>
                </div>
            </div>

            {{-- Card 8: Pending Verification --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Pending Verification</span>
                    <i data-lucide="shield-alert" class="h-4 w-4 text-rose-500"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-rose-700 tracking-tight">₹{{ number_format($overviewData['cards']['pending_verification'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Submitted by shop, unconfirmed</p>
                </div>
            </div>

            {{-- Card 9: Reconciled --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between col-span-2 sm:col-span-1">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Bank Reconciled</span>
                    <i data-lucide="badge-check" class="h-4 w-4 text-indigo-600"></i>
                </div>
                <div>
                    <div class="text-lg font-black text-indigo-900 tracking-tight">₹{{ number_format($overviewData['cards']['reconciled_amount'], 2) }}</div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Finalized with bank statement entry</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Bridge Lifecycle Flow Diagram --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">How Payments Flow Through The System</h3>
            <p class="text-xs text-slate-500 mt-0.5">Understanding the lifecycle of each rupee in this shop</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 pt-2 text-center text-xs">
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 flex flex-col items-center justify-center">
                <div class="h-8 w-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-black mb-1.5">1</div>
                <div class="font-extrabold text-slate-900">Sales Categories</div>
                <p class="text-[11px] text-slate-500 mt-0.5">{{ $overviewData['lifecycle_summary']['direct_categories_count'] }} direct bank, rest cash</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 flex flex-col items-center justify-center">
                <div class="h-8 w-8 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center font-black mb-1.5">2</div>
                <div class="font-extrabold text-slate-900">Settlement</div>
                <p class="text-[11px] text-slate-500 mt-0.5">{{ $overviewData['lifecycle_summary']['payable_relation_name'] }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 flex flex-col items-center justify-center">
                <div class="h-8 w-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-black mb-1.5">3</div>
                <div class="font-extrabold text-slate-900">Company Relation</div>
                <p class="text-[11px] text-slate-500 mt-0.5">Direct Bank vs Shop Drawer</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 flex flex-col items-center justify-center">
                <div class="h-8 w-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-black mb-1.5">4</div>
                <div class="font-extrabold text-slate-900">Company Account</div>
                <p class="text-[11px] text-slate-500 mt-0.5">{{ $overviewData['lifecycle_summary']['company_accounts_count'] }} enabled accounts</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 flex flex-col items-center justify-center">
                <div class="h-8 w-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-black mb-1.5">5</div>
                <div class="font-extrabold text-slate-900">Allocation</div>
                <p class="text-[11px] text-slate-500 mt-0.5">Oldest-first ledger clearance</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 flex flex-col items-center justify-center">
                <div class="h-8 w-8 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center font-black mb-1.5">6</div>
                <div class="font-extrabold text-slate-900">Reconciliation</div>
                <p class="text-[11px] text-slate-500 mt-0.5">Matched with bank statement</p>
            </div>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-xs text-amber-900 flex items-start gap-2.5">
            <i data-lucide="info" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
            <div>
                <span class="font-bold">Summary only:</span> To make changes, use the dedicated tabs above (<span class="font-semibold">Company Collections</span>, <span class="font-semibold">Settlement</span>, <span class="font-semibold">Allocation</span>, etc.). Each section saves independently.
            </div>
        </div>
    </div>
</div>
