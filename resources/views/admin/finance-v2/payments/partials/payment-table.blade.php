<div class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-left">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Date</th>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Shop</th>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Method</th>
                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Submitted</th>
                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Verified</th>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Status</th>
                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($payments as $payment)
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-600">{{ $payment->payment_date?->toDateString() ?? $payment->created_at?->toDateString() }}</td>
                        <td class="px-4 py-3">
                            <p class="text-sm font-black text-slate-950">{{ $payment->shop?->name ?? 'Shop' }}</p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $payment->shop?->client?->name ?? 'Direct Sales' }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-600">
                            {{ $payment->paymentMethodLabel() }}
                            @if($payment->payment_method === 'cheque')
                                <p class="mt-1 text-xs font-black text-amber-700">{{ $payment->chequeStatusLabel() }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) $payment->requested_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right text-sm font-black text-emerald-700">Rs. {{ number_format((float) ($payment->admin_verified_amount ?? $payment->approved_amount ?? 0), 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">{{ $payment->statusLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.finance-v2.payments.show', $payment) }}" class="inline-flex h-9 items-center rounded-[0.8rem] bg-slate-950 px-3 text-[10px] font-black uppercase tracking-[0.14em] text-white hover:bg-slate-800">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No payments in this queue.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
