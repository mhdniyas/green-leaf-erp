<x-layouts.app title="Warehouse Sales">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-4 py-3 lg:max-w-4xl lg:px-4 lg:py-4">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-1">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">Warehouse Sales</h1>
                </div>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">Fast direct sales &amp; instant invoicing.</p>
            </div>

            <a href="{{ route('warehouse.sales.create', ['warehouse_id' => $selectedWarehouseId, 'date' => $date]) }}"
               class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 px-5 py-3 text-xs sm:text-sm font-black text-white shadow-md shadow-emerald-600/20 transition active:scale-95 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>+ NEW SALE</span>
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">✕</button>
            </div>
        @endif

        <!-- Filter & Date Navigation -->
        <form method="GET" action="{{ route('warehouse.sales.index') }}" class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <!-- Warehouse Selector -->
                <div class="flex-1 min-w-[180px]">
                    <label for="warehouse_id" class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Selling Warehouse</label>
                    <select id="warehouse_id" name="warehouse_id" onchange="this.form.submit()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                        @foreach($allowedWarehouses as $wh)
                            <option value="{{ $wh->id }}" @selected((int)$selectedWarehouseId === (int)$wh->id)>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Selector with Prev / Next -->
                <div class="flex items-end gap-1.5">
                    <a href="{{ route('warehouse.sales.index', ['warehouse_id' => $selectedWarehouseId, 'date' => $prevDate]) }}"
                       class="h-10 w-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 transition"
                       title="Previous Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </a>

                    <div>
                        <label for="date" class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Business Date</label>
                        <input id="date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                               class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                    </div>

                    <a href="{{ route('warehouse.sales.index', ['warehouse_id' => $selectedWarehouseId, 'date' => $nextDate]) }}"
                       class="h-10 w-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 transition"
                       title="Next Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </div>
            </div>
        </form>

        <!-- Summary KPIs (Mobile-friendly) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-2xs">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Sales</span>
                <span class="text-lg font-black text-slate-900 font-mono mt-0.5 block">₹{{ number_format($totalSalesAmount, 2) }}</span>
                <span class="text-[10px] text-slate-500 font-semibold">{{ $confirmedCount }} {{ \Illuminate\Support\Str::plural('Invoice', $confirmedCount) }}</span>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-3.5 shadow-2xs">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800 block">Cash Sales</span>
                <span class="text-lg font-black text-emerald-950 font-mono mt-0.5 block">₹{{ number_format($cashSalesAmount, 2) }}</span>
                <span class="text-[10px] text-emerald-700 font-semibold">Collected Cash</span>
            </div>

            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-3.5 shadow-2xs">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-800 block">Online / Other</span>
                <span class="text-lg font-black text-indigo-950 font-mono mt-0.5 block">₹{{ number_format($otherSalesAmount, 2) }}</span>
                <span class="text-[10px] text-indigo-700 font-semibold">UPI, Card, Bank</span>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-2xs">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Current Date</span>
                <span class="text-sm font-black text-slate-800 mt-1 block">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
                <span class="text-[10px] text-slate-400 font-semibold">{{ \Carbon\Carbon::parse($date)->format('l') }}</span>
            </div>
        </div>

        <!-- Sales Card Feed -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">
                    Today's Sales ({{ $sales->count() }})
                </h2>
                <span class="text-[11px] font-semibold text-slate-400 font-mono">
                    {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}
                </span>
            </div>

            @if($sales->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-8 text-center space-y-3">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-black text-slate-800">No warehouse sales recorded for this date.</p>
                        <p class="mt-1 text-xs text-slate-400">Tap "+ NEW SALE" above to create an instant sale.</p>
                    </div>
                    <a href="{{ route('warehouse.sales.create', ['warehouse_id' => $selectedWarehouseId, 'date' => $date]) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 transition">
                        + New Sale
                    </a>
                </div>
            @else
                <div class="space-y-2.5">
                    @foreach($sales as $sale)
                        @php
                            $payment = $sale->primaryPayment();
                            $isCash = $sale->isCashSale();
                            $isCancelled = $sale->isCancelled();
                        @endphp
                        <a href="{{ route('warehouse.sales.show', $sale) }}"
                           class="block rounded-2xl border transition p-4 bg-white shadow-2xs hover:border-emerald-300 hover:shadow-sm {{ $isCancelled ? 'border-rose-200 bg-rose-50/20 opacity-75' : 'border-slate-200' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono text-xs font-black text-slate-900 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                                            {{ $sale->invoice_number }}
                                        </span>
                                        @if($isCancelled)
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-rose-100 text-rose-800 border border-rose-200">
                                                Cancelled
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                Confirmed
                                            </span>
                                        @endif
                                        <span class="text-[11px] font-semibold text-slate-400">
                                            {{ $sale->created_at?->timezone('Asia/Kolkata')->format('h:i A') }}
                                        </span>
                                    </div>

                                    <div class="text-sm font-bold text-slate-900 truncate">
                                        {{ $sale->customer_name_snapshot }}
                                        @if($sale->customer_phone_snapshot)
                                            <span class="text-xs font-mono font-normal text-slate-400">· {{ $sale->customer_phone_snapshot }}</span>
                                        @endif
                                    </div>

                                    <!-- Items Preview -->
                                    <div class="text-xs text-slate-500 flex items-center gap-2 flex-wrap">
                                        <span>{{ $sale->items->count() }} {{ \Illuminate\Support\Str::plural('item', $sale->items->count()) }}</span>
                                        <span>·</span>
                                        <span class="truncate max-w-[200px] sm:max-w-xs">
                                            {{ $sale->items->map(fn ($i) => ($i->product?->name ?? 'Item') . ' (' . (float)$i->entered_qty . ' ' . $i->entered_unit . ')')->join(', ') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="text-right shrink-0 space-y-1">
                                    <div class="text-base sm:text-lg font-black font-mono text-slate-950">
                                        ₹{{ number_format((float)$sale->total_amount, 2) }}
                                    </div>

                                    <div>
                                        @if($isCash)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                <span>Cash</span>
                                                <span class="text-[9px] font-medium text-emerald-600">({{ $sale->isUserHeldCash() ? ($payment?->moneyHolderUser?->name ?? 'User') : 'Company' }})</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 uppercase">
                                                {{ $payment?->payment_method ?? 'Payment' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</x-layouts.app>
