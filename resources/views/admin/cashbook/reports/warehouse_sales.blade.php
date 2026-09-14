@extends('admin.cashbook.layouts.app')

@section('title', 'Warehouse Sales Report — Cashbook')

@section('header_title')
    <i data-lucide="store" class="w-5 h-5 text-emerald-600"></i> Warehouse Sales Report
@endsection

@section('header_subtitle')
    Financial &amp; operational overview of direct warehouse sales, cash holders, and stock movements.
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-5"
         x-data="{
            detailModalOpen: false,
            currentSale: null,
            openDetail(sale) {
                this.currentSale = sale;
                this.detailModalOpen = true;
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            }
         }">

        <!-- 1. Top KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2.5">
            <!-- Total Sales -->
            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Sales</div>
                <div class="mt-1 text-base sm:text-lg font-black font-mono text-slate-950">
                    ₹{{ number_format((float) $summary['total_sales_amount'], 2) }}
                </div>
                <div class="text-[10px] text-slate-500 font-semibold">{{ $summary['invoice_count'] }} {{ \Illuminate\Support\Str::plural('Invoice', $summary['invoice_count']) }}</div>
            </div>

            <!-- Cash Sales -->
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-3.5 shadow-xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Cash Sales</div>
                <div class="mt-1 text-base sm:text-lg font-black font-mono text-emerald-950">
                    ₹{{ number_format((float) $summary['cash_sales_amount'], 2) }}
                </div>
                <div class="text-[10px] text-emerald-700 font-semibold">Physical Cash</div>
            </div>

            <!-- Online Sales -->
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-3.5 shadow-xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-indigo-800">Online Sales</div>
                <div class="mt-1 text-base sm:text-lg font-black font-mono text-indigo-950">
                    ₹{{ number_format((float) $summary['online_sales_amount'], 2) }}
                </div>
                <div class="text-[10px] text-indigo-700 font-semibold">UPI, Card, Bank</div>
            </div>

            <!-- Credit Sales -->
            <div class="rounded-2xl border border-purple-200 bg-purple-50/50 p-3.5 shadow-xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-purple-800">Credit Sales</div>
                <div class="mt-1 text-base sm:text-lg font-black font-mono text-purple-950">
                    ₹{{ number_format((float) $summary['credit_sales_amount'], 2) }}
                </div>
                <div class="text-[10px] text-purple-700 font-semibold">Customer Due</div>
            </div>

            <!-- Company Cash Holding -->
            <div class="rounded-2xl border border-emerald-300 bg-emerald-100/40 p-3.5 shadow-xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-emerald-900">Company Holding</div>
                <div class="mt-1 text-base sm:text-lg font-black font-mono text-emerald-950">
                    ₹{{ number_format((float) $summary['company_cash_holding'], 2) }}
                </div>
                <div class="text-[10px] text-emerald-800 font-semibold">In Company Cash</div>
            </div>

            <!-- User Cash Holding -->
            <div class="rounded-2xl border border-amber-300 bg-amber-100/40 p-3.5 shadow-xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-amber-900">User Holding</div>
                <div class="mt-1 text-base sm:text-lg font-black font-mono text-amber-950">
                    ₹{{ number_format((float) $summary['user_cash_holding'], 2) }}
                </div>
                <div class="text-[10px] text-amber-800 font-semibold">Held with Staff</div>
            </div>

            <!-- Total Invoices -->
            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Count</div>
                <div class="mt-1 text-base sm:text-lg font-black font-mono text-slate-900">
                    {{ $summary['invoice_count'] }}
                </div>
                <div class="text-[10px] text-slate-400 font-semibold">Confirmed entries</div>
            </div>
        </div>

        <!-- 2. Filters Bar -->
        <form method="GET" action="{{ route('admin.cashbook.warehouse-sales') }}"
              class="rounded-3xl border border-slate-200 bg-white p-4 shadow-xs space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Search -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Search Invoice / Customer</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="WS-..., Customer name, phone..."
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>

                <!-- Warehouse Filter -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Warehouse</label>
                    <select name="warehouse_id" onchange="this.form.submit()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected((int)$selectedWarehouseId === (int)$wh->id)>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Customer Filter -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Customer</label>
                    <select name="customer_id" onchange="this.form.submit()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                        <option value="">All Customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected((int)$selectedCustomerId === (int)$c->id)>
                                {{ $c->name }} {{ $c->phone ? "({$c->phone})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Sold By User -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Sold By</label>
                    <select name="sold_by_user_id" onchange="this.form.submit()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((int)$selectedSoldByUserId === (int)$u->id)>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Second Row Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2 border-t border-slate-100 items-end">
                <!-- Date / Date Range -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Single Date</label>
                    <input type="date" name="date" value="{{ $date }}"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>

                <!-- Payment Method -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Payment Method</label>
                    <select name="payment_method" onchange="this.form.submit()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none cursor-pointer">
                        <option value="">All Payment Methods</option>
                        <option value="cash" @selected($selectedPaymentMethod === 'cash')>Cash</option>
                        <option value="upi" @selected($selectedPaymentMethod === 'upi')>UPI</option>
                        <option value="card" @selected($selectedPaymentMethod === 'card')>Card</option>
                        <option value="bank" @selected($selectedPaymentMethod === 'bank')>Bank</option>
                        <option value="credit" @selected($selectedPaymentMethod === 'credit')>Credit</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <button type="submit"
                            class="flex-1 h-10 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-black transition shadow-xs cursor-pointer">
                        Filter
                    </button>
                    <a href="{{ route('admin.cashbook.warehouse-sales') }}"
                       class="h-10 px-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 text-xs font-bold transition flex items-center justify-center">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- 3. Main Report Table -->
        <div class="rounded-3xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="p-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Warehouse Sales Ledger</h2>
                    <p class="text-xs text-slate-500">Trace every sale to invoice, customer, payment, money holder, and inventory movements.</p>
                </div>
                <div class="text-xs font-bold text-slate-500">
                    Showing <span class="font-black text-slate-900">{{ $sales->count() }}</span> records
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500 select-none">
                            <th scope="col" class="py-3 px-3 text-center w-12">#</th>
                            <th scope="col" class="py-3 px-3">Invoice</th>
                            <th scope="col" class="py-3 px-3">Date &amp; Time</th>
                            <th scope="col" class="py-3 px-3">Customer</th>
                            <th scope="col" class="py-3 px-3">Warehouse</th>
                            <th scope="col" class="py-3 px-3">Sold By</th>
                            <th scope="col" class="py-3 px-3 text-center">Items</th>
                            <th scope="col" class="py-3 px-4 text-right">Total Amount</th>
                            <th scope="col" class="py-3 px-3">Payment</th>
                            <th scope="col" class="py-3 px-3">Money Holder</th>
                            <th scope="col" class="py-3 px-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($sales as $idx => $sale)
                            @php
                                $payment = $sale->primaryPayment();
                                $isCash = $sale->isCashSale();
                                $isCancelled = $sale->isCancelled();
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors {{ $isCancelled ? 'bg-rose-50/30' : '' }}">
                                <!-- Sl No -->
                                <td class="py-3 px-3 text-center font-mono text-slate-400 font-bold">
                                    {{ $idx + 1 }}
                                </td>

                                <!-- Invoice -->
                                <td class="py-3 px-3">
                                    <div class="font-mono font-black text-slate-900 flex items-center gap-1.5">
                                        <a href="{{ route('admin.cashbook.warehouse-sales.show', $sale) }}"
                                           class="hover:text-emerald-700 hover:underline">
                                            {{ $sale->invoice_number }}
                                        </a>
                                        @if($isCancelled)
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-rose-100 text-rose-800">Cancelled</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Date & Time -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $sale->business_date->format('d M Y') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $sale->created_at?->timezone('Asia/Kolkata')->format('h:i A') }}</div>
                                </td>

                                <!-- Customer -->
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900">{{ $sale->customer_name_snapshot }}</div>
                                    @if($sale->customer_phone_snapshot)
                                        <div class="text-[10px] font-mono text-slate-400">{{ $sale->customer_phone_snapshot }}</div>
                                    @endif
                                </td>

                                <!-- Warehouse -->
                                <td class="py-3 px-3 font-semibold text-slate-700 whitespace-nowrap">
                                    {{ $sale->warehouse?->name ?? '—' }}
                                </td>

                                <!-- Sold By -->
                                <td class="py-3 px-3 font-semibold text-slate-700 whitespace-nowrap">
                                    {{ $sale->soldBy?->name ?? '—' }}
                                </td>

                                <!-- Items Count -->
                                <td class="py-3 px-3 text-center font-bold font-mono text-slate-600">
                                    {{ $sale->items->count() }}
                                </td>

                                <!-- Total Amount -->
                                <td class="py-3 px-4 text-right font-mono font-black text-slate-950 text-sm whitespace-nowrap">
                                    ₹{{ number_format((float) $sale->total_amount, 2) }}
                                </td>

                                <!-- Payment Method -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($isCash)
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            Cash
                                        </span>
                                    @elseif(strtolower((string)$payment?->payment_method) === 'credit')
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-black bg-purple-100 text-purple-800 border border-purple-200">
                                            Credit
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-black bg-indigo-100 text-indigo-800 border border-indigo-200 uppercase">
                                            {{ $payment?->payment_method ?? 'Payment' }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Money Holder -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($isCash)
                                        @if($sale->isUserHeldCash())
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-200">
                                                <i data-lucide="user" class="w-3 h-3 text-amber-700"></i>
                                                <span>{{ $payment?->moneyHolderUser?->name ?? 'User Held' }}</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-200">
                                                <i data-lucide="building" class="w-3 h-3 text-emerald-700"></i>
                                                <span>Company Direct</span>
                                            </span>
                                        @endif
                                    @elseif(strtolower((string)$payment?->payment_method) === 'credit')
                                        <span class="text-slate-400 font-semibold text-[11px]">Customer Due</span>
                                    @else
                                        <span class="text-slate-600 font-semibold text-[11px]">Company Account</span>
                                    @endif
                                </td>

                                <!-- Action -->
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <a href="{{ route('admin.cashbook.warehouse-sales.show', $sale) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition shadow-2xs">
                                        <i data-lucide="eye" class="w-3 h-3 text-slate-500"></i>
                                        <span>View</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-12 text-center text-slate-400 font-semibold">
                                    No warehouse sales records found matching the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
