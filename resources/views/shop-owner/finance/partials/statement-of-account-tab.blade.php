@php
    $openingBalance = (float) ($statement['openingBalance'] ?? 0);
    $closingBalance = (float) ($statement['closingBalance'] ?? 0);
    $totalDebits = (float) ($statement['totalDebits'] ?? 0);
    $totalCredits = (float) ($statement['totalCredits'] ?? 0);
    $totalInvoiced = (float) ($statement['totalInvoiced'] ?? 0);
    $totalAdjustments = (float) ($statement['totalAdjustments'] ?? 0);
    $totalPayments = (float) ($statement['totalPayments'] ?? 0);
    $entries = $statement['entries'] ?? collect();
@endphp

<div class="space-y-4">
    {{-- Statement Summary KPI Cards --}}
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5 sm:gap-3">
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Opening Balance</p>
            <p class="mt-1 text-sm font-black text-slate-800 font-mono">
                Rs. {{ number_format($openingBalance, 2) }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Invoiced Charges</p>
            <p class="mt-1 text-sm font-black text-slate-950 font-mono">
                Rs. {{ number_format($totalInvoiced, 2) }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Net Adjustments</p>
            <p class="mt-1 text-sm font-black font-mono {{ $totalAdjustments < 0 ? 'text-emerald-700' : ($totalAdjustments > 0 ? 'text-amber-700' : 'text-slate-600') }}">
                {{ $totalAdjustments < 0 ? '-Rs. ' . number_format(abs($totalAdjustments), 2) : ($totalAdjustments > 0 ? '+Rs. ' . number_format($totalAdjustments, 2) : 'Rs. 0.00') }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Payments</p>
            <p class="mt-1 text-sm font-black text-emerald-700 font-mono">
                Rs. {{ number_format($totalPayments, 2) }}
            </p>
        </div>

        <div class="col-span-2 sm:col-span-1 rounded-xl border border-rose-200 bg-white p-3 shadow-2xs">
            <p class="text-[9px] font-black uppercase tracking-wider text-rose-800">Closing Balance</p>
            <p class="mt-1 text-sm font-black font-mono {{ $closingBalance > 0.0001 ? 'text-rose-700' : 'text-emerald-700' }}">
                Rs. {{ number_format($closingBalance, 2) }}
            </p>
        </div>
    </div>

    {{-- Main Statement Ledger Container --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-base font-black text-slate-950 sm:text-lg">Statement of Account</h2>
                <p class="text-xs text-slate-500 font-medium">Chronological record of invoices, credit/debit adjustments, and payments.</p>
            </div>
            @if ($filterStartDate && $filterEndDate)
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                    {{ $filterStartDate->format('d M Y') }} &mdash; {{ $filterEndDate->format('d M Y') }}
                </span>
            @endif
        </div>

        @if ($entries->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center">
                <p class="text-sm font-black text-slate-800">No transactions recorded for this period.</p>
                <p class="mt-1 text-xs text-slate-500">Invoices, credit notes, and payments will appear chronologically here.</p>
            </div>
        @else
            {{-- Mobile Cards View --}}
            <div class="mt-3 space-y-2 md:hidden">
                {{-- Opening Balance Entry for Mobile --}}
                @if ($openingBalance != 0)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-bold flex justify-between items-center">
                        <span class="text-slate-600 uppercase tracking-wider text-[10px]">Opening Balance</span>
                        <span class="font-mono font-black text-slate-900">Rs. {{ number_format($openingBalance, 2) }}</span>
                    </div>
                @endif

                @foreach ($entries as $entry)
                    @php
                        $isDebit = (float) $entry['debit'] > 0;
                        $badgeTone = match ($entry['type']) {
                            'invoice' => 'bg-slate-100 text-slate-800 border-slate-200',
                            'credit_note' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            'debit_note' => 'bg-amber-50 text-amber-800 border-amber-200',
                            'payment' => 'bg-sky-50 text-sky-800 border-sky-200',
                            default => 'bg-slate-50 text-slate-700 border-slate-200',
                        };
                    @endphp
                    <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-black text-slate-900">{{ Carbon\Carbon::parse($entry['date'])->format('d M Y') }}</span>
                                    <span class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $badgeTone }}">
                                        {{ $entry['type_label'] }}
                                    </span>
                                </div>
                                <p class="mt-1 font-mono text-[11px] font-bold text-slate-700">{{ $entry['reference'] }}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">{{ $entry['description'] }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                @if ((float) $entry['debit'] > 0)
                                    <p class="text-xs font-black text-rose-700 font-mono">+Rs. {{ number_format((float) $entry['debit'], 2) }}</p>
                                @endif
                                @if ((float) $entry['credit'] > 0)
                                    <p class="text-xs font-black text-emerald-700 font-mono">-Rs. {{ number_format((float) $entry['credit'], 2) }}</p>
                                @endif
                                <p class="text-[10px] text-slate-400 mt-1">Bal: <span class="font-bold text-slate-800 font-mono">Rs. {{ number_format((float) $entry['balance'], 2) }}</span></p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop Ledger Table --}}
            <div class="mt-3 hidden overflow-x-auto md:block">
                <table class="min-w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Reference</th>
                            <th class="py-2.5 px-3">Description</th>
                            <th class="py-2.5 px-3 text-right">Charges (Debit)</th>
                            <th class="py-2.5 px-3 text-right">Credits</th>
                            <th class="py-2.5 px-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        {{-- Opening Balance Row --}}
                        <tr class="bg-slate-50/70 font-bold">
                            <td class="py-2 px-3 text-slate-500" colspan="4">
                                Opening Balance as of {{ $filterStartDate ? $filterStartDate->format('d M Y') : 'Beginning' }}
                            </td>
                            <td class="py-2 px-3 text-right font-mono text-slate-400">&mdash;</td>
                            <td class="py-2 px-3 text-right font-mono text-slate-400">&mdash;</td>
                            <td class="py-2 px-3 text-right font-mono font-black text-slate-900">
                                Rs. {{ number_format($openingBalance, 2) }}
                            </td>
                        </tr>

                        @foreach ($entries as $entry)
                            @php
                                $badgeTone = match ($entry['type']) {
                                    'invoice' => 'bg-slate-100 text-slate-800 border-slate-200',
                                    'credit_note' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    'debit_note' => 'bg-amber-50 text-amber-800 border-amber-200',
                                    'payment' => 'bg-sky-50 text-sky-800 border-sky-200',
                                    default => 'bg-slate-50 text-slate-700 border-slate-200',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">
                                    {{ Carbon\Carbon::parse($entry['date'])->format('d M Y') }}
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $badgeTone }}">
                                        {{ $entry['type_label'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-xs font-bold text-slate-700 whitespace-nowrap">
                                    @if ($entry['url'])
                                        <a href="{{ $entry['url'] }}" class="text-emerald-700 hover:text-emerald-900 hover:underline">
                                            {{ $entry['reference'] }}
                                        </a>
                                    @else
                                        {{ $entry['reference'] }}
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-slate-600">
                                    {{ $entry['description'] }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold {{ (float) $entry['debit'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                    {{ (float) $entry['debit'] > 0 ? 'Rs. ' . number_format((float) $entry['debit'], 2) : '&mdash;' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold {{ (float) $entry['credit'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ (float) $entry['credit'] > 0 ? 'Rs. ' . number_format((float) $entry['credit'], 2) : '&mdash;' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-black {{ (float) $entry['balance'] > 0.0001 ? 'text-slate-950' : 'text-emerald-700' }}">
                                    Rs. {{ number_format((float) $entry['balance'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-black">
                        <tr>
                            <td class="py-2.5 px-3 text-slate-900" colspan="4">Closing Balance</td>
                            <td class="py-2.5 px-3 text-right font-mono text-rose-700">Rs. {{ number_format($totalDebits, 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-emerald-700">Rs. {{ number_format($totalCredits, 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-base {{ $closingBalance > 0.0001 ? 'text-rose-700' : 'text-emerald-700' }}">
                                Rs. {{ number_format($closingBalance, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>
</div>

