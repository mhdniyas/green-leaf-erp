<x-layouts.accounting title="Purchasers Ledger">
    @php
        $sortDirection = $direction === 'desc' ? 'asc' : 'desc';
        $sortableHeaders = [
            'name' => 'Purchaser',
            'total_in' => 'Total In',
            'total_out' => 'Total Out',
            'balance' => 'Balance',
        ];
        $currentUser = auth()->user();
        $canBuyAsPurchaser = $currentUser?->hasRole('admin') && $currentUser->hasRole('purchaser');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-6">
        <section class="rounded-[1.9rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Purchasers Ledger</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Purchaser accounts</h1>
                    <p class="mt-3 max-w-3xl text-sm font-semibold leading-6 text-slate-600">Track advance cash, invoice outflow, and current balances in a table layout that fits the accounting dashboard.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" data-export="excel" class="inline-flex h-11 items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                        Export Excel
                    </button>
                    <button type="button" data-export="pdf" class="inline-flex h-11 items-center rounded-2xl border border-cyan-200 bg-cyan-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-cyan-700 transition hover:bg-cyan-100">
                        Export PDF
                    </button>
                    <a href="{{ route('admin.accounting.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-[1.9rem] border border-slate-200 bg-white shadow-sm"
            data-purchasers-export
            data-export-table-id="purchasers-table"
            data-export-title="Purchasers Ledger"
            data-export-filename="purchasers-ledger"
        >
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Ledger Table</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Purchaser summary by account</h2>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-500">
                    {{ number_format($purchasers->count()) }} purchaser(s)
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="purchasers-table" class="min-w-full table-auto text-left">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">
                        <tr>
                            @foreach($sortableHeaders as $column => $label)
                                <th class="px-4 py-3 text-left">
                                    <a href="{{ route('admin.accounting.purchasers.index', ['sort' => $column, 'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center gap-1 hover:text-white">
                                        <span>{{ $label }}</span>
                                        @if($sort === $column)
                                            <span class="text-[9px]">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </a>
                                </th>
                            @endforeach
                            <th class="px-4 py-3 text-right">Add Credit</th>
                            @if($canBuyAsPurchaser)
                                <th class="px-4 py-3 text-right">Buy</th>
                            @endif
                            <th class="px-4 py-3 text-right">Ledger</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($purchasers as $row)
                            @php
                                $purchaser = $row['purchaser'];
                                $totalIn = (float) $row['total_in'];
                                $totalOut = (float) $row['total_out'];
                                $balance = (float) $row['balance'];
                                $canBuyThisPurchaser = $canBuyAsPurchaser && $purchaser->is($currentUser);
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-4 py-4">
                                    <p class="font-black text-slate-950">{{ $purchaser->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $purchaser->email }}</p>
                                </td>
                                <td class="px-4 py-4 text-right font-black text-emerald-700">Rs. {{ number_format($totalIn, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-rose-700">Rs. {{ number_format($totalOut, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black {{ $balance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($balance, 2) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.accounting.purchasers.show', $purchaser->public_uuid) }}" class="inline-flex h-9 items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                                        Add Credit
                                    </a>
                                </td>
                                @if($canBuyAsPurchaser)
                                    <td class="px-4 py-4 text-right">
                                        @if($canBuyThisPurchaser)
                                            <form method="POST" action="{{ route('admin.accounting.purchasers.buy', $purchaser->public_uuid) }}" class="inline-flex">
                                                @csrf
                                                <button type="submit" class="inline-flex h-9 items-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-cyan-700 transition hover:bg-cyan-100">
                                                    Buy
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs font-bold text-slate-300">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.accounting.purchasers.show', $purchaser->public_uuid) }}" class="inline-flex h-9 items-center rounded-xl border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-50">
                                        Open Ledger
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canBuyAsPurchaser ? 7 : 6 }}" class="px-4 py-12 text-center text-sm font-bold text-slate-500">
                                    No users with the 'purchaser' role were found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
                        <tr class="font-black text-slate-950">
                            <td class="px-4 py-4">Total</td>
                            <td class="px-4 py-4 text-right text-emerald-700">Rs. {{ number_format($totals['total_in'], 2) }}</td>
                            <td class="px-4 py-4 text-right text-rose-700">Rs. {{ number_format($totals['total_out'], 2) }}</td>
                            <td class="px-4 py-4 text-right {{ $totals['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($totals['balance'], 2) }}</td>
                            <td class="px-4 py-4"></td>
                            @if($canBuyAsPurchaser)
                                <td class="px-4 py-4"></td>
                            @endif
                            <td class="px-4 py-4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>

    <script src="{{ asset('js/accounting-purchasers-export.js') }}" defer></script>
</x-layouts.accounting>
