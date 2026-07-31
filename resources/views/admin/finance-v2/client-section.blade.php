@php
    $sectionLabels = [
        'purchase' => 'Bills',
        'expense' => 'Expense',
        'salary' => 'Salary',
        'credit-loan' => 'Credit',
    ];
    $pageTitle = ($client?->name ?? 'Client').' Details';
@endphp

<x-layouts.accounting :title="$pageTitle">

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">{{ $client?->name ?? 'Client' }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $sectionLabels[$section] ?? 'Details' }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">Shop-level detail for this client section. Open a shop for the full ledger.</p>
            </div>
        </section>

        <section class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            @include('admin.finance-v2.partials.detail-table', ['rows' => $rows])
        </section>
    </div>
</x-layouts.accounting>
