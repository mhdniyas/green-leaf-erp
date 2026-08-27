<form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.match-existing', $classifyStatement) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @if($isReconciled) onsubmit="return confirm('Replace existing statement match? The old transaction will return to Pending Reconciliation.')" @endif>
    @csrf
    <input type="hidden" name="candidate_ref" value="{{ $candidate['candidate_ref'] }}">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">{{ $candidate['source_label'] }}</span>
                @if($candidate['date_match'] === 'exact')
                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-white">Exact Date</span>
                @elseif($candidate['date_difference_days'] < 99999)
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $candidate['date_badge_text'] }}</span>
                @endif
                @if($isReconciled)
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-800">Currently Reconciled</span>
                @endif
            </div>
            <p class="mt-2 font-mono text-base font-black text-slate-950">{{ $candidate['formatted_reference'] }}</p>
            <p class="mt-0.5 break-words text-xs font-semibold text-slate-600">{{ $candidate['description'] }}</p>

            @if($isReconciled && !empty($candidate['matched_to']))
                <div class="mt-2 rounded-xl bg-amber-50/70 border border-amber-100 p-2 text-xs">
                    <span class="font-bold text-amber-900">Current Match:</span>
                    <span class="text-amber-800 font-semibold">{{ $candidate['matched_to'] }}</span>
                    @if(!empty($candidate['matched_date']))
                        <span class="text-amber-600 text-[11px] block mt-0.5">Finalized {{ $candidate['matched_date'] }} ({{ $candidate['matched_by'] ?? 'System' }})</span>
                    @endif
                </div>
            @endif

            <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                <span class="rounded-lg bg-slate-50 px-2 py-1.5"><strong>Date</strong><br>{{ $candidate['entry_date'] }}</span>
                <span class="rounded-lg bg-slate-50 px-2 py-1.5"><strong>Amount</strong><br>{{ $candidate['formatted_amount'] }}</span>
                <span class="rounded-lg bg-slate-50 px-2 py-1.5"><strong>Account</strong><br>{{ $candidate['account_name'] }}</span>
                <span class="rounded-lg bg-slate-50 px-2 py-1.5"><strong>Status</strong><br>{{ $candidate['reconciliation_status_label'] }}</span>
            </div>
        </div>
        <div class="shrink-0 sm:self-center">
            @if($isReconciled)
                <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center gap-1 rounded-xl bg-amber-600 px-4 text-xs font-black text-white hover:bg-amber-700 sm:w-auto shadow-sm">
                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Replace Match
                </button>
            @else
                <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center gap-1 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800 sm:w-auto shadow-sm">
                    <i data-lucide="check" class="h-3.5 w-3.5"></i> Match &amp; Finalize
                </button>
            @endif
        </div>
    </div>
</form>
