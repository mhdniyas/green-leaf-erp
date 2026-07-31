<x-layouts.accounting :title="$shop->name.' Payments'">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">{{ $client->name }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $shop->name }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">Shop payments and company payables for this shop.</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Company Payables</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Open settlements</h2>
            </div>
            <div class="divide-y divide-slate-100 px-5">
                @forelse($payables as $line)
                    <div class="flex items-center justify-between gap-4 py-4">
                        <div>
                            <p class="text-sm font-black text-slate-950">{{ $line->category?->name ?? 'Expense' }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $line->company_payable_status }} · remaining Rs. {{ number_format($line->remainingCompanyPayableAmount(), 2) }}</p>
                        </div>
                        <a href="{{ route('admin.finance-v2.company-payables.show', $line) }}" class="text-xs font-black uppercase tracking-[0.14em] text-orange-600 hover:underline">Review</a>
                    </div>
                @empty
                    <p class="py-8 text-sm font-bold text-slate-500">No open company payables.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Shop Payments</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Payment history</h2>
            </div>
            <div class="divide-y divide-slate-100 px-5">
                @forelse($payments as $payment)
                    <div class="flex items-center justify-between gap-4 py-4">
                        <div>
                            <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $payment->requested_amount, 2) }} · {{ $payment->status }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $payment->payment_date?->toDateString() }} · {{ $payment->paymentMethodLabel() }}</p>
                        </div>
                        <a href="{{ route('admin.finance-v2.payments.show', $payment) }}" class="text-xs font-black uppercase tracking-[0.14em] text-orange-600 hover:underline">Open</a>
                    </div>
                @empty
                    <p class="py-8 text-sm font-bold text-slate-500">No shop payments yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.accounting>
