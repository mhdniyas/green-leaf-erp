@extends('admin.cashbook.layouts.app')

@section('title', 'Needs Attention — Cashbook')

@section('header_title')
    <i data-lucide="shield-alert" class="h-5 w-5 text-amber-600"></i> Needs Attention <span class="sr-only">Cashbook Action Center</span>
@endsection

@section('header_subtitle')
    Review exceptions, resolve amount differences, handle potential duplicates, and match candidate entries.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <details class="relative">
            <summary class="inline-flex min-h-10 cursor-pointer list-none items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-black text-white shadow-sm hover:bg-emerald-500">
                <i data-lucide="plus" class="h-4 w-4"></i>
                <span class="hidden sm:inline">New Transaction</span>
            </summary>
            <div class="absolute right-0 z-20 mt-2 w-72 rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl">
                <p class="px-2 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Money In</p>
                <div class="mt-2 grid gap-2">
                    <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => 'income']) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-emerald-50">Other Income</a>
                    <a href="{{ route('admin.cashbook.finance.direct-sales') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-emerald-50">Direct Sale</a>
                </div>
                <p class="mt-4 px-2 text-[10px] font-black uppercase tracking-[0.18em] text-rose-700">Money Out</p>
                <div class="mt-2 grid gap-2">
                    <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => 'expense']) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-rose-50">Other Expense</a>
                    <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-rose-50">Vendor Payment</a>
                    <a href="{{ route('admin.cashbook.payables') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-rose-50">Pay Payable</a>
                    <a href="{{ route('admin.cashbook.finance.purchase.purchasers') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-rose-50">Purchasers</a>
                    <a href="{{ route('admin.cashbook.index') }}#tab-payments" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-rose-50">Shop Petty</a>
                    <a href="{{ route('admin.staff.payments.index') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-rose-50">Salary Payment</a>
                    <a href="{{ route('admin.staff.advance-payments.index') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-800 hover:bg-rose-50">Salary Advance</a>
                </div>
            </div>
        </details>
        <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 shadow-sm hover:bg-slate-50">
            <i data-lucide="book-open-check" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Approved Transactions</span>
        </a>
    </div>
@endsection

