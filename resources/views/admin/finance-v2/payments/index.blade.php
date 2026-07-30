<x-layouts.accounting title="Finance V2 Payments">
    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-5 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Payment Approval</p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Accept payments from shops</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Invoices are suggested first. Expenses, salary and loans are shown for admin review.</p>
                </div>
                <a href="{{ route('admin.finance-v2.payments.create', ['date' => $date->format('Y-m-d')]) }}" class="inline-flex h-11 items-center justify-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white shadow-sm hover:bg-orange-600">Create Payment</a>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Pending</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Needs admin check</h3>
            </div>
            @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $pending_payments])
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Processing Cheques</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Waiting for clearance</h3>
            </div>
            @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $processing_cheques])
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Approved</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">Posted payments</h3>
                </div>
                @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $approved_payments])
            </div>
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Rejected</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">Rejected or bounced</h3>
                </div>
                @include('admin.finance-v2.payments.partials.payment-table', ['payments' => $rejected_payments])
            </div>
        </section>
    </div>
</x-layouts.accounting>
