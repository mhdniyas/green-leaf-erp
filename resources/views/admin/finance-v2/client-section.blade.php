<x-layouts.accounting title="Aishwarya Veg Details">
    @php
        $sectionLabels = [
            'purchase' => 'Purchase / Bills',
            'expense' => 'Expense',
            'salary' => 'Salary',
            'credit-loan' => 'Credit / Loan',
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Aishwarya Veg Split</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ $sectionLabels[$section] ?? 'Details' }}</h2>
            <p class="mt-2 text-sm font-semibold text-slate-600">Click any shop from the client dashboard to see the full shop ledger.</p>
        </section>

        @include('admin.finance-v2.partials.detail-table', ['rows' => $rows])
    </div>
</x-layouts.accounting>
