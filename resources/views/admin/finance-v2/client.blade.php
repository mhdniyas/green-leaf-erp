<x-layouts.accounting title="Aishwarya Veg">
    @php
        $dateParam = $date->format('Y-m-d');
        $clientName = $client?->name ?? 'Aishwarya Veg';
        $sectionHref = fn (string $section): string => route('admin.finance-v2.aishwarya-veg.section', ['section' => $section, 'date' => $dateParam]);
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Client Account</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ $clientName }}</h2>
            <p class="mt-2 text-sm font-semibold text-slate-600">Same account structure as Green Leaf, split by all shops.</p>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Purchase / Bills', 'value' => $summary['bills'], 'hint' => 'Shop-wise bill split', 'href' => $sectionHref('purchase')])
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Expense', 'value' => $summary['expense'], 'hint' => 'Shop-wise expense split', 'href' => $sectionHref('expense')])
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Salary', 'value' => $summary['salary'], 'hint' => 'Shop staff salary split', 'href' => $sectionHref('salary')])
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Credit / Loan', 'value' => $summary['loan'], 'hint' => 'Shop-wise credit and loan', 'href' => $sectionHref('credit-loan')])
        </section>

        <section class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">All Shops</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">Received, paid, salary, loan and balance</h3>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Shop', 'Opening', 'Bills', 'Expense', 'Salary', 'Loan', 'Received', 'Credit', 'Closing'] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($shops as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.finance-v2.shops.show', ['shop' => $row['shop'], 'date' => $dateParam]) }}" class="font-black text-slate-950 hover:text-cyan-700">{{ $row['shop']->name }}</a>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $row['shop']->code }}</p>
                                </td>
                                @foreach(['opening_balance', 'bills', 'expense', 'salary', 'loan', 'received', 'credit', 'closing_balance'] as $field)
                                    <td class="px-4 py-3 text-right text-sm font-black {{ $field === 'closing_balance' ? 'text-slate-950' : 'text-slate-700' }}">Rs. {{ number_format((float) $row[$field], 2) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No shops found under {{ $clientName }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
