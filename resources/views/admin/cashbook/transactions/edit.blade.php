@extends('admin.cashbook.layouts.app')

@section('title', ($presented['is_verified'] ? 'Correct' : 'Edit') . ' Transaction #' . $transaction->id . ' — Cashbook')

@section('content')
<div class="mx-auto max-w-3xl space-y-6 pb-12">

    <!-- Top Navigation Breadcrumb -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.cashbook.transaction.show', $transaction->id) }}"
           class="inline-flex items-center gap-1.5 text-xs font-black text-slate-500 hover:text-slate-900 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Transaction Detail</span>
        </a>

        <span class="px-2.5 py-1 rounded-xl text-xs font-black uppercase tracking-wider {{ $presented['is_verified'] ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700' }}">
            {{ $presented['is_verified'] ? 'Reconciled Entry' : 'Unreconciled Entry' }}
        </span>
    </div>

    <!-- Main Form Card -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">

        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">
                {{ $presented['is_verified'] ? 'Correct Finalized Transaction' : 'Edit Transaction' }}
            </h1>
            <p class="text-xs text-slate-500 font-medium mt-1">
                Transaction #{{ $transaction->id }} &bull; {{ $presented['shop_name'] }}
            </p>
        </div>

        @if($presented['is_verified'])
            <!-- Reconciled Warning Banner -->
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-2">
                <div class="flex items-center gap-2 font-black">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 flex-shrink-0"></i>
                    <span>Financial Effect Reversal Notice</span>
                </div>
                <p class="font-medium text-amber-800">
                    This transaction has already affected company money and shop settlement. Saving corrections will safely undo those financial effects and return this transaction to a <strong class="font-black text-amber-950">POSTED</strong> state, requiring the normal approval and verification lifecycle again.
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                <div class="font-black">Please resolve the following errors:</div>
                <ul class="list-disc list-inside font-medium">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.cashbook.transaction.update', $transaction->id) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- Amount & Business Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="amount" class="text-xs font-extrabold text-slate-700">Amount (₹)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-bold text-sm">₹</span>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                               value="{{ old('amount', $transaction->amount) }}" required
                               class="w-full pl-8 pr-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-black text-sm focus:bg-white focus:outline-emerald-600">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="business_date" class="text-xs font-extrabold text-slate-700">Business Date</label>
                    <input type="date" id="business_date" name="business_date"
                           value="{{ old('business_date', $transaction->business_date?->toDateString()) }}" required
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold text-sm focus:bg-white focus:outline-emerald-600 cursor-pointer">
                </div>
            </div>

            <!-- Collection Type / Method -->
            <div class="space-y-1.5">
                <label for="entry_type_id" class="text-xs font-extrabold text-slate-700">Collection Type</label>
                <select id="entry_type_id" name="entry_type_id"
                        class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 font-bold text-xs focus:bg-white focus:outline-emerald-600 cursor-pointer">
                    @foreach($entryTypes as $et)
                        <option value="{{ $et->id }}" {{ (int) old('entry_type_id', $transaction->entry_type_id) === (int) $et->id ? 'selected' : '' }}>
                            {{ $et->name }} ({{ strtoupper($et->code) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Notes -->
            <div class="space-y-1.5">
                <label for="notes" class="text-xs font-extrabold text-slate-700">Notes / Reference</label>
                <textarea id="notes" name="notes" rows="3"
                          placeholder="Optional reference or description..."
                          class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 font-medium text-xs focus:bg-white focus:outline-emerald-600">{{ old('notes', $transaction->notes) }}</textarea>
            </div>

            @if($presented['is_verified'])
                <!-- Reason for Correction -->
                <div class="space-y-1.5 border-t border-slate-100 pt-4">
                    <label for="reversal_reason" class="text-xs font-extrabold text-amber-900 flex items-center gap-1.5">
                        <i data-lucide="edit-3" class="w-3.5 h-3.5 text-amber-600"></i>
                        <span>Correction Reason (Required for audit history)</span>
                    </label>
                    <textarea id="reversal_reason" name="reversal_reason" rows="2" required
                              placeholder="Explain why this reconciled transaction is being corrected (e.g. accidental cash entry, wrong amount recorded)..."
                              class="w-full px-4 py-2.5 rounded-2xl bg-amber-50/50 border border-amber-200 text-slate-900 font-medium text-xs focus:bg-white focus:outline-amber-600">{{ old('reversal_reason') }}</textarea>
                </div>
            @endif

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.cashbook.transaction.show', $transaction->id) }}"
                   class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                    Cancel
                </a>

                <button type="submit"
                        class="px-6 py-2.5 rounded-xl {{ $presented['is_verified'] ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-700 hover:bg-emerald-800' }} text-white text-xs font-black shadow-xs transition">
                    {{ $presented['is_verified'] ? 'Apply Correction & Reverse Effects' : 'Save Changes' }}
                </button>
            </div>

        </form>

    </div>

</div>
@endsection
