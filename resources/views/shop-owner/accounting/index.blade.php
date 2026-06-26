@extends('shop-owner.layouts.app')

@section('title', 'Accounting')
@section('page_title', 'Shop Accounting')
@section('page_description', 'Update daily owned-shop accounts, send them for admin approval, and respond to recheck notes from one mobile-friendly workflow.')
@php
    $breadcrumbs = [['label' => 'Accounting']];
@endphp

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.history'), 'label' => 'History', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
@endsection

@section('content')
    @php
        $hasEntry = $entry instanceof \App\Models\ShopAccountingEntry;
        $canEdit = ! $hasEntry || $entry->canBeEditedByShopOwner();
    @endphp

    <div class="space-y-6">
        @include('shop-owner.accounting.partials.tabs')

        <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">{{ strtoupper($shop->accounting_mode) }} Shop</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Daily Accounting Request</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Prepare the day ledger, then submit it for admin approval. If admin flags a recheck, the same day returns here for correction.</p>
                </div>

                <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="flex flex-wrap items-center gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-2">
                    <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                </form>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Income</p>
                <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($incomeTotal, 2) }}</p>
            </div>
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Expense</p>
                <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($expenseTotal, 2) }}</p>
            </div>
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Result</p>
                <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($netAmount, 2) }}</p>
            </div>
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Status</p>
                <div class="mt-3">
                    @if ($hasEntry)
                        @include('shop-owner.components.status-badge', ['label' => $entry->statusLabel(), 'tone' => $entry->statusTone()])
                    @else
                        @include('shop-owner.components.status-badge', ['label' => 'No Entry', 'tone' => 'neutral'])
                    @endif
                </div>
            </div>
        </section>

        @if ($hasEntry && ($entry->admin_note || $entry->shop_reply_note))
            <section class="grid gap-4 lg:grid-cols-2">
                @if ($entry->admin_note)
                    <article class="rounded-[1.75rem] border {{ $entry->status === 'recheck_required' ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] {{ $entry->status === 'recheck_required' ? 'text-red-700' : 'text-slate-500' }}">Admin Note</p>
                        <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">{{ $entry->admin_note }}</p>
                    </article>
                @endif
                @if ($entry->shop_reply_note)
                    <article class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Last Reply Sent</p>
                        <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">{{ $entry->shop_reply_note }}</p>
                    </article>
                @endif
            </section>
        @endif

        <section class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                @if (! $canEdit)
                    <div class="mb-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4">
                        <p class="text-sm font-black text-amber-900">This day is locked while admin review is pending or completed.</p>
                        <p class="mt-2 text-sm font-semibold text-amber-800">If admin marks this day for recheck, it will return here in red with the note to solve.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('shop-owner.accounting.entries.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">

                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Cash</span>
                            <input type="number" step="0.01" min="0" name="opening_cash" value="{{ old('opening_cash', $entry?->opening_cash) }}" @disabled(! $canEdit) class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0 disabled:text-slate-400">
                        </label>
                        <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</span>
                            <input type="number" step="0.01" min="0" name="closing_cash" value="{{ old('closing_cash', $entry?->closing_cash) }}" @disabled(! $canEdit) class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0 disabled:text-slate-400">
                        </label>
                        <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Daily Note</span>
                            <input type="text" name="notes" value="{{ old('notes', $entry?->notes) }}" @disabled(! $canEdit) class="mt-2 w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-0 disabled:text-slate-400">
                        </label>
                    </div>

                    @if ($hasEntry && $entry->status === 'recheck_required')
                        <label class="block rounded-[1.5rem] border border-red-200 bg-red-50 p-4">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-red-700">Reply To Admin Recheck</span>
                            <textarea name="shop_reply_note" rows="4" @disabled(! $canEdit) class="mt-3 w-full rounded-2xl border border-red-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-red-400 focus:outline-none disabled:text-slate-400">{{ old('shop_reply_note', $entry?->shop_reply_note) }}</textarea>
                        </label>
                    @endif

                    <div class="space-y-3">
                        @for($index = 0; $index < max(4, $hasEntry ? $entry->lines->count() : 0); $index++)
                            @php
                                $line = $hasEntry ? $entry->lines[$index] ?? null : null;
                            @endphp
                            <div class="grid gap-3 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1.25fr_0.8fr_1fr]">
                                <select name="lines[{{ $index }}][shop_accounting_category_id]" @disabled(! $canEdit) class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none disabled:text-slate-400">
                                    <option value="">Select category</option>
                                    @foreach($availableCategories as $category)
                                        <option value="{{ $category->id }}" {{ (string) old("lines.$index.shop_accounting_category_id", $line?->shop_accounting_category_id) === (string) $category->id ? 'selected' : '' }}>
                                            {{ strtoupper($category->type) }} • {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.01" min="0" name="lines[{{ $index }}][amount]" value="{{ old("lines.$index.amount", $line?->amount) }}" placeholder="Amount" @disabled(! $canEdit) class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none disabled:text-slate-400">
                                <input type="text" name="lines[{{ $index }}][description]" value="{{ old("lines.$index.description", $line?->description) }}" placeholder="Description" @disabled(! $canEdit) class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none disabled:text-slate-400">
                            </div>
                        @endfor
                    </div>

                    @if ($canEdit)
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button type="submit" name="submission_action" value="save_draft" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                Save Draft
                            </button>
                            <button type="submit" name="submission_action" value="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                {{ $hasEntry && $entry->status === 'recheck_required' ? 'Resubmit For Approval' : 'Send For Approval' }}
                            </button>
                        </div>
                    @endif
                </form>
            </article>

            <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Recent Days</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">History snapshot</h3>
                    </div>
                    <a href="{{ route('shop-owner.accounting.history') }}" class="text-sm font-black text-emerald-700">Open</a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($recentEntries as $recentEntry)
                        @php
                            $recentIncome = (float) $recentEntry->lines->where('type', 'income')->sum('amount');
                            $recentExpense = (float) $recentEntry->lines->where('type', 'expense')->sum('amount');
                        @endphp
                        <a href="{{ route('shop-owner.accounting.index', ['date' => $recentEntry->business_date->format('Y-m-d')]) }}" class="block rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-200 hover:bg-emerald-50">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $recentEntry->business_date->format('d M Y') }}</p>
                                    <div class="mt-2">
                                        @include('shop-owner.components.status-badge', ['label' => $recentEntry->statusLabel(), 'tone' => $recentEntry->statusTone()])
                                    </div>
                                </div>
                                <p class="text-sm font-black text-slate-950">Rs. {{ number_format($recentIncome - $recentExpense, 2) }}</p>
                            </div>
                            @if ($recentEntry->admin_note)
                                <p class="mt-3 line-clamp-2 text-sm font-semibold text-slate-600">{{ $recentEntry->admin_note }}</p>
                            @endif
                        </a>
                    @empty
                        @include('shop-owner.components.empty-state', ['title' => 'No accounting history yet', 'description' => 'Save the first daily accounting sheet to start the approval flow.'])
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
