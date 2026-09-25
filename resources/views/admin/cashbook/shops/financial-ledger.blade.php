@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Shop Financial Ledger ('.$carbonDate->format('d M Y').')')

@section('content')
@php
    $formattedBusinessDate = $carbonDate->format('d M Y');
    $dayOfWeek = $carbonDate->format('l');
    $prevDate = $carbonDate->copy()->subDay()->toDateString();
    $nextDate = $carbonDate->copy()->addDay()->toDateString();
    $todayDate = today()->toDateString();
    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;

    // Group transactions by entry_type_id or header
    $sortedSettings = $settings->sortBy(fn ($s) => (int) ($s->header_display_order ?? $s->entryType?->display_order ?? $s->display_order))->values();
    $settingsByHeader = $sortedSettings->groupBy(fn ($s) => (int) ($s->header_group_id ?? 0));
    $assignedHeaderIds = $headerGroups->pluck('id')->map(fn($id) => (int) $id)->all();

    $headerSections = collect();
    foreach ($headerGroups->sortBy('display_order') as $hg) {
        $hgId = (int) $hg->id;
        $hSettings = $settingsByHeader->get($hgId, collect())->values();
        $headerSections->push([
            'id' => (string) $hgId,
            'name' => $hg->name,
            'type' => strtolower((string) ($hg->type ?? 'income')),
            'display_order' => (int) ($hg->display_order ?? 0),
            'settings' => $hSettings,
        ]);
    }

    $unassignedSettings = $sortedSettings->reject(function ($s) use ($assignedHeaderIds) {
        return $s->header_group_id && in_array((int) $s->header_group_id, $assignedHeaderIds, true);
    })->values();

    if ($unassignedSettings->isNotEmpty()) {
        $headerSections->push([
            'id' => 'unassigned',
            'name' => 'OTHER ENTRIES & SETTINGS',
            'type' => 'mixed',
            'display_order' => 9999,
            'settings' => $unassignedSettings,
        ]);
    }

    $totalSales = (float) ($snapshot->closing_sales ?? $snapshot->total_sales ?? 0);
    $totalExpense = (float) ($snapshot->closing_expenses ?? $snapshot->total_expense ?? 0);
    $closingShopPosition = (float) ($snapshot->closing_shop_position ?? 0);
    $closingPetty = (float) ($snapshot->closing_petty ?? 0);
    $closingCompanyPending = (float) ($snapshot->closing_company_pending ?? 0);
@endphp