@section('content')
    @php
        $remainingStatement = $selectedStatement
            ? max(0, (float) $selectedStatement->amount - (float) $selectedStatement->matched_amount)
            : 0;
        $queueTabs = [
            'needs_action' => 'Needs Action',
            'unmatched' => 'Unmatched',
            'pending' => 'Pending',
            'partial' => 'Partial',
            'finalized_today' => 'Finalized Today',
        ];
        $directionActions = [
            'out' => [
                ['key' => 'payable', 'label' => 'Payable'],
                ['key' => 'vendor-payment', 'label' => 'Vendor Payment'],
                ['key' => 'custom-expense', 'label' => 'Other Expense'],
                ['key' => 'salary-payment', 'label' => 'Salary Payment'],
                ['key' => 'salary-advance', 'label' => 'Salary Advance'],
                ['key' => 'shop-petty', 'label' => 'Shop Petty'],
                ['key' => 'purchaser-funding', 'label' => 'Purchaser Funding'],
                ['key' => 'match-existing', 'label' => 'Match Existing Transaction'],
            ],
            'in' => [
                ['key' => 'custom-income', 'label' => 'Other Income'],
                ['key' => 'shop-payment', 'label' => 'Shop Payment'],
                ['key' => 'direct-company-sale', 'label' => 'Direct Company Sale'],
                ['key' => 'match-existing', 'label' => 'Match Existing Transaction'],
            ],
        ];
        $classificationContext = $classifyStatement ? [
            'statement_public_uuid' => $classifyStatement->public_uuid,
            'direction' => $classifyStatement->direction,
            'amount' => (float) $classifyStatement->amount,
            'transaction_date' => $classifyStatement->transaction_date?->toDateString(),
            'company_account_public_uuid' => $classifyStatement->companyAccount?->public_uuid,
            'reference' => $classifyStatement->reference,
            'narration' => $classifyStatement->narration,
        ] : null;
        $isCreateTransactionPage = $classifyStatement && request()->boolean('create_transaction');
        $actionCenterUrl = route('admin.cashbook.finance.reconciliation', ['company_account_uuid' => $selectedAccountUuid, 'month' => $month, 'direction' => $direction, 'status' => $queueStatus, 'grace_days' => $graceDays, 'search' => $search]);
        $matchTransactionUrl = $classifyStatement
            ? route('admin.cashbook.finance.reconciliation', ['classify_statement' => $classifyStatement->public_uuid, 'company_account_uuid' => $selectedAccountUuid, 'month' => $month, 'direction' => $direction, 'status' => $queueStatus, 'grace_days' => $graceDays, 'search' => $search])
            : $actionCenterUrl;
        $createTransactionUrl = $classifyStatement
            ? route('admin.cashbook.finance.reconciliation.create-transaction', $classifyStatement)
            : $actionCenterUrl;
        $createTabLabels = [
            'expense' => 'Expense',
            'payable' => 'Payable',
            'vendor' => 'Vendor',
            'salary' => 'Salary',
            'advance' => 'Salary Advance',
            'petty' => 'Shop Petty',
            'purchaser' => 'Purchaser Funding',
            'income' => 'Other Income',
            'shop-payment' => 'Shop Payment',
            'direct-sale' => 'Direct Sale',
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <span class="sr-only">Cashbook Action Center Awaiting Reconciliation Statement Movements Finalized This Month Unmatched Statements Partial Classify / Match</span>
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('reconciliation_failures'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-900">
                <p>Needs Review {{ count(session('reconciliation_failures')) }}</p>
                @foreach(session('reconciliation_failures') as $failure)
                    <p class="mt-1">{{ $failure }}</p>
                @endforeach
            </div>
        @endif

        @if(session('skipped_reconciliations'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-amber-900">Protected Manual Counterparts Skipped ({{ count(session('skipped_reconciliations')) }})</span>
                    <button type="button" onclick="document.getElementById('skipped-reconciliations-list').classList.toggle('hidden')" class="text-xs font-black text-amber-800 underline">View Skipped</button>
                </div>
                <div id="skipped-reconciliations-list" class="hidden mt-3 space-y-1">
                    @foreach(session('skipped_reconciliations') as $skipped)
                        <div class="rounded-lg border border-amber-200/60 bg-white/80 p-2.5 flex items-center justify-between font-mono text-[11px]">
                            <div>
                                <span class="font-bold text-slate-800">{{ $skipped['reference'] }}</span>
                                <span class="text-slate-500">({{ $skipped['source'] }})</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-slate-900">₹{{ number_format($skipped['amount'], 2) }}</span>
                                <span class="text-amber-700 text-[10px] font-sans font-semibold">{{ $skipped['reason'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @unless($classifyStatement)
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Reconciliation</p>
                <p class="mt-2 text-2xl font-black text-slate-950">Transaction Queue</p>
            </article>
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Needs Review</p>
                <p class="mt-2 font-mono text-2xl font-black text-amber-950">{{ number_format($transactionCounts['needs_review']) }}</p>
            </article>
            <article class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-sky-700">Suggested</p>
                <p class="mt-2 font-mono text-2xl font-black text-sky-950">{{ number_format($transactionCounts['suggested']) }}</p>
            </article>
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Reconciled</p>
                <p class="mt-2 font-mono text-2xl font-black text-emerald-950">{{ number_format($transactionCounts['reconciled']) }}</p>
            </article>
        </section>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            <button type="button" onclick="openAutoMatchModal()" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-4 text-xs font-black text-emerald-800 shadow-sm hover:bg-emerald-100">
                <i data-lucide="sparkles" class="h-4 w-4 text-emerald-600"></i> Auto Match Shop Collections
            </button>
            <details class="group relative">
                <summary class="inline-flex min-h-11 cursor-pointer list-none items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 shadow-sm hover:bg-slate-50">
                    <i data-lucide="plus" class="h-4 w-4"></i> Record Transaction
                </summary>
                <div class="mt-2 grid w-full gap-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl sm:absolute sm:right-0 sm:z-20 sm:w-64">
                    <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => 'income']) }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Other Income</a>
                    <a href="{{ route('admin.cashbook.finance.direct-sales') }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Direct Sale</a>
                    <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => 'expense']) }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Other Expense</a>
                    <a href="{{ route('admin.cashbook.payables') }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Pay Payable</a>
                    <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Vendor Payment</a>
                    <a href="{{ route('admin.cashbook.finance.purchase.purchasers') }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Purchasers</a>
                    <a href="{{ route('admin.cashbook.index') }}#tab-payments" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Shop Petty</a>
                    <a href="{{ route('admin.staff.payments.index') }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Salary Payment</a>
                    <a href="{{ route('admin.staff.advance-payments.index') }}" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Salary Advance</a>
                </div>
            </details>
            <button type="button" onclick="openClearMonthModal()" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-black text-rose-700 shadow-sm hover:bg-rose-100">
                <i data-lucide="rotate-ccw" class="h-4 w-4 text-rose-600"></i> Clear Reconciliation
            </button>
            <a href="{{ route('admin.cashbook.finance.reconciliation', ['workspace' => 'statements', 'month' => $month]) }}" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-500 shadow-sm hover:bg-slate-50">
                <i data-lucide="file-search" class="h-4 w-4"></i> Manage Statements
            </a>
        </div>

        @if($workspaceTab === 'transactions')
            <section x-data="{ selected: [] }" class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <form method="GET" action="{{ route('admin.cashbook.finance.reconciliation') }}" class="grid grid-cols-1 gap-2 lg:grid-cols-[auto_auto_auto_auto_1fr_auto]">
                    <input type="hidden" name="workspace" value="transactions">
                    <x-cashbook.previous-month-button mode="month" size="md" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                    <input type="month" name="month" value="{{ $month }}" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <select name="company_account_id" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="" @selected(! request()->filled('company_account_id'))>All Company Accounts</option>
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}" @selected(request()->integer('company_account_id') === $account->id)>{{ $account->name }} / {{ strtoupper($account->account_type) }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="search" value="{{ $transactionSearch }}" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Search party, reference, description, amount">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-4 text-xs font-black text-white hover:bg-slate-800">
                        <i data-lucide="filter" class="h-4 w-4"></i> Filter
                    </button>
                </form>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach(['all' => 'ALL', 'in' => 'IN', 'out' => 'OUT'] as $tabDirection => $label)
                        <a href="{{ route('admin.cashbook.finance.reconciliation', array_merge(request()->query(), ['workspace' => 'transactions', 'direction' => $tabDirection, 'type' => 'all'])) }}" class="rounded-xl px-4 py-2 text-xs font-black {{ $direction === $tabDirection ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $label }}</a>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach(['SUGGESTED' => 'Suggested', 'NEEDS_REVIEW' => 'Needs Review', 'RECONCILED' => 'Reconciled'] as $statusKey => $label)
                        <a href="{{ route('admin.cashbook.finance.reconciliation', array_merge(request()->query(), ['workspace' => 'transactions', 'status' => $statusKey])) }}" class="rounded-xl px-4 py-2 text-xs font-black {{ $queueStatus === $statusKey ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $label }}</a>
                    @endforeach
                </div>

                @if($direction !== 'all')
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($transactionTypeFilters as $typeKey => $label)
                            <a href="{{ route('admin.cashbook.finance.reconciliation', array_merge(request()->query(), ['workspace' => 'transactions', 'type' => $typeKey])) }}" class="rounded-full px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] {{ $activeTransactionType === $typeKey ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                @endif

                <form id="bulk-confirm-suggestions" method="POST" action="{{ route('admin.cashbook.finance.reconciliation.confirm-suggestions') }}" class="mt-5 flex items-center justify-between gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-3">
                    @csrf
                    <p class="text-xs font-bold text-sky-900"><span x-text="selected.length">0</span> selected on this page</p>
                    <button type="submit" x-bind:disabled="selected.length === 0" class="rounded-xl bg-sky-600 px-4 py-2 text-xs font-black text-white hover:bg-sky-700 disabled:opacity-50">
                        Confirm Selected Matches
                    </button>
                </form>

                <div class="mt-4 space-y-2">
                    @forelse($transactionRows as $transaction)
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <p class="font-black text-slate-950">{{ $transaction->party_name }}</p>
                                    <p class="mt-1 truncate text-xs font-bold text-slate-600">{{ $transaction->description }}</p>
                                    <p class="mt-1 text-[11px] font-bold text-slate-400">
                                        {{ $transaction->transaction_date }} / {{ $transaction->transaction_type }} / {{ $transaction->company_account_name }} / {{ $transaction->reference }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 lg:items-end">
                                    <strong class="font-mono text-lg text-slate-950">₹{{ number_format((float) $transaction->amount, 2) }}</strong>
                                    @if(isset($transaction->expected_bank_amount) && abs((float) $transaction->expected_bank_amount - (float) $transaction->amount) > 0.001)
                                        <div class="rounded-xl border border-amber-200 bg-amber-50/80 px-2 py-0.5 text-[11px] font-semibold text-slate-600 lg:text-right">
                                            <span class="text-slate-500">Base: ₹{{ number_format((float) $transaction->amount, 2) }}</span>
                                            <span class="mx-1 text-slate-300">|</span>
                                            <span class="{{ ($transaction->adjustment_total ?? 0) < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ ($transaction->adjustment_total ?? 0) >= 0 ? '+' : '' }}₹{{ number_format((float) ($transaction->adjustment_total ?? 0), 2) }}</span>
                                            <span class="mx-1 text-slate-300">|</span>
                                            <span class="font-bold text-slate-900">Exp: ₹{{ number_format((float) $transaction->expected_bank_amount, 2) }}</span>
                                        </div>
                                    @endif
                                    @if($transaction->reconciliation_status === 'RECONCILED')
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase text-emerald-700">✓ Reconciled</span>
                                        <p class="max-w-56 text-xs font-semibold text-slate-500 lg:text-right">{{ $transaction->statement_match_summary }}</p>
                                        @if($transaction->journal_entry_id)
                                            <a href="{{ route('admin.cashbook.finance.journal.entry-show', $transaction->journal_entry_id) }}" class="rounded-xl bg-white px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700 ring-1 ring-slate-200">View Match</a>
                                        @endif
                                    @else
                                        @if(($transaction->suggestion['status'] ?? null) === 'SUGGESTED')
                                            <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-600">
                                                <input form="bulk-confirm-suggestions" type="checkbox" value="{{ $transaction->suggestion['statement_uuid'] }}" @change="selected = $event.target.checked ? [...selected, $event.target.value] : selected.filter(value => value !== $event.target.value)" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                Select
                                            </label>
                                            <input form="bulk-confirm-suggestions" type="hidden" name="matches[{{ $transaction->suggestion['statement_uuid'] }}][statement_uuid]" value="{{ $transaction->suggestion['statement_uuid'] }}" x-bind:disabled="! selected.includes('{{ $transaction->suggestion['statement_uuid'] }}')">
                                            <input form="bulk-confirm-suggestions" type="hidden" name="matches[{{ $transaction->suggestion['statement_uuid'] }}][candidate_ref]" value="{{ $transaction->source_ref }}" x-bind:disabled="! selected.includes('{{ $transaction->suggestion['statement_uuid'] }}')">
                                            <span class="rounded-full bg-sky-100 px-3 py-1 text-[10px] font-black uppercase text-sky-700">{{ strtoupper($transaction->suggestion['confidence']) }} Confidence</span>
                                            <p class="max-w-56 text-xs font-semibold text-slate-500 lg:text-right">{{ $transaction->suggestion['company_account_name'] }} / ₹{{ number_format((float) $transaction->suggestion['statement_amount'], 2) }} / {{ $transaction->suggestion['statement_date'] }}</p>
                                            <p class="max-w-56 text-xs font-semibold text-slate-500 lg:text-right">{{ $transaction->suggestion['reason'] }}</p>
                                            <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.confirm-suggestion', $transaction->suggestion['statement_uuid']) }}">@csrf<input type="hidden" name="candidate_ref" value="{{ $transaction->source_ref }}"><button class="rounded-xl bg-emerald-600 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white">Confirm</button></form>
                                            <a href="{{ route('admin.cashbook.finance.reconciliation', ['workspace' => 'needs_reconciliation', 'find_kind' => $transaction->find_kind ?? ($transaction->source_type === \App\Models\ShopInvoicePaymentRequest::class ? 'shop_payment' : ($transaction->source_type === \App\Models\Cashbook\ShopLedgerTransaction::class ? 'shop_ledger' : 'journal')), 'find_ref' => $transaction->source_ref, 'month' => $month, 'direction' => $transaction->direction]) }}#reconcile-panel" class="rounded-xl bg-white px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700 ring-1 ring-slate-200">{{ $transaction->suggestion['confidence'] === 'likely' ? 'Review' : 'Change Match' }}</a>
                                            @if($transaction->suggestion['confidence'] === 'likely')
                                                <a href="{{ route('admin.cashbook.finance.reconciliation', ['workspace' => 'needs_reconciliation', 'find_kind' => $transaction->find_kind ?? ($transaction->source_type === \App\Models\ShopInvoicePaymentRequest::class ? 'shop_payment' : ($transaction->source_type === \App\Models\Cashbook\ShopLedgerTransaction::class ? 'shop_ledger' : 'journal')), 'find_ref' => $transaction->source_ref, 'month' => $month, 'direction' => $transaction->direction]) }}#reconcile-panel" class="rounded-xl bg-white px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700 ring-1 ring-slate-200">Change Match</a>
                                            @endif
                                        @else
                                            <span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase text-amber-700">{{ ($transaction->suggestion['status'] ?? null) === 'NO_MATCH' ? 'No Match Found' : 'Needs Review' }}</span>
                                            <p class="max-w-56 text-xs font-semibold text-slate-500 lg:text-right">{{ $transaction->suggestion['reason'] ?? 'No eligible statement found.' }}</p>
                                            <a href="{{ route('admin.cashbook.finance.reconciliation', ['workspace' => 'needs_reconciliation', 'find_kind' => $transaction->find_kind ?? ($transaction->source_type === \App\Models\ShopInvoicePaymentRequest::class ? 'shop_payment' : ($transaction->source_type === \App\Models\Cashbook\ShopLedgerTransaction::class ? 'shop_ledger' : 'journal')), 'find_ref' => $transaction->source_ref, 'month' => $month, 'direction' => $transaction->direction]) }}#reconcile-panel" class="rounded-xl bg-slate-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white">{{ ($transaction->suggestion['status'] ?? null) === 'NO_MATCH' ? 'Find Match' : 'Review Matches' }}</a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">No ERP transactions for this filter.</p>
                    @endforelse
                </div>

                @if($transactionRows->hasPages())
                    <div class="mt-4">{{ $transactionRows->links() }}</div>
                @endif
            </section>
        @elseif($workspaceTab === 'needs_reconciliation')
            <section x-data="{
                detail: null,
                reconcile: null,
                candidates: [],
                reconciled: [],
                counts: {pending: 0, reconciled: 0},
                tab: 'pending',
                loading: false,
                openDetail(el) {
                    try {
                        this.detail = JSON.parse(el.getAttribute('data-source'));
                    } catch (e) {
                        console.error('Failed to parse detail data', e);
                    }
                },
                openReconcile(el) {
                    try {
                        this.reconcile = JSON.parse(el.getAttribute('data-source'));
                        this.candidates = [];
                        this.reconciled = [];
                        this.counts = {pending: 0, reconciled: 0};
                        this.tab = 'pending';
                        this.loading = true;
                        fetch(el.getAttribute('data-candidate-url'))
                            .then(r => r.json())
                            .then(data => {
                                this.candidates = data.candidates || [];
                                this.reconciled = data.reconciled || [];
                                this.counts = data.counts || {pending: this.candidates.length, reconciled: this.reconciled.length};
                            })
                            .catch(err => {
                                console.error('Candidate fetch error', err);
                            })
                            .finally(() => {
                                this.loading = false;
                            });
                    } catch (e) {
                        console.error('Failed to parse reconcile data', e);
                    }
                }
            }" class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                    <div><h2 class="text-base font-black text-slate-950">Needs Reconciliation</h2><p class="mt-1 text-xs font-semibold text-slate-500">ERP cash and bank activity waiting for real statement movement.</p></div>
                    <span class="font-mono text-xs font-bold text-slate-400">{{ $pendingSources->total() }} rows</span>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse($pendingSources as $source)
                        @php
                            $candidateUrl = route('admin.cashbook.finance.reconciliation.pending-candidates', ['find_kind' => $source['kind'], 'find_ref' => $source['reference']]);
                            $sourceJson = json_encode($source, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
                        @endphp
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-black text-slate-950">{{ $source['source'] }}</p><p class="mt-1 truncate text-xs font-bold text-slate-600">{{ $source['counterparty'] }}</p><p class="mt-1 text-[11px] font-bold text-slate-400">{{ $source['date'] }} / {{ $source['method'] }} / {{ $source['account'] }} / {{ $source['reference_label'] }}</p></div><strong class="shrink-0 font-mono text-lg text-slate-950">₹{{ number_format($source['amount'], 2) }}</strong></div>
                            <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-amber-700">Pending Reconciliation</span>
                                <div class="flex gap-2">
                                    <button type="button" data-source="{{ $sourceJson }}" @click="openDetail($el)" class="rounded-xl bg-white px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">View Details</button>
                                    <button type="button" data-source="{{ $sourceJson }}" data-candidate-url="{{ $candidateUrl }}" @click="openReconcile($el)" class="rounded-xl bg-slate-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white hover:bg-slate-800">Find Match</button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">No pending cash or bank transactions for this filter.</p>
                    @endforelse
                </div>
                @if($pendingSources->hasPages())<div class="mt-4">{{ $pendingSources->links() }}</div>@endif
                <div x-cloak x-show="detail" @keydown.escape.window="detail = null" class="fixed inset-0 z-50 flex items-end bg-slate-950/50 p-3 sm:items-center sm:justify-center" role="dialog" aria-modal="true">
                    <div @click.outside="detail = null" class="w-full max-w-lg rounded-3xl bg-white p-5 shadow-2xl sm:p-6">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Transaction Details</p><h2 class="mt-1 text-xl font-black text-slate-950" x-text="detail?.source"></h2></div><button type="button" @click="detail = null" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">Close</button></div>
                        <div class="mt-5 grid grid-cols-2 gap-2 text-xs"><template x-for="[label, value] in [['Counterparty', detail?.counterparty], ['Amount', '₹' + Number(detail?.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})], ['Date', detail?.date], ['Method / Account', detail?.method + ' / ' + detail?.account], ['Reference', detail?.reference_label], ['Status', 'Pending Reconciliation']]" :key="label"><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400" x-text="label"></span><strong class="mt-1 block break-words text-slate-900" x-text="value"></strong></div></template></div>
                        <div class="mt-3 rounded-xl bg-slate-50 p-3 text-xs"><span class="block font-bold text-slate-400">Description / Note</span><strong class="mt-1 block break-words text-slate-900" x-text="detail?.description"></strong></div>
                    </div>
                </div>
                <div x-cloak x-show="reconcile" @keydown.escape.window="reconcile = null" class="fixed inset-0 z-50 flex items-end bg-slate-950/50 p-3 sm:items-center sm:justify-center" role="dialog" aria-modal="true">
                    <div @click.outside="reconcile = null" class="max-h-full w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-5 shadow-2xl sm:p-6">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Match Statement</p><h2 class="mt-1 text-xl font-black text-slate-950">Best Matches</h2></div><button type="button" @click="reconcile = null" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">Close</button></div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3"><template x-for="[label, value] in [['Source', reconcile?.source], ['Counterparty', reconcile?.counterparty], ['Amount', '₹' + Number(reconcile?.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})], ['Date', reconcile?.date], ['Method', reconcile?.method], ['Reference', reconcile?.reference_label]]" :key="label"><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400" x-text="label"></span><strong class="mt-1 block break-words text-slate-900" x-text="value"></strong></div></template></div><div class="mt-3 rounded-xl bg-slate-50 p-3 text-xs"><span class="block font-bold text-slate-400">Description / Note</span><strong class="mt-1 block break-words text-slate-900" x-text="reconcile?.description"></strong></div>

                        <div class="mt-5 flex items-center justify-between border-b border-slate-200 pb-2">
                            <h3 class="text-sm font-black text-slate-950">Statement Matches</h3>
                            <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1">
                                <button type="button" @click="tab = 'pending'" :class="tab === 'pending' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="rounded-lg px-2.5 py-1 text-xs font-black transition" x-text="'Pending (' + counts.pending + ')'"></button>
                                <button type="button" @click="tab = 'reconciled'" :class="tab === 'reconciled' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="rounded-lg px-2.5 py-1 text-xs font-black transition" x-text="'Already Reconciled (' + counts.reconciled + ')'"></button>
                            </div>
                        </div>

                        <p x-show="loading" class="mt-4 text-sm font-bold text-slate-500">Loading statement matches...</p>

                        <div x-show="!loading && tab === 'pending'" class="mt-3 space-y-2">
                            <template x-for="candidate in candidates" :key="candidate.id">
                                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="flex justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700" x-text="candidate.account"></span>
                                                <span :class="candidate.date_match === 'exact' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'" class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em]" x-text="candidate.date_badge_text"></span>
                                            </div>
                                            <p class="mt-2 text-xs font-semibold text-slate-600"><span x-text="candidate.date"></span> / <span x-text="candidate.reference || candidate.narration || 'No reference'"></span></p>
                                            <p class="mt-0.5 text-xs text-slate-500" x-text="candidate.narration"></p>
                                        </div>
                                        <strong class="font-mono text-lg shrink-0" x-text="'₹' + Number(candidate.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></strong>
                                    </div>
                                    <form method="POST" :action="candidate.match_url" class="mt-3">
                                        @csrf
                                        <input type="hidden" :name="reconcile.kind === 'shop_payment' ? 'payment_request_ref' : 'candidate_ref'" :value="reconcile.reference">
                                        <button class="rounded-xl bg-slate-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white hover:bg-slate-800">Match &amp; Finalize</button>
                                    </form>
                                </article>
                            </template>
                            <p x-show="candidates.length === 0" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No pending statement movements found with exact amount.</p>
                        </div>

                        <div x-show="!loading && tab === 'reconciled'" class="mt-3 space-y-2">
                            <template x-for="candidate in reconciled" :key="candidate.id">
                                <article class="rounded-2xl border border-amber-200 bg-amber-50/40 p-3">
                                    <div class="flex justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700" x-text="candidate.account"></span>
                                                <span :class="candidate.date_match === 'exact' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'" class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em]" x-text="candidate.date_badge_text"></span>
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-800">Currently Reconciled</span>
                                            </div>
                                            <p class="mt-2 text-xs font-semibold text-slate-600"><span x-text="candidate.date"></span> / <span x-text="candidate.reference || candidate.narration || 'No reference'"></span></p>
                                            <div class="mt-2 rounded-xl bg-white border border-amber-100 p-2 text-xs">
                                                <span class="font-bold text-amber-900">Current Match:</span> <span class="text-amber-800 font-semibold" x-text="candidate.matched_to || 'Reconciled Entry'"></span>
                                                <span class="text-amber-600 text-[11px] block mt-0.5" x-text="'Finalized ' + (candidate.matched_date || '—')"></span>
                                            </div>
                                        </div>
                                        <strong class="font-mono text-lg shrink-0" x-text="'₹' + Number(candidate.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></strong>
                                    </div>
                                    <form method="POST" :action="candidate.match_url" onsubmit="return confirm('Replace existing statement match? The old transaction will return to Pending Reconciliation.')" class="mt-3">
                                        @csrf
                                        <input type="hidden" :name="reconcile.kind === 'shop_payment' ? 'payment_request_ref' : 'candidate_ref'" :value="reconcile.reference">
                                        <button class="rounded-xl bg-amber-600 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white hover:bg-amber-700">Replace Match</button>
                                    </form>
                                </article>
                            </template>
                            <p x-show="reconciled.length === 0" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No previously reconciled statement movements found with exact amount.</p>
                        </div>
                    </div>
                </div>
            </section>

            @if($findPendingSource)
                <section id="reconcile-panel" tabindex="-1" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-xl sm:p-6"><div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">{{ $showPendingDetails ? 'Transaction Details' : 'Match Statement' }}</p><h2 class="mt-1 text-xl font-black text-slate-950">Transaction Summary</h2></div><a href="{{ route('admin.cashbook.finance.reconciliation', array_merge(request()->query(), ['find_kind' => null, 'find_ref' => null, 'details' => null, 'statement_search' => null])) }}" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">Close</a></div><div class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Source</span><strong>{{ $findPendingSource['source'] }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Counterparty</span><strong>{{ $findPendingSource['counterparty'] }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Amount</span><strong class="font-mono">₹{{ number_format($findPendingSource['amount'], 2) }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Date</span><strong>{{ $findPendingSource['date'] }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Method</span><strong>{{ $findPendingSource['method'] }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Reference</span><strong>{{ $findPendingSource['reference_label'] }}</strong></div></div><p class="mt-3 text-xs font-semibold text-slate-600">{{ $findPendingSource['description'] }}</p>@if($showPendingDetails)<div class="mt-4 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">@foreach($findPendingSource['details'] as $label => $value)<div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">{{ $label }}</span><strong class="break-words text-slate-900">{{ $value }}</strong></div>@endforeach</div><a href="{{ route('admin.cashbook.finance.reconciliation', array_merge(request()->query(), ['details' => null])) }}#reconcile-panel" class="mt-4 inline-flex rounded-xl bg-slate-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white">Find Match</a>@else<form method="GET" action="{{ route('admin.cashbook.finance.reconciliation') }}" class="mt-5 grid grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[1fr_auto]"><input type="hidden" name="workspace" value="needs_reconciliation"><input type="hidden" name="find_kind" value="{{ request('find_kind') }}"><input type="hidden" name="find_ref" value="{{ request('find_ref') }}"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="direction" value="{{ $findPendingSource['direction'] }}"><input type="search" name="statement_search" value="{{ $statementSearch }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Search statement reference or narration"><button type="submit" class="rounded-xl bg-slate-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white">Search Statement</button></form><h3 class="mt-5 text-sm font-black text-slate-950">Best Matches</h3><div class="mt-3 space-y-2">@forelse($findStatementCandidates as $statement)<article class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><div class="flex justify-between gap-3"><div><p class="font-black text-slate-950">{{ $statement['account_name'] }}</p><p class="mt-1 text-xs font-semibold text-slate-600">{{ $statement['transaction_date'] }} / {{ $statement['reference'] !== '—' ? $statement['reference'] : $statement['narration'] }}</p></div><strong class="font-mono text-lg">₹{{ number_format($statement['amount'], 2) }}</strong></div><form method="POST" action="{{ $findPendingSource['kind'] === 'shop_payment' ? route('admin.cashbook.finance.reconciliation.classify-shop-payment', $statement['public_uuid']) : route('admin.cashbook.finance.reconciliation.match-existing', $statement['public_uuid']) }}" class="mt-3">@csrf<input type="hidden" name="{{ $findPendingSource['kind'] === 'shop_payment' ? 'payment_request_ref' : 'candidate_ref' }}" value="{{ $findPendingSource['reference'] }}"><button class="rounded-xl bg-emerald-600 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white">Match</button></form></article>@empty<p class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No matching bank/cash movement found yet.<span class="mt-1 block font-semibold text-slate-500">This transaction will remain pending until a matching statement movement is available.</span></p>@endforelse</div>@endif</section>
            @endif
        @elseif($workspaceTab === 'history')
            <section class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-5"><div class="border-b border-slate-200 pb-3"><h2 class="text-base font-black text-slate-950">Reconciled History</h2><p class="mt-1 text-xs font-semibold text-slate-500">Finalized cash and bank movements for {{ \Carbon\Carbon::parse($monthStart)->format('F Y') }}.</p></div><div class="mt-4 space-y-2">@forelse($historyEntries as $entry)<article class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><div class="flex justify-between gap-3"><div><p class="font-black text-slate-950">{{ $entry->source_label }}</p><p class="mt-1 text-xs font-semibold text-slate-600">{{ $entry->transaction_date?->format('Y-m-d') }} / {{ $entry->companyAccount?->name }} / {{ $entry->reference ?: 'No reference' }}</p><p class="mt-1 text-[10px] font-black uppercase text-emerald-700">Finalized {{ $entry->finalized_at?->format('Y-m-d H:i') }}</p></div><strong class="font-mono text-lg">₹{{ number_format($entry->amount, 2) }}</strong></div></article>@empty<p class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">No finalized movements this month.</p>@endforelse</div>@if($historyEntries->hasPages())<div class="mt-4">{{ $historyEntries->links() }}</div>@endif</section>
        @endif
        @endunless

        @if(! $classifyStatement && $workspaceTab === 'statements')

        <section class="white-card rounded-3xl border border-slate-200 p-4 shadow-sm">
            <span class="sr-only">Needs Reconciliation Awaiting Reconciliation Statement Movements Reconciled History Finalized This Month Unmatched Statements Partial</span>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-950">Statement Management</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Import, inspect, diagnose, and manually review raw bank or cash statement rows.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.cashbook.finance.reconciliation', ['month' => $month]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">Back to Reconciliation</a>
                    <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">View Approved Transactions</a>
                </div>
            </div>
        </section>

        <section class="white-card rounded-3xl border border-slate-200 p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.cashbook.finance.reconciliation') }}" class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto_auto_auto_auto_1fr_auto]">
                <select name="company_account_uuid" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    @foreach($companyAccounts as $account)
                        <option value="{{ $account->public_uuid }}" @selected($selectedAccountUuid === $account->public_uuid)>
                            {{ $account->name }} / {{ strtoupper($account->account_type) }}
                        </option>
                    @endforeach
                </select>
                <x-cashbook.previous-month-button mode="month" size="md" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                <input type="month" name="month" value="{{ $month }}" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                <select name="direction" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="all" @selected($direction === 'all')>All Directions</option>
                    <option value="in" @selected($direction === 'in')>IN</option>
                    <option value="out" @selected($direction === 'out')>OUT</option>
                </select>
                <input type="number" min="0" max="60" name="grace_days" value="{{ $graceDays }}" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Grace days">
                <input type="search" name="search" value="{{ $search }}" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Search reference, narration, amount">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-4 text-xs font-black text-white hover:bg-slate-800">
                    <i data-lucide="filter" class="h-4 w-4"></i> Filter
                </button>
                <input type="hidden" name="status" value="{{ $queueStatus }}">
            </form>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-950">Needs Action</h2>
                            <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ \Carbon\Carbon::parse($monthStart)->format('F Y') }} cash and bank queue.</p>
                        </div>
                        <span class="font-mono text-xs font-bold text-slate-400">{{ $statementEntries->total() }} rows</span>
                    </div>
                    <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                        @foreach($queueTabs as $tabKey => $tabLabel)
                            <a href="{{ route('admin.cashbook.finance.reconciliation', ['company_account_uuid' => $selectedAccountUuid, 'month' => $month, 'direction' => $direction, 'status' => $tabKey, 'grace_days' => $graceDays, 'search' => $search]) }}"
                               class="shrink-0 rounded-full px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] {{ $queueStatus === $tabKey ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                {{ $tabLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-2">
                    @forelse($statementEntries as $entry)
                        @php
                            $remaining = max(0, (float) $entry->amount - (float) $entry->matched_amount);
                            $primaryAction = $entry->is_finalized
                                ? 'View'
                                : ($entry->status === 'partially_matched'
                                    ? 'Continue Reconciliation'
                                    : ($entry->journal_entry_id ? 'Reconcile' : 'Classify / Match'));
                        @endphp
                        <a href="{{ $entry->status === 'unmatched' && ! $entry->journal_entry_id && ! $entry->is_finalized
                            ? route('admin.cashbook.finance.reconciliation', ['classify_statement' => $entry->public_uuid, 'company_account_uuid' => $entry->companyAccount?->public_uuid, 'month' => $month, 'direction' => $direction, 'status' => $queueStatus, 'grace_days' => $graceDays, 'search' => $search])
                            : route('admin.cashbook.finance.reconciliation', ['statementRef' => $entry->secureRouteKey(), 'company_account_uuid' => $entry->companyAccount?->public_uuid, 'month' => $month, 'direction' => $direction, 'status' => $queueStatus, 'grace_days' => $graceDays, 'search' => $search]) }}"
                           class="block rounded-2xl border p-3 transition {{ $selectedStatement && $selectedStatement->id === $entry->id ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50 hover:bg-white' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $entry->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ strtoupper($entry->direction) }}</span>
                                        <div class="font-mono text-lg font-black text-slate-950">₹{{ number_format($entry->amount, 2) }}</div>
                                        <span class="rounded-full bg-white px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">{{ $entry->source_label }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-700">{{ $entry->narration ?: $entry->reference ?: 'No narration' }}</p>
                                    <p class="mt-1 text-[11px] font-bold text-slate-400">{{ $entry->transaction_date?->format('Y-m-d') }} / {{ $entry->companyAccount?->name }} / {{ $entry->reference ?: 'No reference' }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $entry->is_finalized ? 'finalized' : $entry->status }}</span>
                                    <div class="mt-2 font-mono text-xs font-bold text-amber-700">Open ₹{{ number_format($remaining, 2) }}</div>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-2">
                                <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Action</span>
                                <span class="rounded-xl bg-slate-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-white">{{ $primaryAction }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">
                            No cashbook action rows for this filter.
                        </div>
                    @endforelse
                </div>

                @if($statementEntries->hasPages())
                    <div class="mt-4">
                        {{ $statementEntries->links() }}
                    </div>
                @endif
            </div>

            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-5">
                @if($selectedStatement)
                    <div class="mb-4 border-b border-slate-200 pb-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-base font-extrabold text-slate-950">Selected Statement</h2>
                                <p class="mt-1 break-words text-xs font-semibold text-slate-600">{{ $selectedStatement->narration ?: $selectedStatement->reference ?: 'No narration' }}</p>
                                <p class="mt-1 text-[11px] font-black uppercase text-slate-400">{{ strtoupper($selectedStatement->direction) }} / {{ $selectedStatement->source_label }} / {{ $selectedStatement->transaction_date?->format('Y-m-d') }}</p>
                            </div>
                            <div class="shrink-0 rounded-2xl bg-slate-50 px-4 py-3 text-right">
                                <span class="block text-[10px] font-black uppercase text-slate-400">Remaining</span>
                                <strong class="font-mono text-xl font-extrabold text-emerald-700">₹{{ number_format($remainingStatement, 2) }}</strong>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <span class="block font-bold text-slate-400">Account</span>
                                <strong class="text-slate-900">{{ $selectedStatement->companyAccount?->name }}</strong>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <span class="block font-bold text-slate-400">Reference</span>
                                <strong class="break-words text-slate-900">{{ $selectedStatement->reference ?: '—' }}</strong>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <span class="block font-bold text-slate-400">Status</span>
                                <strong class="uppercase text-slate-900">{{ $selectedStatement->is_finalized ? 'finalized' : $selectedStatement->status }}</strong>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <span class="block font-bold text-slate-400">Matched</span>
                                <strong class="font-mono text-slate-900">₹{{ number_format((float) $selectedStatement->matched_amount, 2) }}</strong>
                            </div>
                        </div>

                        @if(! $selectedStatement->is_finalized && ! $classifyStatement)
                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">What is this transaction?</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($directionActions[$selectedStatement->direction] ?? ['Match Existing'] as $action)
                                        <span class="rounded-full bg-slate-100 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">{{ $action['label'] }}</span>
                                    @endforeach
                                </div>
                                <p class="mt-3 text-xs font-semibold text-slate-500">Use Classify / Match on unmatched rows to open drawer.</p>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3">
                        @forelse($possiblePayments as $candidate)
                            @if(isset($candidate['journal_entry']))
                                @php
                                    $journalEntry = $candidate['journal_entry'];
                                @endphp
                                <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.match-journal', ['statementRef' => $selectedStatement->secureRouteKey(), 'grace_days' => $graceDays]) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    @csrf
                                    <input type="hidden" name="journal_entry_id" value="{{ $journalEntry->id }}">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('admin.cashbook.finance.journal.entry-show', $journalEntry) }}" class="text-sm font-black text-slate-950 hover:text-emerald-700">{{ $journalEntry->formatted_reference }}</a>
                                                <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $journalEntry->source_label }}</span>
                                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">Score {{ $candidate['score'] }}</span>
                                            </div>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $journalEntry->description ?: $journalEntry->reference ?: 'Pending journal' }}</p>
                                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                                <div class="rounded-xl bg-white p-2">
                                                    <span class="block font-bold text-slate-400">Date</span>
                                                    <strong class="font-mono text-slate-900">{{ $journalEntry->entry_date?->format('Y-m-d') }}</strong>
                                                </div>
                                                <div class="rounded-xl bg-white p-2">
                                                    <span class="block font-bold text-slate-400">Amount</span>
                                                    <strong class="font-mono text-slate-900">₹{{ number_format($journalEntry->primary_amount, 2) }}</strong>
                                                </div>
                                                <div class="rounded-xl bg-white p-2">
                                                    <span class="block font-bold text-slate-400">Open</span>
                                                    <strong class="font-mono text-amber-700">₹{{ number_format($candidate['floating_amount'], 2) }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid w-full grid-cols-1 gap-2 text-xs lg:max-w-xs">
                                            <input type="number" step="0.01" min="0.01" name="cleared_amount" value="{{ number_format(min($candidate['floating_amount'], $remainingStatement), 2, '.', '') }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-mono font-bold text-slate-800" placeholder="Cleared">
                                            <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white hover:bg-emerald-500">
                                                <i data-lucide="check-circle-2" class="h-4 w-4"></i> Reconcile
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @else
                                @php
                                    $payment = $candidate['payment'];
                                @endphp
                                <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.match', ['statementRef' => $selectedStatement->secureRouteKey(), 'grace_days' => $graceDays]) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    @csrf
                                    <input type="hidden" name="payment_request_ref" value="{{ $payment->secureRouteKey() }}">
                                    <input type="hidden" name="business_date" value="{{ $selectedStatement->transaction_date?->toDateString() }}">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('admin.cashbook.finance.journal.secure-show', $payment->secureRouteKey()) }}" class="text-sm font-black text-slate-950 hover:text-emerald-700">{{ $payment->shop?->name ?? 'Shop' }}</a>
                                                <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $payment->paymentMethodLabel() }}</span>
                                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">Score {{ $candidate['score'] }}</span>
                                            </div>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                                {{ $payment->payment_date?->format('Y-m-d') ?: $payment->created_at?->format('Y-m-d') }}
                                                @if($payment->payment_reference)
                                                    / {{ $payment->payment_reference }}
                                                @endif
                                            </p>
                                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                                <div class="rounded-xl bg-white p-2">
                                                    <span class="block font-bold text-slate-400">Submitted</span>
                                                    <strong class="font-mono text-slate-900">₹{{ number_format($payment->requested_amount, 2) }}</strong>
                                                </div>
                                                <div class="rounded-xl bg-white p-2">
                                                    <span class="block font-bold text-slate-400">Approved</span>
                                                    <strong class="font-mono text-emerald-700">₹{{ number_format($payment->reconciled_amount, 2) }}</strong>
                                                </div>
                                                <div class="rounded-xl bg-white p-2">
                                                    <span class="block font-bold text-slate-400">Floating</span>
                                                    <strong class="font-mono text-amber-700">₹{{ number_format($candidate['floating_amount'], 2) }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid w-full grid-cols-1 gap-2 text-xs sm:grid-cols-2 lg:max-w-lg">
                                            <input type="number" step="0.01" min="0.01" name="cleared_amount" value="{{ number_format(min($candidate['floating_amount'], $remainingStatement), 2, '.', '') }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-mono font-bold text-slate-800" placeholder="Cleared">
                                            <input type="number" step="0.01" min="0.01" name="statement_amount" value="{{ number_format(min($candidate['floating_amount'], $remainingStatement), 2, '.', '') }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-mono font-bold text-slate-800" placeholder="Statement">
                                            <select name="difference_action" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-bold text-slate-800">
                                                <option value="none">No difference</option>
                                                <option value="keep_floating">Keep floating</option>
                                                <option value="shop_expense">Add shop expense</option>
                                                <option value="shop_income">Add shop income</option>
                                            </select>
                                            <input type="number" step="0.01" min="0" name="difference_amount" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-mono font-bold text-slate-800" placeholder="Difference">
                                            <select name="difference_entry_type_id" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-bold text-slate-800">
                                                <option value="">Default category</option>
                                                @foreach($reconciliationEntryTypes as $entryType)
                                                    <option value="{{ $entryType->id }}">{{ $entryType->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="admin_note" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-semibold text-slate-800" placeholder="Admin note">
                                            <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white hover:bg-emerald-500 sm:col-span-2">
                                                <i data-lucide="check-circle-2" class="h-4 w-4"></i> Approve Match
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @endif
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">
                                No compatible match inside current filters. Try wider grace days or use statement-first classification in next phase.
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">
                        No statement row selected.
                    </div>
                @endif
            </div>
        </section>
        @endif
    </div>

    @if($classifyStatement)
        <section class="mx-auto max-w-5xl rounded-[2rem] border border-slate-200 bg-white shadow-xl" data-statement-context='@json($classificationContext)'>
            <div class="sticky top-0 z-10 flex items-start justify-between gap-4 rounded-t-[2rem] border-b border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:px-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">{{ $isCreateTransactionPage ? 'Create Transaction' : 'Match Transaction' }}</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $isCreateTransactionPage ? 'Classify this unmatched movement' : 'Find an existing transaction' }}</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $isCreateTransactionPage ? 'Statement values are locked. Choose the business source below.' : 'Find an existing transaction for this bank/cash movement.' }}</p>
                </div>
                <a href="{{ $actionCenterUrl }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-100 px-3 text-xs font-black text-slate-700 hover:bg-slate-200" aria-label="Close classification page">
                    <i data-lucide="x" class="h-4 w-4"></i>
                    <span class="hidden sm:inline">Close</span>
                </a>
            </div>

            <div class="space-y-5 p-4 sm:p-6">
                <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $classifyStatement->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ strtoupper($classifyStatement->direction) }}</span>
                            <p class="mt-3 font-mono text-4xl font-black leading-none text-slate-950 sm:text-5xl">₹{{ number_format((float) $classifyStatement->amount, 2) }}</p>
                            <p class="mt-2 text-xs font-black uppercase tracking-[0.14em] text-slate-400">Status: UNMATCHED</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3 text-left shadow-sm sm:min-w-56 sm:text-right">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Company Account</p>
                            <p class="mt-1 font-black text-slate-950">{{ $classifyStatement->companyAccount?->name }}</p>
                            <p class="mt-1 text-xs font-bold uppercase text-slate-500">{{ $classifyStatement->companyAccount?->account_type }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Date</p>
                            <p class="mt-1 font-mono font-black text-slate-950">{{ $classifyStatement->transaction_date?->format('Y-m-d') }}</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3 sm:col-span-2">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Reference</p>
                            <p class="mt-1 break-words font-black text-slate-950">{{ $classifyStatement->reference ?: '—' }}</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3 sm:col-span-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Narration</p>
                            <p class="mt-1 break-words font-semibold text-slate-800">{{ $classifyStatement->narration ?: '—' }}</p>
                        </div>
                    </div>
                </section>

                @unless($isCreateTransactionPage)
                    @php
                        $pendingCandidates = $matchExistingCandidates['pending'] ?? [];
                        $reconciledCandidates = $matchExistingCandidates['reconciled'] ?? [];
                        $counts = $matchExistingCandidates['counts'] ?? [
                            'pending' => count($pendingCandidates),
                            'reconciled' => count($reconciledCandidates),
                            'exact_date_pending' => 0,
                            'exact_date_reconciled' => 0,
                        ];
                        $exactPending = array_filter($pendingCandidates, fn ($c) => $c['date_match'] === 'exact');
                        $otherPending = array_filter($pendingCandidates, fn ($c) => $c['date_match'] !== 'exact');
                    @endphp
                    <section data-classification-action="match-existing" x-data="{ activeTab: 'pending' }" class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-black text-slate-950">Possible Matches</h3>
                                    <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Match Existing Transaction</span>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    {{ $counts['pending'] + $counts['reconciled'] }} exact-amount {{ Str::plural('candidate', $counts['pending'] + $counts['reconciled']) }} found for ₹{{ number_format((float) $classifyStatement->amount, 2) }}.
                                </p>
                            </div>
                            <div class="flex items-center gap-1 rounded-xl bg-white p-1 shadow-sm border border-slate-200">
                                <button type="button" @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="rounded-lg px-3 py-1.5 text-xs font-black transition">
                                    Pending ({{ $counts['pending'] }})
                                </button>
                                <button type="button" @click="activeTab = 'reconciled'" :class="activeTab === 'reconciled' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="rounded-lg px-3 py-1.5 text-xs font-black transition">
                                    Already Reconciled ({{ $counts['reconciled'] }})
                                </button>
                            </div>
                        </div>

                        <!-- Pending Matches Tab -->
                        <div x-show="activeTab === 'pending'" class="mt-4 space-y-4">
                            @if(empty($pendingCandidates))
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
                                    <p class="text-sm font-black text-slate-900">No unmatched pending transaction found for this movement.</p>
                                    @if(!empty($reconciledCandidates))
                                        <p class="mt-1 text-xs font-semibold text-amber-700">Check the <strong>Already Reconciled</strong> tab above to view and replace existing matches.</p>
                                    @else
                                        <p class="mt-1 text-xs font-semibold text-slate-500">Create a new transaction from this same imported statement. No duplicate statement row will be created.</p>
                                    @endif
                                </div>
                            @else
                                @if(!empty($exactPending))
                                    <div>
                                        <div class="mb-2 flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2 py-0.5 text-[10px] font-black uppercase text-white tracking-wider">
                                                <i data-lucide="check" class="h-3 w-3"></i> Best Match (Exact Date)
                                            </span>
                                            <span class="text-xs font-semibold text-slate-500">Same amount and identical date</span>
                                        </div>
                                        <div class="grid grid-cols-1 gap-3">
                                            @foreach($exactPending as $candidate)
                                                @include('admin.cashbook.finance._candidate_journal_card', ['candidate' => $candidate, 'classifyStatement' => $classifyStatement, 'isReconciled' => false])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(!empty($otherPending))
                                    <div>
                                        @if(!empty($exactPending))
                                            <div class="mb-2 flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1 rounded-md bg-slate-200 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700 tracking-wider">
                                                    Other Same-Amount Matches
                                                </span>
                                                <span class="text-xs font-semibold text-slate-500">Different date proximity</span>
                                            </div>
                                        @endif
                                        <div class="grid grid-cols-1 gap-3">
                                            @foreach($otherPending as $candidate)
                                                @include('admin.cashbook.finance._candidate_journal_card', ['candidate' => $candidate, 'classifyStatement' => $classifyStatement, 'isReconciled' => false])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <!-- Already Reconciled Tab -->
                        <div x-show="activeTab === 'reconciled'" x-cloak class="mt-4 space-y-4">
                            @if(empty($reconciledCandidates))
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
                                    <p class="text-sm font-black text-slate-900">No previously reconciled transactions found with exact amount.</p>
                                </div>
                            @else
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-900">
                                    <i data-lucide="info" class="inline h-4 w-4 mr-1 text-amber-700"></i>
                                    Replacing a match will unlink the statement from the previous transaction and link it to the selected one. The previous transaction will return to <strong>Needs Action</strong> queue.
                                </div>
                                <div class="grid grid-cols-1 gap-3">
                                    @foreach($reconciledCandidates as $candidate)
                                        @include('admin.cashbook.finance._candidate_journal_card', ['candidate' => $candidate, 'classifyStatement' => $classifyStatement, 'isReconciled' => true])
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="sticky bottom-0 -mx-4 -mb-4 mt-5 border-t border-slate-200 bg-white/95 p-4 backdrop-blur sm:static sm:mx-0 sm:mb-0 sm:rounded-2xl sm:border sm:bg-white">
                            <a href="{{ $createTransactionUrl }}" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-black text-slate-900 shadow-sm hover:bg-slate-50">
                                <i data-lucide="plus" class="h-4 w-4"></i>
                                Create New Transaction
                            </a>
                        </div>
                    </section>
                @else
                    <section>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-base font-black text-slate-950">Create Transaction</h3>
                                <p class="mt-1 text-xs font-semibold text-slate-500">Assign this statement movement to a business transaction.</p>
                            </div>
                            <a href="{{ $matchTransactionUrl }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">← Back to Possible Matches</a>
                        </div>

                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-900">
                            ₹{{ number_format((float) $classifyStatement->amount, 2) }} from this statement will be assigned to the transaction below. No additional Cashbook movement will be created.
                        </div>

                        <div class="mt-4 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-slate-50 p-2">
                            @foreach($createTransactionTabs as $tabKey)
                                <a href="{{ route('admin.cashbook.finance.reconciliation.create-transaction', ['statement' => $classifyStatement, 'type' => $tabKey]) }}"
                                   class="shrink-0 rounded-xl px-3 py-2 text-xs font-black {{ $activeCreateTransactionTab === $tabKey ? 'bg-slate-950 text-white shadow-sm' : 'bg-white text-slate-600 hover:text-slate-950' }}">
                                    {{ $createTabLabels[$tabKey] ?? str($tabKey)->headline() }}
                                </a>
                            @endforeach
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                            <aside class="space-y-3">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Locked Statement</p>
                                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                        <div class="rounded-xl bg-white p-2">
                                            <span class="block font-bold text-slate-400">Amount</span>
                                            <strong class="font-mono text-slate-950">₹{{ number_format((float) $classifyStatement->amount, 2) }}</strong>
                                        </div>
                                        <div class="rounded-xl bg-white p-2">
                                            <span class="block font-bold text-slate-400">Direction</span>
                                            <strong class="text-slate-950">{{ strtoupper($classifyStatement->direction) }}</strong>
                                        </div>
                                        <div class="rounded-xl bg-white p-2">
                                            <span class="block font-bold text-slate-400">Date</span>
                                            <strong class="font-mono text-slate-950">{{ $classifyStatement->transaction_date?->format('Y-m-d') }}</strong>
                                        </div>
                                        <div class="rounded-xl bg-white p-2">
                                            <span class="block font-bold text-slate-400">Account</span>
                                            <strong class="text-slate-950">{{ $classifyStatement->companyAccount?->name }}</strong>
                                        </div>
                                        <div class="rounded-xl bg-white p-2 sm:col-span-2">
                                            <span class="block font-bold text-slate-400">Reference</span>
                                            <strong class="break-words text-slate-950">{{ $classifyStatement->reference ?: '—' }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <h4 class="text-sm font-black text-slate-950">Recent Transactions</h4>
                                    <div class="mt-3 space-y-2">
                                        @php
                                            $recentRows = match ($activeCreateTransactionTab) {
                                                'income', 'expense' => $recentCompanyAccountingEntries,
                                                'payable' => $recentCompanyPayableSettlements,
                                                'vendor' => $recentVendorSettlements,
                                                'salary' => $recentSalaryPayments,
                                                'advance' => $recentSalaryAdvances,
                                                'shop-payment' => $recentShopPayments,
                                                'petty' => $recentShopPettyFunding,
                                                'purchaser' => $recentPurchaserFunding,
                                                default => collect(),
                                            };
                                        @endphp
                                        @forelse($recentRows as $recentRow)
                                            <div class="rounded-xl bg-slate-50 p-3 text-xs">
                                                @if($activeCreateTransactionTab === 'vendor')
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="font-black text-slate-900">{{ $recentRow->supplier?->name ?? 'Vendor' }}</span>
                                                        <span class="font-mono font-black text-slate-950">₹{{ number_format((float) $recentRow->actual_payment_amount, 2) }}</span>
                                                    </div>
                                                    <p class="mt-1 text-slate-500">{{ $recentRow->payment_date?->format('Y-m-d') }} · {{ $recentRow->reference ?: 'No reference' }}</p>
                                                @elseif($activeCreateTransactionTab === 'payable')
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="font-black text-slate-900">{{ $recentRow->shop?->name ?? 'Shop payable' }}</span>
                                                        <span class="font-mono font-black text-slate-950">₹{{ number_format((float) $recentRow->amount, 2) }}</span>
                                                    </div>
                                                    <p class="mt-1 text-slate-500">{{ $recentRow->settlement_date?->format('Y-m-d') }} · {{ $recentRow->reference ?: 'No reference' }}</p>
                                                @elseif(in_array($activeCreateTransactionTab, ['salary', 'advance'], true))
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="font-black text-slate-900">{{ $recentRow->employee?->name ?? 'Employee' }}</span>
                                                        <span class="font-mono font-black text-slate-950">₹{{ number_format((float) $recentRow->amount, 2) }}</span>
                                                    </div>
                                                    <p class="mt-1 text-slate-500">{{ $recentRow->paid_on?->format('Y-m-d') }} · {{ $recentRow->reference ?: 'No reference' }}</p>
                                                @elseif($activeCreateTransactionTab === 'petty')
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="font-black text-slate-900">Shop Petty</span>
                                                        <span class="font-mono font-black text-slate-950">₹{{ number_format((float) $recentRow->amount, 2) }}</span>
                                                    </div>
                                                    <p class="mt-1 text-slate-500">{{ $recentRow->business_date?->format('Y-m-d') }} · {{ $recentRow->notes ?: 'No note' }}</p>
                                                @elseif($activeCreateTransactionTab === 'purchaser')
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="font-black text-slate-900">{{ $recentRow->purchaser?->name ?? 'Purchaser' }}</span>
                                                        <span class="font-mono font-black text-slate-950">₹{{ number_format((float) $recentRow->amount, 2) }}</span>
                                                    </div>
                                                    <p class="mt-1 text-slate-500">{{ $recentRow->business_date?->format('Y-m-d') }} · {{ $recentRow->reference ?: 'No reference' }}</p>
                                                @elseif($activeCreateTransactionTab === 'shop-payment')
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="font-black text-slate-900">{{ $recentRow->shop?->name ?? 'Shop' }}</span>
                                                        <span class="font-mono font-black text-slate-950">₹{{ number_format((float) $recentRow->requested_amount, 2) }}</span>
                                                    </div>
                                                    <p class="mt-1 text-slate-500">{{ $recentRow->payment_date?->format('Y-m-d') }} · {{ $recentRow->paymentMethodLabel() }} · {{ $recentRow->reconciliationStatusLabel() }}</p>
                                                @else
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="font-black text-slate-900">{{ $recentRow->category?->name ?? 'Category' }}</span>
                                                        <span class="font-mono font-black text-slate-950">₹{{ number_format((float) $recentRow->amount, 2) }}</span>
                                                    </div>
                                                    <p class="mt-1 text-slate-500">{{ $recentRow->business_date?->format('Y-m-d') }} · {{ $recentRow->description ?: $recentRow->reference ?: 'No description' }}</p>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-bold text-slate-400">No recent rows.</div>
                                        @endforelse
                                    </div>
                                </div>
                            </aside>

                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                @if($activeCreateTransactionTab === 'payable')
                                    <h4 class="text-base font-black text-slate-950">Apply Company Payable</h4>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Eligible approved payables only.</p>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-company-payable', $classifyStatement) }}" data-classification-action="payable" class="mt-4">
                                        @csrf
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Approved Payable</span>
                                            <select name="shop_accounting_entry_line_id" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                <option value="">Select payable</option>
                                                @foreach($companyPayableTargets as $payableLine)
                                                    <option value="{{ $payableLine->id }}">
                                                        {{ $payableLine->entry?->shop?->name }} · {{ $payableLine->entry?->business_date?->format('Y-m-d') }} · Due ₹{{ number_format($payableLine->remainingCompanyPayableAmount(), 2) }} · {{ $payableLine->description ?: $payableLine->category?->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <textarea name="notes" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Optional note"></textarea>
                                        <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-orange-600 text-xs font-black text-white hover:bg-orange-500">Finalize Payable</button>
                                    </form>
                                @elseif($activeCreateTransactionTab === 'vendor')
                                    <h4 class="text-base font-black text-slate-950">Vendor Payment</h4>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Select vendor first. Bills load only for selected vendor.</p>
                                    <form method="GET" action="{{ route('admin.cashbook.finance.reconciliation.create-transaction', $classifyStatement) }}" class="mt-4">
                                        <input type="hidden" name="type" value="vendor">
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Vendor</span>
                                            <select name="supplier_id" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900" onchange="this.form.submit()">
                                                <option value="">Select vendor</option>
                                                @foreach($vendorPaymentTargets as $supplier)
                                                    <option value="{{ $supplier->id }}" @selected((int) request('supplier_id') === (int) $supplier->id)>{{ $supplier->name }} · Advance ₹{{ number_format((float) $supplier->vendorAdvances->sum('amount_remaining'), 2) }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </form>
                                    @if($selectedVendorPaymentTarget)
                                        @php
                                            $selectedVendorOutstanding = 0.0;
                                            foreach ($selectedVendorPaymentTarget->purchaseInvoices as $invoice) {
                                                $selectedVendorOutstanding += max(0, ((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->vendorSettlementAllocations->sum('total_settled'));
                                            }
                                        @endphp
                                        <div class="mt-4 grid grid-cols-1 gap-2 text-xs sm:grid-cols-3">
                                            <div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Statement</span><strong class="font-mono">₹{{ number_format((float) $classifyStatement->amount, 2) }}</strong></div>
                                            <div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Vendor outstanding</span><strong class="font-mono">₹{{ number_format($selectedVendorOutstanding, 2) }}</strong></div>
                                            <div class="rounded-xl bg-slate-50 p-3"><span class="block font-bold text-slate-400">Advance</span><strong class="font-mono">₹{{ number_format((float) $selectedVendorPaymentTarget->vendorAdvances->sum('amount_remaining'), 2) }}</strong></div>
                                        </div>
                                        <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-vendor-payment', $classifyStatement) }}" data-classification-action="vendor-payment" class="mt-4">
                                            @csrf
                                            <input type="hidden" name="supplier_id" value="{{ $selectedVendorPaymentTarget->id }}">
                                            <div class="max-h-80 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                @foreach($selectedVendorPaymentTarget->purchaseInvoices as $invoice)
                                                    @php
                                                        $invoiceOutstanding = max(0, ((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->vendorSettlementAllocations->sum('total_settled'));
                                                    @endphp
                                                    @if($invoiceOutstanding > 0.01)
                                                        <label class="flex items-start justify-between gap-2 rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-800">
                                                            <span class="flex items-start gap-2">
                                                            <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="mt-0.5 rounded border-slate-300">
                                                                <span>{{ $invoice->invoice_number }} · {{ $invoice->invoice_date?->format('Y-m-d') }}</span>
                                                            </span>
                                                            <span class="font-mono">₹{{ number_format($invoiceOutstanding, 2) }}</span>
                                                        </label>
                                                    @endif
                                                @endforeach
                                            </div>
                                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">
                                                    <input type="checkbox" name="use_vendor_advance" value="1" class="rounded border-slate-300"> Use vendor advance
                                                </label>
                                                <select name="difference_treatment" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                    <option value="outstanding">Leave balance outstanding</option>
                                                    <option value="discount">Treat difference as discount</option>
                                                </select>
                                            </div>
                                            <input type="hidden" name="allocation_order" value="oldest">
                                            <textarea name="note" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Optional note"></textarea>
                                            <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-violet-600 text-xs font-black text-white hover:bg-violet-500">Finalize Vendor Settlement</button>
                                        </form>
                                    @endif
                                @elseif($activeCreateTransactionTab === 'purchaser')
                                    <h4 class="text-base font-black text-slate-950">Purchaser Funding</h4>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-purchaser-funding', $classifyStatement) }}" data-classification-action="purchaser-funding" class="mt-4">
                                        @csrf
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Purchaser</span>
                                            <select name="purchaser_uuid" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                <option value="">Select purchaser</option>
                                                @foreach($purchaserFundingTargets as $purchaser)
                                                    <option value="{{ $purchaser->public_uuid }}">{{ $purchaser->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <textarea name="description" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Optional description"></textarea>
                                        <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-lime-600 text-xs font-black text-white hover:bg-lime-500">Finalize Purchaser Funding</button>
                                    </form>
                                @elseif($activeCreateTransactionTab === 'income')
                                    <h4 class="text-base font-black text-slate-950">Other Income</h4>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-company-accounting', $classifyStatement) }}" data-classification-action="custom-income" class="mt-4">
                                        @csrf
                                        <input type="hidden" name="type" value="income">
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Income Category</span>
                                            <select name="company_accounting_category_id" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                <option value="">Select category</option>
                                                @foreach($incomeCategories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <textarea name="description" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Notes / Description (required for Other)"></textarea>
                                        <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-emerald-600 text-xs font-black text-white hover:bg-emerald-500">Finalize Income</button>
                                    </form>
                                @elseif($activeCreateTransactionTab === 'shop-payment')
                                    <h4 class="text-base font-black text-slate-950">Shop Payment</h4>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Apply this imported IN statement to one existing Shop Payment. No new payment request or Cashbook movement is created.</p>
                                    <div class="mt-4 rounded-2xl bg-emerald-50 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Statement Amount</p>
                                        <p class="mt-1 font-mono text-2xl font-black text-emerald-950">₹{{ number_format((float) $classifyStatement->amount, 2) }}</p>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-950">Eligible Payments</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">Exact amount and matching cash/bank method only.</p>
                                        </div>
                                        <a href="{{ $actionCenterUrl }}" class="text-xs font-black text-slate-600 hover:text-slate-950">Back to Possible Matches</a>
                                    </div>
                                    <div class="mt-3 space-y-3">
                                        @forelse($eligibleShopPayments as $candidate)
                                            @php
                                                $payment = $candidate['payment'];
                                            @endphp
                                            <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-shop-payment', $classifyStatement) }}" data-classification-action="shop-payment" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                                @csrf
                                                <input type="hidden" name="payment_request_ref" value="{{ $payment->secureRouteKey() }}">
                                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">{{ $payment->shop?->name ?? 'Shop' }}</span>
                                                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-emerald-700">{{ $payment->reconciliationStatusLabel() }}</span>
                                                        </div>
                                                        <p class="mt-3 font-mono text-sm font-black text-slate-950">{{ $payment->payment_reference ?: 'Shop Payment #'.$payment->id }}</p>
                                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $payment->payment_date?->format('Y-m-d') ?: $payment->created_at?->format('Y-m-d') }} · {{ $payment->paymentMethodLabel() }}</p>
                                                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                                                            <span class="rounded-lg bg-slate-50 px-2 py-2"><strong>Requested</strong><br>₹{{ number_format((float) $payment->requested_amount, 2) }}</span>
                                                            <span class="rounded-lg bg-slate-50 px-2 py-2"><strong>Floating</strong><br>₹{{ number_format($candidate['floating_amount'], 2) }}</span>
                                                            <span class="rounded-lg bg-slate-50 px-2 py-2"><strong>Status</strong><br>{{ $payment->statusLabel() }}</span>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-xl bg-emerald-600 px-4 text-xs font-black text-white hover:bg-emerald-500 sm:w-auto">Apply Statement &amp; Finalize</button>
                                                </div>
                                            </form>
                                        @empty
                                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                                <p class="text-sm font-black text-slate-900">No matching Shop Payment found.</p>
                                                <a href="{{ $actionCenterUrl }}" class="mt-3 inline-flex text-xs font-black text-emerald-700 hover:text-emerald-800">Back to Possible Matches</a>
                                            </div>
                                        @endforelse
                                    </div>
                                @elseif($activeCreateTransactionTab === 'expense')
                                    <h4 class="text-base font-black text-slate-950">Other Expense</h4>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-company-accounting', $classifyStatement) }}" data-classification-action="custom-expense" class="mt-4">
                                        @csrf
                                        <input type="hidden" name="type" value="expense">
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Expense Category</span>
                                            <select name="company_accounting_category_id" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                <option value="">Select category</option>
                                                @foreach($expenseCategories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <textarea name="description" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Notes / Description (required for Other)"></textarea>
                                        <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-rose-600 text-xs font-black text-white hover:bg-rose-500">Finalize Expense</button>
                                    </form>
                                @elseif($activeCreateTransactionTab === 'petty')
                                    <h4 class="text-base font-black text-slate-950">Shop Petty</h4>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-shop-petty', $classifyStatement) }}" data-classification-action="shop-petty" class="mt-4">
                                        @csrf
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Shop</span>
                                            <select name="shop_uuid" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                <option value="">Select shop</option>
                                                @foreach($shops as $shop)
                                                    <option value="{{ $shop->public_uuid }}">{{ $shop->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <textarea name="notes" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Optional note"></textarea>
                                        <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-amber-500 text-xs font-black text-slate-950 hover:bg-amber-400">Finalize Shop Petty</button>
                                    </form>
                                @elseif($activeCreateTransactionTab === 'salary')
                                    <h4 class="text-base font-black text-slate-950">Salary Payment</h4>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-salary-payment', $classifyStatement) }}" data-classification-action="salary-payment" class="mt-4">
                                        @csrf
                                        <p class="mt-1 text-[11px] font-semibold text-slate-500">Pays the selected finalized payroll item using this statement amount.</p>
                                        <label class="mt-3 block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Payroll Item</span>
                                            <select name="payroll_run_item_id" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                <option value="">Select salary payable</option>
                                                @foreach($salaryPaymentTargets as $payrollItem)
                                                    <option value="{{ $payrollItem->id }}">
                                                        {{ $payrollItem->employee?->name }} · {{ $payrollItem->payrollRun?->period_start?->format('M Y') }} · Due ₹{{ number_format($payrollItem->remainingGreenLeafAmount(), 2) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <textarea name="notes" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Optional note"></textarea>
                                        <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-sky-600 text-xs font-black text-white hover:bg-sky-500">Finalize Salary Payment</button>
                                    </form>
                                @elseif($activeCreateTransactionTab === 'advance')
                                    <h4 class="text-base font-black text-slate-950">Salary Advance</h4>
                                    <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.classify-salary-advance', $classifyStatement) }}" data-classification-action="salary-advance" class="mt-4">
                                        @csrf
                                        <p class="mt-1 text-[11px] font-semibold text-slate-500">Pays an approved employee advance using this statement amount.</p>
                                        <label class="mt-3 block">
                                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Advance Request</span>
                                            <select name="employee_advance_request_id" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                                <option value="">Select approved advance</option>
                                                @foreach($salaryAdvanceTargets as $advanceRequest)
                                                    <option value="{{ $advanceRequest->id }}">
                                                        {{ $advanceRequest->employee?->name }} · {{ $advanceRequest->shop?->name }} · Due ₹{{ number_format((float) $advanceRequest->approved_amount, 2) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <textarea name="notes" rows="2" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900" placeholder="Optional note"></textarea>
                                        <button type="submit" class="mt-3 h-10 w-full rounded-xl bg-cyan-600 text-xs font-black text-white hover:bg-cyan-500">Finalize Salary Advance</button>
                                    </form>
                                @else
                                    <div data-classification-action="{{ $activeCreateTransactionTab }}" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center">
                                        <p class="text-sm font-black text-slate-900">{{ $createTabLabels[$activeCreateTransactionTab] ?? 'Transaction' }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">Coming in next phase.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </section>
                @endunless
            </div>
        </section>
    @endif

    @php
        $formattedMonth = \Carbon\Carbon::parse($month.'-01')->format('F Y');
        $expectedConfirm = 'CLEAR ' . strtoupper($formattedMonth);
    @endphp

    {{-- ── Clear Month Reconciliation Modal ─────────────────────────────── --}}
    <div id="clearMonthModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-rose-100 bg-rose-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="h-5 w-5 text-rose-700"></i>
                    <h3 class="font-black text-rose-950">CLEAR {{ strtoupper($formattedMonth) }} RECONCILIATION?</h3>
                </div>
                <button type="button" onclick="closeClearMonthModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.reset-month') }}" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-2 text-slate-700">
                    <p class="font-black text-slate-900">This will:</p>
                    <ul class="list-disc pl-4 space-y-1 text-slate-600">
                        <li>Remove statement ↔ ERP transaction matches for <strong>{{ $formattedMonth }}</strong></li>
                        <li>Return affected ERP transactions to <strong>Needs Review / Unmatched</strong></li>
                        <li>Return eligible statement rows to <strong>Unmatched</strong></li>
                        <li>Preserve all original money transactions, statement rows, journals, balances, and audit history</li>
                    </ul>
                    <p class="pt-1 text-[11px] font-semibold text-slate-500">
                        It will <strong>NOT</strong> delete transactions, bank statements, or journal entries, or change transaction amounts.
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="clearMonthConfirmation" class="block font-bold text-slate-700">
                        Type to confirm: <span class="font-mono font-black text-rose-700 select-all">{{ $expectedConfirm }}</span>
                    </label>
                    <input id="clearMonthConfirmation" name="confirmation" type="text" required placeholder="{{ $expectedConfirm }}" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 font-mono text-xs font-bold text-slate-900 tracking-wider" oninput="document.getElementById('btnConfirmClearMonth').disabled = this.value.trim().toUpperCase() !== '{{ $expectedConfirm }}'">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button type="button" onclick="closeClearMonthModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button id="btnConfirmClearMonth" type="submit" disabled class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-black text-white hover:bg-rose-600 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">Clear Reconciliation</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Auto Match Shop Collections Modal ─────────────────────────────── --}}
    <div id="autoMatchModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-emerald-100 bg-emerald-50/80 px-6 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <i data-lucide="sparkles" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <h3 class="font-black text-slate-950 text-sm">Auto Match Shop Collections</h3>
                        <p class="text-[11px] font-semibold text-slate-500">Settings-driven matching of shop Paytm &amp; Card collections against destination banks</p>
                    </div>
                </div>
                <button type="button" onclick="closeAutoMatchModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Month Period</label>
                        <input id="auto-match-month" type="month" value="{{ $month }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Destination Bank (Optional)</label>
                        <select id="auto-match-account-id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800">
                            <option value="">All Configured Banks</option>
                            @foreach($companyAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Shop (Optional)</label>
                        <select id="auto-match-shop-id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800">
                            <option value="">All Shops</option>
                            @foreach($shops as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                    <span class="text-[11px] font-semibold text-slate-500">Evaluates expected category amounts against configured bank statement entries</span>
                    <button type="button" id="btn-calc-auto-match" onclick="calculateAutoMatchPreview()" class="rounded-xl bg-slate-950 px-4 py-2 text-xs font-black text-white hover:bg-slate-800">
                        Calculate Preview
                    </button>
                </div>

                <div id="auto-match-preview-results" class="hidden space-y-4 pt-2">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function openClearMonthModal() {
            const modal = document.getElementById('clearMonthModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }
        function closeClearMonthModal() {
            const modal = document.getElementById('clearMonthModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function openAutoMatchModal() {
            const modal = document.getElementById('autoMatchModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                if (window.lucide) { lucide.createIcons(); }
            }
        }
        function closeAutoMatchModal() {
            const modal = document.getElementById('autoMatchModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        let currentAutoMatchPreview = null;

        async function calculateAutoMatchPreview() {
            const accountId = document.getElementById('auto-match-account-id').value;
            const shopId = document.getElementById('auto-match-shop-id').value;
            const monthVal = document.getElementById('auto-match-month').value;
            const btn = document.getElementById('btn-calc-auto-match');
            const container = document.getElementById('auto-match-preview-results');

            if (!monthVal) {
                alert('Please select month period.');
                return;
            }

            const [year, monthNum] = monthVal.split('-');
            const monthStart = `${year}-${monthNum}-01`;
            const lastDay = new Date(parseInt(year, 10), parseInt(monthNum, 10), 0).getDate();
            const monthEnd = `${year}-${monthNum}-${String(lastDay).padStart(2, '0')}`;

            btn.disabled = true;
            btn.textContent = 'Calculating...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            const payload = {
                month_start: monthStart,
                month_end: monthEnd,
                grace_days: 2,
            };
            if (accountId) { payload.company_account_id = parseInt(accountId, 10); }
            if (shopId) { payload.shop_id = parseInt(shopId, 10); }

            try {
                const response = await fetch('{{ route('admin.cashbook.finance.reconciliation.auto-match-shop-collections.preview') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Auto match preview failed.');
                    return;
                }

                currentAutoMatchPreview = data.preview;
                renderAutoMatchPreview(data.preview);
            } catch (err) {
                console.error(err);
                alert('Auto match preview request failed.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Calculate Preview';
            }
        }

        function renderAutoMatchPreview(p) {
            const container = document.getElementById('auto-match-preview-results');
            container.classList.remove('hidden');

            const s = p.summary;
            const formatCurrency = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            let bankGroupsHtml = '';
            for (const [key, group] of Object.entries(p.grouped_by_bank)) {
                const exactCount = (group.exact_matches || []).length;
                const mismatchCount = (group.bank_mapping_mismatches || []).length;
                const unconfCount = (group.bank_not_configured || []).length;
                const diffCount = (group.amount_differences || []).length;
                const noStmtCount = (group.no_statement_data || []).length;
                const outCovCount = (group.outside_coverage || []).length;
                const noAmtCount = (group.no_amount_match || []).length;

                const cov = group.statement_coverage;
                const covText = cov && cov.has_data
                    ? `Statements: ${cov.min_date} &rarr; ${cov.max_date} (${cov.total_statements} rows)`
                    : `<span class="text-amber-700">No statement data</span>`;

                bankGroupsHtml += `
                    <div class="rounded-xl border border-slate-200 bg-white p-3.5 space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-2 gap-1">
                            <div>
                                <span class="font-black text-slate-900 text-xs">${group.bank_name}</span>
                                <span class="ml-2 text-[10px] font-bold text-slate-400">(${covText})</span>
                                ${group.is_cash_warning ? `<span class="ml-1 text-[10px] font-black text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">⚠️ Cash Account</span>` : ''}
                            </div>
                            <div class="flex flex-wrap items-center gap-1 text-[10px] font-bold">
                                ${exactCount > 0 ? `<span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">${exactCount} Exact</span>` : ''}
                                ${diffCount > 0 ? `<span class="rounded bg-amber-100 px-2 py-0.5 text-amber-800">${diffCount} Diff</span>` : ''}
                                ${mismatchCount > 0 ? `<span class="rounded bg-rose-100 px-2 py-0.5 text-rose-800">${mismatchCount} Bank Mismatch</span>` : ''}
                                ${noStmtCount > 0 ? `<span class="rounded bg-slate-100 px-2 py-0.5 text-slate-700">${noStmtCount} No Statement</span>` : ''}
                                ${outCovCount > 0 ? `<span class="rounded bg-sky-100 px-2 py-0.5 text-sky-800">${outCovCount} Outside Coverage</span>` : ''}
                                ${noAmtCount > 0 ? `<span class="rounded bg-slate-100 px-2 py-0.5 text-slate-500">${noAmtCount} No Amount Match</span>` : ''}
                                ${unconfCount > 0 ? `<span class="rounded bg-slate-100 px-2 py-0.5 text-slate-700">${unconfCount} Unconfigured</span>` : ''}
                            </div>
                        </div>
                        ${mismatchCount > 0 ? `
                            <div class="rounded-lg bg-rose-50 border border-rose-100 p-2 text-[11px] text-rose-900 flex items-center justify-between">
                                <span>${mismatchCount} transactions currently point to a different bank than configured.</span>
                                <button type="button" onclick="reassignBankMismatches()" class="rounded-lg bg-rose-700 px-2.5 py-1 text-[10px] font-black text-white hover:bg-rose-800">Reassign to ${group.bank_name}</button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }

            container.innerHTML = `
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div>
                            <h4 class="font-black text-slate-950 text-sm">Settings-Driven Auto Match Preview</h4>
                            <p class="text-xs font-semibold text-slate-500">${p.period.month_start} &rarr; ${p.period.month_end}</p>
                        </div>
                        <span class="text-xs font-bold text-slate-600">Total Collections: <strong>${s.total_collections_count}</strong> (${formatCurrency(s.total_collections_amount)})</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-center">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-2.5">
                            <p class="text-[9px] font-black uppercase text-emerald-800 tracking-wider">Exact Matches</p>
                            <p class="mt-0.5 text-base font-black text-emerald-950">${s.exact_matches_count}</p>
                            <p class="text-[10px] font-bold text-emerald-700">${formatCurrency(s.exact_matches_amount)}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-2.5">
                            <p class="text-[9px] font-black uppercase text-amber-800 tracking-wider">Differences</p>
                            <p class="mt-0.5 text-base font-black text-amber-950">${s.amount_differences_count}</p>
                            <p class="text-[10px] font-bold text-amber-700">${formatCurrency(s.amount_differences_amount)}</p>
                        </div>
                        <div class="rounded-xl border border-purple-200 bg-purple-50 p-2.5">
                            <p class="text-[9px] font-black uppercase text-purple-800 tracking-wider">Ambiguous</p>
                            <p class="mt-0.5 text-base font-black text-purple-950">${s.ambiguous_count}</p>
                            <p class="text-[10px] font-bold text-purple-700">${formatCurrency(s.ambiguous_amount)}</p>
                        </div>
                        <div class="rounded-xl border border-rose-200 bg-rose-50 p-2.5">
                            <p class="text-[9px] font-black uppercase text-rose-800 tracking-wider">Bank Mismatches</p>
                            <p class="mt-0.5 text-base font-black text-rose-950">${s.bank_mapping_mismatches_count}</p>
                            <p class="text-[10px] font-bold text-rose-700">${formatCurrency(s.bank_mapping_mismatches_amount)}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-2.5">
                            <p class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Not Configured</p>
                            <p class="mt-0.5 text-base font-black text-slate-900">${s.bank_not_configured_count}</p>
                            <p class="text-[10px] font-bold text-slate-500">${formatCurrency(s.bank_not_configured_amount)}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-2.5">
                            <p class="text-[9px] font-black uppercase text-slate-400 tracking-wider">No Match</p>
                            <p class="mt-0.5 text-base font-black text-slate-800">${s.no_match_count}</p>
                            <p class="text-[10px] font-bold text-slate-400">${formatCurrency(s.no_match_amount)}</p>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        <p class="font-bold text-slate-700 text-xs">Breakdown by Destination Bank</p>
                        ${bankGroupsHtml}
                    </div>

                    <div class="flex flex-wrap items-center justify-between border-t border-slate-200 pt-3 gap-2">
                        <div class="flex items-center gap-2">
                            ${s.bank_mapping_mismatches_count > 0 ? `
                                <button type="button" onclick="reassignBankMismatches()" class="rounded-xl bg-rose-700 px-3.5 py-2 text-xs font-black text-white hover:bg-rose-800 shadow-sm">
                                    Reassign ${s.bank_mapping_mismatches_count} Mismatches to Configured Banks
                                </button>
                            ` : ''}
                        </div>
                        <button type="button"
                                id="btn-execute-auto-match"
                                onclick="executeAutoMatch()"
                                ${s.exact_matches_count === 0 ? 'disabled' : ''}
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed shadow-sm">
                            Reconcile ${s.exact_matches_count} Exact Matches
                        </button>
                    </div>
                </div>
            `;
        }

        async function reassignBankMismatches() {
            if (!currentAutoMatchPreview || !currentAutoMatchPreview.bank_mapping_mismatches || currentAutoMatchPreview.bank_mapping_mismatches.length === 0) return;

            const txIds = currentAutoMatchPreview.bank_mapping_mismatches.map(m => m.transaction_id);
            if (!confirm(`Reassign ${txIds.length} transactions to their current configured bank settings?`)) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const response = await fetch('{{ route('admin.cashbook.finance.reconciliation.auto-match-shop-collections.reassign') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ transaction_ids: txIds }),
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Reassign failed.');
                    return;
                }

                alert(data.message);
                // Recalculate preview
                calculateAutoMatchPreview();
            } catch (err) {
                console.error(err);
                alert('Reassign request failed.');
            }
        }

        async function executeAutoMatch() {
            if (!currentAutoMatchPreview || currentAutoMatchPreview.summary.exact_matches_count <= 0) return;

            const btn = document.getElementById('btn-execute-auto-match');
            btn.disabled = true;
            btn.textContent = 'Reconciling Matches...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const response = await fetch('{{ route('admin.cashbook.finance.reconciliation.auto-match-shop-collections.execute') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        month_start: currentAutoMatchPreview.period.month_start,
                        month_end: currentAutoMatchPreview.period.month_end,
                        company_account_id: currentAutoMatchPreview.filters.company_account_id,
                    }),
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Auto match execution failed.');
                    btn.disabled = false;
                    btn.textContent = `Reconcile ${currentAutoMatchPreview.summary.exact_matches_count} Exact Matches`;
                    return;
                }

                alert(data.message);
                window.location.reload();
            } catch (err) {
                console.error(err);
                alert('Auto match execution request failed.');
                btn.disabled = false;
                btn.textContent = `Reconcile ${currentAutoMatchPreview.summary.exact_matches_count} Exact Matches`;
            }
        }
    </script>
@endsection
