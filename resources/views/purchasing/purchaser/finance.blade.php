<x-layouts.app title="Purchaser Finance">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 6</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Cart finance</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Update bill payments, review remaining balances, and check supplier credit status from one finance table.</p>
                </div>
                <form action="{{ route('purchaser.finance') }}" method="GET">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                </form>
            </div>
        </section>

        @include('purchasing.invoices.partials.finance-table', [
            'invoices' => $invoices,
            'date' => $date,
            'paymentUpdateRouteName' => 'purchaser.invoices.payment',
            'billPdfRouteName' => 'purchaser.invoices.pdf',
            'financeAudience' => $financeAudience,
            'canManageSuppliers' => $canManageSuppliers,
        ])
    </div>
</x-layouts.app>
