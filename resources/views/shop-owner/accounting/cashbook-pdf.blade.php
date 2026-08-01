<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashbook Receipt {{ $selectedDate->format('d M Y') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950">
    @php
        $calculatedClosing = (float) ($receiptSummary['entered_closing'] ?? $receiptSummary['expected_closing']);
        $receiptRows = [
            ['label' => 'Opening Cash', 'amount' => $receiptSummary['opening_balance'], 'type' => 'Opening'],
            ['label' => 'Cash From Sales', 'amount' => $receiptSummary['cash_credit'], 'type' => 'Cash In'],
            ['label' => 'Cash From Company', 'amount' => $receiptSummary['cash_given_to_shop'], 'type' => 'Cash In'],
            ['label' => 'Online Payment', 'amount' => $receiptSummary['non_cash_income'], 'type' => 'Non Cash'],
            ['label' => 'Cash Paid To Company', 'amount' => $receiptSummary['payment_to_company'], 'type' => 'Cash Out'],
            ['label' => 'Cash Debit', 'amount' => $receiptSummary['cash_debit'], 'type' => 'Cash Out'],
            ['label' => 'Expected Closing', 'amount' => $receiptSummary['expected_closing'], 'type' => 'Closing'],
            ['label' => 'Closing Cash', 'amount' => $calculatedClosing, 'type' => 'Closing'],
        ];
    @endphp

    <div class="mx-auto max-w-3xl p-3 sm:p-8">
        <div class="mb-4 flex items-center justify-between gap-3 print:hidden">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Cashbook PDF View</p>
                <h1 class="mt-1 text-xl font-black text-slate-950">Cashbook Receipt</h1>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white">
                Print / Save PDF
            </button>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:rounded-none print:border-slate-300 print:shadow-none">
            <header class="border-b border-dashed border-slate-400 px-4 py-4 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Daily Cashbook Receipt</p>
                <h2 class="mt-1 text-2xl font-black uppercase tracking-wide text-slate-950">{{ $shop->name }}</h2>
                <p class="mt-1 text-xs font-bold text-slate-600">{{ $selectedDate->format('d F Y') }} · CB-{{ $selectedDate->format('Ymd') }}</p>
            </header>

            <div class="grid grid-cols-3 gap-2 border-b border-dashed border-slate-400 p-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Opening</p>
                    <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Net Sale</p>
                    <p class="mt-1 text-lg font-black {{ (float) $receiptSummary['daily_net_sale'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format((float) $receiptSummary['daily_net_sale'], 2) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Closing</p>
                    <p class="mt-1 text-lg font-black {{ $calculatedClosing < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($calculatedClosing, 2) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto p-4">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead class="border-b border-dashed border-slate-400 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Particulars</th>
                            <th class="py-2 pr-3">Type</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($receiptRows as $row)
                            <tr>
                                <td class="py-2 pr-3 font-bold text-slate-950">{{ $row['label'] }}</td>
                                <td class="py-2 pr-3 text-xs font-semibold text-slate-600">{{ $row['type'] }}</td>
                                <td class="py-2 text-right font-black text-slate-950">Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($entry?->lines?->isNotEmpty())
                <div class="border-t border-dashed border-slate-400 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Manual Entry Lines</p>
                    <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Type</th>
                                    <th class="px-3 py-2">Category</th>
                                    <th class="px-3 py-2">Note</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($entry->lines as $line)
                                    <tr>
                                        <td class="px-3 py-2 font-black uppercase text-slate-700">{{ $line->type }}</td>
                                        <td class="px-3 py-2 font-bold text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</td>
                                        <td class="px-3 py-2 font-semibold text-slate-600">{{ $line->description ?: 'No note' }}</td>
                                        <td class="px-3 py-2 text-right font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </div>
</body>
</html>
