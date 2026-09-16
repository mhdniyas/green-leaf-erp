<div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">Close Day Verification Checklist</h3>
        <p class="text-xs font-semibold text-slate-400">Review all movements and reconciliation data for this business day before closing.</p>
    </div>

    <div class="divide-y divide-slate-100">
        <!-- 1. Purchase Bills -->
        <div class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50/70">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6H2.25m0 0v10.5m0-10.5h19.5m0 0v10.5m0-10.5a.75.75 0 00-.75-.75H18.75M21.75 16.5H2.25m19.5 0v2.25c0 .754-.726 1.294-1.453 1.096a60.114 60.114 0 00-15.797-2.101" /></svg>
                </span>
                <div>
                    <p class="text-xs font-bold text-slate-900">Purchase Bills</p>
                    <p class="text-[11px] font-medium text-slate-500">Recorded supplier bills for this day</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-black text-slate-900">{{ $bills->count() }}</span>
                <a href="{{ route('purchaser.business-days.close.bills', $day->uuid) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-700">
                    <span>View</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>

        <!-- 2. Advance Receives -->
        <div class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50/70">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.215-9.13A2.25 2.25 0 0015.625 7.5H13.5V3.75a1.125 1.125 0 00-1.125-1.125H3.375A1.125 1.125 0 002.25 3.75v10.5" /></svg>
                </span>
                <div>
                    <p class="text-xs font-bold text-slate-900">Advance Receives</p>
                    <p class="text-[11px] font-medium text-slate-500">Warehouse intake receipts awaiting bill match</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-black text-slate-900">{{ $advanceReceives->count() }}</span>
                <a href="{{ route('purchaser.business-days.close.advances', $day->uuid) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-700">
                    <span>View</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>

        <!-- 3. Pending Products -->
        <div class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50/70">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl {{ $pendingList->count() > 0 ? 'bg-amber-50 text-amber-600' : 'bg-slate-50 text-slate-400' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                </span>
                <div>
                    <p class="text-xs font-bold text-slate-900">Pending Products</p>
                    <p class="text-[11px] font-medium text-slate-500">Advance products without matching purchase bills</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @if ($pendingList->count() > 0)
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-black text-amber-800">{{ $pendingList->count() }}</span>
                @else
                    <span class="text-sm font-black text-slate-400">0</span>
                @endif
                <a href="{{ route('purchaser.business-days.close.pending', $day->uuid) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-700">
                    <span>View</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>

        <!-- 4. Unit Issues -->
        <div class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50/70">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl {{ $unitIssues->count() > 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-50 text-slate-400' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                </span>
                <div>
                    <p class="text-xs font-bold text-slate-900">Unit Issues</p>
                    <p class="text-[11px] font-medium text-slate-500">Mismatches between advance receive units and bill units</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @if ($unitIssues->count() > 0)
                    <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-black text-rose-700">{{ $unitIssues->count() }}</span>
                @else
                    <span class="text-sm font-black text-slate-400">0</span>
                @endif
                <a href="{{ route('purchaser.business-days.close.issues', $day->uuid) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-700">
                    <span>View</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>

        <!-- 5. Inventory Status -->
        <div class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50/70">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                </span>
                <div>
                    <p class="text-xs font-bold text-slate-900">Inventory Status</p>
                    <p class="text-[11px] font-medium text-slate-500">Advance vs Bill stock comparison breakdown</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1 text-xs font-black text-emerald-700">
                    <span>✓</span>
                    <span>Reconciled</span>
                </span>
                <a href="{{ route('purchaser.business-days.close.inventory', $day->uuid) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-700">
                    <span>View</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>

        <!-- 6. Cancelled Purchases -->
        <div class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50/70">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                </span>
                <div>
                    <p class="text-xs font-bold text-slate-900">Cancelled Purchases</p>
                    <p class="text-[11px] font-medium text-slate-500">Cancelled purchase orders or receipts for this day</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-black text-slate-900">{{ $cancelledPurchases->count() }}</span>
                <a href="{{ route('purchaser.business-days.close.cancelled', $day->uuid) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-700">
                    <span>View</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    </div>
</div>
