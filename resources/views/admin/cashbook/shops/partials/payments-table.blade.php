@php
    $isFull = $isFull ?? false;
@endphp

<div class="overflow-x-auto rounded-2xl border border-slate-200">
    <table class="w-full text-left text-xs border-collapse">
        <thead>
            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                <th class="py-3 px-4 rounded-l-xl">Date &amp; Ref</th>
                <th class="py-3 px-4">Method &amp; Account</th>
                <th class="py-3 px-4 text-right">Received</th>
                <th class="py-3 px-4 text-right">Allocated</th>
                <th class="py-3 px-4 text-right">Unallocated</th>
                <th class="py-3 px-4">Allocated Date</th>
                <th class="py-3 px-4 text-center">Status</th>
                @if($isFull)
                    <th class="py-3 px-4">Notes</th>
                @endif
                <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono">
            @forelse($payments as $payment)
                @php
                    $isModel = $payment instanceof \App\Models\ShopInvoicePaymentRequest;
                    $ref = $isModel ? $payment->payment_reference : ($payment['reference'] ?? null);
                    $date = $isModel ? \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y') : ($payment['date'] ?? '');
                    $method = $isModel ? ucfirst($payment->payment_method ?? 'bank') : ($payment['method'] ?? '');
                    $account = $isModel ? ($payment->reconciliations->first()?->companyAccount?->name ?? 'Company Account') : ($payment['account'] ?? '');
                    $received = (float) ($isModel ? $payment->requested_amount : ($payment['amount'] ?? 0));
                    $allocated = (float) ($isModel ? ($payment->ledgerAllocations->sum('amount') ?? 0) : ($payment['allocated'] ?? 0));
                    $unallocated = (float) ($isModel ? max(0, $received - $allocated) : ($payment['unallocated'] ?? 0));
                    $lastAllocDate = $isModel ? ($payment->ledgerAllocations->max('created_at')?->format('d M Y') ?? null) : ($payment['last_allocated_date'] ?? null);
                    $paymentId = $isModel ? $payment->id : ($payment['id'] ?? null);
                    $chequeStatus = $isModel ? $payment->cheque_status : ($payment['cheque_status'] ?? null);
                    $statusLabel = $isModel ? ($chequeStatus === 'pending' ? 'Floating Cheque' : ($allocated >= $received ? 'Fully Allocated' : ($allocated > 0 ? 'Partially Allocated' : 'Unallocated'))) : ($payment['allocation_status_label'] ?? 'Unallocated');
                    $statusClass = match(true) {
                        $chequeStatus === 'pending' => 'bg-violet-50 text-violet-800 border-violet-200',
                        $allocated >= $received => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        $allocated > 0 => 'bg-sky-50 text-sky-800 border-sky-200',
                        default => 'bg-amber-50 text-amber-800 border-amber-200',
                    };
                @endphp
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-4 font-sans">
                        <span class="font-extrabold text-slate-900 text-sm block">{{ $date }}</span>
                        <span class="text-[10px] text-slate-400 font-mono">{{ $ref ?: 'No reference' }}</span>
                    </td>
                    <td class="py-3 px-4 font-sans">
                        <span class="font-bold text-slate-800 uppercase text-[11px] block">{{ $method }}</span>
                        <span class="text-[10px] text-slate-500 font-mono">{{ $account }}</span>
                    </td>
                    <td class="py-3 px-4 text-right font-bold text-slate-900">
                        ₹{{ number_format($received, 2) }}
                    </td>
                    <td class="py-3 px-4 text-right font-bold text-emerald-700">
                        ₹{{ number_format($allocated, 2) }}
                    </td>
                    <td class="py-3 px-4 text-right font-black {{ $unallocated > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                        ₹{{ number_format($unallocated, 2) }}
                    </td>
                    <td class="py-3 px-4 font-sans">
                        <span class="text-[11px] font-bold {{ $lastAllocDate ? 'text-slate-700' : 'text-slate-400' }}">
                            {{ $lastAllocDate ?: '—' }}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center font-sans">
                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-lg border {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    @if($isFull)
                        <td class="py-3 px-4 font-sans text-slate-500 text-[11px] max-w-xs truncate">
                            {{ $isModel ? ($payment->shop_note ?: $payment->admin_note) : ($payment['notes'] ?? '—') }}
                        </td>
                    @endif
                    <td class="py-3 px-4 text-right font-sans">
                        <div class="flex items-center justify-end gap-1.5">
                            @if($isModel)
                                <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => \Illuminate\Support\Carbon::parse($payment->payment_date)->format('Y-m')]) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                    <i data-lucide="eye" class="w-3 h-3"></i>
                                    <span>View Month</span>
                                </a>
                            @else
                                <button type="button" @click="openDetailsModal({{ json_encode($payment) }})"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition cursor-pointer">
                                    <i data-lucide="eye" class="w-3 h-3"></i>
                                    <span>Details</span>
                                </button>
                                @if($unallocated > 0 && ($payment['can_allocate'] ?? true) && $chequeStatus !== 'pending')
                                    <button type="button" @click="openAllocateModal({{ json_encode($payment) }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                                        <i data-lucide="check-square" class="w-3 h-3"></i>
                                        <span>Allocate</span>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isFull ? 9 : 8 }}" class="py-8 text-center text-slate-400 font-medium font-sans">
                        <i data-lucide="wallet" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                        No payment records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
