<x-layouts.accounting title="Green Leaf Account">
    @php
        $dateParam = $date->format('Y-m-d');
        $sectionLabels = [
            'purchase' => 'Purchase',
            'expense' => 'Expense',
            'salary' => 'Salary',
            'credit-loan' => 'Advances',
            'balance' => 'Company Balance',
        ];
        $cards = [
            ['section' => 'purchase', 'label' => 'Purchase', 'value' => $summary['purchase_total'], 'hint' => 'Supplier invoices · paid and pending'],
            ['section' => 'expense', 'label' => 'Expense', 'value' => $summary['expense_total'], 'hint' => 'Company operating expenses'],
            ['section' => 'salary', 'label' => 'Salary', 'value' => $summary['salary_total'], 'hint' => 'Salary and staff advances'],
            ['section' => 'balance', 'label' => 'Company Balance', 'value' => $summary['balance'], 'hint' => 'Received Rs. '.number_format($summary['total_received'], 2).' · Outflow Rs. '.number_format($summary['total_paid'], 2)],
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Green Leaf Account</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $sectionLabels[$section] ?? 'Account' }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">{{ $month_start->format('d M Y') }} – {{ $month_end->format('d M Y') }}</p>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($cards as $card)
                @include('admin.finance-v2.partials.metric-card', [
                    'label' => $card['label'],
                    'value' => $card['value'],
                    'hint' => $card['hint'],
                    'href' => route('admin.finance-v2.green-leaf.section', ['section' => $card['section'], 'date' => $dateParam]),
                ])
            @endforeach
        </section>

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Detail Ledger</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ $sectionLabels[$section] ?? 'Account' }} split</h2>
                </div>
                @if($section === 'purchase')
                    <div class="rounded-[1rem] border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-black text-amber-800">
                        Credit pending Rs. {{ number_format((float) $summary['purchase_pending'], 2) }}
                    </div>
                @elseif($section === 'balance')
                    <div class="rounded-[1rem] border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800">
                        Received Rs. {{ number_format((float) $summary['total_received'], 2) }} · Outflow Rs. {{ number_format((float) $summary['total_paid'], 2) }}
                    </div>
                @endif
            </div>
            <div class="p-4 sm:p-5">
                @include('admin.finance-v2.partials.detail-table', ['rows' => $rows])
            </div>
        </section>
    </div>
</x-layouts.accounting>
