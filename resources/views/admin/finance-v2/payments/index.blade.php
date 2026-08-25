<x-layouts.accounting title="Finance V2 Payments">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Payment Approval</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">Shop Payments</h1>
                        <p class="mt-2 text-sm font-semibold text-slate-300">Review pending invoices first. Expenses and salary remain visible for admin check.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.finance-v2.direct-payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">Direct Payments</a>
                        <a href="{{ route('admin.finance-v2.client-payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">Client Payments</a>
                        <a href="{{ route('admin.cashbook.all-shops') }}" class="inline-flex h-11 items-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-orange-600">Open Cashbook Shop</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Pending</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Needs admin review</h2>
            </div>
            @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $pending_payments])
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Processing Cheques</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Waiting for clearance</h2>
            </div>
            @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $processing_cheques])
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Approved</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Posted payments</h2>
                </div>
                @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $approved_payments])
            </div>
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Rejected</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Rejected or bounced</h2>
                </div>
                @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $rejected_payments])
            </div>
        </section>
    </div>
</x-layouts.accounting>
