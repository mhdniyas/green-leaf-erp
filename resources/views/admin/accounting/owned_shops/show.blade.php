<x-layouts.accounting :title="$shop->name.' Accounting'">
    @php
        $hasEntry = $entry instanceof \App\Models\ShopAccountingEntry;
        $entryAction = $hasEntry
            ? route('admin.accounting.owned-shops.entries.update', ['shop' => $shop, 'entry' => $entry])
            : route('admin.accounting.owned-shops.entries.store', $shop);
        $entryIncomeTotal = $hasEntry ? (float) $entry->lines->where('type', 'income')->sum('amount') : 0.0;
        $entryExpenseTotal = $hasEntry ? (float) $entry->lines->where('type', 'expense')->sum('amount') : 0.0;
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
                        Ledger
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
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 lg:grid-cols-4">
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
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approval Status</p>
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
                    <div class="mt-6 space-y-4">
                        <div class="rounded-[1.25rem] border {{ $entry->status === 'recheck_required' ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50' }} p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] {{ $entry->status === 'recheck_required' ? 'text-red-700' : 'text-slate-500' }}">Review Timeline</p>
                            <div class="mt-3 grid gap-3 text-sm font-semibold text-slate-700 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3">
                                    <p><span class="font-black text-slate-950">Submitted by:</span> {{ $entry->submittedBy?->name ?? 'Admin entry' }}</p>
                                </div>
                                <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3">
                                    <p><span class="font-black text-slate-950">Last updated:</span> {{ $entry->updated_at?->format('d M Y h:i A') }}</p>
                                </div>
                                @if ($entry->submitted_at)
                                    <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3">
                                        <p><span class="font-black text-slate-950">Submitted at:</span> {{ $entry->submitted_at->format('d M Y h:i A') }}</p>
                                    </div>
                                @endif
                                @if ($entry->admin_note)
                                    <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3 md:col-span-2 xl:col-span-1">
                                        <p><span class="font-black text-slate-950">Admin note:</span> {{ $entry->admin_note }}</p>
                                    </div>
                                @endif
                                @if ($entry->shop_reply_note)
                                    <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3 md:col-span-2 xl:col-span-2">
                                        <p><span class="font-black text-slate-950">Shop reply:</span> {{ $entry->shop_reply_note }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                            @csrf
                            @method('PATCH')
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Admin Approval</p>
                            <div class="mt-3 rounded-[1rem] border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-black text-slate-950">Request details</p>
                                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">{{ $entry->lines->count() }} item{{ $entry->lines->count() === 1 ? '' : 's' }}</p>
                                </div>
                                <p class="mt-2 text-xs font-semibold text-slate-500">Approve all at once, or review each item separately when some lines need recheck.</p>
                                <div class="mt-3 space-y-2">
                                    @foreach ($entry->lines as $line)
                                        <div class="rounded-[0.95rem] border border-slate-200 bg-slate-50 px-3 py-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                            {{ $line->type }}
                                                        </span>
                                                        <span class="text-sm font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</span>
                                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $line->reviewStatusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($line->reviewStatusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                                            {{ $line->reviewStatusLabel() }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-2 text-sm font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</p>
                                                </div>
                                                <p class="shrink-0 text-sm font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</p>
                                            </div>
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="line-review-open inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-white transition hover:bg-emerald-500"
                                                    data-line-id="{{ $line->id }}"
                                                    data-line-action="approve"
                                                    data-line-title="Approve This Item"
                                                    data-line-label="{{ $line->category?->name ?? 'Category removed' }}"
                                                    data-line-description="{{ $line->description ?: 'No note added' }}"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.7">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="line-review-open inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-red-600 text-white transition hover:bg-red-500"
                                                    data-line-id="{{ $line->id }}"
                                                    data-line-action="recheck"
                                                    data-line-title="Send Item For Recheck"
                                                    data-line-label="{{ $line->category?->name ?? 'Category removed' }}"
                                                    data-line-description="{{ $line->description ?: 'No note added' }}"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.7">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                                                    </svg>
                                                </button>
                                                <span class="text-xs font-semibold text-slate-500">Use the tick to approve this line or X to send only this item for recheck.</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-[0.95rem] border border-emerald-200 bg-emerald-50 px-3 py-3">
                                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Income Total</p>
                                        <p class="mt-1 text-sm font-black text-emerald-900">Rs. {{ number_format($entryIncomeTotal, 2) }}</p>
                                    </div>
                                    <div class="rounded-[0.95rem] border border-amber-200 bg-amber-50 px-3 py-3">
                                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Expense Total</p>
                                        <p class="mt-1 text-sm font-black text-amber-900">Rs. {{ number_format($entryExpenseTotal, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                            <textarea name="admin_note" rows="4" class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none" placeholder="Add a note when approval or recheck needs context.">{{ old('admin_note', $entry->admin_note) }}</textarea>
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                                <button type="button" id="approve-entry-open-modal" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                    Approve All Items
                                </button>
                                <button type="submit" name="decision" value="recheck" class="inline-flex h-11 items-center justify-center rounded-2xl bg-red-600 px-5 text-sm font-black text-white transition hover:bg-red-500">
                                    Send Recheck
                                </button>
                                <button type="button" id="review-details-open-modal" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                    View Request
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="mt-4 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Submitted Items</p>
                                <h3 class="mt-1 text-lg font-black text-slate-950">Exact update from shop owner</h3>
                            </div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">{{ $entry->lines->count() }} manual item{{ $entry->lines->count() === 1 ? '' : 's' }}</p>
                        </div>

                        <div class="mt-4 overflow-x-auto rounded-[1.15rem] border border-slate-200 bg-white">
                            <table class="min-w-full text-left">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Type</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Notes</th>
                                        <th class="px-4 py-3">Review</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm">
                                    @foreach ($entry->lines as $line)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                    {{ $line->type }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</td>
                                            <td class="px-4 py-3 text-sm font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</td>
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
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-slate-50/80">
                                        <td colspan="4" class="px-4 py-3 font-black text-rose-900">Warehouse Delivery Invoice</td>
                                        <td class="px-4 py-3 text-right font-black text-rose-700">Shown in shop ledger totals from bill data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <button type="button" id="ownership-open-modal" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                        Manage Ownership Shares
                    </button>
                    <a href="{{ route('admin.accounting.owned-shops.categories.index', $shop) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                        Open Categories Page
                    </a>
                </div>
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

        @if ($hasEntry)
            <div id="line-review-modal" class="hidden fixed inset-0 z-[85]">
                <div class="line-review-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="w-full max-w-2xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <div>
                                <p id="line-review-modal-kicker" class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Line Review</p>
                                <h2 id="line-review-modal-title" class="mt-2 text-2xl font-black tracking-tight text-slate-950">Review Item</h2>
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
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Selected Item</p>
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
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Approve Request</p>
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
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Submitted By</p>
                                    <p class="mt-2 text-sm font-black text-slate-950">{{ $entry->submittedBy?->name ?? 'Admin entry' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->submitted_at?->format('d M Y h:i A') }}</p>
                                </div>
                                <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Day Summary</p>
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
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Admin Note Preview</p>
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
                                    Confirm Approve All
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
                    lineReviewTitle.textContent = action === 'approve' ? 'Approve This Item' : 'Send Item For Recheck';
                    lineReviewDescription.textContent = description;
                    lineReviewNoteLabel.textContent = action === 'approve' ? 'Optional Note' : 'Recheck Note';
                    lineReviewNoteInput.placeholder = action === 'approve' ? 'Add a short note if needed.' : 'Tell the shop owner what to fix.';
                    lineReviewSubmitButton.textContent = action === 'approve' ? 'Confirm Approve' : 'Confirm Recheck';
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
        @endif
    </div>
</x-layouts.accounting>
