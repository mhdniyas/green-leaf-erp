@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Adjustments Full History')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16" x-data="{ showReverseModal: false, showAddAdjustmentModal: false, targetAdjustmentId: null, targetAdjustmentName: '', targetAdjustmentAmount: '', reverseReason: '', isSubmitting: false, openReverse(id, name, amount) { this.targetAdjustmentId = id; this.targetAdjustmentName = name; this.targetAdjustmentAmount = amount; this.reverseReason = ''; this.showReverseModal = true; } }">
    <!-- Header with Back & Settings buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cashbook.shop.show', $currentShop->slug ?: $currentShop->shop_id) }}"
               class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
               title="Back to Shop Operation Center">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">{{ $currentShop->name }}</h1>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono">{{ $currentShop->code }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Settlement Adjustments &amp; Corrections Audit History</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if(auth()->user() && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                <a href="{{ route('admin.cashbook.settings.shop', $currentShop->slug ?: $currentShop->shop_id) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition"
                   title="Cashbook Settings">
                    <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
                    <span>Settings</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.cashbook.shop.history.adjustments', $currentShop->slug ?: $currentShop->shop_id) }}"
          class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center gap-3 text-xs font-bold">
        <div class="flex items-center gap-1.5">
            <label class="text-slate-400 uppercase text-[10px]">Month:</label>
            <input type="month" name="month" value="{{ $month }}" class="px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <div class="flex items-center gap-1.5 flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search note, reference..." class="w-full px-3 py-1.5 bg-slate-50 rounded-xl border border-slate-300 text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <button type="submit" class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold shadow-xs transition">
            Filter
        </button>
        @if($month || $search)
            <a href="{{ route('admin.cashbook.shop.history.adjustments', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                Reset
            </a>
        @endif
    </form>

    <!-- Full History Table Card -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">All Settlement Adjustments</h2>
            <span class="text-xs text-slate-500 font-mono font-bold">Total: {{ $adjustments->total() }} records</span>
        </div>

        @include('admin.cashbook.shops.partials.adjustments-table', ['adjustments' => $adjustments, 'isFull' => true])

        @if($adjustments->hasPages())
            <div class="pt-4 border-t border-slate-100">
                {{ $adjustments->links() }}
            </div>
        @endif
    </div>

    <!-- Reversal Modal -->
    <div x-show="showReverseModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
             @click.away="if(!isSubmitting) showReverseModal = false">
            <div class="flex items-center gap-3 text-rose-700">
                <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200">
                    <i data-lucide="rotate-ccw" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900">Reverse Adjustment</h3>
                    <p class="text-xs text-slate-500 font-medium">Create offsetting immutable ledger transaction</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.cashbook.shop.day.adjustments.reverse', $currentShop->slug ?: $currentShop->shop_id) }}" @submit="isSubmitting = true">
                @csrf
                <input type="hidden" name="business_date" value="{{ today()->toDateString() }}">
                <input type="hidden" name="adjustment_id" :value="targetAdjustmentId">

                <div class="space-y-4 text-xs">
                    <div class="p-3 rounded-xl bg-rose-50/50 border border-rose-100 text-slate-700 space-y-1">
                        <div class="font-extrabold text-rose-950" x-text="'Reverse ' + targetAdjustmentName + ' of ₹' + targetAdjustmentAmount"></div>
                        <p class="text-[11px] text-slate-500">This will record an exact opposite ledger entry to neutralize the outstanding effect while preserving audit history.</p>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 mb-1">Reversal Reason (Optional)</label>
                        <input type="text" name="reason" x-model="reverseReason" placeholder="e.g. Incorrect amount recorded"
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-medium text-slate-900 focus:outline-none focus:border-slate-500">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showReverseModal = false" :disabled="isSubmitting"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isSubmitting"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                            <span x-text="isSubmitting ? 'Reversing...' : 'Confirm Reversal'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
