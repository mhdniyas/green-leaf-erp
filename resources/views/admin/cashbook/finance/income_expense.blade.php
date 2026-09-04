@extends('admin.cashbook.layouts.app')

@section('title', 'Company Income & Expense - Cashbook')

@section('header_title')
    <i data-lucide="arrow-left-right" class="h-5 w-5 text-emerald-600"></i> Company Income &amp; Expense
@endsection

@section('header_subtitle')
    Record and manage company-level income and expenses across bank and cash accounts.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-sm hover:bg-slate-50">
            <i data-lucide="shield-alert" class="h-4 w-4 text-amber-500"></i>
            <span class="hidden sm:inline">Reconciliation Hub</span>
        </a>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-add-income'))" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition">
            <i data-lucide="plus" class="h-4 w-4"></i>
            <span>+ Add Income</span>
        </button>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-add-expense'))" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-3.5 text-xs font-bold text-white shadow-sm hover:bg-rose-500 transition">
            <i data-lucide="minus" class="h-4 w-4"></i>
            <span>+ Add Expense</span>
        </button>
    </div>
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5" x-data="{
    showAddIncome: false,
    showAddExpense: false,
    showEdit: false,
    showDelete: false,
    showView: false,
    editDescErr: false,
    activeTab: '{{ $activeType }}',
    editEntry: {
        public_uuid: '',
        type: 'income',
        business_date: '{{ today()->toDateString() }}',
        company_accounting_category_id: '',
        category_name: '',
        company_account_uuid: '',
        amount: '',
        reference: '',
        description: '',
        reason: ''
    },
    deleteEntry: {
        public_uuid: '',
        type: 'expense',
        amount: '0.00',
        account_name: '',
        category_name: '',
        reason: ''
    },
    viewEntry: null,
    openEdit(entry) {
        this.editEntry = {
            public_uuid: entry.public_uuid,
            type: entry.type,
            business_date: entry.business_date_raw,
            company_accounting_category_id: entry.company_accounting_category_id,
            category_name: entry.category ? entry.category.name : '',
            company_account_uuid: entry.company_account_uuid,
            amount: entry.amount,
            reference: entry.reference || '',
            description: entry.description || '',
            reason: ''
        };
        this.editDescErr = false;
        this.showEdit = true;
    },
    openDelete(entry) {
        this.deleteEntry = {
            public_uuid: entry.public_uuid,
            type: entry.type,
            amount: parseFloat(entry.amount).toFixed(2),
            account_name: entry.company_account ? entry.company_account.name : 'Account',
            category_name: entry.category ? entry.category.name : 'Category',
            reason: ''
        };
        this.showDelete = true;
    },
    openView(entry) {
        this.viewEntry = entry;
        this.showView = true;
    }
}"
@open-add-income.window="showAddIncome = true"
@open-add-expense.window="showAddExpense = true">

    <!-- Flash Notifications -->
    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-900 shadow-sm">
            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 flex-shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-900 shadow-sm">
            <div class="flex items-center gap-2 mb-1.5 font-extrabold text-rose-950">
                <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-4 font-semibold text-rose-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- SECTION 1: KPI SUMMARY CARDS (Matching Journal Page Exactly) -->
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <!-- Total Income -->
        <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Income</span>
            <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($totalIncome, 2) }}</div>
            <span class="mt-1 block text-xs font-bold text-emerald-600">Active received funds</span>
        </div>

        <!-- Total Expense -->
        <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Expense</span>
            <div class="mt-2 break-words font-mono text-2xl font-extrabold text-rose-700">₹{{ number_format($totalExpense, 2) }}</div>
            <span class="mt-1 block text-xs font-bold text-rose-600">Active outgoing expenses</span>
        </div>

        <!-- Net Movement -->
        <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Net Position</span>
            <div class="mt-2 break-words font-mono text-2xl font-extrabold {{ $netTotal >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $netTotal >= 0 ? '+' : '' }}₹{{ number_format($netTotal, 2) }}
            </div>
            <span class="mt-1 block text-xs font-bold text-slate-500">Income minus Expense</span>
        </div>

        <!-- Transactions Count -->
        <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Transactions</span>
            <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">{{ number_format($entries->total()) }}</div>
            <span class="mt-1 block text-xs font-bold text-slate-500">Total recorded entries</span>
        </div>
    </section>

    <!-- SECTION 2: FUNCTIONAL TYPE TABS (Matching Journal Functional Tabs) -->
    <section class="white-card rounded-2xl border border-slate-200 p-2 shadow-sm">
        <div class="flex flex-wrap items-center gap-1.5">
            @php
                $typeTabs = [
                    'all' => ['label' => 'All Transactions', 'icon' => 'layers'],
                    'income' => ['label' => 'Income', 'icon' => 'arrow-down-left'],
                    'expense' => ['label' => 'Expense', 'icon' => 'arrow-up-right'],
                ];
            @endphp

            @foreach($typeTabs as $tabKey => $tabInfo)
                <a href="{{ route('admin.cashbook.finance.income-expense', array_merge(request()->query(), ['type' => $tabKey, 'page' => 1])) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition {{ ($activeType === $tabKey || ($tabKey === 'all' && blank($activeType))) ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    <i data-lucide="{{ $tabInfo['icon'] }}" class="h-3.5 w-3.5"></i>
                    {{ $tabInfo['label'] }}
                </a>
            @endforeach
        </div>
    </section>

    <!-- SECTION 3: FILTERS TOOLBAR (Matching Journal Filters Toolbar) -->
    <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('admin.cashbook.finance.income-expense') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6 lg:items-end">
            <input type="hidden" name="type" value="{{ $activeType }}">

            <!-- Status Filter -->
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Status</label>
                <select name="status" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="" @selected(blank($status) || $status === 'all')>All Statuses</option>
                    <option value="active" @selected($status === 'active' || $status === 'finalized')>Active Only</option>
                    <option value="reversed" @selected($status === 'reversed')>Reversed Only</option>
                </select>
            </div>

            <!-- From Date with previous month shortcut -->
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label class="text-xs font-bold text-slate-600">From Date</label>
                    <x-cashbook.previous-month-button mode="range" size="xs" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                </div>
                <input type="date" name="start_date" value="{{ $startDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
            </div>

            <!-- To Date -->
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">To Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
            </div>

            <!-- Company Account Selector -->
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Company Account</label>
                <select name="company_account" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">All Accounts</option>
                    @foreach($companyAccounts as $account)
                        <option value="{{ $account->id }}" @selected($selectedAccount === (int) $account->id)>{{ $account->name }} ({{ strtoupper($account->account_type) }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Category Selector -->
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Category</label>
                <select name="category" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">All Categories</option>
                    @foreach($allCategories as $cat)
                        <option value="{{ $cat->id }}" @selected($selectedCategory === (int) $cat->id)>{{ $cat->name }} ({{ ucfirst($cat->type) }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Search + Submit / Reset -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search ref, desc, cat..." class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-500 transition shadow-sm">
                    <i data-lucide="filter" class="h-4 w-4"></i> Filter
                </button>
                @if($status !== 'all' || $startDate !== '' || $endDate !== '' || $search !== '' || $activeType !== 'all' || $selectedCategory || $selectedAccount)
                    <a href="{{ route('admin.cashbook.finance.income-expense') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition" title="Clear Filters">
                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                    </a>
                @endif
            </div>
        </form>
    </section>

    <!-- SECTION 4: TRANSACTIONS TABLE (Matching Journal Table Design) -->
    <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
        <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-extrabold text-slate-950">Company Transactions</h2>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">Recorded company income and expense entries across bank and cash accounts.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="font-mono text-xs font-bold text-slate-400">{{ $entries->total() }} entries</span>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showAddIncome = true" class="inline-flex items-center gap-1 rounded-xl bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i> + Add Income
                    </button>
                    <button type="button" @click="showAddExpense = true" class="inline-flex items-center gap-1 rounded-xl bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100 transition">
                        <i data-lucide="minus" class="h-3.5 w-3.5"></i> + Add Expense
                    </button>
                </div>
            </div>
        </div>

        <!-- MOBILE CARD VIEW (lg:hidden) matching Journal page mobile UX -->
        <div class="space-y-3 lg:hidden">
            @forelse($entries as $entry)
                @php
                    $isIncome = $entry->type === 'income';
                    $isReversed = $entry->status === \App\Models\CompanyAccountingEntry::StatusReversed;
                    $entryData = json_encode([
                        'id' => $entry->id,
                        'public_uuid' => $entry->public_uuid,
                        'type' => $entry->type,
                        'business_date' => $entry->business_date->format('d M Y'),
                        'business_date_raw' => $entry->business_date->toDateString(),
                        'company_accounting_category_id' => $entry->company_accounting_category_id,
                        'company_account_uuid' => $entry->companyAccount?->public_uuid,
                        'amount' => (float) $entry->amount,
                        'reference' => $entry->reference,
                        'description' => $entry->description,
                        'status' => $entry->status,
                        'category' => $entry->category ? ['id' => $entry->category->id, 'name' => $entry->category->name] : null,
                        'company_account' => $entry->companyAccount ? ['id' => $entry->companyAccount->id, 'name' => $entry->companyAccount->name, 'type' => $entry->companyAccount->account_type] : null,
                        'journal_ref' => $entry->journalEntry?->reference,
                        'cashbook_status' => $entry->cashbookMovement?->is_finalized ? 'Finalized' : 'Pending Reconciliation',
                        'creator' => $entry->creator?->name,
                        'created_at' => $entry->created_at?->format('d M Y H:i'),
                        'reversed_by' => $entry->reversedBy?->name,
                        'reversed_at' => $entry->reversed_at?->format('d M Y H:i'),
                        'reversal_note' => $entry->reversal_note,
                    ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
                @endphp

                <div class="rounded-2xl border {{ $isReversed ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-slate-50' }} p-3.5 transition hover:bg-slate-100/80" data-entry="{{ $entryData }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if($isIncome)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">Income</span>
                                @else
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-800">Expense</span>
                                @endif
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">{{ $entry->category?->name ?? 'Uncategorized' }}</span>
                                @if($isReversed)
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-800">REVERSED</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-900">ACTIVE</span>
                                @endif
                            </div>

                            <div class="mt-1 text-sm font-black text-slate-900">{{ $entry->description ?: ($entry->reference ?: 'No description') }}</div>
                            <div class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $entry->business_date->format('d M Y') }}</div>
                            @if($entry->reference && $entry->description)
                                <div class="mt-0.5 font-mono text-[10px] font-bold text-slate-400">Ref: {{ $entry->reference }}</div>
                            @endif
                        </div>

                        <div class="text-right">
                            <div class="font-mono text-base font-black {{ $isReversed ? 'line-through text-slate-400' : ($isIncome ? 'text-emerald-700' : 'text-slate-950') }}">
                                {{ $isIncome ? '+' : '-' }} ₹{{ number_format((float) $entry->amount, 2) }}
                            </div>
                            <span class="mt-1 inline-block font-mono text-[10px] font-bold text-slate-500">
                                {{ $entry->companyAccount?->name ?? '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-2.5">
                        <button type="button" @click="openView(JSON.parse($el.closest('[data-entry]').dataset.entry))" class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-slate-900">
                            <i data-lucide="eye" class="h-3.5 w-3.5"></i> Details
                        </button>
                        <div class="flex items-center gap-2">
                            @if(! $isReversed)
                                <button type="button" @click="openEdit(JSON.parse($el.closest('[data-entry]').dataset.entry))" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100">
                                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                                </button>
                                <button type="button" @click="openDelete(JSON.parse($el.closest('[data-entry]').dataset.entry))" class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
                                </button>
                            @else
                                <span class="text-[11px] font-bold text-slate-400">Reversed</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">
                    No transactions match your current filters.
                </div>
            @endforelse
        </div>

        <!-- DESKTOP TABLE VIEW (hidden lg:block) matching Journal page desktop table -->
        <div class="hidden overflow-x-auto custom-scrollbar lg:block">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-3">Date</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3">Category</th>
                        <th class="px-3 py-3">Account</th>
                        <th class="px-3 py-3">Description / Reference</th>
                        <th class="px-3 py-3 text-right">Amount</th>
                        <th class="px-3 py-3 text-center">Status</th>
                        <th class="px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($entries as $entry)
                        @php
                            $isIncome = $entry->type === 'income';
                            $isReversed = $entry->status === \App\Models\CompanyAccountingEntry::StatusReversed;
                            $entryData = json_encode([
                                'id' => $entry->id,
                                'public_uuid' => $entry->public_uuid,
                                'type' => $entry->type,
                                'business_date' => $entry->business_date->format('d M Y'),
                                'business_date_raw' => $entry->business_date->toDateString(),
                                'company_accounting_category_id' => $entry->company_accounting_category_id,
                                'company_account_uuid' => $entry->companyAccount?->public_uuid,
                                'amount' => (float) $entry->amount,
                                'reference' => $entry->reference,
                                'description' => $entry->description,
                                'status' => $entry->status,
                                'category' => $entry->category ? ['id' => $entry->category->id, 'name' => $entry->category->name] : null,
                                'company_account' => $entry->companyAccount ? ['id' => $entry->companyAccount->id, 'name' => $entry->companyAccount->name, 'type' => $entry->companyAccount->account_type] : null,
                                'journal_ref' => $entry->journalEntry?->reference,
                                'cashbook_status' => $entry->cashbookMovement?->is_finalized ? 'Finalized' : 'Pending Reconciliation',
                                'creator' => $entry->creator?->name,
                                'created_at' => $entry->created_at?->format('d M Y H:i'),
                                'reversed_by' => $entry->reversedBy?->name,
                                'reversed_at' => $entry->reversed_at?->format('d M Y H:i'),
                                'reversal_note' => $entry->reversal_note,
                            ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
                        @endphp

                        <tr class="hover:bg-slate-50 {{ $isReversed ? 'bg-rose-50/20 text-slate-400' : '' }}" data-entry="{{ $entryData }}">
                            <!-- Date -->
                            <td class="px-3 py-3 font-mono font-bold text-slate-700 whitespace-nowrap">
                                {{ $entry->business_date->format('Y-m-d') }}
                            </td>

                            <!-- Type -->
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($isIncome)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                        Income
                                    </span>
                                @else
                                    <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-rose-800">
                                        Expense
                                    </span>
                                @endif
                            </td>

                            <!-- Category -->
                            <td class="px-3 py-3 font-semibold text-slate-900 whitespace-nowrap">
                                <div>{{ $entry->category?->name ?? '—' }}</div>
                                @if($entry->category?->account)
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $entry->category->account->code }} · {{ $entry->category->account->name }}</div>
                                @endif
                            </td>

                            <!-- Account -->
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($entry->companyAccount)
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-extrabold text-slate-900">{{ $entry->companyAccount->name }}</span>
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-slate-600 border border-slate-200">
                                            {{ $entry->companyAccount->account_type }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">No account</span>
                                @endif
                            </td>

                            <!-- Description / Reference -->
                            <td class="max-w-xs px-3 py-3">
                                <div class="truncate font-semibold text-slate-900" title="{{ $entry->description ?: $entry->reference }}">
                                    {{ $entry->description ?: ($entry->reference ?: '—') }}
                                </div>
                                @if($entry->reference && $entry->description)
                                    <div class="text-[10px] font-mono text-slate-400">Ref: {{ $entry->reference }}</div>
                                @endif
                            </td>

                            <!-- Amount -->
                            <td class="px-3 py-3 text-right font-mono font-bold whitespace-nowrap {{ $isReversed ? 'line-through text-slate-400' : ($isIncome ? 'text-emerald-700' : 'text-slate-950') }}">
                                {{ $isIncome ? '+' : '-' }} ₹{{ number_format((float) $entry->amount, 2) }}
                            </td>

                            <!-- Status -->
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                @if($isReversed)
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-800">
                                        REVERSED
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-900">
                                        ACTIVE
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button" @click="openView(JSON.parse($el.closest('[data-entry]').dataset.entry))" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i> View
                                    </button>

                                    @if(! $isReversed)
                                        <button type="button" @click="openEdit(JSON.parse($el.closest('[data-entry]').dataset.entry))" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-emerald-700">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                                        </button>
                                        <button type="button" @click="openDelete(JSON.parse($el.closest('[data-entry]').dataset.entry))" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-bold text-rose-600 shadow-sm hover:bg-rose-50">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-sm font-bold text-slate-400">
                                No transactions found for the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- MODAL 1: ADD COMPANY INCOME (Matches Journal Modal Pattern) -->
    <!-- ========================================================================= -->
    <div x-show="showAddIncome" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm sm:p-6" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-center justify-center">
            <div @click.away="showAddIncome = false" class="relative w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7 space-y-4 animate-scale-up">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-mono text-lg font-black text-slate-950">+ Add Company Income</h3>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">INFLOW</span>
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            Money will be received directly into the selected company bank or cash account.
                        </p>
                    </div>
                    <button type="button" @click="showAddIncome = false" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.cashbook.finance.income-expense.store') }}" class="space-y-4"
                    x-data="{ incCatName: '', incDesc: '', incDescErr: false,
                              checkIncome(evt) {
                                  if (this.incCatName.toLowerCase().trim() === 'other' && !this.incDesc.trim()) {
                                      this.incDescErr = true;
                                  } else {
                                      this.incDescErr = false;
                                      evt.target.closest('form').submit();
                                  }
                              } }">
                    @csrf
                    <input type="hidden" name="type" value="income">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="business_date" value="{{ today()->toDateString() }}" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Amount (₹) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-mono text-sm font-extrabold text-slate-900 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                    </div>

                    <!-- Category (Income categories only) -->
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Income Category <span class="text-rose-500">*</span></label>
                        <select name="company_accounting_category_id" required
                            @change="incCatName = $event.target.selectedOptions[0]?.text?.split('(')[0]?.trim() ?? ''; incDescErr = false;"
                            class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">-- Select Income Category --</option>
                            @foreach($incomeCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}{{ $cat->account ? ' ('.$cat->account->code.' - '.$cat->account->name.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Receive Into Account (Mandatory Company Account) -->
                    <div>
                        <label class="mb-1 block text-xs font-bold text-emerald-800 flex items-center gap-1.5">
                            <i data-lucide="landmark" class="h-4 w-4 text-emerald-600"></i> Receive Into Company Account <span class="text-rose-500">*</span>
                        </label>
                        <select name="company_account_uuid" required class="min-h-11 w-full rounded-xl border-2 border-emerald-300 bg-emerald-50/20 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">-- Select Receiving Account --</option>
                            @foreach($companyAccounts as $account)
                                <option value="{{ $account->public_uuid }}">{{ $account->name }} ({{ strtoupper($account->account_type) }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500">Selected account balance will increase with incoming funds.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Reference / UTR / Voucher</label>
                            <input type="text" name="reference" placeholder="e.g. UTR99812, INV-01" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700"
                                :class="incDescErr ? 'text-rose-600' : 'text-slate-700'">
                                Description / Narration
                                <span x-show="incCatName.toLowerCase().trim() === 'other'" class="ml-1 text-[10px] font-semibold text-amber-600">(required for Other)</span>
                            </label>
                            <input type="text" name="description" x-model="incDesc" @input="incDescErr = false"
                                placeholder="Narration or purpose..."
                                :class="incDescErr ? 'border-rose-400 ring-1 ring-rose-300 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500'"
                                class="min-h-11 w-full rounded-xl border bg-white px-3 text-xs font-bold text-slate-800 transition">
                            <p x-show="incDescErr" x-cloak class="mt-1 flex items-center gap-1 text-[11px] font-bold text-rose-600">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                                Description is required when category is "Other".
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button type="button" @click="showAddIncome = false" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="button" @click="checkIncome($event)" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition">
                            Record Income
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: ADD COMPANY EXPENSE (Matches Journal Modal Pattern) -->
    <!-- ========================================================================= -->
    <div x-show="showAddExpense" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm sm:p-6" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-center justify-center">
            <div @click.away="showAddExpense = false" class="relative w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7 space-y-4 animate-scale-up">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-mono text-lg font-black text-slate-950">+ Add Company Expense</h3>
                            <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-rose-800">OUTFLOW</span>
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            Money will be paid out directly from the selected company bank or cash account.
                        </p>
                    </div>
                    <button type="button" @click="showAddExpense = false" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.cashbook.finance.income-expense.store') }}" class="space-y-4"
                    x-data="{ expCatName: '', expDesc: '', expDescErr: false,
                              checkExpense(evt) {
                                  if (this.expCatName.toLowerCase().trim() === 'other' && !this.expDesc.trim()) {
                                      this.expDescErr = true;
                                  } else {
                                      this.expDescErr = false;
                                      evt.target.closest('form').submit();
                                  }
                              } }">
                    @csrf
                    <input type="hidden" name="type" value="expense">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="business_date" value="{{ today()->toDateString() }}" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-rose-500 focus:ring-rose-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Amount (₹) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-mono text-sm font-extrabold text-slate-900 focus:border-rose-500 focus:ring-rose-500">
                        </div>
                    </div>

                    <!-- Category (Expense categories only) -->
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Expense Category <span class="text-rose-500">*</span></label>
                        <select name="company_accounting_category_id" required
                            @change="expCatName = $event.target.selectedOptions[0]?.text?.split('(')[0]?.trim() ?? ''; expDescErr = false;"
                            class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-rose-500 focus:ring-rose-500">
                            <option value="">-- Select Expense Category --</option>
                            @foreach($expenseCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}{{ $cat->account ? ' ('.$cat->account->code.' - '.$cat->account->name.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Pay From Account (Mandatory Company Account) -->
                    <div>
                        <label class="mb-1 block text-xs font-bold text-rose-800 flex items-center gap-1.5">
                            <i data-lucide="landmark" class="h-4 w-4 text-rose-600"></i> Pay From Company Account <span class="text-rose-500">*</span>
                        </label>
                        <select name="company_account_uuid" required class="min-h-11 w-full rounded-xl border-2 border-rose-300 bg-rose-50/20 px-3 text-xs font-bold text-slate-900 focus:border-rose-500 focus:ring-rose-500">
                            <option value="">-- Select Paying Account --</option>
                            @foreach($companyAccounts as $account)
                                <option value="{{ $account->public_uuid }}">{{ $account->name }} ({{ strtoupper($account->account_type) }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500">Selected account balance will decrease with outgoing expense.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Reference / Bill / Voucher</label>
                            <input type="text" name="reference" placeholder="e.g. BILL-4412, CHQ-102" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-rose-500 focus:ring-rose-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold"
                                :class="expDescErr ? 'text-rose-600' : 'text-slate-700'">
                                Description / Narration
                                <span x-show="expCatName.toLowerCase().trim() === 'other'" class="ml-1 text-[10px] font-semibold text-amber-600">(required for Other)</span>
                            </label>
                            <input type="text" name="description" x-model="expDesc" @input="expDescErr = false"
                                placeholder="Narration or purpose..."
                                :class="expDescErr ? 'border-rose-400 ring-1 ring-rose-300 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-300 focus:border-rose-500 focus:ring-rose-500'"
                                class="min-h-11 w-full rounded-xl border bg-white px-3 text-xs font-bold text-slate-800 transition">
                            <p x-show="expDescErr" x-cloak class="mt-1 flex items-center gap-1 text-[11px] font-bold text-rose-600">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                                Description is required when category is "Other".
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button type="button" @click="showAddExpense = false" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="button" @click="checkExpense($event)" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-rose-500 transition">
                            Record Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: EDIT TRANSACTION (Matches Journal Edit Modal Pattern) -->
    <!-- ========================================================================= -->
    <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm sm:p-6" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-center justify-center">
            <div @click.away="showEdit = false" class="relative w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7 space-y-4 animate-scale-up">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-mono text-lg font-black text-slate-950">Edit <span x-text="editEntry.type === 'income' ? 'Income' : 'Expense'"></span> Entry</h3>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-slate-700" x-text="editEntry.type.toUpperCase()"></span>
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            Immutable double-entry correction: reverses original movement and posts corrected record.
                        </p>
                    </div>
                    <button type="button" @click="showEdit = false" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form method="POST" :action="'/admin/cashbook/finance/income-expense/' + editEntry.public_uuid" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="type" :value="editEntry.type">

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-900">
                        <div class="flex items-center gap-1.5 font-bold">
                            <i data-lucide="info" class="h-4 w-4 text-amber-700"></i>
                            <span>Accounting Rule:</span>
                        </div>
                        <p class="mt-0.5 text-[11px] leading-relaxed text-amber-800">
                            Updating will safely reverse previous movement on the original company account, apply the corrected entry on the selected account, and maintain an unbroken audit trail.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="business_date" x-model="editEntry.business_date" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Amount (₹) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" x-model="editEntry.amount" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-mono text-sm font-extrabold text-slate-900 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Category <span class="text-rose-500">*</span></label>
                        <select name="company_accounting_category_id" x-model="editEntry.company_accounting_category_id" required
                            @change="editEntry.category_name = $event.target.selectedOptions[0]?.text?.trim() ?? ''; editDescErr = false;"
                            class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                            <template x-if="editEntry.type === 'income'">
                                <optgroup label="Income Categories">
                                    @foreach($incomeCategories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </optgroup>
                            </template>
                            <template x-if="editEntry.type === 'expense'">
                                <optgroup label="Expense Categories">
                                    @foreach($expenseCategories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </optgroup>
                            </template>
                        </select>
                    </div>

                    <!-- Company Account Selector -->
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Company Bank / Cash Account <span class="text-rose-500">*</span></label>
                        <select name="company_account_uuid" x-model="editEntry.company_account_uuid" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                            @foreach($companyAccounts as $account)
                                <option value="{{ $account->public_uuid }}">{{ $account->name }} ({{ strtoupper($account->account_type) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Reference</label>
                            <input type="text" name="reference" x-model="editEntry.reference" placeholder="Reference..." class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold"
                                :class="editDescErr ? 'text-rose-600' : 'text-slate-700'">
                                Description
                                <span x-show="editEntry.category_name.toLowerCase().trim() === 'other'" class="ml-1 text-[10px] font-semibold text-amber-600">(required for Other)</span>
                            </label>
                            <input type="text" name="description" x-model="editEntry.description"
                                @input="editDescErr = false"
                                placeholder="Description..."
                                :class="editDescErr ? 'border-rose-400 ring-1 ring-rose-300' : 'border-slate-300'"
                                class="min-h-11 w-full rounded-xl border bg-white px-3 text-xs font-bold text-slate-800 transition">
                            <p x-show="editDescErr" x-cloak class="mt-1 flex items-center gap-1 text-[11px] font-bold text-rose-600">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                                Description is required when category is "Other".
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Correction Reason</label>
                        <input type="text" name="reason" x-model="editEntry.reason" placeholder="State why this entry is being edited..." class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button type="button" @click="showEdit = false" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="button"
                            @click="if (editEntry.category_name.toLowerCase().trim() === 'other' && !editEntry.description.trim()) { editDescErr = true; } else { editDescErr = false; $el.closest('form').submit(); }"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                            Save Correction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: DELETE / CANCEL TRANSACTION (Matches Journal Delete Pattern) -->
    <!-- ========================================================================= -->
    <div x-show="showDelete" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm sm:p-6" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-center justify-center">
            <div @click.away="showDelete = false" class="relative w-full max-w-md rounded-3xl border border-rose-200 bg-white p-6 shadow-2xl sm:p-7 space-y-4 animate-scale-up">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <div class="h-10 w-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="trash-2" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="font-mono text-base font-black text-slate-950">Cancel / Reverse Transaction</h3>
                        <p class="text-xs font-semibold text-slate-500">Safe financial cancellation with audit retention.</p>
                    </div>
                </div>

                <div class="rounded-2xl bg-rose-50/70 p-3.5 border border-rose-200/80 space-y-2 text-xs">
                    <div class="flex justify-between font-bold">
                        <span class="text-slate-600">Category:</span>
                        <span class="text-slate-900" x-text="deleteEntry.category_name"></span>
                    </div>
                    <div class="flex justify-between font-bold">
                        <span class="text-slate-600">Account:</span>
                        <span class="text-slate-900" x-text="deleteEntry.account_name"></span>
                    </div>
                    <div class="flex justify-between font-black text-sm pt-1.5 border-t border-rose-200/60">
                        <span class="text-slate-800">Amount:</span>
                        <span class="font-mono text-rose-700" x-text="'₹' + deleteEntry.amount"></span>
                    </div>
                </div>

                <form method="POST" :action="'/admin/cashbook/finance/income-expense/' + deleteEntry.public_uuid" class="space-y-4">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Reason for Cancellation</label>
                        <input type="text" name="reason" x-model="deleteEntry.reason" placeholder="e.g. Duplicate voucher, error in billing" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-rose-500 focus:ring-rose-500">
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button type="button" @click="showDelete = false" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-rose-500 transition">
                            Confirm Reversal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5: VIEW DETAILS (Matches Journal Detail Inspection Pattern) -->
    <!-- ========================================================================= -->
    <div x-show="showView" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm sm:p-6" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-center justify-center">
            <div @click.away="showView = false" class="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7 space-y-4 animate-scale-up">
                <template x-if="viewEntry">
                    <div>
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-mono text-lg font-black text-slate-950">Transaction Details</h3>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase" :class="viewEntry.type === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'" x-text="viewEntry.type.toUpperCase()"></span>
                                </div>
                                <p class="mt-1 font-mono text-[11px] font-semibold text-slate-500" x-text="'Ref: ' + (viewEntry.reference || viewEntry.public_uuid)"></p>
                            </div>
                            <button type="button" @click="showView = false" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                <i data-lucide="x" class="h-5 w-5"></i>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <span class="text-[10px] font-black uppercase text-slate-400">Type</span>
                                <div class="mt-1 font-bold text-slate-900 capitalize" x-text="viewEntry.type"></div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <span class="text-[10px] font-black uppercase text-slate-400">Amount</span>
                                <div class="mt-1 font-mono text-base font-black" :class="viewEntry.type === 'income' ? 'text-emerald-700' : 'text-slate-950'" x-text="'₹' + parseFloat(viewEntry.amount).toFixed(2)"></div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <span class="text-[10px] font-black uppercase text-slate-400">Date</span>
                                <div class="mt-1 font-mono font-bold text-slate-900" x-text="viewEntry.business_date"></div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <span class="text-[10px] font-black uppercase text-slate-400">Status</span>
                                <div class="mt-1 font-black uppercase" :class="viewEntry.status === 'reversed' ? 'text-rose-600' : 'text-emerald-700'" x-text="viewEntry.status"></div>
                            </div>

                            <div class="col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <span class="text-[10px] font-black uppercase text-slate-400">Company Account</span>
                                <div class="mt-1 font-bold text-slate-900" x-text="viewEntry.company_account ? viewEntry.company_account.name + ' (' + viewEntry.company_account.type.toUpperCase() + ')' : '—'"></div>
                            </div>

                            <div class="col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <span class="text-[10px] font-black uppercase text-slate-400">Category</span>
                                <div class="mt-1 font-bold text-slate-900" x-text="viewEntry.category ? viewEntry.category.name : '—'"></div>
                            </div>

                            <div class="col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <span class="text-[10px] font-black uppercase text-slate-400">Description / Narration</span>
                                <div class="mt-1 text-slate-700 leading-relaxed" x-text="viewEntry.description || 'No description recorded.'"></div>
                            </div>

                            <template x-if="viewEntry.journal_ref">
                                <div class="col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <span class="text-[10px] font-black uppercase text-slate-400">Journal Reference</span>
                                    <div class="mt-1 font-mono font-bold text-slate-800" x-text="viewEntry.journal_ref"></div>
                                </div>
                            </template>

                            <template x-if="viewEntry.status === 'reversed'">
                                <div class="col-span-2 rounded-2xl border border-rose-200 bg-rose-50/70 p-3 space-y-1">
                                    <span class="text-[10px] font-black uppercase text-rose-700">Reversal Information</span>
                                    <div class="text-rose-900 font-semibold text-[11px]" x-text="'Reversed at: ' + (viewEntry.reversed_at || '—')"></div>
                                    <div class="text-rose-900 font-semibold text-[11px]" x-text="'Reversed by: ' + (viewEntry.reversed_by || 'Admin')"></div>
                                    <div class="text-rose-800 font-medium text-[11px]" x-text="'Reason: ' + (viewEntry.reversal_note || '—')"></div>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="button" @click="showView = false" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-900 px-5 text-xs font-bold text-white hover:bg-slate-800 transition">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection
