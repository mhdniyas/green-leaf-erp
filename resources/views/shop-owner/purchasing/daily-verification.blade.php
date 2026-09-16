@extends('shop-owner.layouts.app')

@section('title', 'Purchaser Daily Verification')
@section('page_title', 'Daily Verification')
@section('page_description', 'Review, validate, and verify daily purchasing bills before final closing.')
@php($breadcrumbs = [['label' => 'Purchasing', 'url' => route('shop-owner.purchasing.index')], ['label' => 'Daily Verification']])

@section('content')
<div class="space-y-6">
    {{-- Header Row & Date Selector --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h2 class="text-xl font-black text-slate-950">Daily Verification</h2>
                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-black uppercase tracking-wider {{ $verification->status->badgeClasses() }}">
                    {{ $verification->status->label() }}
                </span>
            </div>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                Purchaser: <strong class="text-slate-800">{{ $purchaser->name }}</strong> • Shop: <strong class="text-slate-800">{{ $activeShop->name }}</strong> • Business Date: <strong class="text-slate-800">{{ $businessDate }}</strong>
            </p>
        </div>

        <form method="GET" action="{{ route('shop-owner.purchasing.verification') }}" class="flex items-center gap-2">
            <div class="relative">
                <input
                    type="date"
                    name="date"
                    value="{{ $businessDate }}"
                    onchange="this.form.submit()"
                    class="h-10 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-800 shadow-xs focus:border-emerald-500 focus:outline-none"
                >
            </div>
            <noscript>
                <button type="submit" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Go</button>
            </noscript>
        </form>
    </div>

    {{-- Reopen Reason Banner if Reopened --}}
    @if($verification->isReopened())
        <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 shadow-xs">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 rounded-full bg-rose-100 p-1.5 text-rose-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                </div>
                <div>
                    <h3 class="text-sm font-black text-rose-950">This Purchasing Day Was Reopened by Admin</h3>
                    <p class="mt-1 text-xs font-bold text-rose-800">
                        Reason: <span class="font-semibold">{{ $verification->reopen_reason }}</span>
                    </p>
                    <p class="mt-0.5 text-[11px] font-semibold text-rose-600">
                        Reopened by {{ $verification->reopenedBy?->name ?? 'Admin' }} at {{ $verification->reopened_at?->format('d M Y, h:i A') }}. Please correct any bills and re-verify.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Status Stepper --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-4">Verification Lifecycle</p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            {{-- Step 1: Open / Automated Check --}}
            <div class="relative flex items-center gap-3 rounded-xl border p-3 {{ $data['all_clear'] ? 'border-emerald-200 bg-emerald-50/40 text-emerald-950' : 'border-amber-200 bg-amber-50/40 text-amber-950' }}">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-black text-xs {{ $data['all_clear'] ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }}">
                    1
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-black">Automated Validation</p>
                    <p class="text-[11px] font-semibold opacity-80">{{ $data['all_clear'] ? 'All bills complete' : $data['incomplete_bills_count'].' need attention' }}</p>
                </div>
            </div>

            {{-- Step 2: User Verified --}}
            <div class="relative flex items-center gap-3 rounded-xl border p-3 {{ $verification->verified_at ? 'border-blue-200 bg-blue-50/40 text-blue-950' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-black text-xs {{ $verification->verified_at ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                    2
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-black">User Verified</p>
                    <p class="text-[11px] font-semibold opacity-80">
                        {{ $verification->verified_at ? 'Verified at '.$verification->verified_at->format('h:i A') : 'Pending purchaser action' }}
                    </p>
                </div>
            </div>

            {{-- Step 3: Second Verified --}}
            <div class="relative flex items-center gap-3 rounded-xl border p-3 {{ $verification->second_verified_at ? 'border-indigo-200 bg-indigo-50/40 text-indigo-950' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-black text-xs {{ $verification->second_verified_at ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                    3
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-black">Second Verified</p>
                    <p class="text-[11px] font-semibold opacity-80">
                        {{ $verification->second_verified_at ? 'By '.($verification->secondVerifiedBy?->name ?? 'Verifier') : 'Pending 2nd verifier' }}
                    </p>
                </div>
            </div>

            {{-- Step 4: Finalized --}}
            <div class="relative flex items-center gap-3 rounded-xl border p-3 {{ $verification->isFinalized() ? 'border-emerald-300 bg-emerald-100/60 text-emerald-950' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-black text-xs {{ $verification->isFinalized() ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-600' }}">
                    4
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-black">Finalized & Locked</p>
                    <p class="text-[11px] font-semibold opacity-80">
                        {{ $verification->isFinalized() ? 'Locked by '.($verification->finalizedBy?->name ?? 'Admin') : 'Unlocked' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Purchase Bills</p>
            <p class="mt-1 text-2xl font-black text-slate-950">{{ $data['total_bills'] }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-slate-400">₹{{ number_format($data['total_amount'], 2) }} total</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Cash Purchases</p>
            <p class="mt-1 text-2xl font-black text-emerald-900">₹{{ number_format($data['cash_total'], 2) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-emerald-600">{{ $data['cash_bills_count'] }} cash bills</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Credit Purchases</p>
            <p class="mt-1 text-2xl font-black text-amber-900">₹{{ number_format($data['credit_total'], 2) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-amber-600">{{ $data['credit_bills_count'] }} vendor credit bills</p>
        </div>
        <div class="rounded-2xl border {{ $data['incomplete_bills_count'] > 0 ? 'border-rose-200 bg-rose-50/60' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider {{ $data['incomplete_bills_count'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">Pending Attention</p>
            <p class="mt-1 text-2xl font-black {{ $data['incomplete_bills_count'] > 0 ? 'text-rose-900' : 'text-slate-950' }}">{{ $data['incomplete_bills_count'] }}</p>
            <p class="mt-0.5 text-[11px] font-semibold {{ $data['incomplete_bills_count'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                {{ $data['incomplete_bills_count'] > 0 ? 'Must fix or carry forward' : ($data['carried_forward_count'].' carried forward') }}
            </p>
        </div>
    </div>

    {{-- Incomplete Bills (Need Attention) --}}
    @if($data['incomplete_bills_count'] > 0)
        <div class="rounded-2xl border border-rose-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-xs font-black text-rose-700">!</span>
                    <h3 class="text-sm font-black text-slate-900">Bills Requiring Attention ({{ $data['incomplete_bills_count'] }})</h3>
                </div>
                <p class="text-xs font-semibold text-rose-600">All incomplete bills must be resolved or explicitly carried forward before verifying.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-2.5 px-3">Bill #</th>
                            <th class="py-2.5 px-3">Vendor</th>
                            <th class="py-2.5 px-3">Amount</th>
                            <th class="py-2.5 px-3">Validation Errors</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @foreach($data['incomplete_bills'] as $item)
                            @php($inv = $item['invoice'])
                            <tr class="hover:bg-rose-50/30">
                                <td class="py-3 px-3 font-bold text-slate-950">{{ $inv->invoice_number }}</td>
                                <td class="py-3 px-3">{{ $inv->supplier?->name ?? 'Missing Vendor' }}</td>
                                <td class="py-3 px-3 font-bold">₹{{ number_format((float) $inv->amount, 2) }}</td>
                                <td class="py-3 px-3">
                                    <div class="space-y-1">
                                        @foreach($item['failures'] as $fail)
                                            <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700 border border-rose-200">
                                                {{ $fail }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <button
                                        type="button"
                                        onclick="openCarryForwardModal({{ $inv->id }}, '{{ $inv->invoice_number }}')"
                                        class="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-black text-amber-800 hover:bg-amber-100 transition"
                                    >
                                        Carry Forward
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Action Controls & Verification Buttons --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-black text-slate-950">Verification Actions</h3>
                <p class="text-xs font-semibold text-slate-500">Perform the required verification steps in order.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Action 1: Verify My Day --}}
                @if($verification->canBeUserVerified())
                    <form method="POST" action="{{ route('shop-owner.purchasing.verification.verify') }}">
                        @csrf
                        <input type="hidden" name="business_date" value="{{ $businessDate }}">
                        <button
                            type="submit"
                            @if(! $data['all_clear']) disabled @endif
                            class="inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-xs font-black shadow-xs transition {{ $data['all_clear'] ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-slate-200 text-slate-400 cursor-not-allowed' }}"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            VERIFY MY DAY
                        </button>
                    </form>
                @endif

                {{-- Action 2: Second Verification (Only if logged in user is NOT the purchaser) --}}
                @if($verification->canBeSecondVerified() && auth()->id() !== (int) $verification->purchaser_user_id)
                    <form method="POST" action="{{ route('shop-owner.purchasing.verification.second-verify') }}">
                        @csrf
                        <input type="hidden" name="verification_id" value="{{ $verification->id }}">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-black text-white shadow-xs hover:bg-indigo-700 transition"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            SECOND VERIFY DAY
                        </button>
                    </form>
                @elseif($verification->canBeSecondVerified())
                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-500 border border-slate-200">
                        Second verifier must be another user
                    </span>
                @endif

                {{-- Action 3: Finalize Day --}}
                @if($verification->canBeFinalized())
                    <form method="POST" action="{{ route('shop-owner.purchasing.verification.finalize') }}">
                        @csrf
                        <input type="hidden" name="verification_id" value="{{ $verification->id }}">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white shadow-xs hover:bg-emerald-700 transition"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                            FINALIZE DAY & LOCK
                        </button>
                    </form>
                @endif

                @if($verification->isFinalized())
                    <div class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-2 text-xs font-black text-emerald-800 border border-emerald-200">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        Day Finalized & Locked ({{ $verification->finalized_at?->format('d M Y, h:i A') }})
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Complete Bills Table --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <h3 class="text-sm font-black text-slate-950 mb-3">Complete & Validated Bills ({{ $data['complete_bills_count'] }})</h3>
        @if($data['complete_bills']->isEmpty())
            <p class="py-6 text-center text-xs font-semibold text-slate-400">No complete bills recorded for this purchaser on this day.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-2.5 px-3">Bill #</th>
                            <th class="py-2.5 px-3">Vendor</th>
                            <th class="py-2.5 px-3">Payment Method</th>
                            <th class="py-2.5 px-3">Items</th>
                            <th class="py-2.5 px-3 text-right">Amount</th>
                            <th class="py-2.5 px-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @foreach($data['complete_bills'] as $inv)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3 px-3 font-bold text-slate-950">{{ $inv->invoice_number }}</td>
                                <td class="py-3 px-3">{{ $inv->supplier?->name ?? '—' }}</td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold {{ strcasecmp((string)$inv->payment_method, 'Cash') === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $inv->payment_method }}
                                    </span>
                                </td>
                                <td class="py-3 px-3">{{ $inv->purchaserCart?->items?->count() ?? $inv->goodsReceived?->items?->count() ?? 0 }} items</td>
                                <td class="py-3 px-3 text-right font-black text-slate-900">₹{{ number_format((float) $inv->amount, 2) }}</td>
                                <td class="py-3 px-3 text-right">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                        ✓ Validated
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Carried Forward Bills --}}
    @if($data['carried_forward_count'] > 0)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/30 p-5 shadow-xs">
            <h3 class="text-sm font-black text-amber-950 mb-3">Carried Forward Bills ({{ $data['carried_forward_count'] }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-amber-200 text-[11px] font-black uppercase tracking-wider text-amber-800">
                            <th class="py-2.5 px-3">Bill #</th>
                            <th class="py-2.5 px-3">Vendor</th>
                            <th class="py-2.5 px-3">Original Date</th>
                            <th class="py-2.5 px-3">Reason</th>
                            <th class="py-2.5 px-3">Carried By</th>
                            <th class="py-2.5 px-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100 font-semibold text-slate-700">
                        @foreach($data['carried_forward_bills'] as $inv)
                            <tr>
                                <td class="py-3 px-3 font-bold text-slate-950">{{ $inv->invoice_number }}</td>
                                <td class="py-3 px-3">{{ $inv->supplier?->name ?? '—' }}</td>
                                <td class="py-3 px-3">{{ $inv->original_business_date?->toDateString() ?? $businessDate }}</td>
                                <td class="py-3 px-3 text-amber-900 font-bold">{{ $inv->carry_forward_reason }}</td>
                                <td class="py-3 px-3 text-slate-500">{{ $inv->carried_forward_at?->format('d M, h:i A') }}</td>
                                <td class="py-3 px-3 text-right font-black text-slate-900">₹{{ number_format((float) $inv->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- Carry Forward Modal --}}
<div id="carry-forward-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-xs flex items-center justify-center">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-base font-black text-slate-950">Carry Forward Purchase Bill</h3>
        <p class="mt-1 text-xs font-semibold text-slate-500">
            Carrying forward bill <strong id="modal-bill-number" class="text-slate-900"></strong>. The original business date will remain preserved.
        </p>

        <form method="POST" action="{{ route('shop-owner.purchasing.verification.carry-forward') }}" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="invoice_id" id="modal-invoice-id" value="">

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Reason for Carry Forward <span class="text-rose-600">*</span></label>
                <textarea
                    name="reason"
                    required
                    rows="3"
                    placeholder="e.g. Waiting for vendor rate confirmation on tomato crates..."
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-800 focus:bg-white focus:border-emerald-500 focus:outline-none"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button
                    type="button"
                    onclick="closeCarryForwardModal()"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="rounded-xl bg-amber-600 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-700 transition shadow-xs"
                >
                    Confirm Carry Forward
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCarryForwardModal(invoiceId, billNumber) {
        document.getElementById('modal-invoice-id').value = invoiceId;
        document.getElementById('modal-bill-number').textContent = billNumber;
        document.getElementById('carry-forward-modal').classList.remove('hidden');
    }

    function closeCarryForwardModal() {
        document.getElementById('carry-forward-modal').classList.add('hidden');
    }
</script>
@endsection