<div class="mx-auto max-w-7xl space-y-6 pb-20"
     x-data="{
        showEditModal: false,
        showDeleteModal: false,
        showClearDayModal: false,
        isSubmitting: false,
        editTx: {
            id: null,
            business_date: '{{ $businessDate }}',
            entry_type_id: '',
            amount: 0,
            funding_source: 'sales',
            company_account_id: '',
            notes: '',
            status: 'posted',
            reason: '',
            reference_tag: '',
            allocated_amount: 0
        },
        deleteTx: {
            id: null,
            name: '',
            amount: 0,
            reason: ''
        },
        openEdit(tx) {
            this.editTx.id = tx.id;
            this.editTx.business_date = tx.business_date ? tx.business_date.substring(0, 10) : '{{ $businessDate }}';
            this.editTx.entry_type_id = String(tx.entry_type_id || '');
            this.editTx.amount = Number(tx.amount || 0);
            this.editTx.funding_source = tx.funding_source || 'sales';
            this.editTx.company_account_id = tx.company_account_id ? String(tx.company_account_id) : '';
            this.editTx.notes = tx.notes || '';
            this.editTx.status = tx.status || 'posted';
            this.editTx.reason = '';
            this.editTx.reference_tag = tx.reference_type ? (tx.reference_type.split('\\').pop() + ' #' + (tx.reference_id || '')) : '';
            this.editTx.allocated_amount = (tx.payment_ledger_allocations || []).reduce((acc, a) => acc + Number(a.amount || 0), 0);
            this.showEditModal = true;
        },
        openDelete(tx) {
            this.deleteTx.id = tx.id;
            this.deleteTx.name = (tx.entry_type ? tx.entry_type.name : (tx.entry_type_code || 'Entry #' + tx.id));
            this.deleteTx.amount = Number(tx.amount || 0);
            this.deleteTx.reason = '';
            this.showDeleteModal = true;
        },
        async submitEdit() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;
            try {
                const response = await fetch('{{ route('admin.cashbook.shop.financial-ledger.update', $currentShopSlugOrId) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        transaction_id: this.editTx.id,
                        business_date: this.editTx.business_date,
                        entry_type_id: this.editTx.entry_type_id,
                        amount: this.editTx.amount,
                        funding_source: this.editTx.funding_source,
                        company_account_id: this.editTx.company_account_id || null,
                        notes: this.editTx.notes,
                        status: this.editTx.status,
                        reason: this.editTx.reason
                    })
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Error updating entry.');
                    this.isSubmitting = false;
                    return;
                }

                window.location.href = '{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}?date=' + encodeURIComponent(this.editTx.business_date);
            } catch (err) {
                alert('An unexpected error occurred: ' + err.message);
                this.isSubmitting = false;
            }
        },
        async submitDelete() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;
            try {
                const response = await fetch('{{ route('admin.cashbook.shop.financial-ledger.delete', $currentShopSlugOrId) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        transaction_id: this.deleteTx.id,
                        reason: this.deleteTx.reason || 'Deleted by Admin'
                    })
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Error deleting transaction.');
                    this.isSubmitting = false;
                    return;
                }

                window.location.reload();
            } catch (err) {
                alert('An unexpected error occurred: ' + err.message);
                this.isSubmitting = false;
            }
        },
        async submitClearDay() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;
            try {
                const response = await fetch('{{ route('admin.cashbook.shop.financial-ledger.clear-day', $currentShopSlugOrId) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        business_date: '{{ $businessDate }}'
                    })
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Error clearing day entries.');
                    this.isSubmitting = false;
                    return;
                }

                window.location.reload();
            } catch (err) {
                alert('An unexpected error occurred: ' + err.message);
                this.isSubmitting = false;
            }
        }
     }">

    <!-- 1. HEADER & NAVIGATION -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight uppercase">
                        {{ $currentShop->name ?: 'Shop #'.$currentShop->shop_id }}
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-sky-100 text-sky-800 px-3 py-0.5 text-xs font-black uppercase tracking-wider">
                        Shop Financial Ledger
                    </span>
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Review and manage day-wise cashbook transactions, balances, payment relations, and financial adjustments.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.cashbook.shop.cashbook-layout', $currentShopSlugOrId) }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-sky-300 bg-sky-50 px-4 py-2 text-xs font-black uppercase tracking-wider text-sky-900 shadow-xs hover:bg-sky-100 transition cursor-pointer">
                    <svg class="w-4 h-4 text-sky-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Manage Shop Cashbook Layout</span>
                </a>
                <a href="{{ route('admin.cashbook.shop.overview', $currentShopSlugOrId) }}?month={{ $month }}&period_mode=day&date={{ $businessDate }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-indigo-300 bg-indigo-50 px-4 py-2 text-xs font-black uppercase tracking-wider text-indigo-900 shadow-xs hover:bg-indigo-100 transition cursor-pointer">
                    <svg class="w-4 h-4 text-indigo-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Back to Overview</span>
                </a>
                <a href="{{ route('admin.cashbook.shop.show', $currentShopSlugOrId) }}?month={{ $month }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-black uppercase tracking-wider text-emerald-900 shadow-xs hover:bg-emerald-100 transition cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Sales Report</span>
                </a>
            </div>
        </div>

        <!-- Date Selector Bar -->
        <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}?date={{ $prevDate }}"
                   class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                    &larr; Prev Day
                </a>
                <a href="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}?date={{ $todayDate }}"
                   class="inline-flex items-center gap-1 rounded-xl border {{ $businessDate === $todayDate ? 'border-sky-500 bg-sky-500 text-white font-black' : 'border-slate-200 bg-white text-slate-700 font-bold hover:bg-slate-100' }} px-3 py-1.5 text-xs transition">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}?date={{ $nextDate }}"
                   class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                    Next Day &rarr;
                </a>
            </div>

            <div class="flex items-center gap-3">
                <button type="button"
                        @click="showClearDayModal = true"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-800 px-3.5 py-1.5 text-xs font-black uppercase tracking-wider transition shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Clear Day</span>
                </button>

                <form method="GET" action="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}" class="flex items-center gap-2">
                    <label for="date_picker" class="text-xs font-bold text-slate-600 uppercase">Business Date:</label>
                    <input type="date" id="date_picker" name="date" value="{{ $businessDate }}"
                           onchange="this.form.submit()"
                           class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                </form>
            </div>
        </div>
    </header>

    <!-- 2. DAY KPI SUMMARY CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Gross Sales</p>
            <p class="mt-1 text-lg font-black text-slate-950">₹{{ number_format($totalSales, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Expenses</p>
            <p class="mt-1 text-lg font-black text-red-600">₹{{ number_format($totalExpense, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Shop Cash Position</p>
            <p class="mt-1 text-lg font-black {{ $closingShopPosition >= 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                ₹{{ number_format($closingShopPosition, 2) }}
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Petty Balance</p>
            <p class="mt-1 text-lg font-black text-purple-700">₹{{ number_format($closingPetty, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs col-span-2 md:col-span-1">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Company Pending</p>
            <p class="mt-1 text-lg font-black text-indigo-700">₹{{ number_format($closingCompanyPending, 2) }}</p>
        </div>
    </div>

    <!-- 3. CANONICAL TRANSACTIONS TABLE -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wide text-slate-900">
                    Actual Ledger Entries ({{ $formattedBusinessDate }})
                </h2>
                <p class="text-xs text-slate-500 font-medium">
                    Showing {{ $transactions->count() }} canonical records directly from the live database
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-100 text-sky-900 border border-sky-200">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Financial Control &amp; Correction
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/70 border-b border-slate-200 text-[11px] font-black uppercase text-slate-600 tracking-wider">
                        <th class="py-3 px-4"># ID</th>
                        <th class="py-3 px-4">Entry / Category</th>
                        <th class="py-3 px-4">Direction</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4">Funding / Source</th>
                        <th class="py-3 px-4">Company Bank</th>
                        <th class="py-3 px-4">Source Ref</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Notes &amp; Audit</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $tx)
                        @php
                            $isVoid = in_array($tx->status, ['void', \App\Enums\Cashbook\TransactionStatus::Void->value], true);
                            $isProtected = $tx->isProtectedSalaryOrGlBill();
                            $dirTone = match($tx->direction) {
                                'income' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                                'expense' => 'text-red-700 bg-red-50 border-red-200',
                                'transfer' => 'text-indigo-700 bg-indigo-50 border-indigo-200',
                                default => 'text-slate-700 bg-slate-50 border-slate-200',
                            };
                            $fundingLabel = match($tx->funding_source) {
                                'sales' => 'Shop Balance',
                                'petty' => 'Petty Cash',
                                'company' => 'Company Paid',
                                'bank' => 'Bank',
                                'external' => 'External',
                                default => $tx->funding_source ?: 'None',
                            };
                            $refTag = '';
                            if ($tx->reference_type) {
                                $shortRef = class_basename($tx->reference_type);
                                $refTag = $shortRef . ($tx->reference_id ? ' #' . $tx->reference_id : '');
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition {{ $isVoid ? 'opacity-50 line-through bg-slate-50' : '' }}">
                            <td class="py-3 px-4 font-mono font-bold text-slate-500">
                                #{{ $tx->id }}
                                @if($tx->generated_by_rule)
                                    <span class="block text-[9px] font-black text-amber-700 uppercase">Child of #{{ $tx->parent_transaction_id }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <p class="font-black text-slate-900 text-xs">{{ $tx->entryType?->name ?: $tx->entry_type_code ?: 'Unknown Item' }}</p>
                                <span class="text-[10px] text-slate-400 font-medium uppercase">{{ $tx->entryType?->category ?: 'General' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase border {{ $dirTone }}">
                                    {{ $tx->direction }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <span class="font-mono font-black text-sm {{ $tx->direction === 'expense' ? 'text-red-600' : ($tx->direction === 'income' ? 'text-emerald-700' : 'text-slate-900') }}">
                                    ₹{{ number_format((float) $tx->amount, 2) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center gap-1 font-bold text-slate-700 text-xs">
                                    {{ $fundingLabel }}
                                </span>
                                @if((float) $tx->petty_delta != 0)
                                    <span class="block text-[10px] font-mono text-purple-700">Petty: {{ (float) $tx->petty_delta > 0 ? '+' : '' }}{{ number_format((float) $tx->petty_delta, 2) }}</span>
                                @endif
                                @if((float) $tx->settlement_delta != 0)
                                    <span class="block text-[10px] font-mono text-indigo-700">Settlement: {{ (float) $tx->settlement_delta > 0 ? '+' : '' }}{{ number_format((float) $tx->settlement_delta, 2) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($tx->companyAccount)
                                    <span class="font-bold text-slate-900 text-xs">{{ $tx->companyAccount->name }}</span>
                                    <span class="block text-[10px] text-slate-400">{{ $tx->companyAccount->bank_name ?: $tx->companyAccount->account_number }}</span>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($refTag)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ $refTag }}
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[10px]">Direct</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase
                                    {{ $tx->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($tx->status === 'posted' ? 'bg-blue-100 text-blue-800' : ($isVoid ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800')) }}">
                                    {{ $tx->statusLabel() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 max-w-xs">
                                <p class="text-xs text-slate-700 truncate" title="{{ $tx->notes }}">{{ $tx->notes ?: '—' }}</p>
                                <span class="block text-[10px] text-slate-400">
                                    By: {{ $tx->enteredBy?->name ?: 'System' }} &bull; {{ $tx->created_at?->format('H:i') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    @if(!$tx->generated_by_rule && !$isProtected)
                                        <button type="button"
                                                @click="openEdit(@js($tx))"
                                                class="px-2.5 py-1 rounded-xl bg-sky-50 text-sky-800 border border-sky-200 hover:bg-sky-100 font-bold text-xs transition cursor-pointer">
                                            Edit
                                        </button>
                                        <button type="button"
                                                @click="openDelete(@js($tx))"
                                                class="px-2 py-1 rounded-xl bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 font-bold text-xs transition cursor-pointer">
                                            Delete
                                        </button>
                                    @elseif($tx->generated_by_rule)
                                        <span class="text-[10px] text-slate-400 italic">Edit Parent #{{ $tx->parent_transaction_id }}</span>
                                    @elseif($isProtected)
                                        @if(!$tx->generated_by_rule)
                                            <button type="button"
                                                    @click="openEdit(@js($tx))"
                                                    class="px-2.5 py-1 rounded-xl bg-sky-50 text-sky-800 border border-sky-200 hover:bg-sky-100 font-bold text-xs transition cursor-pointer">
                                                Edit
                                            </button>
                                        @endif
                                        <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Protected</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-10 text-center text-slate-400 text-xs">
                                No canonical ledger entries recorded for {{ $formattedBusinessDate }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. PRODUCT LEDGER DAILY BREAKDOWN -->
    @php
        $hasProductEntries = !empty($productEntries) && $productEntries->isNotEmpty();
    @endphp
    <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wide text-slate-900">
                    Product Ledger Daily Breakdown ({{ $formattedBusinessDate }})
                </h2>
                <p class="text-xs text-slate-500 font-medium">
                    Showing {{ $productEntries->count() }} product entries tagged to cashbook headers
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-200">
                    <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    Product Tagging
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/70 border-b border-slate-200 text-[11px] font-black uppercase text-slate-600 tracking-wider">
                        <th class="py-3 px-4"># ID</th>
                        <th class="py-3 px-4">Header Group</th>
                        <th class="py-3 px-4">Product Name</th>
                        <th class="py-3 px-4">SKU / Code</th>
                        <th class="py-3 px-4 text-right">Quantity</th>
                        <th class="py-3 px-4">Unit</th>
                        <th class="py-3 px-4 text-right">Avg Rate</th>
                        <th class="py-3 px-4 text-right">Total Amount</th>
                        <th class="py-3 px-4">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($productEntries as $pe)
                        @php
                            $qty = (float) ($pe->quantity ?? 0);
                            $amt = (float) ($pe->amount ?? 0);
                            $rate = ($qty > 0 && $amt > 0) ? ($amt / $qty) : null;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-4 font-mono font-bold text-slate-500">
                                #{{ $pe->id }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-indigo-50 text-indigo-800 border border-indigo-200">
                                    {{ $pe->headerGroup?->name ?: ('Header #'.$pe->header_group_id) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">
                                {{ $pe->product?->name ?: ('Product #'.$pe->product_id) }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                {{ $pe->product?->sku ?: '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-800">
                                {{ $qty > 0 ? number_format($qty, 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 uppercase text-[11px] font-bold text-slate-600">
                                {{ $pe->unit ?: ($pe->product?->unit ?: 'unit') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-600">
                                {{ $rate !== null ? '₹'.number_format($rate, 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-black text-sm text-slate-900">
                                ₹{{ number_format($amt, 2) }}
                            </td>
                            <td class="py-3 px-4 text-[11px] text-slate-500">
                                {{ $pe->enteredBy?->name ?: 'Shop Owner' }} &bull; {{ $pe->created_at?->format('H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-10 text-center text-slate-400 text-xs">
                                No product entries recorded for {{ $formattedBusinessDate }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- ── MODAL: ADMIN DIRECT EDIT TRANSACTION ─────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div x-show="showEditModal"
         x-cloak
         @keydown.escape.window="showEditModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showEditModal = false"
             class="bg-white rounded-3xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-sky-900 to-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <svg class="w-5 h-5 text-sky-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Edit Financial Ledger Entry</h3>
                        <p class="text-[11px] text-sky-200 font-medium">
                            Correct canonical transaction #<span x-text="editTx.id"></span>
                        </p>
                    </div>
                </div>
                <button type="button" @click="showEditModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitEdit()" class="p-6 space-y-4">
                <template x-if="editTx.allocated_amount > 0">
                    <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Settlement allocations attached: ₹<span x-text="editTx.allocated_amount.toFixed(2)"></span>. New amount cannot be lower than this.</span>
                    </div>
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Business Date</label>
                        <input type="date" x-model="editTx.business_date" required
                               class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Amount (₹)</label>
                        <input type="number" step="0.01" min="0" x-model="editTx.amount" required
                               class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Category / Entry Type -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category / Entry Type</label>
                        <select x-model="editTx.entry_type_id" required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                            @foreach($allEntryTypes as $et)
                                <option value="{{ $et->id }}">{{ $et->name }} ({{ strtoupper($et->category) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Funding Source -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Funding / Payment Source</label>
                        <select x-model="editTx.funding_source"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                            <option value="sales">Shop Balance (Cash in hand)</option>
                            <option value="petty">Petty Cash</option>
                            <option value="company">Paid by Company</option>
                            <option value="bank">Bank</option>
                            <option value="external">External</option>
                            <option value="none">None / Direct</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Company Bank Account -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Company Bank Account</label>
                        <select x-model="editTx.company_account_id"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                            <option value="">-- No Bank Account --</option>
                            @foreach($companyAccounts as $ca)
                                <option value="{{ $ca->id }}">{{ $ca->name }} ({{ $ca->bank_name ?: $ca->account_number }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
                        <select x-model="editTx.status"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                            <option value="posted">Posted</option>
                            <option value="approved">Approved</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Pending Approval</option>
                        </select>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notes / Description</label>
                    <input type="text" x-model="editTx.notes" placeholder="Transaction note..."
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                </div>

                <!-- Audit Reason -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Correction Reason (Audit Log)</label>
                    <input type="text" x-model="editTx.reason" placeholder="e.g. Corrected entry amount as per daily physical cash count"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 shadow-2xs focus:border-sky-500 focus:outline-hidden">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="showEditModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-black uppercase tracking-wider shadow-xs transition cursor-pointer disabled:opacity-50">
                        <span x-text="isSubmitting ? 'Saving &amp; Recalculating...' : 'Save &amp; Recalculate Day'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- ── MODAL: ADMIN DELETE TRANSACTION ──────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div x-show="showDeleteModal"
         x-cloak
         @keydown.escape.window="showDeleteModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showDeleteModal = false"
             class="bg-white rounded-3xl max-w-md w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-red-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <svg class="w-5 h-5 text-red-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Delete Ledger Transaction</h3>
                        <p class="text-[11px] text-red-200 font-medium">Permanently removes entry and recalculates</p>
                    </div>
                </div>
                <button type="button" @click="showDeleteModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitDelete()" class="p-6 space-y-4">
                <p class="text-xs text-slate-700 leading-relaxed">
                    Are you sure you want to permanently delete <strong class="text-slate-950" x-text="deleteTx.name"></strong> (₹<span class="font-mono font-bold" x-text="deleteTx.amount.toFixed(2)"></span>)? This will completely remove the entry and recalculate all daily balances.
                </p>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Reason (Optional)</label>
                    <input type="text" x-model="deleteTx.reason" placeholder="e.g. Duplicate entry posted by mistake"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 shadow-2xs focus:border-red-500 focus:outline-hidden">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="showDeleteModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase tracking-wider shadow-xs transition cursor-pointer disabled:opacity-50">
                        <span x-text="isSubmitting ? 'Deleting...' : 'Delete'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- ── MODAL: ADMIN CLEAR DAY ────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div x-show="showClearDayModal"
         x-cloak
         @keydown.escape.window="showClearDayModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showClearDayModal = false"
             class="bg-white rounded-3xl max-w-md w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-rose-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <svg class="w-5 h-5 text-rose-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Clear Day Entries</h3>
                        <p class="text-[11px] text-rose-200 font-medium">{{ $formattedBusinessDate }}</p>
                    </div>
                </div>
                <button type="button" @click="showClearDayModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitClearDay()" class="p-6 space-y-4">
                <div>
                    <h4 class="text-sm font-black text-slate-900 leading-snug">
                        Clear all Shop Cashbook entries for {{ $formattedBusinessDate }}?
                    </h4>
                    <p class="mt-2 text-xs font-semibold text-slate-500 rounded-xl bg-slate-50 border border-slate-200 p-3">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1.5"></span>
                        Salary and GL Bill will remain.
                    </p>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="showClearDayModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-black uppercase tracking-wider shadow-xs transition cursor-pointer disabled:opacity-50">
                        <span x-text="isSubmitting ? 'Clearing Day...' : 'Clear Day'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
