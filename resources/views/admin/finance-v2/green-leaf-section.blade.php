<x-layouts.accounting title="Green Leaf Account">
    @php
        $dateParam = $date->format('Y-m-d');
        $sectionLabels = [
            'purchase' => 'Purchase',
            'expense' => 'Expense',
            'salary' => 'Salary',
            'credit-loan' => 'Credit / Loan',
        ];
        $cards = [
            ['section' => 'purchase', 'label' => 'Purchase', 'value' => $summary['purchase_total'], 'hint' => 'Supplier total buy, paid and credit pending'],
            ['section' => 'expense', 'label' => 'Expense', 'value' => $summary['expense_total'], 'hint' => 'All company expenses'],
            ['section' => 'salary', 'label' => 'Salary', 'value' => $summary['salary_total'], 'hint' => 'Salary and staff advance payments'],
            ['section' => 'credit-loan', 'label' => 'Credit / Loan', 'value' => $summary['loan_total'], 'hint' => 'Given to shops with details link'],
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Green Leaf Account Details</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ $sectionLabels[$section] ?? 'Account' }}</h2>
            <p class="mt-2 text-sm font-semibold text-slate-600">{{ $month_start->format('d M Y') }} to {{ $month_end->format('d M Y') }}</p>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach($cards as $card)
                @include('admin.finance-v2.partials.metric-card', [
                    'label' => $card['label'],
                    'value' => $card['value'],
                    'hint' => $card['hint'],
                    'href' => route('admin.finance-v2.green-leaf.section', ['section' => $card['section'], 'date' => $dateParam]),
                ])
            @endforeach
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Details Table</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">{{ $sectionLabels[$section] ?? 'Account' }} split</h3>
                </div>
                @if($section === 'purchase')
                    <div class="rounded-[1rem] border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-black text-amber-800">
                        Credit pending Rs. {{ number_format((float) $summary['purchase_pending'], 2) }}
                    </div>
                @endif
            </div>
            @include('admin.finance-v2.partials.detail-table', ['rows' => $rows])
        </section>
    </div>
</x-layouts.accounting>
