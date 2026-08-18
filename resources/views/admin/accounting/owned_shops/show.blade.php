<x-layouts.accounting :title="$shop->name.' Accounting'">
    @php
        $hasEntry = $entry instanceof \App\Models\ShopAccountingEntry;
        $entryAction = $hasEntry
            ? route('admin.accounting.owned-shops.entries.update', ['shop' => $shop, 'entry' => $entry])
            : route('admin.accounting.owned-shops.entries.store', $shop);
        $entryIncomeTotal = $hasEntry ? (float) $entry->lines->where('type', 'income')->sum('amount') : 0.0;
        $entryExpenseTotal = $hasEntry ? (float) $entry->lines->where('type', 'expense')->sum('amount') : 0.0;

        $pendingReviewCount = $approvalEntriesByTab->get('pending', collect())->count();
        $recheckCount = $approvalEntriesByTab->get('recheck', collect())->count();
        $pendingPaymentRequestCount = $pendingPaymentRequestCount
            ?? $paymentRequests->getCollection()->filter(fn ($paymentRequest) => $paymentRequest->status === 'pending')->count();

        $attentionItems = [];
        if ($pendingReviewCount > 0) {
            $attentionItems[] = 'Ready for review — '.$pendingReviewCount.' cashbook '.str('entry')->plural($pendingReviewCount).' waiting for your check.';
        }
        if ($recheckCount > 0) {
            $attentionItems[] = 'Needs recheck — '.$recheckCount.' '.str('entry')->plural($recheckCount).' must be corrected by the shop.';
        }
        if ($pendingPaymentRequestCount > 0) {
            $attentionItems[] = 'Payment waiting — '.$pendingPaymentRequestCount.' payment '.str('request')->plural($pendingPaymentRequestCount).' need approve/reject.';
        }

        $legacyTab = $tab ?? 'bills';
        $scrollTarget = $defaultSection
            ?? match ($legacyTab) {
                'cashbook' => 'cashbook',
                'bills' => 'bills',
                default => 'approve',
            };
        $activeSection = $scrollTarget;

        $approvalTabs = [
            'pending' => ['label' => 'Ready for review', 'empty' => 'No entries waiting for review.'],
            'recheck' => ['label' => 'Needs recheck', 'empty' => 'No entries waiting for shop correction.'],
            'approved' => ['label' => 'Approved', 'empty' => 'No approved entries in this period.'],
        ];

        $pendingPaymentRequests = $paymentRequests->getCollection()->filter(fn ($paymentRequest) => $paymentRequest->status === 'pending');
        $historicalPaymentRequests = $paymentRequests->getCollection()->filter(fn ($paymentRequest) => $paymentRequest->status !== 'pending');
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-800 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Client accounting</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $shop->name }}</h1>
                        <p class="mt-2 text-sm font-semibold text-slate-300">Client: {{ $shop->client?->name ?? 'Aishwarya Veg' }}</p>
                    </div>

                    <form method="GET" action="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-white/10 bg-white/5 p-2">
                        <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                        <input type="hidden" name="end_date" value="{{ $endDate->format('Y-m-d') }}">
                        @if (! empty($approvalTab))
                            <input type="hidden" name="approval_tab" value="{{ $approvalTab }}">
                        @endif
                        <label class="rounded-xl bg-white/10 px-4 py-2 text-white">
                            <span class="block text-[10px] font-semibold uppercase text-slate-400">Business date</span>
                            <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-semibold text-white focus:outline-none focus:ring-0 [color-scheme:dark]">
                        </label>
                        <a href="{{ route('admin.accounting.owned-shops.index') }}" class="inline-flex h-11 items-center rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-semibold text-white transition hover:bg-white/15">
                            All shops
                        </a>
                    </form>
                </div>
            </div>
        </section>

        @include('admin.accounting.owned_shops.partials.section-tabs', ['activeSection' => $activeSection])

        <section class="rounded-[1.6rem] border {{ count($attentionItems) > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} px-5 py-4 shadow-sm sm:px-6">
            <p class="text-[10px] font-black uppercase tracking-[0.22em] {{ count($attentionItems) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Needs attention</p>
            @if (count($attentionItems) > 0)
                <ul class="mt-3 space-y-2">
                    @foreach ($attentionItems as $attentionItem)
                        <li class="flex items-start gap-2 text-sm font-semibold text-amber-950">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                            <span>{{ $attentionItem }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm font-semibold text-emerald-900">Nothing waiting — review today’s cashbook below.</p>
            @endif
        </section>

        @include('admin.accounting.owned_shops.partials.analytics-cards')

        <section id="approve" class="scroll-mt-28 overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Review queue</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Approve daily cashbook entries</h2>
                <p class="mt-2 text-sm font-medium text-slate-500">Review submitted receipts, approve lines, or send entries back to the shop.</p>
            </div>

            <div id="owned-shop-approval-workflow" class="space-y-4 px-5 py-5 sm:px-6">
                <div class="flex flex-wrap gap-2 rounded-2xl bg-slate-100 p-1">
                    @foreach ($approvalTabs as $approvalKey => $approvalMeta)
                        <button
                            type="button"
                            data-approval-tab-trigger="{{ $approvalKey }}"
                            aria-selected="{{ $approvalTab === $approvalKey ? 'true' : 'false' }}"
                            class="{{ $approvalTab === $approvalKey ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }} inline-flex h-10 items-center rounded-xl px-4 text-sm font-semibold transition"
                        >
                            {{ $approvalMeta['label'] }}
                            @php
                                $tabCount = $approvalEntriesByTab->get($approvalKey, collect())->count();
                            @endphp
                            @if ($tabCount > 0)
                                <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-black text-slate-700">{{ $tabCount }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="space-y-3">
                    @foreach ($approvalTabs as $approvalKey => $approvalMeta)
                        @php
                            $workflowEntries = $approvalEntriesByTab->get($approvalKey, collect());
                            $selectedApprovalEntryIsVisible = $hasEntry && match ($approvalKey) {
                                'approved' => in_array($entry->status, ['approved', 'finalized'], true),
                                'recheck' => $entry->status === 'recheck_required',
                                default => $entry->status === 'submitted',
                            };
                            $visibleWorkflowEntries = $selectedApprovalEntryIsVisible
                                ? $workflowEntries->reject(fn ($workflowEntry) => $workflowEntry->is($entry))->values()
                                : $workflowEntries;
                            $panelHidden = $approvalTab !== $approvalKey;
                        @endphp

                        <div data-approval-tab-panel="{{ $approvalKey }}" @class(['space-y-3', 'hidden' => $panelHidden])>
                            @if ($selectedApprovalEntryIsVisible)
                                @include('admin.accounting.owned_shops.partials.admin-approval')
                            @endif

                            @forelse ($visibleWorkflowEntries as $workflowEntry)
                                    @php
                                        $workflowIncome = round((float) $workflowEntry->lines->where('type', 'income')->sum('amount'), 2);
                                        $workflowExpense = round((float) $workflowEntry->lines->where('type', 'expense')->sum('amount'), 2);
                                        $workflowEntryUrl = route('admin.accounting.owned-shops.show', [
                                            'shop' => $shop,
                                            'approval_tab' => $approvalKey,
                                            'date' => $workflowEntry->business_date->format('Y-m-d'),
                                            'start_date' => $startDate->format('Y-m-d'),
                                            'end_date' => $endDate->format('Y-m-d'),
                                        ]).'#approve';
                                    @endphp
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-200 hover:bg-emerald-50">
                                        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-semibold text-slate-950">{{ $workflowEntry->business_date->format('d M Y') }}</p>
                                                    <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase {{ $workflowEntry->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($workflowEntry->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $workflowEntry->statusLabel() }}</span>
                                                </div>
                                                <p class="mt-2 text-sm font-medium text-slate-600">
                                                    Income Rs. {{ number_format($workflowIncome, 2) }} · Expense Rs. {{ number_format($workflowExpense, 2) }}
                                                </p>
                                                @if ($workflowEntry->admin_note)
                                                    <p class="mt-2 text-sm font-medium text-slate-500">{{ $workflowEntry->admin_note }}</p>
                                                @endif
                                            </div>
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center xl:justify-end">
                                                <p class="text-sm font-semibold text-slate-950">Rs. {{ number_format($workflowIncome - $workflowExpense, 2) }}</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <a href="{{ $workflowEntryUrl }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.12em] text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                                                        Open
                                                    </a>
                                                    @if ($workflowEntry->status === 'submitted')
                                                        <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $workflowEntry]) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="decision" value="approve">
                                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-emerald-500">
                                                                Approve
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    @unless ($selectedApprovalEntryIsVisible)
                                        <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm font-semibold text-slate-500">
                                            {{ $approvalMeta['empty'] }}
                                        </div>
                                    @endunless
                                @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="cashbook" class="scroll-mt-28 overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Day cashbook</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">{{ $selectedDate->format('d M Y') }}</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($hasEntry)
                            <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $entry->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($entry->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : ($entry->statusTone() === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-100 text-slate-700')) }}">
                                {{ $entry->statusLabel() }}
                            </span>
                        @else
                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">No entry</span>
                        @endif
                        <button type="button" id="daily-entry-open-modal" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                            {{ $hasEntry ? 'Update daily entry' : 'Add daily entry' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-5 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-3 rounded-[1.15rem] border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Petty</p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">{{ $loanBalance < 0 ? 'Overused balance' : 'Available balance' }} · Rs. {{ number_format($loanBalance, 2) }}</p>
                    </div>
                    <a href="{{ route('admin.accounting.loans', ['shop' => $shop]) }}" class="inline-flex h-10 items-center rounded-xl bg-emerald-600 px-4 text-sm font-black text-white transition hover:bg-emerald-500">
                        Open petty
                    </a>
                </div>

                @if ($hasEntry)
                    <div class="overflow-x-auto rounded-[1.15rem] border border-slate-200">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Notes</th>
                                    <th class="px-4 py-3">Review</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-right">Update</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach ($entry->lines as $line)
                                    @php
                                        $lineUpdateFormId = 'entry-line-update-'.$line->id;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <form id="{{ $lineUpdateFormId }}" method="POST" action="{{ route('admin.accounting.owned-shops.entries.lines.update', ['shop' => $shop, 'entry' => $entry, 'line' => $line]) }}">
                                                @csrf
                                                @method('PATCH')
                                            </form>
                                            <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                {{ $line->type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="shop_accounting_category_id" form="{{ $lineUpdateFormId }}" class="h-10 min-w-[13rem] rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-900 focus:border-cyan-400 focus:outline-none">
                                                @foreach ($availableCategories as $category)
                                                    <option value="{{ $category->id }}" @selected((int) $line->shop_accounting_category_id === (int) $category->id)>
                                                        {{ strtoupper($category->type) }} · {{ $category->name }}{{ $category->shop_id ? ' (Shop)' : ' (Global)' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text" name="description" form="{{ $lineUpdateFormId }}" value="{{ $line->description }}" placeholder="No note added" class="h-10 min-w-[14rem] rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col gap-2">
                                                <span class="inline-flex w-fit rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $line->reviewStatusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($line->reviewStatusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                                    {{ $line->reviewStatusLabel() }}
                                                </span>
                                                @if ($line->review_note)
                                                    <p class="text-xs font-semibold text-slate-500">{{ $line->review_note }}</p>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <input type="number" step="0.01" min="0.01" name="amount" form="{{ $lineUpdateFormId }}" value="{{ number_format((float) $line->amount, 2, '.', '') }}" class="h-10 w-32 rounded-xl border border-slate-200 bg-white px-3 text-right text-xs font-black text-slate-950 focus:border-cyan-400 focus:outline-none">
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="submit" form="{{ $lineUpdateFormId }}" class="inline-flex h-10 items-center rounded-xl bg-cyan-600 px-3 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-cyan-500">
                                                Update
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <details class="rounded-[1.15rem] border border-rose-200 bg-rose-50">
                        <summary class="cursor-pointer list-none px-4 py-4 text-sm font-black text-rose-950 [&::-webkit-details-marker]:hidden">
                            Danger zone · Clear cashbook only
                        </summary>
                        <div class="border-t border-rose-200 px-4 py-4">
                            <p class="text-sm font-semibold text-rose-800">Removes this date's manual cashbook lines so the shop incharge can submit again. Bills, invoices, payment requests, and loan movements stay unchanged.</p>
                            <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.clear', ['shop' => $shop, 'entry' => $entry]) }}" class="mt-4 flex flex-col gap-2 sm:max-w-md" onsubmit="return confirm('Clear only this cashbook entry? Invoices, bills, payment requests, and loan cash movements will be skipped.');">
                                @csrf
                                @method('DELETE')
                                <input type="text" name="confirmation" required pattern="CLEAR CASHBOOK" placeholder="Type CLEAR CASHBOOK" class="h-11 rounded-2xl border border-rose-200 bg-white px-4 text-sm font-black text-rose-950 placeholder:text-rose-300 focus:border-rose-400 focus:outline-none">
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-rose-700 px-5 text-sm font-black text-white transition hover:bg-rose-600">
                                    Clear cashbook only
                                </button>
                            </form>
                        </div>
                    </details>
                @endif

                <div class="flex justify-end border-t border-slate-100 pt-4">
                    <a href="{{ route('admin.accounting.owned-shops.categories.index', $shop) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                        Open categories
                    </a>
                </div>
            </div>
        </section>

        <section id="bills" class="scroll-mt-28 space-y-5">
            <article class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Delivery bills</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Invoice payments</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Bill</th>
                                <th class="px-4 py-3 text-right">Due</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse ($billingInvoices as $billingInvoice)
                                <tr>
                                    <td class="px-4 py-3 text-xs font-black text-slate-950 sm:text-sm">{{ $billingInvoice->invoice_number }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-slate-500 sm:text-sm">{{ $billingInvoice->business_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right text-xs font-black text-slate-950 sm:text-sm">Rs. {{ number_format((float) $billingInvoice->final_total, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-xs font-black text-rose-700 sm:text-sm">Rs. {{ number_format((float) $billingInvoice->balance_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if ((float) $billingInvoice->balance_amount <= 0)
                                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-black text-emerald-700">Settled</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-xs font-black text-rose-700">Due</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ((float) $billingInvoice->balance_amount > 0)
                                            <a href="{{ route('admin.finance-v2.payments.create', ['date' => $billingInvoice->business_date?->toDateString() ?? $selectedDate->toDateString(), 'shop_id' => $billingInvoice->shop_id, 'requested_amount' => round((float) $billingInvoice->balance_amount, 2)]) }}"
                                               class="inline-flex h-8 items-center rounded-xl bg-cyan-600 px-3 text-xs font-black text-white transition hover:bg-cyan-500">
                                                Finance payment
                                            </a>
                                        @else
                                            <span class="text-xs font-bold text-slate-400">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No delivery bills found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($billingInvoices->hasPages())
                    <div class="border-t border-slate-100 px-5 py-4">{{ $billingInvoices->withQueryString()->links() }}</div>
                @endif
            </article>

            <article class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Payment requests</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Pending shop payment requests</h2>
                </div>

                <div class="space-y-3 px-5 py-5 sm:px-6">
                    @forelse ($pendingPaymentRequests as $paymentRequest)
                        <div class="rounded-[1.15rem] border border-amber-200 bg-amber-50 p-4">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-950">{{ $paymentRequest->invoice?->invoice_number }}</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-600">Requested Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                                        <p class="mt-2 inline-flex rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">{{ $paymentRequest->applicationLabel() }}</p>
                                        @if ($paymentRequest->shop_note)
                                            <p class="mt-2 text-sm font-semibold text-slate-700">{{ $paymentRequest->shop_note }}</p>
                                        @endif
                                    </div>
                                    <span class="rounded-full border border-amber-200 bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">{{ $paymentRequest->statusLabel() }}</span>
                                </div>

                                <div class="rounded-[1.1rem] border border-white/80 bg-white/80 px-4 py-3">
                                    <p class="text-xs font-bold text-amber-800">Review, approve, reject and cheque clearance are handled from Finance V2 Payments.</p>
                                    <div class="mt-3 flex justify-end">
                                        <a href="{{ route('admin.finance-v2.payments.show', ['paymentRequest' => $paymentRequest, 'date' => $selectedDate->toDateString()]) }}" class="inline-flex h-10 items-center rounded-2xl bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-slate-800">
                                            Open in Finance V2
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[1.15rem] border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                            No pending payment requests.
                        </div>
                    @endforelse

                    @if ($historicalPaymentRequests->isNotEmpty())
                        <details class="rounded-[1.15rem] border border-slate-200 bg-slate-50">
                            <summary class="cursor-pointer list-none px-4 py-4 text-sm font-semibold text-slate-700 [&::-webkit-details-marker]:hidden">
                                Show request history ({{ $historicalPaymentRequests->count() }})
                            </summary>
                            <div class="space-y-3 border-t border-slate-200 p-4">
                                @foreach ($historicalPaymentRequests as $paymentRequest)
                                    <div class="rounded-[1rem] border border-slate-200 bg-white p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-black text-slate-950">{{ $paymentRequest->invoice?->invoice_number }}</p>
                                                <p class="mt-1 text-sm font-semibold text-slate-600">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                                                @if ($paymentRequest->admin_note)
                                                    <p class="mt-2 text-sm font-semibold text-slate-700">Admin note: {{ $paymentRequest->admin_note }}</p>
                                                @endif
                                            </div>
                                            <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $paymentRequest->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($paymentRequest->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                                {{ $paymentRequest->statusLabel() }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>

                @if ($paymentRequests->hasPages())
                    <div class="border-t border-slate-100 px-5 py-4">{{ $paymentRequests->withQueryString()->links() }}</div>
                @endif
            </article>
        </section>

        <div id="daily-entry-modal" class="hidden fixed inset-0 z-[70]">
            <div class="daily-entry-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-5xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily entry</p>
                            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $hasEntry ? 'Update daily entry' : 'Add daily entry' }}</h2>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Only category, amount, and description are required here.</p>
                        </div>
                        <button type="button" class="daily-entry-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ $entryAction }}" class="space-y-4 px-6 py-6">
                        @csrf
                        @if ($hasEntry)
                            @method('PATCH')
                        @endif
                        <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">
                        <input type="hidden" name="status" value="{{ old('status', in_array($entry?->status, ['approved', 'finalized'], true) ? $entry->status : 'submitted') }}">
                        <input type="hidden" name="opening_cash" value="{{ old('opening_cash', $entry?->opening_cash ?? $suggestedOpeningBalance) }}">
                        <input type="hidden" name="closing_cash" value="{{ old('closing_cash', $entry?->closing_cash) }}">

                        <div class="space-y-3">
                            @for ($index = 0; $index < max(4, $hasEntry ? $entry->lines->count() : 0); $index++)
                                @php
                                    $line = $hasEntry ? $entry->lines[$index] ?? null : null;
                                @endphp
                                <div class="grid gap-3 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1.2fr_0.8fr_1.2fr]">
                                    <select name="lines[{{ $index }}][shop_accounting_category_id]" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                        <option value="">Select category</option>
                                        @foreach ($availableCategories as $category)
                                            <option value="{{ $category->id }}" {{ (string) old("lines.$index.shop_accounting_category_id", $line?->shop_accounting_category_id) === (string) $category->id ? 'selected' : '' }}>
                                                {{ strtoupper($category->type) }} · {{ $category->name }}{{ $category->shop_id ? ' (Shop)' : ' (Global)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0.01" name="lines[{{ $index }}][amount]" value="{{ old("lines.$index.amount", $line?->amount) }}" placeholder="Amount" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                    <input type="text" name="lines[{{ $index }}][description]" value="{{ old("lines.$index.description", $line?->description) }}" placeholder="Description" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                    <input type="hidden" name="lines[{{ $index }}][is_loan_entry]" value="{{ old("lines.$index.is_loan_entry", (int) ((bool) $line?->is_loan_entry)) }}">
                                </div>
                            @endfor
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                                {{ $hasEntry ? 'Submit daily update' : 'Submit daily entry' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($hasEntry)
            <div id="line-review-modal" class="hidden fixed inset-0 z-[85]">
                <div class="line-review-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="w-full max-w-2xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <div>
                                <p id="line-review-modal-kicker" class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Line review</p>
                                <h2 id="line-review-modal-title" class="mt-2 text-2xl font-black tracking-tight text-slate-950">Review item</h2>
                                <p id="line-review-modal-description" class="mt-2 text-sm font-semibold text-slate-600"></p>
                            </div>
                            <button type="button" class="line-review-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="space-y-4 px-6 py-6">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="decision" value="review_lines">
                            <input type="hidden" name="line_reviews[0][decision]" id="line-review-decision" value="">
                            <input type="hidden" name="line_reviews[0][review_note]" id="line-review-note-hidden" value="">
                            <input type="hidden" name="admin_note" id="line-review-admin-note-hidden" value="">
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Selected item</p>
                                <p id="line-review-modal-item" class="mt-2 text-sm font-black text-slate-950"></p>
                            </div>
                            <label class="block">
                                <span id="line-review-note-label" class="mb-2 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Note</span>
                                <textarea id="line-review-note-input" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none" placeholder="Add a note if needed."></textarea>
                            </label>
                            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                                <button type="button" class="line-review-modal-close inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" id="line-review-submit-button" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                                    Confirm
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="approve-entry-modal" class="hidden fixed inset-0 z-[80]">
                <div class="approve-entry-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="w-full max-w-3xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Approve request</p>
                                <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Confirm all submitted items</h2>
                                <p class="mt-2 text-sm font-semibold text-slate-600">Review the exact update before accounting approves this day.</p>
                            </div>
                            <button type="button" class="approve-entry-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="space-y-4 px-6 py-6">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Submitted by</p>
                                    <p class="mt-2 text-sm font-black text-slate-950">{{ $entry->submittedBy?->name ?? 'Admin entry' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->submitted_at?->format('d M Y h:i A') }}</p>
                                </div>
                                <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Day summary</p>
                                    <p class="mt-2 text-sm font-black text-slate-950">Income Rs. {{ number_format($entryIncomeTotal, 2) }}</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">Expense Rs. {{ number_format($entryExpenseTotal, 2) }}</p>
                                </div>
                            </div>

                            <div class="max-h-[22rem] overflow-y-auto rounded-[1.15rem] border border-slate-200">
                                <table class="min-w-full text-left">
                                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                        <tr>
                                            <th class="px-4 py-3">Type</th>
                                            <th class="px-4 py-3">Category</th>
                                            <th class="px-4 py-3">Notes</th>
                                            <th class="px-4 py-3 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-sm">
                                        @foreach ($entry->lines as $line)
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                        {{ $line->type }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</td>
                                                <td class="px-4 py-3 text-sm font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</td>
                                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Admin note preview</p>
                                <p id="approve-entry-note-preview" class="mt-2 text-sm font-semibold text-slate-700">No note added.</p>
                            </div>

                            <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="decision" value="approve">
                                <input type="hidden" name="admin_note" id="approve-entry-note-hidden" value="{{ old('admin_note', $entry->admin_note) }}">
                                <button type="button" class="approve-entry-modal-close inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                    Confirm approve all
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <script>
            (() => {
                const initialSection = @json($scrollTarget);
                const sectionNav = document.getElementById('owned-shop-section-nav');
                const sectionLinks = sectionNav?.querySelectorAll('[data-section-nav]') ?? [];

                const setActiveSection = (section) => {
                    sectionLinks.forEach((link) => {
                        const isActive = link.dataset.sectionNav === section;
                        link.classList.toggle('bg-white', isActive);
                        link.classList.toggle('text-slate-950', isActive);
                        link.classList.toggle('shadow-sm', isActive);
                        link.classList.toggle('text-slate-500', !isActive);
                        link.classList.toggle('hover:text-slate-900', !isActive);
                    });
                };

                const scrollToSection = (section, replaceHash = true) => {
                    const target = document.getElementById(section);
                    if (!target) {
                        return;
                    }

                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    setActiveSection(section);

                    if (replaceHash) {
                        const url = new URL(window.location.href);
                        url.hash = section;
                        window.history.replaceState({}, '', url);
                    }
                };

                sectionLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        scrollToSection(link.dataset.sectionNav ?? 'approve');
                    });
                });

                const hashSection = window.location.hash.replace('#', '');
                const resolvedSection = ['approve', 'cashbook', 'bills'].includes(hashSection)
                    ? hashSection
                    : initialSection;
                window.requestAnimationFrame(() => scrollToSection(resolvedSection, false));

                const workflow = document.getElementById('owned-shop-approval-workflow');
                if (workflow) {
                    const buttons = workflow.querySelectorAll('[data-approval-tab-trigger]');
                    const panels = workflow.querySelectorAll('[data-approval-tab-panel]');

                    const setApprovalTab = (tab) => {
                        buttons.forEach((button) => {
                            const isActive = button.dataset.approvalTabTrigger === tab;
                            button.classList.toggle('bg-white', isActive);
                            button.classList.toggle('text-slate-950', isActive);
                            button.classList.toggle('shadow-sm', isActive);
                            button.classList.toggle('text-slate-500', !isActive);
                            button.classList.toggle('hover:text-slate-900', !isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        });

                        panels.forEach((panel) => {
                            panel.classList.toggle('hidden', panel.dataset.approvalTabPanel !== tab);
                        });

                        const url = new URL(window.location.href);
                        url.searchParams.set('approval_tab', tab);
                        window.history.replaceState({}, '', url);
                    };

                    buttons.forEach((button) => {
                        button.addEventListener('click', () => setApprovalTab(button.dataset.approvalTabTrigger ?? 'pending'));
                    });
                }

                const money = (amount) => 'Rs. ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const bindModal = (openId, modalId, closeSelector, forceOpen = false) => {
                    const openButton = document.getElementById(openId);
                    const modal = document.getElementById(modalId);

                    if (!modal) {
                        return;
                    }

                    const openModal = () => {
                        modal.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    };

                    const closeModal = () => {
                        modal.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    };

                    if (forceOpen) {
                        openModal();
                    }

                    openButton?.addEventListener('click', openModal);
                    modal.querySelectorAll(closeSelector).forEach((button) => {
                        button.addEventListener('click', closeModal);
                    });
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal.querySelector(`.${modalId}-overlay`) || event.target.classList.contains(`${modalId}-overlay`)) {
                            closeModal();
                        }
                    });
                };

                bindModal('daily-entry-open-modal', 'daily-entry-modal', '.daily-entry-modal-close', {{ $errors->has('lines') || $errors->has('business_date') ? 'true' : 'false' }});
                bindModal('approve-entry-open-modal', 'approve-entry-modal', '.approve-entry-modal-close');
                bindModal('review-details-open-modal', 'approve-entry-modal', '.approve-entry-modal-close');
                bindModal('line-review-open-missing', 'line-review-modal', '.line-review-modal-close', {{ old('decision') === 'review_lines' ? 'true' : 'false' }});

                const approvalNoteInput = document.querySelector('textarea[name="admin_note"]');
                const approveEntryOpenModal = document.getElementById('approve-entry-open-modal');
                const approveEntryNoteHidden = document.getElementById('approve-entry-note-hidden');
                const approveEntryNotePreview = document.getElementById('approve-entry-note-preview');
                const lineReviewModal = document.getElementById('line-review-modal');
                const lineReviewButtons = document.querySelectorAll('.line-review-open');
                const lineReviewTitle = document.getElementById('line-review-modal-title');
                const lineReviewDescription = document.getElementById('line-review-modal-description');
                const lineReviewItem = document.getElementById('line-review-modal-item');
                const lineReviewDecision = document.getElementById('line-review-decision');
                const lineReviewNoteHidden = document.getElementById('line-review-note-hidden');
                const lineReviewAdminNoteHidden = document.getElementById('line-review-admin-note-hidden');
                const lineReviewNoteInput = document.getElementById('line-review-note-input');
                const lineReviewNoteLabel = document.getElementById('line-review-note-label');
                const lineReviewSubmitButton = document.getElementById('line-review-submit-button');

                approveEntryOpenModal?.addEventListener('click', () => {
                    const note = approvalNoteInput instanceof HTMLTextAreaElement ? approvalNoteInput.value.trim() : '';
                    if (approveEntryNoteHidden instanceof HTMLInputElement) {
                        approveEntryNoteHidden.value = note;
                    }

                    if (approveEntryNotePreview) {
                        approveEntryNotePreview.textContent = note !== '' ? note : 'No note added.';
                    }
                });

                const openLineReviewModal = (button) => {
                    if (!lineReviewModal || !lineReviewDecision || !lineReviewItem || !lineReviewTitle || !lineReviewDescription || !lineReviewNoteInput || !lineReviewSubmitButton || !lineReviewAdminNoteHidden || !lineReviewNoteHidden || !lineReviewNoteLabel) {
                        return;
                    }

                    const lineId = button.dataset.lineId ?? '';
                    const action = button.dataset.lineAction ?? 'approve';
                    const label = button.dataset.lineLabel ?? 'Item';
                    const description = button.dataset.lineDescription ?? '';

                    lineReviewDecision.name = `line_reviews[${lineId}][decision]`;
                    lineReviewDecision.value = action;
                    lineReviewNoteHidden.name = `line_reviews[${lineId}][review_note]`;
                    lineReviewItem.textContent = label;
                    lineReviewTitle.textContent = action === 'approve' ? 'Approve this item' : 'Send item for recheck';
                    lineReviewDescription.textContent = description;
                    lineReviewNoteLabel.textContent = action === 'approve' ? 'Optional note' : 'Recheck note';
                    lineReviewNoteInput.placeholder = action === 'approve' ? 'Add a short note if needed.' : 'Tell the shop incharge what to fix.';
                    lineReviewSubmitButton.textContent = action === 'approve' ? 'Confirm approve' : 'Confirm recheck';
                    lineReviewSubmitButton.className = `inline-flex h-11 items-center justify-center rounded-2xl px-5 text-sm font-black text-white transition ${action === 'approve' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-red-600 hover:bg-red-500'}`;
                    lineReviewNoteInput.value = '';
                    lineReviewModal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                };

                lineReviewButtons.forEach((button) => {
                    button.addEventListener('click', () => openLineReviewModal(button));
                });

                lineReviewModal?.querySelector('form')?.addEventListener('submit', () => {
                    if (!(lineReviewNoteInput instanceof HTMLTextAreaElement) || !(lineReviewAdminNoteHidden instanceof HTMLInputElement) || !(lineReviewNoteHidden instanceof HTMLInputElement) || !(lineReviewDecision instanceof HTMLInputElement)) {
                        return;
                    }

                    const note = lineReviewNoteInput.value.trim();
                    lineReviewNoteHidden.value = note;
                    lineReviewAdminNoteHidden.value = lineReviewDecision.value === 'recheck' ? note : '';
                });
            })();
        </script>
    </div>
</x-layouts.accounting>
