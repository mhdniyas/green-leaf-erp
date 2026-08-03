@extends('purchase-manager.layouts.app')

@section('title', 'Flagged Calculation Bills')
@section('page_title', 'Flagged Calculation Bills')
@section('page_description', 'Review and recalculate supplier bills flagged with calculation discrepancies.')

@section('content')
    <div class="space-y-5">
        <section class="purchase-manager-panel p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-rose-100 text-rose-700 font-bold text-xs">⚠️</span>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-rose-700">Calculation Error Audit</p>
                    </div>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Flagged Supplier Bills</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">
                        Bills with mismatches between gross line items sum and stored bill amount. Flagged bills can only be updated or fixed by an Admin.
                    </p>
                </div>

                {{-- Filter Form --}}
                <form action="{{ route('purchasing.invoices.flagged') }}" method="GET" class="grid gap-3 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_160px_auto]">
                    <div>
                        <label for="search" class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Search</label>
                        <input id="search" name="search" value="{{ $search }}" placeholder="Supplier or bill number" class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="purchaser_id" class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Purchaser</label>
                        <select id="purchaser_id" name="purchaser_id" class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                            <option value="">All Purchasers</option>
                            @foreach ($purchasers as $purchaser)
                                <option value="{{ $purchaser->id }}" @selected($selectedPurchaser === $purchaser->id)>
                                    {{ $purchaser->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="date" class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Date</label>
                        <input id="date" type="date" name="date" value="{{ $selectedDate }}" class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="h-10 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">
                            Filter
                        </button>
                        <a href="{{ route('purchasing.invoices.flagged') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-black text-slate-700 hover:bg-slate-50">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Summary Cards --}}
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Flagged Bills</p>
                    <p class="mt-1.5 text-2xl font-black text-rose-950">{{ number_format($flaggedInvoices->count()) }}</p>
                    <p class="mt-1 text-xs font-semibold text-rose-700">Require Admin recalculation</p>
                </div>

                @php
                    $totalDiscrepancy = $flaggedInvoices->sum(fn ($inv) => abs($inv->itemsGrossTotal() - (float)$inv->amount));
                @endphp
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-800">Total Discrepancy Amount</p>
                    <p class="mt-1.5 text-2xl font-black text-amber-950">₹{{ number_format($totalDiscrepancy, 2) }}</p>
                    <p class="mt-1 text-xs font-semibold text-amber-800">Combined variance</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Security Restraint</p>
                    <p class="mt-1.5 text-sm font-black text-slate-900">Admin Only Access</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Non-admin bill updates blocked</p>
                </div>
            </div>
        </section>

        {{-- Flagged Bills Table --}}
        <section class="purchase-manager-panel overflow-hidden">
            @if ($flaggedInvoices->isEmpty())
                <div class="p-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 font-black text-lg">✓</div>
                    <h3 class="mt-3 text-sm font-black text-slate-900">No Flagged Bills Found</h3>
                    <p class="mt-1 text-xs font-semibold text-slate-500">All matching bills have accurate calculations matching line item totals.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs font-semibold text-slate-800">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-3.5">Bill No / Date</th>
                                <th class="px-5 py-3.5">Purchaser</th>
                                <th class="px-5 py-3.5">Supplier</th>
                                <th class="px-5 py-3.5">Gross Item Total</th>
                                <th class="px-5 py-3.5">Stored Bill Amount</th>
                                <th class="px-5 py-3.5">Discount</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($flaggedInvoices as $invoice)
                                @php
                                    $grossTotal = $invoice->itemsGrossTotal();
                                    $storedAmount = (float) $invoice->amount;
                                    $diff = $grossTotal - $storedAmount;
                                    $purchaserName = $invoice->purchaserSubmittedBy?->name ?? $invoice->purchaserCart?->user?->name ?? 'Purchaser pending';
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-5 py-4 font-mono font-bold text-slate-950">
                                        <a href="{{ route('purchasing.invoices.show', $invoice) }}" class="text-indigo-600 hover:underline">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                        <div class="text-[10px] font-semibold text-slate-400">{{ $invoice->created_at?->format('d M Y, h:i A') }}</div>
                                    </td>
                                    <td class="px-5 py-4 font-bold text-slate-800">
                                        {{ $purchaserName }}
                                    </td>
                                    <td class="px-5 py-4 font-bold text-slate-900">
                                        {{ $invoice->supplier?->name ?? 'Supplier pending' }}
                                    </td>
                                    <td class="px-5 py-4 font-black text-emerald-700">
                                        ₹{{ number_format($grossTotal, 2) }}
                                    </td>
                                    <td class="px-5 py-4 font-black text-rose-700">
                                        ₹{{ number_format($storedAmount, 2) }}
                                        <span class="block text-[10px] font-semibold text-rose-500">(Diff: {{ $diff > 0 ? '+' : '' }}₹{{ number_format($diff, 2) }})</span>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-700">
                                        ₹{{ number_format((float) $invoice->discount_amount, 2) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-lg bg-rose-100 px-2.5 py-1 text-[10px] font-black uppercase text-rose-800 border border-rose-200">
                                            🔒 Admin Only
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('purchasing.invoices.show', $invoice) }}" class="inline-flex h-8 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 hover:bg-slate-50">
                                                View
                                            </a>
                                            @if (auth()->user()?->hasRole('admin'))
                                                <form action="{{ route('purchasing.invoices.fix-calculation', $invoice) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="inline-flex h-8 items-center justify-center gap-1 rounded-xl bg-rose-700 px-3.5 text-xs font-black text-white hover:bg-rose-800 shadow-xs">
                                                        <span>Fix & Recalculate</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
