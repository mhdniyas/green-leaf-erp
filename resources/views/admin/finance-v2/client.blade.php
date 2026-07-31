@php
    $dateParam = $date->format('Y-m-d');
    $clientName = $client?->name ?? 'Client';
    $sectionHref = fn (string $section): string => route('admin.finance-v2.clients.section', ['client' => $client, 'section' => $section, 'date' => $dateParam]);
@endphp

<x-layouts.accounting :title="$clientName">

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Client Account</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $clientName }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">Shop-level bills, expenses, salary, receipts and closing balances for this period.</p>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Bills', 'value' => $summary['bills'], 'hint' => 'Shop invoice totals', 'href' => $sectionHref('purchase')])
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Expense', 'value' => $summary['expense'], 'hint' => 'Shop expense totals', 'href' => $sectionHref('expense')])
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Salary', 'value' => $summary['salary'], 'hint' => 'Shop staff salary', 'href' => $sectionHref('salary')])
            @include('admin.finance-v2.partials.metric-card', ['label' => 'Unallocated Credit', 'value' => $summary['credit'], 'hint' => 'Approved payment credit remaining'])
        </section>

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Shop Summary</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Opening, bills, expense, salary, received, credit and closing</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Shop', 'Opening', 'Bills', 'Expense', 'Salary', 'Received', 'Credit', 'Closing'] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($shops as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-sm font-black text-slate-950">
                                    <a href="{{ route('admin.finance-v2.shops.show', ['shop' => $row['shop'], 'date' => $dateParam]) }}" class="hover:underline">{{ $row['shop']->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['opening_balance'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['bills'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['expense'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['salary'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-700">Rs. {{ number_format((float) $row['received'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-cyan-700">Rs. {{ number_format((float) $row['credit'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) $row['closing_balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No shops for this client.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
