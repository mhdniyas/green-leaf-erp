@extends('admin.cashbook.layouts.app')

@section('title', 'Payment Journal Detail - Cashbook')

@section('header_title')
    <i data-lucide="book-open-check" class="h-5 w-5 text-emerald-600"></i> Journal Detail
@endsection

@section('header_subtitle')
    Full trace for one shop payment and its reconciliation.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>
        <span class="hidden sm:inline">Journal</span>
    </a>
@endsection

@section('content')
    @php
        $floatingAmount = (float) $paymentRequest->floating_amount > 0
            ? (float) $paymentRequest->floating_amount
            : max(0, (float) $paymentRequest->requested_amount - (float) $paymentRequest->reconciled_amount);

        $statementOptions = $openStatementEntries->map(function ($entry) {
            $openAmount = (float) max(0, $entry->amount - $entry->matched_amount);
            return [
                'id' => (string) $entry->id,
                'account_id' => (string) $entry->company_account_id,
                'account_name' => $entry->companyAccount?->name ?? 'Account',
                'date' => $entry->transaction_date?->format('d M Y') ?? '',
                'short_date' => $entry->transaction_date?->format('d M') ?? '',
                'amount' => $openAmount,
                'amount_formatted' => '₹' . number_format($openAmount, 2),
                'reference' => $entry->reference ?? '',
                'narration' => $entry->narration ?? '',
                'search_text' => strtolower(implode(' ', array_filter([
                    $entry->companyAccount?->name,
                    $entry->transaction_date?->format('d M Y'),
                    $entry->transaction_date?->format('d M'),
                    $entry->transaction_date?->format('Y-m-d'),
                    (string) $openAmount,
                    number_format($openAmount, 2),
                    $entry->reference,
                    $entry->narration,
                ]))),
            ];
        })->values();
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
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

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="break-words text-xl font-extrabold text-slate-950">{{ $paymentRequest->shop?->name ?? 'Shop Payment' }}</h2>
                        <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black uppercase text-amber-700">{{ $paymentRequest->reconciliationStatusLabel() }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $paymentRequest->paymentMethodLabel() }}</span>
                    </div>
                    <div class="mt-2 grid gap-2 text-xs font-semibold text-slate-500 sm:grid-cols-2">
                        <div>Reference: <span class="font-mono font-bold text-slate-700">{{ $paymentRequest->payment_reference ?: '-' }}</span></div>
                        <div>Payment date: <span class="font-mono font-bold text-slate-700">{{ $paymentRequest->payment_date?->format('Y-m-d') ?: '-' }}</span></div>
                        <div>Requested by: <span class="font-bold text-slate-700">{{ $paymentRequest->requestedBy?->name ?: '-' }}</span></div>
                        <div>Reviewed by: <span class="font-bold text-slate-700">{{ $paymentRequest->reviewedBy?->name ?: '-' }}</span></div>
                    </div>
                </div>
                <a href="{{ route('admin.cashbook.finance.reconciliation', ['month' => ($paymentRequest->payment_date ?: $paymentRequest->created_at)->format('Y-m')]) }}" class="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-700 shadow-sm hover:bg-emerald-100 sm:w-auto">
                    <i data-lucide="git-compare-arrows" class="h-4 w-4"></i> Match Statement
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Requested</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($paymentRequest->requested_amount, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Reconciled</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($paymentRequest->reconciled_amount, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Floating</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-amber-700">₹{{ number_format($floatingAmount, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-cyan-700">₹{{ number_format($paymentRequest->shop_advance_amount, 2) }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[0.8fr_1.2fr]">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-950">Reconcile This Payment</h3>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Use the statement-first queue for normal approval. This form remains for admin fallback.</p>
                </div>

                @if($paymentRequest->reconciliation_status !== 'reconciled')
                    <form method="POST" action="{{ route('admin.cashbook.finance.payments.reconcile', $paymentRequest) }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="mb-1 block text-[11px] font-bold text-slate-700">Company Account <span class="text-rose-500">*</span></label>
                            <select name="company_account_id" id="company_account_id" required class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <option value="">Select Account</option>
                                @foreach($companyAccounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('company_account_id') == $account->id)>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Tailwind + Alpine.js Searchable Statement Dropdown -->
                        <div x-data="searchableStatementDropdown({
                            entries: {{ Js::from($statementOptions) }},
                            selectedId: '{{ old('statement_entry_id', '') }}'
                        })" class="relative">
                            <label class="mb-1 block text-[11px] font-bold text-slate-700">
                                Statement Entry Match <span class="font-normal text-slate-400">(Optional)</span>
                            </label>

                            <!-- Hidden field for standard form submission -->
                            <input type="hidden" name="statement_entry_id" :value="selectedId">

                            <!-- Dropdown Trigger Button -->
                            <button type="button"
                                    @click="toggleDropdown()"
                                    @keydown.escape="open = false"
                                    class="flex min-h-10 w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-left text-xs font-bold text-slate-800 shadow-sm transition hover:border-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">

                                <div class="flex min-w-0 items-center gap-2">
                                    <template x-if="!selectedEntry">
                                        <span class="flex items-center gap-1.5 text-slate-600">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                            <span class="font-bold">Auto add to selected account statement</span>
                                        </span>
                                    </template>
                                    <template x-if="selectedEntry">
                                        <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase text-slate-700" x-text="selectedEntry.account_name"></span>
                                            <span class="text-slate-400">•</span>
                                            <span class="font-semibold text-slate-600" x-text="selectedEntry.short_date"></span>
                                            <span class="text-slate-400">•</span>
                                            <span class="font-mono font-extrabold text-emerald-700" x-text="selectedEntry.amount_formatted"></span>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center gap-1">
                                    <template x-if="selectedId">
                                        <span @click.stop="clearSelection()"
                                              title="Clear selection"
                                              class="flex h-5 w-5 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            ✕
                                        </span>
                                    </template>
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                                </div>
                            </button>

                            <!-- Dropdown Menu Box -->
                            <div x-show="open"
                                 x-cloak
                                 @click.outside="open = false"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                                 class="absolute left-0 right-0 z-50 mt-1.5 rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl">

                                <!-- Search Input Box with Icon -->
                                <div class="relative mb-2">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                        <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                    </div>
                                    <input type="text"
                                           x-ref="searchInput"
                                           x-model="searchQuery"
                                           placeholder="Search account, date, amount, reference..."
                                           class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-8 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                    <button x-show="searchQuery"
                                            type="button"
                                            @click="searchQuery = ''; $refs.searchInput.focus()"
                                            class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-xs text-slate-400 hover:text-slate-700">
                                        ✕
                                    </button>
                                </div>

                                <!-- Header info -->
                                <div class="flex items-center justify-between px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <span>Statement Options</span>
                                    <span x-text="filteredEntries.length + ' entries'"></span>
                                </div>

                                <!-- Scrollable Option List -->
                                <div class="max-h-60 space-y-1 overflow-y-auto pr-1 custom-scrollbar">
                                    <!-- Default Auto Add Option -->
                                    <button type="button"
                                            @click="selectEntry(null)"
                                            class="flex w-full items-start justify-between gap-2 rounded-xl p-2 text-left transition"
                                            :class="!selectedId ? 'bg-emerald-50 border border-emerald-200 text-emerald-950' : 'hover:bg-slate-50 text-slate-700'">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 text-xs font-bold">
                                                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                                <span>Auto add to selected account statement</span>
                                            </div>
                                            <p class="mt-0.5 text-[10px] text-slate-500">Create/match automatic statement entry upon approval</p>
                                        </div>
                                        <template x-if="!selectedId">
                                            <span class="font-bold text-emerald-600">✓</span>
                                        </template>
                                    </button>

                                    <!-- Filtered Statement Entry Items -->
                                    <template x-for="entry in filteredEntries" :key="entry.id">
                                        <button type="button"
                                                @click="selectEntry(entry)"
                                                class="flex w-full items-start justify-between gap-2 rounded-xl p-2 text-left transition"
                                                :class="selectedId === entry.id ? 'bg-emerald-50 border border-emerald-200 text-emerald-950' : 'hover:bg-slate-50 text-slate-800'">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-black uppercase text-slate-700" x-text="entry.account_name"></span>
                                                    <span class="text-xs font-bold text-slate-900" x-text="entry.date"></span>
                                                </div>
                                                <template x-if="entry.narration || entry.reference">
                                                    <p class="mt-0.5 truncate text-[10px] font-medium text-slate-500" x-text="entry.narration || entry.reference"></p>
                                                </template>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <div class="font-mono text-xs font-extrabold text-emerald-700" x-text="entry.amount_formatted"></div>
                                                <template x-if="selectedId === entry.id">
                                                    <span class="text-[10px] font-black text-emerald-600">✓ Selected</span>
                                                </template>
                                            </div>
                                        </button>
                                    </template>

                                    <!-- Empty Result Message -->
                                    <div x-show="filteredEntries.length === 0" class="py-6 text-center text-xs font-semibold text-slate-400">
                                        No statement entries matching "<span x-text="searchQuery"></span>"
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-[11px] font-bold text-slate-700">Cleared Amount (₹)</label>
                                <input type="number" step="0.01" min="0.01" name="cleared_amount" value="{{ number_format($floatingAmount ?: $paymentRequest->requested_amount, 2, '.', '') }}" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Cleared">
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-bold text-slate-700">Statement Amount (₹)</label>
                                <input type="number" step="0.01" min="0" name="statement_amount" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Bank amount">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-bold text-slate-700">Difference Handling</label>
                            <select name="difference_action" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <option value="none">No difference</option>
                                <option value="keep_floating">Keep floating</option>
                                <option value="shop_expense">Add shop expense</option>
                                <option value="shop_income">Add shop income</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-[11px] font-bold text-slate-700">Difference Amount (₹)</label>
                                <input type="number" step="0.01" min="0" name="difference_amount" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Difference">
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-bold text-slate-700">Business Date</label>
                                <input type="date" name="business_date" value="{{ today()->toDateString() }}" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-bold text-slate-700">Admin Note</label>
                            <textarea name="admin_note" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Admin note"></textarea>
                        </div>

                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white shadow-sm transition hover:bg-emerald-500">
                            <i data-lucide="check-circle-2" class="h-4 w-4"></i> Approve Reconciliation
                        </button>
                    </form>
                @else
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">
                        This payment is fully reconciled.
                    </div>
                @endif
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-950">Reconciliation Trace</h3>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Account, statement, amount, difference, and admin record.</p>
                </div>
                <div class="space-y-3">
                    @forelse($paymentRequest->reconciliations as $reconciliation)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-black text-slate-950">{{ $reconciliation->companyAccount?->name ?? 'Company account' }}</div>
                                    <p class="mt-1 break-words text-xs font-semibold text-slate-500">
                                        {{ $reconciliation->statementEntry?->narration ?: $reconciliation->statementEntry?->reference ?: 'Auto statement entry' }}
                                    </p>
                                    <div class="mt-1 text-[11px] font-bold text-slate-400">
                                        {{ $reconciliation->reconciled_at?->format('Y-m-d H:i') }} / {{ $reconciliation->reconciledBy?->name ?? '-' }}
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">{{ $reconciliation->status }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Statement</span>
                                    <strong class="font-mono text-slate-800">₹{{ number_format($reconciliation->statement_amount, 2) }}</strong>
                                </div>
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Cleared</span>
                                    <strong class="font-mono text-emerald-700">₹{{ number_format($reconciliation->cleared_amount, 2) }}</strong>
                                </div>
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Difference</span>
                                    <strong class="font-mono text-amber-700">₹{{ number_format($reconciliation->difference_amount, 2) }}</strong>
                                </div>
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Action</span>
                                    <strong class="text-slate-700">{{ str_replace('_', ' ', $reconciliation->difference_action) }}</strong>
                                </div>
                            </div>
                            @if($reconciliation->admin_note)
                                <div class="mt-3 rounded-xl bg-white p-3 text-xs font-semibold text-slate-600">{{ $reconciliation->admin_note }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No reconciliation recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 border-b border-slate-200 pb-3">
                <h3 class="text-base font-extrabold text-slate-950">Allocation Details</h3>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">Invoices or balance records connected to this payment.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse($paymentRequest->allocations as $allocation)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-sm font-black text-slate-950">Invoice #{{ $allocation->invoice?->invoice_number ?: $allocation->shop_invoice_id }}</div>
                        <div class="mt-2 font-mono text-lg font-extrabold text-emerald-700">₹{{ number_format($allocation->amount, 2) }}</div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400 md:col-span-2 xl:col-span-3">
                        No invoice allocation rows. This may be a shop balance payment.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        function searchableStatementDropdown(config) {
            return {
                open: false,
                searchQuery: '',
                entries: config.entries || [],
                selectedId: config.selectedId || '',
                selectedEntry: null,

                init() {
                    if (this.selectedId) {
                        this.selectedEntry = this.entries.find(e => String(e.id) === String(this.selectedId)) || null;
                    }
                },

                get filteredEntries() {
                    const q = this.searchQuery.trim().toLowerCase();
                    if (!q) {
                        return this.entries;
                    }
                    return this.entries.filter(e => e.search_text && e.search_text.includes(q));
                },

                toggleDropdown() {
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => {
                            if (this.$refs.searchInput) {
                                this.$refs.searchInput.focus();
                            }
                            if (window.lucide) {
                                lucide.createIcons();
                            }
                        });
                    }
                },

                selectEntry(entry) {
                    if (!entry) {
                        this.selectedId = '';
                        this.selectedEntry = null;
                        this.open = false;
                        return;
                    }

                    this.selectedId = String(entry.id);
                    this.selectedEntry = entry;
                    this.open = false;

                    // Automatically sync company_account_id select if account matches
                    const accountSelect = document.getElementById('company_account_id');
                    if (accountSelect && entry.account_id) {
                        accountSelect.value = entry.account_id;
                    }

                    // Automatically populate statement amount with statement entry amount
                    const statementAmountInput = document.querySelector('input[name="statement_amount"]');
                    if (statementAmountInput && (!statementAmountInput.value || parseFloat(statementAmountInput.value) === 0)) {
                        statementAmountInput.value = Number(entry.amount).toFixed(2);
                    }
                },

                clearSelection() {
                    this.selectedId = '';
                    this.selectedEntry = null;
                }
            };
        }
    </script>
    @endpush
@endsection
