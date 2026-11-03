<x-layouts.app title="Expense">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-2 py-2 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="overflow-hidden rounded-xl bg-slate-950 text-white shadow-sm lg:rounded-[2rem]">
            <div class="bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_58%,_#134e4a_100%)] px-3 py-3 sm:px-5 lg:px-5 lg:py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-[0.16em] text-teal-200">Purchaser</p>
                        <h1 class="mt-0.5 truncate text-lg font-black tracking-tight sm:text-2xl">Expense</h1>
                        <p class="mt-1 hidden max-w-2xl text-sm font-semibold text-slate-200 sm:block">Daily operating costs post to company expense automatically.</p>
                    </div>
                    <form method="GET" action="{{ route('purchaser.procurement-expenses.index') }}" class="flex shrink-0 items-center gap-1 rounded-xl bg-white/10 p-1">
                        <input type="hidden" name="view" value="{{ $view }}">
                        <a href="{{ route('purchaser.procurement-expenses.index', ['date' => $previousDate, 'view' => $view]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/15" title="Previous day" aria-label="Previous day">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </a>
                        <label class="w-32 rounded-lg bg-white/10 px-2 py-1 sm:w-44">
                            <span class="block text-[8px] font-black uppercase tracking-[0.12em] text-teal-100">Date</span>
                            <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="mt-0.5 w-full border-0 bg-transparent p-0 text-[11px] font-black text-white outline-none sm:text-sm">
                        </label>
                        <a href="{{ route('purchaser.procurement-expenses.index', ['date' => $nextDate, 'view' => $view]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/15" title="Next day" aria-label="Next day">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </form>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-3 gap-1.5 sm:gap-3">
            <article class="rounded-xl border border-teal-200 bg-teal-50 p-2 sm:p-4">
                <p class="text-[8px] font-black uppercase tracking-[0.12em] text-teal-700 sm:text-[9px]">Day</p>
                <p class="mt-1 truncate text-sm font-black text-teal-950 sm:text-2xl">Rs. {{ number_format($selectedDateTotal, 2) }}</p>
                <p class="mt-0.5 text-[9px] font-bold text-teal-800">{{ $selectedDateCount }} rows</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-2 sm:p-4">
                <p class="text-[8px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-[9px]">Month</p>
                <p class="mt-1 truncate text-sm font-black text-slate-950 sm:text-2xl">Rs. {{ number_format($monthlyTotal, 2) }}</p>
                <p class="mt-0.5 truncate text-[9px] font-bold text-slate-500">{{ $date->format('M Y') }}</p>
            </article>
            <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-2 sm:p-4">
                <p class="text-[8px] font-black uppercase tracking-[0.12em] text-emerald-700 sm:text-[9px]">Journal</p>
                <p class="mt-1 truncate text-sm font-black text-emerald-950 sm:text-base">Auto</p>
                <p class="mt-0.5 text-[9px] font-bold text-emerald-800">Daily entry</p>
            </article>
        </section>

        <section class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
            <a href="{{ route('purchaser.procurement-expenses.index', ['date' => $date->toDateString(), 'view' => 'entries']) }}" class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] {{ $view === 'entries' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                Entries
            </a>
            <a href="{{ route('purchaser.procurement-expenses.index', ['date' => $date->toDateString(), 'view' => 'datewise']) }}" class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] {{ $view === 'datewise' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                Date-wise
            </a>
        </section>

        <section class="grid gap-2 lg:grid-cols-[minmax(18rem,0.75fr)_minmax(0,1.25fr)] lg:gap-4">
            <form method="POST" action="{{ $editingExpense ? route('purchaser.procurement-expenses.update', $editingExpense) : route('purchaser.procurement-expenses.store') }}" class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-5">
                @csrf
                @if($editingExpense)
                    @method('PATCH')
                @endif

                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $editingExpense ? 'Edit' : 'New' }}</p>
                        <h2 class="mt-0.5 truncate text-base font-black text-slate-950">{{ $editingExpense ? 'Edit Expense' : 'Add Expense' }}</h2>
                    </div>
                    @if($editingExpense)
                        <a href="{{ route('purchaser.procurement-expenses.index', ['date' => $date->toDateString()]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100" title="Cancel edit" aria-label="Cancel edit">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </a>
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <label>
                        <span class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Date</span>
                        <input type="date" name="expense_date" value="{{ old('expense_date', $editingExpense?->expense_date?->toDateString() ?? $date->toDateString()) }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs font-black text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100" required>
                    </label>
                    <label>
                        @php
                            $selectedCategory = old('category', $editingExpense?->category ?? array_key_first($categories));
                            $selectedCategoryLabel = $categories[$selectedCategory] ?? reset($categories);
                        @endphp
                        <span class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Category</span>
                        <div class="procurement-category-select relative mt-1">
                            <button type="button" class="procurement-category-trigger flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2 text-left text-xs font-black text-slate-900 transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100" aria-haspopup="listbox" aria-expanded="false">
                                <span class="procurement-category-label truncate">{{ $selectedCategoryLabel }}</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m6 9 6 6 6-6"/>
                                </svg>
                            </button>
                            <input type="hidden" name="category" value="{{ $selectedCategory }}" class="procurement-category-input" required>
                            <div class="procurement-category-options absolute left-0 right-0 top-[calc(100%+0.25rem)] z-40 hidden max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl" role="listbox" aria-label="Category">
                                @foreach($categories as $value => $label)
                                    <button type="button" data-value="{{ $value }}" data-label="{{ $label }}" class="procurement-category-option flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs {{ $selectedCategory === $value ? 'bg-teal-50 font-black text-teal-800' : 'font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-950' }}" role="option" aria-selected="{{ $selectedCategory === $value ? 'true' : 'false' }}">
                                        <span class="truncate">{{ $label }}</span>
                                        <svg class="procurement-category-check h-3.5 w-3.5 shrink-0 text-teal-600 {{ $selectedCategory === $value ? '' : 'hidden' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20 6 9 17l-5-5"/>
                                        </svg>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </label>

                    <div id="location-field-container" class="col-span-2 hidden">
                        <label>
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Location / Route</span>
                                <span id="location-mode-badge" class="rounded px-1.5 py-0.5 text-[8px] font-black uppercase">Optional</span>
                            </div>
                            <div class="procurement-location-select relative mt-1">
                                <button type="button"
                                        id="procurement-location-trigger"
                                        class="procurement-location-trigger flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2 text-left text-xs font-black text-slate-900 transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100"
                                        aria-haspopup="listbox"
                                        aria-expanded="false">
                                    <span id="procurement-location-label" class="procurement-location-label truncate">Select location / route</span>
                                    <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                </button>
                                <input type="hidden"
                                       name="procurement_expense_sub_category_id"
                                       id="procurement-location-select"
                                       class="procurement-location-input"
                                       value="{{ old('procurement_expense_sub_category_id', $editingExpense?->procurement_expense_sub_category_id ?? '') }}">
                                <div id="procurement-location-options"
                                     class="procurement-location-options absolute left-0 right-0 top-[calc(100%+0.25rem)] z-40 hidden max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                                     role="listbox"
                                     aria-label="Location / Route">
                                </div>
                            </div>
                        </label>
                    </div>

                    <label class="col-span-2">
                        <span class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Amount</span>
                        <input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount', $editingExpense?->amount) }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-sm font-black text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100" placeholder="0.00" required>
                    </label>
                    <label class="col-span-2">
                        <span class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Note</span>
                        <textarea name="note" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100" placeholder="Optional details">{{ old('note', $editingExpense?->note) }}</textarea>
                    </label>
                </div>

                <button type="submit" class="mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-teal-600 px-3 text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-teal-500">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
                    {{ $editingExpense ? 'Update Expense' : 'Add Expense' }}
                </button>
            </form>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:rounded-[2rem]">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2.5 lg:px-4 lg:py-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-black text-slate-950">Expense History</h2>
                        <p class="mt-0.5 truncate text-[10px] font-semibold text-slate-500">{{ $date->format('M Y') }} operating expenses</p>
                    </div>
                    <a href="{{ route('purchaser.daily', ['date' => $date->toDateString()]) }}" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100" title="Daily" aria-label="Daily">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    </a>
                </div>

                @if ($view === 'datewise')
                    <div class="divide-y divide-slate-100">
                        @forelse($dateWiseTotals as $row)
                            <div class="px-3 py-2.5 lg:px-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</p>
                                        <p class="mt-0.5 text-[10px] font-semibold text-slate-500">{{ $row['count'] }} {{ \Illuminate\Support\Str::plural('entry', (int) $row['count']) }}</p>
                                    </div>
                                    <p class="text-sm font-black text-teal-700">Rs. {{ number_format((float) $row['total'], 2) }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-3 py-8 text-center text-xs font-bold text-slate-400">No date-wise totals for this month.</div>
                        @endforelse
                    </div>
                @else
                    <div class="divide-y divide-slate-100 lg:hidden">
                        @forelse($expenses as $expense)
                        <div class="px-3 py-2">
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                                        <p class="shrink-0 text-[11px] font-black text-slate-950">{{ $expense->expense_date?->format('d M') }}</p>
                                        <span class="min-w-0 truncate rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.1em] text-slate-600">{{ $expense->categoryDefinition?->name ?? $expense->categoryLabel() }}</span>
                                        @if($expense->sub_category || $expense->subCategory)
                                            <span class="min-w-0 truncate rounded-full bg-blue-50 px-2 py-0.5 text-[8px] font-black tracking-wide text-blue-700 border border-blue-200/60">
                                                📍 {{ $expense->sub_category ?: $expense->subCategory?->name }}
                                            </span>
                                        @endif
                                        <span class="shrink-0 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[8px] font-black uppercase text-emerald-700">Added</span>
                                    </div>
                                    <p class="mt-1 truncate text-[10px] font-semibold text-slate-600">{{ $expense->note ?: 'No note' }}</p>
                                    @if($expense->companyAccountingEntry)
                                        <p class="mt-0.5 truncate text-[9px] font-bold text-teal-700">{{ $expense->companyAccountingEntry->reference }}</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-black text-slate-950">Rs. {{ number_format((float) $expense->amount, 2) }}</p>
                                    <div class="mt-1 flex justify-end gap-1">
                                        <a href="{{ route('purchaser.procurement-expenses.index', ['date' => $expense->expense_date?->toDateString(), 'edit' => $expense->id]) }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700" title="Edit" aria-label="Edit">
                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('purchaser.procurement-expenses.destroy', $expense) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-rose-200 bg-rose-50 text-rose-700" title="Delete" aria-label="Delete">
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                            <div class="px-3 py-8 text-center text-xs font-bold text-slate-400">No operating expenses for this month.</div>
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Location / Route</th>
                                <th class="px-4 py-3">Note</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($expenses as $expense)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $expense->expense_date?->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $expense->categoryDefinition?->name ?? $expense->categoryLabel() }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        @if($expense->sub_category || $expense->subCategory)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-700 border border-blue-200/60">
                                                📍 {{ $expense->sub_category ?: $expense->subCategory?->name }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="max-w-xs px-4 py-3">
                                        <p class="truncate font-semibold text-slate-600">{{ $expense->note ?: 'No note' }}</p>
                                        @if($expense->companyAccountingEntry)
                                            <p class="mt-1 text-[10px] font-black text-teal-700">{{ $expense->companyAccountingEntry->reference }}{{ $expense->companyAccountingEntry->journalEntry ? ' / JRN-'.$expense->companyAccountingEntry->journalEntry->id : '' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $expense->amount, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-emerald-700">Added</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('purchaser.procurement-expenses.index', ['date' => $expense->expense_date?->toDateString(), 'edit' => $expense->id]) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50" title="Edit" aria-label="Edit">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            </a>
                                            <form method="POST" action="{{ route('purchaser.procurement-expenses.destroy', $expense) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" title="Delete" aria-label="Delete">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-sm font-bold text-slate-400">No operating expenses for this month.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                @endif
            </section>
        </section>
    </div>

    <script>
        document.addEventListener('click', (event) => {
            // 1. Category Trigger Click
            const catTrigger = event.target.closest('.procurement-category-trigger');
            if (catTrigger) {
                const container = catTrigger.closest('.procurement-category-select');
                const options = container?.querySelector('.procurement-category-options');

                // Close any open location dropdown
                document.querySelectorAll('.procurement-location-options').forEach(panel => {
                    panel.classList.add('hidden');
                    panel.closest('.procurement-location-select')?.querySelector('.procurement-location-trigger')?.setAttribute('aria-expanded', 'false');
                    panel.closest('.procurement-location-select')?.querySelector('.procurement-location-trigger svg')?.classList.remove('rotate-180');
                });

                document.querySelectorAll('.procurement-category-options').forEach((panel) => {
                    if (panel !== options) {
                        panel.classList.add('hidden');
                        panel.closest('.procurement-category-select')?.querySelector('.procurement-category-trigger')?.setAttribute('aria-expanded', 'false');
                        panel.closest('.procurement-category-select')?.querySelector('.procurement-category-trigger svg')?.classList.remove('rotate-180');
                    }
                });

                options?.classList.toggle('hidden');
                const isOpen = options ? ! options.classList.contains('hidden') : false;
                catTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                catTrigger.querySelector('svg')?.classList.toggle('rotate-180', isOpen);
                return;
            }

            // 2. Category Option Click
            const catOption = event.target.closest('.procurement-category-option');
            if (catOption) {
                const container = catOption.closest('.procurement-category-select');
                const input = container?.querySelector('.procurement-category-input');
                const label = container?.querySelector('.procurement-category-label');
                const options = container?.querySelector('.procurement-category-options');
                const selectedValue = catOption.dataset.value ?? '';
                const selectedLabel = catOption.dataset.label ?? catOption.textContent.trim();

                if (input) input.value = selectedValue;
                if (label) label.textContent = selectedLabel;

                container?.querySelectorAll('.procurement-category-option').forEach((item) => {
                    const isSelected = item === catOption;
                    item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    item.classList.toggle('bg-teal-50', isSelected);
                    item.classList.toggle('font-black', isSelected);
                    item.classList.toggle('text-teal-800', isSelected);
                    item.classList.toggle('font-bold', ! isSelected);
                    item.classList.toggle('text-slate-700', ! isSelected);
                    item.classList.toggle('hover:bg-slate-50', ! isSelected);
                    item.classList.toggle('hover:text-slate-950', ! isSelected);
                    item.querySelector('.procurement-category-check')?.classList.toggle('hidden', ! isSelected);
                });

                options?.classList.add('hidden');
                const selectedTrigger = container?.querySelector('.procurement-category-trigger');
                selectedTrigger?.setAttribute('aria-expanded', 'false');
                selectedTrigger?.querySelector('svg')?.classList.remove('rotate-180');

                updateLocationSelector(selectedValue, null);
                return;
            }

            // 3. Location Trigger Click
            const locTrigger = event.target.closest('.procurement-location-trigger');
            if (locTrigger) {
                const container = locTrigger.closest('.procurement-location-select');
                const options = container?.querySelector('.procurement-location-options');

                // Close any open category dropdown
                document.querySelectorAll('.procurement-category-options').forEach(panel => {
                    panel.classList.add('hidden');
                    panel.closest('.procurement-category-select')?.querySelector('.procurement-category-trigger')?.setAttribute('aria-expanded', 'false');
                    panel.closest('.procurement-category-select')?.querySelector('.procurement-category-trigger svg')?.classList.remove('rotate-180');
                });

                options?.classList.toggle('hidden');
                const isOpen = options ? ! options.classList.contains('hidden') : false;
                locTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                locTrigger.querySelector('svg')?.classList.toggle('rotate-180', isOpen);
                return;
            }

            // 4. Location Option Click
            const locOption = event.target.closest('.procurement-location-option');
            if (locOption) {
                const container = locOption.closest('.procurement-location-select');
                const input = container?.querySelector('.procurement-location-input');
                const label = container?.querySelector('.procurement-location-label');
                const options = container?.querySelector('.procurement-location-options');
                const selectedValue = locOption.dataset.value ?? '';
                const selectedLabel = locOption.dataset.label ?? locOption.textContent.trim();

                if (input) input.value = selectedValue;
                if (label) label.textContent = selectedLabel;

                // Remove red error highlight if present
                const trigger = container?.querySelector('.procurement-location-trigger');
                trigger?.classList.remove('border-rose-400', 'ring-2', 'ring-rose-100');

                container?.querySelectorAll('.procurement-location-option').forEach((item) => {
                    const isSelected = item === locOption;
                    item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    item.classList.toggle('bg-teal-50', isSelected);
                    item.classList.toggle('font-black', isSelected);
                    item.classList.toggle('text-teal-800', isSelected);
                    item.classList.toggle('font-bold', ! isSelected);
                    item.classList.toggle('text-slate-700', ! isSelected);
                    item.classList.toggle('hover:bg-slate-50', ! isSelected);
                    item.classList.toggle('hover:text-slate-950', ! isSelected);
                    item.querySelector('.procurement-location-check')?.classList.toggle('hidden', ! isSelected);
                });

                options?.classList.add('hidden');
                trigger?.setAttribute('aria-expanded', 'false');
                trigger?.querySelector('svg')?.classList.remove('rotate-180');
                return;
            }

            // 5. Click Outside: Close any open dropdowns
            if (! event.target.closest('.procurement-category-select')) {
                document.querySelectorAll('.procurement-category-options').forEach((panel) => {
                    panel.classList.add('hidden');
                    panel.closest('.procurement-category-select')?.querySelector('.procurement-category-trigger')?.setAttribute('aria-expanded', 'false');
                    panel.closest('.procurement-category-select')?.querySelector('.procurement-category-trigger svg')?.classList.remove('rotate-180');
                });
            }
            if (! event.target.closest('.procurement-location-select')) {
                document.querySelectorAll('.procurement-location-options').forEach((panel) => {
                    panel.classList.add('hidden');
                    panel.closest('.procurement-location-select')?.querySelector('.procurement-location-trigger')?.setAttribute('aria-expanded', 'false');
                    panel.closest('.procurement-location-select')?.querySelector('.procurement-location-trigger svg')?.classList.remove('rotate-180');
                });
            }
        });

        const categoryConfigs = @json($categoryConfigs ?? []);
        const initialCategory = '{{ $selectedCategory }}';
        const initialLocationId = '{{ old('procurement_expense_sub_category_id', $editingExpense?->procurement_expense_sub_category_id ?? '') }}';

        function updateLocationSelector(categoryKey, preselectedLocationId = null) {
            const container = document.getElementById('location-field-container');
            const input = document.getElementById('procurement-location-select');
            const label = document.getElementById('procurement-location-label');
            const optionsContainer = document.getElementById('procurement-location-options');
            const badge = document.getElementById('location-mode-badge');
            const trigger = document.getElementById('procurement-location-trigger');
            if (!container || !input || !label || !optionsContainer || !badge) return;

            // Clear any error states
            trigger?.classList.remove('border-rose-400', 'ring-2', 'ring-rose-100');

            const config = categoryConfigs.find(c => c.key === categoryKey);
            if (!config || config.mode === 'none') {
                container.classList.add('hidden');
                optionsContainer.innerHTML = '';
                input.value = '';
                input.removeAttribute('required');
                label.textContent = 'No locations available';
                return;
            }

            container.classList.remove('hidden');

            const isRequired = config.mode === 'required';
            if (isRequired) {
                badge.textContent = 'Required';
                badge.className = 'rounded px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wide bg-purple-50 text-purple-700 border border-purple-200/60';
                input.setAttribute('required', 'required');
            } else {
                badge.textContent = 'Optional';
                badge.className = 'rounded px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wide bg-blue-50 text-blue-700 border border-blue-200/60';
                input.removeAttribute('required');
            }

            const defaultPlaceholder = isRequired ? 'Select required location / route' : 'Select location (optional)';
            const locations = config.locations || [];

            let selectedLoc = null;
            if (preselectedLocationId) {
                selectedLoc = locations.find(l => String(l.id) === String(preselectedLocationId));
            }

            const activeValue = selectedLoc ? String(selectedLoc.id) : '';
            input.value = activeValue;
            label.textContent = selectedLoc ? (selectedLoc.name + (selectedLoc.code ? ` (${selectedLoc.code})` : '')) : defaultPlaceholder;

            let optionsHtml = '';
            if (!isRequired) {
                const isPlaceholderSelected = !activeValue;
                optionsHtml += `
                    <button type="button" data-value="" data-label="${defaultPlaceholder}" class="procurement-location-option flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs ${isPlaceholderSelected ? 'bg-teal-50 font-black text-teal-800' : 'font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-900'}" role="option" aria-selected="${isPlaceholderSelected ? 'true' : 'false'}">
                        <span class="truncate italic">${defaultPlaceholder}</span>
                        <svg class="procurement-location-check h-3.5 w-3.5 shrink-0 text-teal-600 ${isPlaceholderSelected ? '' : 'hidden'}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                    </button>
                `;
            }

            locations.forEach(loc => {
                const locLabel = loc.name + (loc.code ? ` (${loc.code})` : '');
                const isSelected = String(loc.id) === activeValue;
                optionsHtml += `
                    <button type="button" data-value="${loc.id}" data-label="${locLabel}" class="procurement-location-option flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs ${isSelected ? 'bg-teal-50 font-black text-teal-800' : 'font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-950'}" role="option" aria-selected="${isSelected ? 'true' : 'false'}">
                        <span class="truncate">${locLabel}</span>
                        <svg class="procurement-location-check h-3.5 w-3.5 shrink-0 text-teal-600 ${isSelected ? '' : 'hidden'}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                    </button>
                `;
            });

            optionsContainer.innerHTML = optionsHtml;
        }

        // Validate on form submission if location is required
        document.querySelector('form[action*="procurement-expenses"]')?.addEventListener('submit', function(e) {
            const container = document.getElementById('location-field-container');
            const input = document.getElementById('procurement-location-select');
            const badge = document.getElementById('location-mode-badge');
            const trigger = document.getElementById('procurement-location-trigger');
            if (container && !container.classList.contains('hidden') && badge?.textContent.trim() === 'Required') {
                if (!input || !input.value) {
                    e.preventDefault();
                    trigger?.classList.add('border-rose-400', 'ring-2', 'ring-rose-100');
                    trigger?.focus();
                }
            }
        });

        // Initialize on page load
        updateLocationSelector(initialCategory, initialLocationId);

        // Initialize on page load
        updateLocationSelector(initialCategory, initialLocationId);
    </script>
</x-layouts.app>
