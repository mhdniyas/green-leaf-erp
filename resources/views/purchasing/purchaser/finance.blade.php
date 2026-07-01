<x-layouts.app title="Purchaser Finance">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 6</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Cart finance</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Update bill payments, review remaining balances, and check supplier credit status from one finance table.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <form action="{{ route('purchaser.finance') }}" method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="hidden" name="tab" value="{{ $selectedTab }}">
                        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none lg:rounded-2xl lg:px-4">
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search vendor, invoice, cart..." class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none sm:w-64">
                    </form>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 lg:max-w-sm">
                <a href="{{ route('purchaser.finance', ['date' => $date, 'tab' => 'today', 'search' => $search]) }}"
                   @class([
                       'flex items-center justify-between rounded-2xl border px-4 py-3 text-xs font-black transition',
                       'border-slate-950 bg-slate-950 text-white shadow-sm' => $selectedTab === 'today',
                       'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' => $selectedTab !== 'today',
                   ])>
                    <span>Today</span>
                    <span class="rounded-full px-2 py-1 text-[10px] {{ $selectedTab === 'today' ? 'bg-white/15 text-white' : 'bg-white text-slate-700' }}">{{ $financeTabs['today'] }}</span>
                </a>
                <a href="{{ route('purchaser.finance', ['date' => $date, 'tab' => 'old', 'search' => $search]) }}"
                   @class([
                       'flex items-center justify-between rounded-2xl border px-4 py-3 text-xs font-black transition',
                       'border-slate-950 bg-slate-950 text-white shadow-sm' => $selectedTab === 'old',
                       'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' => $selectedTab !== 'old',
                   ])>
                    <span>Old</span>
                    <span class="rounded-full px-2 py-1 text-[10px] {{ $selectedTab === 'old' ? 'bg-white/15 text-white' : 'bg-white text-slate-700' }}">{{ $financeTabs['old'] }}</span>
                </a>
            </div>
        </section>

        @include('purchasing.invoices.partials.finance-table', [
            'invoices' => $invoices,
            'date' => $date,
            'selectedTab' => $selectedTab,
            'paymentUpdateRouteName' => 'purchaser.invoices.payment',
            'billPdfRouteName' => 'purchaser.invoices.pdf',
            'financeAudience' => $financeAudience,
            'canManageSuppliers' => $canManageSuppliers,
        ])
    </div>
</x-layouts.app>
