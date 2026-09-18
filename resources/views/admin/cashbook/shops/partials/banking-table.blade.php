@php
    $isFull = $isFull ?? false;
    $bankingTransactions = $bankingTransactions ?? ($bankingPagination ? (is_array($bankingPagination) ? $bankingPagination : (method_exists($bankingPagination, 'items') ? $bankingPagination->items() : $bankingPagination)) : []);
@endphp

<div class="overflow-x-auto rounded-2xl border border-slate-200">
    <table class="w-full text-left text-xs border-collapse">
        <thead>
            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                <th class="py-3 px-4 rounded-l-xl">Date</th>
                <th class="py-3 px-4">Category / Notes</th>
                <th class="py-3 px-4">Location / Dest</th>
                <th class="py-3 px-4 text-right">Amount</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right rounded-r-xl">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono">
            @forelse($bankingTransactions as $tx)
                @php
                    $stmt = $tx->statementEntries->first();
                    $isReconciled = (bool) ($stmt && $stmt->is_finalized && $stmt->status === 'reconciled');
                    $isApproved = in_array($tx->status, [\App\Enums\Cashbook\TransactionStatus::Approved->value, 'approved'], true);
                @endphp
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-4 font-mono font-bold text-slate-700 whitespace-nowrap">
                        {{ \Illuminate\Support\Carbon::parse($tx->business_date)->format('d M Y') }}
                    </td>
                    <td class="py-3 px-4 font-sans">
                        <span class="font-bold text-slate-800 text-xs block">
                            {{ $tx->entryType?->name ?: $tx->entry_type_code }}
                        </span>
                        @if($tx->notes)
                            <span class="text-[10px] text-slate-400 block truncate max-w-xs">{{ $tx->notes }}</span>
                        @endif
                    </td>
                    <td class="py-3 px-4 font-sans">
                        @if($isReconciled)
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md">
                                <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i>
                                {{ $selectedBankAccount?->name ?? 'Company Bank' }}
                            </span>
                        @elseif($isApproved)
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-sky-800 bg-sky-50 px-2 py-0.5 rounded-md">
                                <i data-lucide="clock" class="w-3 h-3 text-sky-600"></i>
                                Pending Statement Match
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md">
                                <i data-lucide="store" class="w-3 h-3 text-amber-600"></i>
                                Held at Shop
                            </span>
                        @endif
                    </td>
                    <td class="py-3 px-4 text-right font-black font-mono text-slate-900">
                        ₹{{ number_format((float) $tx->amount, 2) }}
                    </td>
                    <td class="py-3 px-4 text-center font-sans">
                        @if($isReconciled)
                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                <i data-lucide="check-check" class="w-3 h-3"></i> VERIFIED
                            </span>
                        @elseif($isApproved)
                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-sky-700 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200">
                                <i data-lucide="clock" class="w-3 h-3"></i> PENDING VERIFY
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i> NEEDS REVIEW
                            </span>
                        @endif
                    </td>
                    <td class="py-3 px-4 text-right font-sans">
                        <div class="flex items-center justify-end gap-1.5">
                            @if(! $isReconciled && $isApproved)
                                <form method="POST" action="{{ route('admin.cashbook.transaction.verify', $tx->id) }}" class="inline-flex">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition cursor-pointer shadow-xs">
                                        <i data-lucide="check" class="w-3 h-3"></i>
                                        <span>Verify</span>
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('admin.cashbook.transaction.show', $tx->id) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                <span>View</span>
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-8 text-center text-slate-400 font-medium font-sans">
                        <i data-lucide="building" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                        No banking verification records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
