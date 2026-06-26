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
                    <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                    <a href="{{ route('admin.accounting.owned-shops.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:bg-slate-100">
                        All Shops
                    </a>
                </form>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Ownership</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Ownership shares</h2>
                    </div>
                    <span class="rounded-full border {{ abs($ownershipTotal - 100) < 0.01 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em]">
                        {{ number_format($ownershipTotal, 2) }}%
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.accounting.owned-shops.ownerships.store', $shop) }}" class="mt-6 space-y-3">
                    @csrf
                    @for($index = 0; $index < max(3, $shop->ownerships->count()); $index++)
                        @php
                            $ownership = $shop->ownerships[$index] ?? null;
                        @endphp
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
                    <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                        Save Ownership Shares
                    </button>
                </form>
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

                <form method="POST" action="{{ $entryAction }}" class="mt-6 space-y-4">
                    @csrf
                    @if($hasEntry)
                        @method('PATCH')
                    @endif
                    <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">

                    <div class="grid gap-4 md:grid-cols-4">
                        <label class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Cash</span>
                            <input type="number" step="0.01" min="0" name="opening_cash" value="{{ old('opening_cash', $entry?->opening_cash) }}" class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0">
                        </label>
                        <label class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</span>
                            <input type="number" step="0.01" min="0" name="closing_cash" value="{{ old('closing_cash', $entry?->closing_cash) }}" class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0">
                        </label>
                        <label class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Status</span>
                            <select name="status" class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0">
                                <option value="draft" {{ old('status', $entry?->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="submitted" {{ old('status', $entry?->status) === 'submitted' ? 'selected' : '' }}>Submitted</option>
                                <option value="recheck_required" {{ old('status', $entry?->status) === 'recheck_required' ? 'selected' : '' }}>Recheck Required</option>
                                <option value="approved" {{ old('status', $entry?->status) === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="finalized" {{ old('status', $entry?->status) === 'finalized' ? 'selected' : '' }}>Finalized</option>
                            </select>
                        </label>
                        <label class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Notes</span>
                            <input type="text" name="notes" value="{{ old('notes', $entry?->notes) }}" class="mt-2 w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-0">
                        </label>
                    </div>

                    <div class="space-y-3">
                        @for($index = 0; $index < max(4, $hasEntry ? $entry->lines->count() : 0); $index++)
                            @php
                                $line = $hasEntry ? $entry->lines[$index] ?? null : null;
                            @endphp
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

                    <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                        {{ $hasEntry ? 'Update Daily Entry' : 'Save Daily Entry' }}
                    </button>
                </form>

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

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Categories</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Hybrid income and expense categories</h2>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.accounting.owned-shops.categories.store', $shop) }}" class="mt-6 grid gap-3 md:grid-cols-4">
                    @csrf
                    <select name="scope" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                        <option value="global">Global</option>
                        <option value="shop">Shop specific</option>
                    </select>
                    <select name="type" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                    <input type="text" name="name" placeholder="Category name" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                    <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">
                        Create Category
                    </button>
                </form>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Global Categories</p>
                        <div class="mt-3 space-y-2">
                            @forelse($globalCategories as $category)
                                <div class="rounded-2xl bg-white px-3 py-3 text-sm font-semibold text-slate-700">{{ strtoupper($category->type) }} • {{ $category->name }}</div>
                            @empty
                                <p class="text-sm font-semibold text-slate-500">No global categories yet.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Categories</p>
                        <div class="mt-3 space-y-2">
                            @forelse($shopCategories as $category)
                                <div class="rounded-2xl bg-white px-3 py-3 text-sm font-semibold text-slate-700">{{ strtoupper($category->type) }} • {{ $category->name }}</div>
                            @empty
                                <p class="text-sm font-semibold text-slate-500">No shop-specific categories yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Settlement Invoices</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Generate date-range settlement invoices</h2>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.accounting.owned-shops.invoices.store', $shop) }}" class="mt-6 grid gap-3 md:grid-cols-4">
                    @csrf
                    <input type="date" name="period_start" value="{{ $selectedDate->copy()->startOfMonth()->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                    <input type="date" name="period_end" value="{{ $selectedDate->copy()->endOfMonth()->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                    <input type="text" name="notes" placeholder="Invoice note" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                    <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white transition hover:bg-emerald-500">
                        Generate Invoice
                    </button>
                </form>

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
            </article>
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
    </div>
</x-layouts.accounting>
