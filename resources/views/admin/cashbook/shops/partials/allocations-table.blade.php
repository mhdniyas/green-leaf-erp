@php
    $isFull = $isFull ?? false;
@endphp

<div class="overflow-x-auto rounded-2xl border border-slate-200">
    <table class="w-full text-left text-xs border-collapse">
        <thead>
            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                <th class="py-3 px-4 rounded-l-xl">Date &amp; ID</th>
                <th class="py-3 px-4">Payment Reference</th>
                <th class="py-3 px-4">Settlement Item</th>
                <th class="py-3 px-4 text-right">Allocated Amount</th>
                <th class="py-3 px-4">Allocated By</th>
                <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono">
            @forelse($allocations as $alloc)
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-4 font-sans">
                        <span class="font-extrabold text-slate-900 text-sm block">
                            {{ $alloc->created_at ? $alloc->created_at->format('d M Y') : '—' }}
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">#{{ $alloc->id }}</span>
                    </td>
                    <td class="py-3 px-4 font-sans">
                        <span class="font-bold text-slate-800 text-xs block">
                            {{ $alloc->paymentRequest?->payment_reference ?: 'Payment #'.($alloc->shop_invoice_payment_request_id ?? '—') }}
                        </span>
                        <span class="text-[10px] text-slate-500 font-mono">
                            Total: ₹{{ number_format((float) ($alloc->paymentRequest?->requested_amount ?? 0), 2) }}
                        </span>
                    </td>
                    <td class="py-3 px-4 font-sans">
                        <span class="font-bold text-slate-800 text-xs block">
                            {{ $alloc->ledgerTransaction?->entryType?->name ?: 'Daily Settlement' }}
                        </span>
                        <span class="text-[10px] text-slate-500 font-mono">
                            Date: {{ $alloc->ledgerTransaction?->business_date ? \Illuminate\Support\Carbon::parse($alloc->ledgerTransaction->business_date)->format('d M Y') : '—' }}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right font-black text-emerald-700">
                        ₹{{ number_format((float) $alloc->amount, 2) }}
                    </td>
                    <td class="py-3 px-4 font-sans text-slate-600 text-xs">
                        {{ $alloc->reconciledBy?->name ?? 'System' }}
                    </td>
                    <td class="py-3 px-4 text-right font-sans">
                        <form method="POST"
                              action="{{ route('admin.cashbook.shop.allocations.remove', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'allocation' => $alloc->id]) }}"
                              class="inline-block"
                              onsubmit="return confirm('Remove this allocation? The amount will return to the payment\'s unallocated balance.');">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition cursor-pointer">
                                <i data-lucide="trash-2" class="w-3 h-3 text-rose-600"></i>
                                <span>Remove</span>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-8 text-center text-slate-400 font-medium font-sans">
                        <i data-lucide="check-square" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                        No payment allocation records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
