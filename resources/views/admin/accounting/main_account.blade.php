<x-layouts.accounting title="Main Account">
    @php
        $previousDate = $date->copy()->subDay()->toDateString();
        $nextDate = $date->copy()->addDay()->toDateString();
        $todayDate = today()->toDateString();
        $daily = $report['daily'];
        $monthly = $report['monthly'];
        $dailyRows = $report['daily_rows'];
        $categoryRows = $report['category_rows'];
        $entries = $report['entries'];
        $incomeCategories = $categories->where('type', 'income')->values();
        $expenseCategories = $categories->where('type', 'expense')->values();
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700">Company Ledger</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Main Account</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Company income and expense entries post directly to journal using mapped ledger categories.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.main-account.index') }}" class="flex flex-wrap items-center gap-2 rounded-[1.2rem] border border-slate-200 bg-slate-50 p-2">
                    <a href="{{ route('admin.accounting.main-account.index', ['date' => $previousDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Previous day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </a>
                    <label class="min-w-[11rem] rounded-[1rem] border border-slate-200 bg-white px-3 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black text-slate-900 focus:outline-none focus:ring-0">
                    </label>
                    @if($date->toDateString() !== $todayDate)
                        <a href="{{ route('admin.accounting.main-account.index', ['date' => $todayDate]) }}" class="inline-flex h-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white px-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-950 transition hover:bg-slate-100">Today</a>
                    @endif
                    <a href="{{ route('admin.accounting.main-account.index', ['date' => $nextDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Next day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </form>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[1rem] border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Daily Income</p>
                <p class="mt-2 text-2xl font-black text-emerald-950">Rs. {{ number_format($daily['income_total'], 2) }}</p>
                <p class="mt-1 text-xs font-bold text-emerald-800">{{ $date->format('d M Y') }}</p>
            </article>
            <article class="rounded-[1rem] border border-rose-200 bg-rose-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Daily Expense</p>
                <p class="mt-2 text-2xl font-black text-rose-950">Rs. {{ number_format($daily['expense_total'], 2) }}</p>
                <p class="mt-1 text-xs font-bold text-rose-800">{{ number_format($daily['entry_count']) }} entry(s)</p>
            </article>
            <article class="rounded-[1rem] border border-sky-200 bg-sky-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-sky-700">Monthly Net</p>
                <p class="mt-2 text-2xl font-black {{ $monthly['net_total'] >= 0 ? 'text-sky-950' : 'text-rose-800' }}">Rs. {{ number_format($monthly['net_total'], 2) }}</p>
                <p class="mt-1 text-xs font-bold text-sky-800">{{ $report['month_start']->format('F Y') }}</p>
            </article>
            <article class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Monthly Entries</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($monthly['entry_count']) }}</p>
                <p class="mt-1 text-xs font-bold text-slate-500">Final entries only in totals</p>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)]">
            <form method="POST" action="{{ route('admin.accounting.main-account.entries.store') }}" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm" data-main-account-entry-form>
                @csrf
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Final Posting</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Add income or expense</h2>
                    </div>
                    <span class="rounded-full bg-slate-950 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-white">Journal</span>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Type</span>
                        <input type="hidden" name="type" value="income" required data-entry-type-input>
                        <div class="relative mt-2" data-tailwind-dropdown>
                            <button type="button" class="flex h-11 w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 text-left text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" data-tailwind-dropdown-button>
                                <span class="truncate" data-tailwind-dropdown-label>Income</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div class="absolute left-0 right-0 top-full z-30 mt-2 hidden rounded-lg border border-slate-200 bg-white p-1 shadow-xl ring-1 ring-slate-950/5" data-tailwind-dropdown-menu>
                                <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md bg-slate-950 px-3 py-2.5 text-left text-sm font-black text-white transition hover:bg-slate-800" data-tailwind-dropdown-option data-value="income" data-label="Income">
                                    <span>Income</span>
                                    <span class="h-2 w-2 rounded-full bg-emerald-400" data-selected-dot></span>
                                </button>
                                <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-black text-slate-700 transition hover:bg-rose-50 hover:text-rose-800" data-tailwind-dropdown-option data-value="expense" data-label="Expense">
                                    <span>Expense</span>
                                    <span class="hidden h-2 w-2 rounded-full bg-rose-500" data-selected-dot></span>
                                </button>
                            </div>
                        </div>
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category</span>
                        <input type="hidden" name="company_accounting_category_id" required data-entry-category-input>
                        <div class="relative mt-2" data-entry-category-dropdown>
                            <button type="button" class="flex h-11 w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 text-left text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-100 disabled:text-slate-400" data-entry-category-button>
                                <span class="truncate" data-entry-category-label>Select category</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div class="absolute left-0 right-0 top-full z-30 mt-2 hidden max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl ring-1 ring-slate-950/5" data-entry-category-menu>
                                @foreach($incomeCategories as $category)
                                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-black text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-800" data-entry-category-option data-type="income" data-value="{{ $category->id }}" data-label="{{ $category->name }}">
                                        <span class="truncate">{{ $category->name }}</span>
                                        <span class="hidden h-2 w-2 rounded-full bg-emerald-500" data-selected-dot></span>
                                    </button>
                                @endforeach
                                @foreach($expenseCategories as $category)
                                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-black text-slate-700 transition hover:bg-rose-50 hover:text-rose-800" data-entry-category-option data-type="expense" data-value="{{ $category->id }}" data-label="{{ $category->name }}">
                                        <span class="truncate">{{ $category->name }}</span>
                                        <span class="hidden h-2 w-2 rounded-full bg-rose-500" data-selected-dot></span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <span class="mt-2 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800" data-entry-category-empty>No active categories for this type. Add one from the category panel.</span>
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Date</span>
                        <input type="date" name="business_date" value="{{ old('business_date', $date->toDateString()) }}" class="mt-2 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" required>
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                        <input type="number" name="amount" min="0.01" step="0.01" class="mt-2 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" required>
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Mode</span>
                        <input type="hidden" name="payment_mode" value="cash" required data-payment-mode-input>
                        <div class="relative mt-2" data-tailwind-dropdown>
                            <button type="button" class="flex h-11 w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 text-left text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" data-tailwind-dropdown-button>
                                <span class="truncate" data-tailwind-dropdown-label>Cash</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div class="absolute left-0 right-0 top-full z-30 mt-2 hidden rounded-lg border border-slate-200 bg-white p-1 shadow-xl ring-1 ring-slate-950/5" data-tailwind-dropdown-menu>
                                @foreach(['cash' => 'Cash', 'bank' => 'Bank', 'upi' => 'UPI', 'cheque' => 'Cheque'] as $modeValue => $modeLabel)
                                    <button type="button" @class([
                                        'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-black transition',
                                        'bg-slate-950 text-white hover:bg-slate-800' => $modeValue === 'cash',
                                        'text-slate-700 hover:bg-emerald-50 hover:text-emerald-800' => $modeValue !== 'cash',
                                    ]) data-tailwind-dropdown-option data-value="{{ $modeValue }}" data-label="{{ $modeLabel }}">
                                        <span>{{ $modeLabel }}</span>
                                        <span @class([
                                            'h-2 w-2 rounded-full bg-emerald-400',
                                            'hidden' => $modeValue !== 'cash',
                                        ]) data-selected-dot></span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Ref</span>
                        <input type="text" name="payment_reference" class="mt-2 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900">
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Voucher Ref</span>
                        <input type="text" name="reference" class="mt-2 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900">
                    </label>
                    <label class="md:col-span-2">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Details</span>
                        <textarea name="description" rows="3" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-900"></textarea>
                    </label>
                </div>
                <button type="submit" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-slate-800">Save Final Entry</button>
            </form>

            <form method="POST" action="{{ route('admin.accounting.main-account.categories.store') }}" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm" data-main-account-category-form>
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Dynamic Categories</p>
                <h2 class="mt-2 text-xl font-black text-slate-950">Add category</h2>
                <div class="mt-5 grid gap-3">
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category Type</span>
                        <input type="hidden" name="type" value="income" required data-category-type-input>
                        <div class="relative mt-2" data-tailwind-dropdown>
                            <button type="button" class="flex h-11 w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 text-left text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" data-tailwind-dropdown-button>
                                <span class="truncate" data-tailwind-dropdown-label>Income</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div class="absolute left-0 right-0 top-full z-30 mt-2 hidden rounded-lg border border-slate-200 bg-white p-1 shadow-xl ring-1 ring-slate-950/5" data-tailwind-dropdown-menu>
                                <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md bg-slate-950 px-3 py-2.5 text-left text-sm font-black text-white transition hover:bg-slate-800" data-tailwind-dropdown-option data-value="income" data-label="Income">
                                    <span>Income</span>
                                    <span class="h-2 w-2 rounded-full bg-emerald-400" data-selected-dot></span>
                                </button>
                                <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-black text-slate-700 transition hover:bg-rose-50 hover:text-rose-800" data-tailwind-dropdown-option data-value="expense" data-label="Expense">
                                    <span>Expense</span>
                                    <span class="hidden h-2 w-2 rounded-full bg-rose-500" data-selected-dot></span>
                                </button>
                            </div>
                        </div>
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Name</span>
                        <input type="text" name="name" class="mt-2 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" required>
                    </label>
                    <label>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Mapped Ledger Account</span>
                        <input type="hidden" name="account_id" required data-category-account-input>
                        <div class="relative mt-2" data-category-account-dropdown>
                            <button type="button" class="flex h-11 w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 text-left text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-100 disabled:text-slate-400" data-category-account-button>
                                <span class="truncate" data-category-account-label>Select ledger account</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div class="absolute left-0 right-0 top-full z-30 mt-2 hidden max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl ring-1 ring-slate-950/5" data-category-account-menu>
                                @foreach($incomeAccounts as $account)
                                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-black text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-800" data-category-account-option data-type="income" data-value="{{ $account->id }}" data-label="{{ $account->code }} - {{ $account->name }}">
                                        <span class="truncate">{{ $account->code }} - {{ $account->name }}</span>
                                        <span class="hidden h-2 w-2 rounded-full bg-emerald-500" data-selected-dot></span>
                                    </button>
                                @endforeach
                                @foreach($expenseAccounts as $account)
                                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-black text-slate-700 transition hover:bg-rose-50 hover:text-rose-800" data-category-account-option data-type="expense" data-value="{{ $account->id }}" data-label="{{ $account->code }} - {{ $account->name }}">
                                        <span class="truncate">{{ $account->code }} - {{ $account->name }}</span>
                                        <span class="hidden h-2 w-2 rounded-full bg-rose-500" data-selected-dot></span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <span class="mt-2 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800" data-category-account-empty>No active ledger accounts for this category type.</span>
                    </label>
                    <label class="inline-flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-black text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-emerald-600">
                        Active
                    </label>
                </div>
                <button type="submit" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-950 transition hover:bg-slate-50">Create Category</button>
            </form>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Monthly Daily Details</h2>
                <div class="mt-4 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Income</th>
                                <th class="px-4 py-3 text-right">Expense</th>
                                <th class="px-4 py-3 text-right">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($dailyRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $row['label'] }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-700">Rs. {{ number_format($row['income_total'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-rose-700">Rs. {{ number_format($row['expense_total'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black {{ $row['net_total'] >= 0 ? 'text-slate-950' : 'text-rose-700' }}">Rs. {{ number_format($row['net_total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Monthly Category Details</h2>
                <div class="mt-4 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3 text-right">Entries</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($categoryRows as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $row['category_name'] }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['account_label'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-black {{ $row['type'] === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">{{ ucfirst($row['type']) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-600">{{ number_format($row['entry_count']) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No category totals for this month.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Monthly Transaction Details</h2>
            <div class="mt-4 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Journal</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($entries as $entry)
                            <tr>
                                <td class="px-4 py-4 font-black text-slate-950">{{ $entry['date']?->format('d M Y') }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-black {{ $entry['type'] === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $entry['category_name'] }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry['description'] }}</p>
                                </td>
                                <td class="px-4 py-4 font-semibold text-slate-600">{{ $entry['payment_label'] }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-600">{{ $entry['journal_reference'] }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-400">{{ $entry['source_label'] }}</p>
                                </td>
                                <td class="px-4 py-4 font-black {{ $entry['type'] === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">{{ ucfirst($entry['type']) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $entry['amount'], 2) }}</td>
                                <td class="px-4 py-4 text-right">
                                    @if(auth()->user()?->hasRole('admin') && $entry['reversible_entry'] instanceof \App\Models\CompanyAccountingEntry)
                                        <form method="POST" action="{{ route('admin.accounting.main-account.entries.reverse', $entry['reversible_entry']) }}" class="inline-flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="reversal_note" placeholder="Reason" class="h-9 w-36 rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-900" required>
                                            <button type="submit" class="h-9 rounded-lg border border-rose-200 bg-white px-3 text-xs font-black uppercase tracking-[0.12em] text-rose-700">Reverse</button>
                                        </form>
                                    @else
                                        <span class="text-xs font-bold text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No main account entries for this month.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const initTailwindDropdown = (root, input) => {
                    const button = root?.querySelector('[data-tailwind-dropdown-button]');
                    const label = root?.querySelector('[data-tailwind-dropdown-label]');
                    const menu = root?.querySelector('[data-tailwind-dropdown-menu]');
                    const options = Array.from(root?.querySelectorAll('[data-tailwind-dropdown-option]') ?? []);

                    if (! root || ! input || ! button || ! label || ! menu || options.length === 0) {
                        return;
                    }

                    const close = () => menu.classList.add('hidden');
                    const selectOption = (option) => {
                        input.value = option.dataset.value ?? '';
                        label.textContent = option.dataset.label ?? option.textContent.trim();
                        options.forEach((candidate) => {
                            const selected = candidate === option;
                            candidate.classList.toggle('bg-slate-950', selected);
                            candidate.classList.toggle('text-white', selected);
                            candidate.classList.toggle('text-slate-700', ! selected);
                            candidate.querySelector('[data-selected-dot]')?.classList.toggle('hidden', ! selected);
                        });
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    };

                    button.addEventListener('click', () => {
                        menu.classList.toggle('hidden');
                    });
                    options.forEach((option) => {
                        option.addEventListener('click', () => {
                            selectOption(option);
                            close();
                        });
                    });
                    document.addEventListener('click', (event) => {
                        if (! root.contains(event.target)) {
                            close();
                        }
                    });

                    selectOption(options.find((option) => option.dataset.value === input.value) ?? options[0]);
                };

                const initEntryCategoryDropdown = (form) => {
                    const typeSelect = form.querySelector('[data-entry-type-input]');
                    const hiddenInput = form.querySelector('[data-entry-category-input]');
                    const button = form.querySelector('[data-entry-category-button]');
                    const label = form.querySelector('[data-entry-category-label]');
                    const menu = form.querySelector('[data-entry-category-menu]');
                    const options = Array.from(form.querySelectorAll('[data-entry-category-option]'));
                    const emptyNotice = form.querySelector('[data-entry-category-empty]');

                    if (! typeSelect || ! hiddenInput || ! button || ! label || ! menu) {
                        return;
                    }

                    const close = () => menu.classList.add('hidden');
                    const open = () => {
                        if (! button.disabled) {
                            menu.classList.remove('hidden');
                        }
                    };
                    const selectOption = (option) => {
                        hiddenInput.value = option?.dataset.value ?? '';
                        label.textContent = option?.dataset.label ?? 'Select category';
                        options.forEach((candidate) => {
                            const selected = candidate === option;
                            candidate.classList.toggle('bg-slate-950', selected);
                            candidate.classList.toggle('text-white', selected);
                            candidate.classList.toggle('text-slate-700', ! selected);
                            candidate.querySelector('[data-selected-dot]')?.classList.toggle('hidden', ! selected);
                        });
                    };
                    const apply = () => {
                        const selectedType = typeSelect.value;
                        const visibleOptions = options.filter((option) => option.dataset.type === selectedType);

                        options.forEach((option) => {
                            const matchesType = option.dataset.type === selectedType;
                            option.classList.toggle('hidden', ! matchesType);
                            option.disabled = ! matchesType;
                        });

                        const currentOption = visibleOptions.find((option) => option.dataset.value === hiddenInput.value);
                        selectOption(currentOption ?? visibleOptions[0] ?? null);

                        const hasOptions = visibleOptions.length > 0;
                        button.disabled = ! hasOptions;
                        emptyNotice?.classList.toggle('hidden', hasOptions);
                    };

                    button.addEventListener('click', () => {
                        menu.classList.contains('hidden') ? open() : close();
                    });
                    options.forEach((option) => {
                        option.addEventListener('click', () => {
                            selectOption(option);
                            close();
                        });
                    });
                    typeSelect.addEventListener('change', apply);
                    document.addEventListener('click', (event) => {
                        if (! form.contains(event.target)) {
                            close();
                        }
                    });

                    apply();
                };

                const initCategoryAccountDropdown = (form) => {
                    const typeSelect = form.querySelector('[data-category-type-input]');
                    const hiddenInput = form.querySelector('[data-category-account-input]');
                    const button = form.querySelector('[data-category-account-button]');
                    const label = form.querySelector('[data-category-account-label]');
                    const menu = form.querySelector('[data-category-account-menu]');
                    const options = Array.from(form.querySelectorAll('[data-category-account-option]'));
                    const emptyNotice = form.querySelector('[data-category-account-empty]');

                    if (! typeSelect || ! hiddenInput || ! button || ! label || ! menu) {
                        return;
                    }

                    const close = () => menu.classList.add('hidden');
                    const open = () => {
                        if (! button.disabled) {
                            menu.classList.remove('hidden');
                        }
                    };
                    const selectOption = (option) => {
                        hiddenInput.value = option?.dataset.value ?? '';
                        label.textContent = option?.dataset.label ?? 'Select ledger account';
                        options.forEach((candidate) => {
                            const selected = candidate === option;
                            candidate.classList.toggle('bg-slate-950', selected);
                            candidate.classList.toggle('text-white', selected);
                            candidate.classList.toggle('text-slate-700', ! selected);
                            candidate.querySelector('[data-selected-dot]')?.classList.toggle('hidden', ! selected);
                        });
                    };
                    const apply = () => {
                        const selectedType = typeSelect.value;
                        const visibleOptions = options.filter((option) => option.dataset.type === selectedType);

                        options.forEach((option) => {
                            const matchesType = option.dataset.type === selectedType;
                            option.classList.toggle('hidden', ! matchesType);
                            option.disabled = ! matchesType;
                        });

                        const currentOption = visibleOptions.find((option) => option.dataset.value === hiddenInput.value);
                        selectOption(currentOption ?? visibleOptions[0] ?? null);

                        const hasOptions = visibleOptions.length > 0;
                        button.disabled = ! hasOptions;
                        emptyNotice?.classList.toggle('hidden', hasOptions);
                    };

                    button.addEventListener('click', () => {
                        menu.classList.contains('hidden') ? open() : close();
                    });
                    options.forEach((option) => {
                        option.addEventListener('click', () => {
                            selectOption(option);
                            close();
                        });
                    });
                    typeSelect.addEventListener('change', apply);
                    document.addEventListener('click', (event) => {
                        if (! form.contains(event.target)) {
                            close();
                        }
                    });

                    apply();
                };

                document.querySelectorAll('[data-main-account-entry-form]').forEach((form) => {
                    initTailwindDropdown(
                        form.querySelector('[data-entry-type-input]')?.nextElementSibling,
                        form.querySelector('[data-entry-type-input]'),
                    );
                    initTailwindDropdown(
                        form.querySelector('[data-payment-mode-input]')?.nextElementSibling,
                        form.querySelector('[data-payment-mode-input]'),
                    );
                    initEntryCategoryDropdown(form);
                });

                document.querySelectorAll('[data-main-account-category-form]').forEach((form) => {
                    initTailwindDropdown(
                        form.querySelector('[data-category-type-input]')?.nextElementSibling,
                        form.querySelector('[data-category-type-input]'),
                    );
                    initCategoryAccountDropdown(form);
                });
            });
        </script>
    @endpush
</x-layouts.accounting>
