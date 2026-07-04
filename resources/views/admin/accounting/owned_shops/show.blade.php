<x-layouts.accounting :title="$shop->name.' Accounting'">
    @php
        $hasEntry = $entry instanceof \App\Models\ShopAccountingEntry;
        $entryAction = $hasEntry
            ? route('admin.accounting.owned-shops.entries.update', ['shop' => $shop, 'entry' => $entry])
            : route('admin.accounting.owned-shops.entries.store', $shop);
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Owned Shop Accounting</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $shop->name }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">{{ $shop->code }} • {{ ucfirst($shop->accounting_mode) }} accounting workflow.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="flex flex-wrap items-center gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                    <a href="{{ route('admin.accounting.owned-shops.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:bg-slate-100">
                        All Shops
                    </a>
                </form>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Reserve Amount</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) $shop->reserve_amount, 2) }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-500">Admin provides this reserve cash for the shop to use as the base cash amount.</p>
                </div>

                <form method="POST" action="{{ route('admin.accounting.owned-shops.reserve-amount.update', $shop) }}" class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="block flex-1">
                            <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Update Reserve Amount</span>
                            <input type="number" step="0.01" min="0" name="reserve_amount" value="{{ old('reserve_amount', number_format((float) $shop->reserve_amount, 2, '.', '')) }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                        </label>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                            Save Reserve
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop, 'tab' => 'bills', 'date' => $selectedDate->format('Y-m-d'), 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="{{ $tab === 'bills' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800' }} inline-flex h-11 items-center justify-center rounded-2xl px-5 text-sm font-black transition">
                        Bills
                    </a>
                    <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop, 'tab' => 'cashbook', 'date' => $selectedDate->format('Y-m-d'), 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="{{ $tab === 'cashbook' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800' }} inline-flex h-11 items-center justify-center rounded-2xl px-5 text-sm font-black transition">
                        Cashbook
                    </a>
                </div>

                <form method="GET" action="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="grid gap-3 sm:grid-cols-4">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                    <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                    <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                    <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-cyan-600 px-4 text-sm font-black text-white transition hover:bg-cyan-500">
                        Update Analytics
                    </button>
                </form>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Billed</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) $analytics['cards']['total_billed'], 2) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Collected</p>
                    <p class="mt-2 text-2xl font-black text-emerald-700">Rs. {{ number_format((float) $analytics['cards']['total_paid'], 2) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                    <p class="mt-2 text-2xl font-black text-rose-700">Rs. {{ number_format((float) $analytics['cards']['total_balance'], 2) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Income</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) $analytics['cards']['income'], 2) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Expense</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) $analytics['cards']['expense'], 2) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Cash Flow</p>
                    <p class="mt-2 text-2xl font-black text-cyan-700">Rs. {{ number_format((float) $analytics['cards']['cash_flow'], 2) }}</p>
                </div>
            </div>
        </section>

        @if ($tab === 'bills')
            <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Delivery Bills</p>
                            <h2 class="mt-2 text-xl font-black text-slate-950">Daily bill table</h2>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        @forelse($billingInvoices as $billingInvoice)
                            <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <p class="text-sm font-black text-slate-950">{{ $billingInvoice->invoice_number }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $billingInvoice->business_date->format('d M Y') }}</p>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[360px]">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Bill</p>
                                            <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) $billingInvoice->final_total, 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid</p>
                                            <p class="mt-1 text-sm font-black text-emerald-700">Rs. {{ number_format((float) $billingInvoice->paid_amount, 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Due</p>
                                            <p class="mt-1 text-sm font-black text-rose-700">Rs. {{ number_format((float) $billingInvoice->balance_amount, 2) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[1.25rem] border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                                No delivery bills found in this timeframe.
                            </div>
                        @endforelse
                    </div>

                    @if ($billingInvoices->hasPages())
                        <div class="mt-5">{{ $billingInvoices->withQueryString()->links() }}</div>
                    @endif
                </article>

                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Payment Requests</p>
                            <h2 class="mt-2 text-xl font-black text-slate-950">Approve requested payments</h2>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        @forelse($paymentRequests as $paymentRequest)
                            <div class="rounded-[1.25rem] border {{ $paymentRequest->status === 'pending' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex flex-col gap-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-950">{{ $paymentRequest->invoice?->invoice_number }}</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-600">Requested Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                                            @if ($paymentRequest->shop_note)
                                                <p class="mt-2 text-sm font-semibold text-slate-700">{{ $paymentRequest->shop_note }}</p>
                                            @endif
                                        </div>
                                        <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $paymentRequest->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($paymentRequest->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                            {{ $paymentRequest->statusLabel() }}
                                        </span>
                                    </div>

                                    @if ($paymentRequest->status === 'pending')
                                        <div class="rounded-[1.1rem] border border-sky-200 bg-sky-50 px-4 py-3">
                                            <p class="text-sm font-black text-sky-900">Review moved to Purchasing Dashboard.</p>
                                            <p class="mt-1 text-sm font-semibold text-sky-800">Payment approvals are no longer handled from admin accounting.</p>
                                            <a href="{{ route('purchasing.dashboard') }}" class="mt-3 inline-flex h-10 items-center rounded-2xl bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:bg-slate-800">
                                                Open Purchasing Dashboard
                                            </a>
                                        </div>
                                    @elseif ($paymentRequest->admin_note)
                                        <p class="text-sm font-semibold text-slate-700">Admin note: {{ $paymentRequest->admin_note }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[1.25rem] border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                                No payment requests found.
                            </div>
                        @endforelse
                    </div>

                    @if ($paymentRequests->hasPages())
                        <div class="mt-5">{{ $paymentRequests->withQueryString()->links() }}</div>
                    @endif
                </article>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Daily Summary</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Cash flow by day</h2>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Billed</th>
                                <th class="px-4 py-3 text-right">Collected</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                                <th class="px-4 py-3 text-right">Income</th>
                                <th class="px-4 py-3 text-right">Expense</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($analytics['daily_summaries'] as $summary)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($summary['date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $summary['billed'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $summary['paid'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-rose-700">Rs. {{ number_format((float) $summary['balance'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $summary['income'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $summary['expense'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No daily summary rows found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($tab === 'cashbook')
        <section class="grid gap-6 xl:grid-cols-[0.82fr_1.18fr]">
            <article class="space-y-6">
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Ownership</p>
                            <h2 class="mt-2 text-xl font-black text-slate-950">Ownership shares</h2>
                        </div>
                        <span class="rounded-full border {{ abs($ownershipTotal - 100) < 0.01 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em]">
                            {{ number_format($ownershipTotal, 2) }}%
                        </span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse($shop->ownerships as $ownership)
                            <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-black text-slate-950">{{ $ownership->owner_name }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $ownership->role_label ?: 'Role not set' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-black text-slate-950">{{ number_format((float) $ownership->ownership_percent, 2) }}%</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $ownership->user?->name ?? 'No linked user' }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[1.25rem] border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                                No ownership shares configured yet.
                            </div>
                        @endforelse
                    </div>

                    <button type="button" id="ownership-open-modal" class="mt-5 inline-flex h-11 items-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                        Manage Ownership Shares
                    </button>
                </div>

                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Expense Categories</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Owned shop expense categories</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Categories are global now. They are shown here for quick selection in the daily-entry popup.</p>

                    <div class="mt-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Global Expense Categories</p>
                        <div class="mt-3 space-y-2">
                            @forelse($globalCategories->where('type', 'expense')->values() as $category)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700">
                                    {{ $category->name }}
                                </div>
                            @empty
                                <p class="text-sm font-semibold text-slate-500">No global expense categories yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-4">
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Income</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($incomeTotal, 2) }}</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Expense</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($expenseTotal, 2) }}</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($netAmount, 2) }}</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Entry Status</p>
                        <div class="mt-3">
                            @if ($hasEntry)
                                <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $entry->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($entry->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : ($entry->statusTone() === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-100 text-slate-700')) }}">
                                    {{ $entry->statusLabel() }}
                                </span>
                            @else
                                <p class="text-2xl font-black text-slate-950">None</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <button type="button" id="daily-entry-open-modal" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                        {{ $hasEntry ? 'Update Daily Entry' : 'Add Daily Entry' }}
                    </button>
                </div>

                @if ($hasEntry)
                    <div class="mt-6 grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                        <div class="rounded-[1.25rem] border {{ $entry->status === 'recheck_required' ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50' }} p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] {{ $entry->status === 'recheck_required' ? 'text-red-700' : 'text-slate-500' }}">Review Notes</p>
                            <div class="mt-3 space-y-3 text-sm font-semibold text-slate-700">
                                <p><span class="font-black text-slate-950">Submitted by:</span> {{ $entry->submittedBy?->name ?? 'Admin entry' }}</p>
                                @if ($entry->submitted_at)
                                    <p><span class="font-black text-slate-950">Submitted at:</span> {{ $entry->submitted_at->format('d M Y h:i A') }}</p>
                                @endif
                                @if ($entry->admin_note)
                                    <p><span class="font-black text-slate-950">Admin note:</span> {{ $entry->admin_note }}</p>
                                @endif
                                @if ($entry->shop_reply_note)
                                    <p><span class="font-black text-slate-950">Shop reply:</span> {{ $entry->shop_reply_note }}</p>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                            @csrf
                            @method('PATCH')
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Admin Approval</p>
                            <textarea name="admin_note" rows="4" class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none" placeholder="Add a note when approval or recheck needs context.">{{ old('admin_note', $entry->admin_note) }}</textarea>
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                                <button type="submit" name="decision" value="approve" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                    Approve Day
                                </button>
                                <button type="submit" name="decision" value="recheck" class="inline-flex h-11 items-center justify-center rounded-2xl bg-red-600 px-5 text-sm font-black text-white transition hover:bg-red-500">
                                    Send Recheck
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </article>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Settlement Invoices</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Generated settlement invoices</h2>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse($invoices as $invoice)
                    <a href="{{ route('admin.accounting.owned-shops.invoices.show', ['shop' => $shop, 'invoice' => $invoice]) }}" class="flex items-center justify-between gap-3 rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <div>
                            <p class="text-sm font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->period_start->format('d M Y') }} to {{ $invoice->period_end->format('d M Y') }}</p>
                        </div>
                        <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $invoice->net_amount, 2) }}</p>
                    </a>
                @empty
                    <div class="rounded-[1.25rem] border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                        No settlement invoices have been generated yet.
                    </div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.accounting.owned-shops.invoices.store', $shop) }}" class="mt-6 grid gap-3 border-t border-slate-100 pt-6 md:grid-cols-4">
                @csrf
                <input type="date" name="period_start" value="{{ $selectedDate->copy()->startOfMonth()->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                <input type="date" name="period_end" value="{{ $selectedDate->copy()->endOfMonth()->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                <input type="text" name="notes" placeholder="Invoice note" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white transition hover:bg-emerald-500">
                    Generate Invoice
                </button>
            </form>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Recent Daily Entries</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Latest accounting activity</h2>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                <table class="min-w-full text-left">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Income</th>
                            <th class="px-4 py-3 text-right">Expense</th>
                            <th class="px-4 py-3 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($recentEntries as $recentEntry)
                            @php
                                $recentIncome = (float) $recentEntry->lines->where('type', 'income')->sum('amount');
                                $recentExpense = (float) $recentEntry->lines->where('type', 'expense')->sum('amount');
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-black text-slate-950">{{ $recentEntry->business_date->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $recentEntry->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($recentEntry->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : ($recentEntry->statusTone() === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-100 text-slate-700')) }}">
                                        {{ $recentEntry->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($recentIncome, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($recentExpense, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($recentIncome - $recentExpense, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No daily accounting entries recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="ownership-modal" class="hidden fixed inset-0 z-[70]">
            <div class="ownership-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-5xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Ownership</p>
                            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Manage ownership shares</h2>
                        </div>
                        <button type="button" class="ownership-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.accounting.owned-shops.ownerships.store', $shop) }}" class="space-y-3 px-6 py-6">
                        @csrf
                        @for($index = 0; $index < max(3, $shop->ownerships->count()); $index++)
                            @php $ownership = $shop->ownerships[$index] ?? null; @endphp
                            <div class="grid gap-3 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4 md:grid-cols-4">
                                <input type="text" name="ownerships[{{ $index }}][owner_name]" value="{{ old("ownerships.$index.owner_name", $ownership?->owner_name) }}" placeholder="Owner / Partner name" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                <input type="number" step="0.01" min="0" max="100" name="ownerships[{{ $index }}][ownership_percent]" value="{{ old("ownerships.$index.ownership_percent", $ownership?->ownership_percent) }}" placeholder="Share %" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                <input type="text" name="ownerships[{{ $index }}][role_label]" value="{{ old("ownerships.$index.role_label", $ownership?->role_label) }}" placeholder="Role label" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                <select name="ownerships[{{ $index }}][user_id]" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                    <option value="">No linked user</option>
                                    @foreach($shop->users as $user)
                                        <option value="{{ $user->id }}" {{ (string) old("ownerships.$index.user_id", $ownership?->user_id) === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endfor
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                                Save Ownership Shares
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="daily-entry-modal" class="hidden fixed inset-0 z-[70]">
            <div class="daily-entry-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-5xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily Entry</p>
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
                        @if($hasEntry)
                            @method('PATCH')
                        @endif
                        <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">
                        <input type="hidden" name="status" value="{{ old('status', $entry?->status ?? 'draft') }}">

                        <div class="space-y-3">
                            @for($index = 0; $index < max(4, $hasEntry ? $entry->lines->count() : 0); $index++)
                                @php $line = $hasEntry ? $entry->lines[$index] ?? null : null; @endphp
                                <div class="grid gap-3 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1.2fr_0.8fr_1.2fr]">
                                    <select name="lines[{{ $index }}][shop_accounting_category_id]" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                        <option value="">Select category</option>
                                        @foreach($availableCategories as $category)
                                            <option value="{{ $category->id }}" {{ (string) old("lines.$index.shop_accounting_category_id", $line?->shop_accounting_category_id) === (string) $category->id ? 'selected' : '' }}>
                                                {{ strtoupper($category->type) }} • {{ $category->name }}{{ $category->shop_id ? ' (Shop)' : ' (Global)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0" name="lines[{{ $index }}][amount]" value="{{ old("lines.$index.amount", $line?->amount) }}" placeholder="Amount" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                    <input type="text" name="lines[{{ $index }}][description]" value="{{ old("lines.$index.description", $line?->description) }}" placeholder="Description" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                </div>
                            @endfor
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                                {{ $hasEntry ? 'Update Daily Entry' : 'Save Daily Entry' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            (() => {
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

                bindModal('ownership-open-modal', 'ownership-modal', '.ownership-modal-close', {{ $errors->has('ownerships') ? 'true' : 'false' }});
                bindModal('daily-entry-open-modal', 'daily-entry-modal', '.daily-entry-modal-close', {{ $errors->has('lines') || $errors->has('business_date') ? 'true' : 'false' }});
            })();
        </script>
        @endif
    </div>
</x-layouts.accounting>
