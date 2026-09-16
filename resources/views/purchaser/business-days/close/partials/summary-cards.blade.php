<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <!-- Bills -->
    <a href="{{ route('purchaser.business-days.close.bills', $day->uuid) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-teal-300 hover:shadow-md">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Bills</p>
        <p class="mt-1 text-2xl font-black text-slate-900">{{ $bills->count() }}</p>
        <p class="mt-0.5 text-[10px] font-bold text-teal-600">Recorded</p>
    </a>

    <!-- Advance -->
    <a href="{{ route('purchaser.business-days.close.advances', $day->uuid) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-teal-300 hover:shadow-md">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance</p>
        <p class="mt-1 text-2xl font-black text-slate-900">{{ $advanceReceives->count() }}</p>
        <p class="mt-0.5 text-[10px] font-bold text-teal-600">Receipts</p>
    </a>

    <!-- Pending -->
    <a href="{{ route('purchaser.business-days.close.pending', $day->uuid) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-teal-300 hover:shadow-md">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending</p>
        <p class="mt-1 text-2xl font-black {{ $pendingList->count() > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $pendingList->count() }}</p>
        <p class="mt-0.5 text-[10px] font-bold {{ $pendingList->count() > 0 ? 'text-amber-600' : 'text-slate-400' }}">Products</p>
    </a>

    <!-- Unit Issues -->
    <a href="{{ route('purchaser.business-days.close.issues', $day->uuid) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-teal-300 hover:shadow-md">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Unit Issues</p>
        <p class="mt-1 text-2xl font-black {{ $unitIssues->count() > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $unitIssues->count() }}</p>
        <p class="mt-0.5 text-[10px] font-bold {{ $unitIssues->count() > 0 ? 'text-rose-600' : 'text-slate-400' }}">Mismatches</p>
    </a>

    <!-- Inventory -->
    <a href="{{ route('purchaser.business-days.close.inventory', $day->uuid) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-teal-300 hover:shadow-md">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Inventory</p>
        <p class="mt-1 text-2xl font-black text-emerald-600">{{ $summary['fully_billed_count'] ?? 0 }}</p>
        <p class="mt-0.5 text-[10px] font-bold text-slate-400">Fully Matched</p>
    </a>

    <!-- Cancelled -->
    <a href="{{ route('purchaser.business-days.close.cancelled', $day->uuid) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-teal-300 hover:shadow-md">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cancelled</p>
        <p class="mt-1 text-2xl font-black text-slate-900">{{ $cancelledPurchases->count() }}</p>
        <p class="mt-0.5 text-[10px] font-bold text-slate-400">Purchases</p>
    </a>
</div>
