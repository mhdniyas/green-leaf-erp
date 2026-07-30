<x-layouts.accounting title="Finance V2 Reports">
    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Report Page</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Total received, total paid, salary, loan and balance</h2>
            <p class="mt-2 text-sm font-semibold text-slate-600">{{ $month_start->format('d M Y') }} to {{ $date->format('d M Y') }}</p>
        </section>

        <section class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily Report</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Main finance report table</h3>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Date', 'Total Received', 'Total Paid', 'Salary', 'Loan', 'Expense', 'Balance'] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($daily_rows as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['total_received'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['total_paid'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['salary'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['loan'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['expense'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) $row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No report rows found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Shop Balance Report</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Aishwarya Veg shop balances</h3>
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
                        @forelse($shop_rows as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $row['shop']->name }}</td>
                                @foreach(['opening_balance', 'bills', 'expense', 'salary', 'loan', 'received', 'credit', 'closing_balance'] as $field)
                                    <td class="px-4 py-3 text-right text-sm font-black text-slate-700">Rs. {{ number_format((float) $row[$field], 2) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No Aishwarya Veg shops found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
