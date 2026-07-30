<x-layouts.accounting title="Shop Loans">
    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Owned Shop Loans</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Loan control</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Cash given and repayments affect company cash journal. Selected daily categories only consume the loan balance.</p>
                </div>
                @if($selectedShop)
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <a href="{{ route('admin.accounting.owned-shops.categories.index', ['shop' => $selectedShop->code]) }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                            Manage categories
                        </a>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-right">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $loanBalance < 0 ? 'Overused Balance' : 'Available Balance' }}</p>
                            <p class="mt-1 text-xl font-black {{ $loanBalance < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($loanBalance, 2) }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[22rem_1fr]">
            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Shops</p>
                <div class="mt-4 space-y-2">
                    @forelse($shops as $shop)
                        @php($summary = $shopSummaries->get($shop->id, ['balance' => 0, 'category_count' => 0]))
                        <a href="{{ route('admin.accounting.loans', ['shop' => $shop->code]) }}" class="block rounded-[1.15rem] border px-4 py-3 transition {{ $selectedShop?->id === $shop->id ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50 hover:bg-white' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $shop->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $shop->client?->name ?? 'Owned shop' }} · {{ $summary['category_count'] }} loan categories</p>
                                </div>
                                <p class="text-right text-sm font-black {{ (float) $summary['balance'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format((float) $summary['balance'], 2) }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm font-bold text-slate-500">No owned shops enabled.</p>
                    @endforelse
                </div>
            </section>

            @if($selectedShop)
                <div class="space-y-5">
                    <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Settings</p>
                                <h2 class="mt-2 text-xl font-black text-slate-950">{{ $selectedShop->name }} loan categories</h2>
                                <p class="mt-1 text-sm font-semibold text-slate-600">Mark expense categories that should show as paid from loan.</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.accounting.loans.categories.update', ['shop' => $selectedShop->code]) }}" class="mt-5">
                            @csrf
                            @method('PATCH')
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                @forelse($availableCategories->where('type', 'expense') as $category)
                                    @php($setting = $loanSettings->get($category->id))
                                    <label class="flex items-start gap-3 rounded-[1.15rem] border border-slate-200 bg-slate-50 px-4 py-3">
                                        <input type="checkbox" name="loan_effects[{{ $category->id }}]" value="use_loan" @checked($setting) class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-black text-slate-950">{{ $category->name }}</span>
                                            <span class="mt-1 block text-xs font-semibold text-slate-500">{{ ucfirst($category->type) }} · {{ $category->shop_id ? 'Shop' : 'Global' }}</span>
                                            <span class="mt-3 block">
                                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Auto daily amount</span>
                                                <input type="number" step="0.01" min="0" name="loan_default_daily_amounts[{{ $category->id }}]" value="{{ old("loan_default_daily_amounts.$category->id", $setting?->default_daily_amount > 0 ? $setting->default_daily_amount : '') }}" placeholder="Optional" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900">
                                            </span>
                                            <span class="mt-3 block rounded-xl border border-white bg-white px-3 py-2 text-xs font-semibold text-slate-500">
                                                Loan expense only updates the loan balance.
                                            </span>
                                        </span>
                                    </label>
                                @empty
                                    <p class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm font-bold text-slate-500">No categories available for this shop.</p>
                                @endforelse
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">Save loan categories</button>
                            </div>
                        </form>
                    </section>

                    <section class="grid gap-5 lg:grid-cols-2">
                        <form method="POST" action="{{ route('admin.accounting.loans.entries.store', ['shop' => $selectedShop->code]) }}" class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                            @csrf
                            <input type="hidden" name="type" value="cash_given">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Cash Journal OUT</p>
                            <h3 class="mt-2 text-lg font-black text-slate-950">Cash loan given</h3>
                            <div class="mt-4 grid gap-3">
                                <input type="date" name="business_date" value="{{ today()->toDateString() }}" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <input type="text" name="title" value="Cash loan given" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <input type="text" name="description" placeholder="Optional note" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">Record cash given</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.accounting.loans.entries.store', ['shop' => $selectedShop->code]) }}" class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                            @csrf
                            <input type="hidden" name="type" value="repayment">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">Cash Journal IN</p>
                            <h3 class="mt-2 text-lg font-black text-slate-950">Repayment received</h3>
                            <div class="mt-4 grid gap-3">
                                <input type="date" name="business_date" value="{{ today()->toDateString() }}" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <input type="text" name="title" value="Loan repayment received" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <input type="text" name="description" placeholder="Optional note" class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">Record repayment</button>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Ledger</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Loan movements</h2>
                        <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Title</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-right">Total</th>
                                        <th class="px-4 py-3 text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($loanRows as $row)
                                        <tr>
                                            <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                            <td class="px-4 py-3 font-black text-slate-950">{{ $row['category'] }}</td>
                                            <td class="px-4 py-3 font-semibold text-slate-600">{{ $row['title'] }}</td>
                                            <td class="px-4 py-3">
                                                <span class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ in_array($row['status'], ['approved', 'finalized'], true) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ str_replace('_', ' ', $row['status']) }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-right font-black {{ (float) $row['signed_amount'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ (float) $row['signed_amount'] < 0 ? '-' : '+' }} Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">
                                                Rs. {{ number_format((float) $row['balance'], 2) }}
                                                @if($row['pending_balance'] !== null)
                                                    <span class="block text-xs font-semibold text-amber-700">Pending Rs. {{ number_format((float) $row['pending_balance'], 2) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-10 text-center font-bold text-slate-500">No loan movements yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            @endif
        </div>
    </div>
</x-layouts.accounting>
