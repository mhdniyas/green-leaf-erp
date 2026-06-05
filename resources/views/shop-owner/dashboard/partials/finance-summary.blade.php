<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Finance</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Finance Summary</h2>
        </div>
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.finance.index'), 'label' => 'Open Finance', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Paid Amount</p>
            <p class="mt-3 text-2xl font-black text-emerald-700">Rs. {{ number_format((float) $financeSummary['paid_amount'], 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Outstanding</p>
            <p class="mt-3 text-2xl font-black text-red-600">Rs. {{ number_format((float) $financeSummary['outstanding_balance'], 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shortage Value</p>
            <p class="mt-3 text-2xl font-black text-amber-600">Rs. {{ number_format((float) $financeSummary['shortage_value'], 2) }}</p>
        </div>
    </div>

    @if ($recentOrders->isNotEmpty())
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <th class="py-3 pr-4">Order</th>
                        <th class="py-3 pr-4">Payment</th>
                        <th class="py-3 pr-4 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @foreach ($recentOrders->take(4) as $order)
                        <tr>
                            <td class="py-3 pr-4 font-bold text-slate-900">{{ $order->order_number }}</td>
                            <td class="py-3 pr-4">@include('shop-owner.finance.partials.payment-status-badge', ['order' => $order])</td>
                            <td class="py-3 pr-4 text-right font-bold text-slate-900">Rs. {{ number_format((float) $order->balance_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
