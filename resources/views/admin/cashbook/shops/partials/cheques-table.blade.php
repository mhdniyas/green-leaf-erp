@php
    $isFull = $isFull ?? false;
@endphp

<div class="overflow-x-auto rounded-2xl border border-slate-200">
    <table class="w-full text-left text-xs border-collapse">
        <thead>
            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                <th class="py-3 px-4 rounded-l-xl">Cheque Date &amp; Number</th>
                <th class="py-3 px-4">Cheque Bank</th>
                <th class="py-3 px-4 text-right">Amount</th>
                <th class="py-3 px-4">Received Date</th>
                <th class="py-3 px-4 text-center">Status</th>
                @if($isFull)
                    <th class="py-3 px-4">Notes</th>
                @endif
                <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono">
            @forelse($cheques as $cheque)
                @php
                    $isPending = $cheque->cheque_status === 'pending';
                    $isCleared = $cheque->cheque_status === 'cleared';
                    $isRejected = $cheque->cheque_status === 'rejected';
                    $statusBadge = match(true) {
                        $isPending => 'bg-violet-50 text-violet-800 border-violet-200',
                        $isCleared => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        $isRejected => 'bg-rose-50 text-rose-800 border-rose-200',
                        default => 'bg-slate-100 text-slate-700 border-slate-200',
                    };
                    $statusText = match(true) {
                        $isPending => 'Floating / Pending Deposit',
                        $isCleared => 'Cleared in Bank',
                        $isRejected => 'Rejected / Bounced',
                        default => ucfirst((string) $cheque->cheque_status),
                    };
                @endphp
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-4 font-sans">
                        <span class="font-extrabold text-slate-900 text-sm block">
                            {{ $cheque->cheque_date ? \Illuminate\Support\Carbon::parse($cheque->cheque_date)->format('d M Y') : '—' }}
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">{{ $cheque->payment_reference ?: 'No Cheque #' }}</span>
                    </td>
                    <td class="py-3 px-4 font-sans font-bold text-slate-800">
                        {{ $cheque->cheque_bank_name ?: 'Bank Cheque' }}
                    </td>
                    <td class="py-3 px-4 text-right font-black text-slate-900">
                        ₹{{ number_format((float) $cheque->requested_amount, 2) }}
                    </td>
                    <td class="py-3 px-4 font-sans text-slate-600">
                        {{ \Illuminate\Support\Carbon::parse($cheque->payment_date)->format('d M Y') }}
                    </td>
                    <td class="py-3 px-4 text-center font-sans">
                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-lg border {{ $statusBadge }}">
                            {{ $statusText }}
                        </span>
                    </td>
                    @if($isFull)
                        <td class="py-3 px-4 font-sans text-slate-500 text-[11px] max-w-xs truncate">
                            {{ $cheque->shop_note ?: $cheque->admin_note ?: '—' }}
                        </td>
                    @endif
                    <td class="py-3 px-4 text-right font-sans">
                        <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => \Illuminate\Support\Carbon::parse($cheque->payment_date)->format('Y-m')]) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                            <i data-lucide="eye" class="w-3 h-3"></i>
                            <span>View Month</span>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isFull ? 7 : 6 }}" class="py-8 text-center text-slate-400 font-medium font-sans">
                        <i data-lucide="file-text" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                        No cheque payment records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
