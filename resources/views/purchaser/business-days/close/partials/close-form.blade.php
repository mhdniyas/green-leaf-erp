@php
    $canClose = auth()->user()->hasRole('admin') || auth()->user()->isMainAdmin() || ($warehouseSettings['purchasers_can_close'] ?? true);
    $allowCloseWithPending = auth()->user()->hasRole('admin') || auth()->user()->isMainAdmin() || ($warehouseSettings['allow_close_with_pending'] ?? true);
    $hasPending = $pendingList->isNotEmpty() || $unitIssues->isNotEmpty();
    $blocked = $hasPending && ! $allowCloseWithPending;
@endphp

<div id="close-form-container" class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    @if ($day->isClosed())
        <div class="flex items-center justify-between rounded-2xl bg-slate-100 p-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-200 text-slate-600 font-bold">
                    ✓
                </span>
                <div>
                    <h4 class="text-sm font-black text-slate-900">This Business Day is Closed</h4>
                    <p class="text-xs font-semibold text-slate-500">
                        Closed by {{ $day->closedBy?->name ?? 'User' }} on {{ $day->closed_at?->format('d M Y, h:i A') }}
                        @if ($day->close_note)
                            · Note: "{{ $day->close_note }}"
                        @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('purchasing.business-days.show', $day->uuid) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700">
                View Day
            </a>
        </div>
    @elseif (! $canClose)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-bold text-amber-800">
            Purchaser closing is disabled for this warehouse in Company Settings. Only an administrator can close this Business Day.
        </div>
    @elseif ($blocked)
        <div class="space-y-3">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800">
                Closing with pending items is disabled for this warehouse in Company Settings. Please record bills to match all pending advance receives before closing.
            </div>
            <div class="flex gap-3">
                <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-teal-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-teal-500">
                    + Record Purchase Bill
                </a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('purchaser.business-days.close.store', $day->uuid) }}" class="space-y-4">
            @csrf

            @if ($isClean)
                <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-900">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white font-black text-sm">
                        ✓
                    </span>
                    <div>
                        <p class="text-xs font-black">Everything Complete ✓</p>
                        <p class="text-[11px] font-medium text-emerald-700">All advance receives have been fully matched with purchase bills. No unit issues or pending items found.</p>
                    </div>
                </div>
            @else
                <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-amber-900">
                    <p class="text-xs font-black">Pending Items Detected ({{ $pendingList->count() }})</p>
                    <p class="mt-0.5 text-[11px] font-medium text-amber-700">Closing this business day now will freeze advance inventory for today. A reason / close note is required.</p>
                </div>
            @endif

            <div>
                <label for="close_note" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">
                    Close Note {{ $hasPending ? '(Reason Required)' : '(Optional)' }}
                </label>
                <textarea
                    id="close_note"
                    name="close_note"
                    rows="3"
                    {{ $hasPending ? 'required' : '' }}
                    placeholder="{{ $hasPending ? 'Enter reason for closing with pending advance items...' : 'Optional notes regarding today\'s purchases...' }}"
                    class="mt-1.5 w-full rounded-2xl border border-slate-200 bg-slate-50 p-3.5 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none"
                >{{ old('close_note') }}</textarea>
                @error('close_note')
                    <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    + Add Bill Before Closing
                </a>

                @if ($isClean)
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-6 text-xs font-black text-white shadow-md shadow-emerald-600/20 transition hover:bg-emerald-500">
                        Close Business Day
                    </button>
                @else
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-amber-600 px-6 text-xs font-black text-white shadow-md shadow-amber-600/20 transition hover:bg-amber-500">
                        Close With Pending
                    </button>
                @endif
            </div>
        </form>
    @endif
</div>
