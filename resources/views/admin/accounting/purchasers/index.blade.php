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
        $activeReportTab = $reportFilters['tab'] ?? 'cash';
        $reportQuery = [
            'from_date' => $reportFilters['from_date'] ?? now()->startOfMonth()->toDateString(),
            'to_date' => $reportFilters['to_date'] ?? now()->toDateString(),
            'purchaser_id' => $reportFilters['purchaser_id'] ?? null,
            'category' => $reportFilters['category'] ?? '',
        ];
        $reportTabs = [
            'cash' => 'Cash Flow',
            'procurement' => 'Procurement Expenses',
            'other' => 'Other Expenses',
            'summary' => 'Summary',
        ];
        $expenseCategories = \App\Models\ProcurementExpense::categories();
        $otherExpenseCategories = \App\Models\OtherExpense::categories();
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-6">
        <section class="rounded-[1.9rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Purchasers Ledger</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Purchaser Accounts & Cash Distribution</h1>
                    <p class="mt-3 max-w-3xl text-sm font-semibold leading-6 text-slate-600">Track advance cash distribution, daily paid purchases, and active holding balances across all purchaser accounts.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" data-export="excel" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-100">
                        Export Excel
                    </button>
                    <button type="button" data-export="pdf" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-50">
                        Export PDF
                    </button>
                    <a href="{{ route('admin.accounting.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </section>

        {{-- Cash Distribution KPI Overview Cards --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            {{-- Total Cash Distributed --}}
            <div class="relative overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white p-5 text-slate-950 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Total Cash Distributed</p>
                        <p class="mt-2 text-2xl font-black sm:text-3xl">Rs. {{ number_format($totals['total_in'], 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Total advances handed to purchasers</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100">
                        <svg class="h-5 w-5 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Total Daily Paid Purchases --}}
            <div class="relative overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white p-5 text-slate-950 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Daily Paid Purchases</p>
                        <p class="mt-2 text-2xl font-black sm:text-3xl">Rs. {{ number_format($totals['total_out'], 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Cash spent by purchasers on daily orders</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100">
                        <svg class="h-5 w-5 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Active Purchaser Holding Balance --}}
            <div class="relative overflow-hidden rounded-[1.6rem] border border-slate-200 bg-slate-950 p-5 text-white shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300">Purchaser Cash Balance</p>
                        <p class="mt-2 text-2xl font-black sm:text-3xl">Rs. {{ number_format($totals['balance'], 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-300">{{ $totals['balance'] >= 0 ? 'Net cash held by purchasers' : 'Purchaser deficit' }}</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/20">
                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        @if (($billUpdateRequests ?? collect())->isNotEmpty())
            <section class="overflow-hidden rounded-[1.9rem] border border-amber-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-amber-100 bg-amber-50 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Purchaser Bill Access</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Old bill update approvals</h2>
                        <p class="mt-1 text-sm font-semibold text-amber-800">Approve access when a purchaser needs to update bill details or correct the purchase date from mobile.</p>
                    </div>
                    <span class="rounded-2xl bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-amber-700">
                        {{ $billUpdateRequests->where('status', 'pending')->count() }} pending
                    </span>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($billUpdateRequests as $accessRequest)
                        <div class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-black text-slate-950">{{ $accessRequest->requestedBy?->name ?: 'Purchaser' }}</p>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] {{ $accessRequest->status === 'pending' ? 'bg-amber-100 text-amber-700' : ($accessRequest->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $accessRequest->status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                    Bill {{ $accessRequest->invoice?->invoice_number ?: 'Pending' }}
                                    @if ($accessRequest->cart)
                                        • Cart {{ $accessRequest->cart->cart_number }}
                                    @endif
                                    @if ($accessRequest->cart?->supplier)
                                        • {{ $accessRequest->cart->supplier->name }}
                                    @endif
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    Current {{ $accessRequest->current_business_date?->format('d M Y') ?: 'not set' }}
                                    @if ($accessRequest->requested_business_date)
                                        • Requested {{ $accessRequest->requested_business_date->format('d M Y') }}
                                    @endif
                                </p>
                                <p class="mt-2 whitespace-pre-line text-xs font-semibold text-slate-700">{{ $accessRequest->reason }}</p>
                            </div>

                            @if ($accessRequest->status === 'pending')
                                <div class="grid gap-2">
                                    <form method="POST" action="{{ route('admin.accounting.purchaser-bill-update-requests.approve', $accessRequest) }}" class="grid gap-2">
                                        @csrf
                                        <input type="text" name="review_note" placeholder="Approval note" class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:outline-none">
                                        <button type="submit" class="h-9 rounded-xl bg-emerald-600 text-xs font-black text-white hover:bg-emerald-500">Approve 24h Access</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.accounting.purchaser-bill-update-requests.reject', $accessRequest) }}">
                                        @csrf
                                        <button type="submit" class="h-9 w-full rounded-xl border border-slate-200 bg-white text-xs font-black text-slate-700 hover:bg-slate-50">Reject</button>
                                    </form>
                                </div>
                            @else
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-600">
                                    Reviewed by {{ $accessRequest->reviewedBy?->name ?: 'admin' }}
                                    @if ($accessRequest->reviewed_at)
                                        on {{ $accessRequest->reviewed_at->format('d M Y h:i A') }}
                                    @endif
                                    @if ($accessRequest->expires_at && $accessRequest->status === 'approved')
                                        <br>Expires {{ $accessRequest->expires_at->format('d M Y h:i A') }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

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
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($totalIn, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($totalOut, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($balance, 2) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.accounting.purchasers.show', $purchaser->public_uuid) }}" class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-50">
                                        Add Credit
                                    </a>
                                </td>
                                @if($canBuyAsPurchaser)
                                    <td class="px-4 py-4 text-right">
                                        @if($canBuyThisPurchaser)
                                            <form method="POST" action="{{ route('admin.accounting.purchasers.buy', $purchaser->public_uuid) }}" class="inline-flex">
                                                @csrf
                                                <button type="submit" class="inline-flex h-9 items-center rounded-xl border border-blue-200 bg-blue-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-blue-700 transition hover:bg-blue-100">
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
                            <td class="px-4 py-4 text-right text-slate-950">Rs. {{ number_format($totals['total_in'], 2) }}</td>
                            <td class="px-4 py-4 text-right text-slate-950">Rs. {{ number_format($totals['total_out'], 2) }}</td>
                            <td class="px-4 py-4 text-right text-slate-950">Rs. {{ number_format($totals['balance'], 2) }}</td>
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

        <section id="purchaser-reports-section" class="overflow-hidden rounded-[1.9rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-5 text-white">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-300">Purchaser Reports</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight">Cash flow details</h2>
                        <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-300">Every row shows who moved money, how the bill was paid, and what is still pending.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.accounting.purchasers.index') }}" data-purchaser-report-form class="grid gap-2 rounded-2xl bg-white/10 p-2 sm:grid-cols-2 lg:grid-cols-5">
                        <input type="hidden" name="report_tab" value="{{ $activeReportTab }}">
                        <label>
                            <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-300">From</span>
                            <input type="date" name="from_date" value="{{ $reportQuery['from_date'] }}" class="mt-1 h-10 w-full rounded-xl border border-white/10 bg-white px-3 text-xs font-black text-slate-950 focus:outline-none">
                        </label>
                        <label>
                            <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-300">To</span>
                            <input type="date" name="to_date" value="{{ $reportQuery['to_date'] }}" class="mt-1 h-10 w-full rounded-xl border border-white/10 bg-white px-3 text-xs font-black text-slate-950 focus:outline-none">
                        </label>
                        <label>
                            <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-300">Purchaser</span>
                            <select name="purchaser_id" class="mt-1 h-10 w-full rounded-xl border border-white/10 bg-white px-3 text-xs font-black text-slate-950 focus:outline-none">
                                <option value="">All</option>
                                @foreach($purchaserOptions as $option)
                                    <option value="{{ $option->id }}" @selected((int) $reportQuery['purchaser_id'] === (int) $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-300">Category</span>
                            <select name="category" class="mt-1 h-10 w-full rounded-xl border border-white/10 bg-white px-3 text-xs font-black text-slate-950 focus:outline-none">
                                <option value="">All</option>
                                <optgroup label="Procurement">
                                    @foreach($expenseCategories as $value => $label)
                                        <option value="{{ $value }}" @selected($reportQuery['category'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Other">
                                    @foreach($otherExpenseCategories as $value => $label)
                                        <option value="{{ $value }}" @selected($reportQuery['category'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </label>
                        <button type="submit" class="mt-4 inline-flex h-10 items-center justify-center rounded-xl bg-slate-100 px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-950 transition hover:bg-white lg:mt-5">
                            Apply
                        </button>
                    </form>
                </div>
            </div>

            <div class="space-y-5 p-4 sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">Total Company Out</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($reportTotals['company_total_out'], 2) }}</p>
                        <p class="mt-1 text-[10px] font-semibold text-slate-500">Cash purchases + online bills + purchaser expenses</p>
                    </div>
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-blue-700">Company Online Paid</p>
                        <p class="mt-2 text-2xl font-black text-blue-950">Rs. {{ number_format($reportTotals['company_online_out'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Credit Pending</p>
                        <p class="mt-2 text-2xl font-black text-amber-950">Rs. {{ number_format($reportTotals['credit_pending'], 2) }}</p>
                        <p class="mt-1 text-[10px] font-semibold text-amber-700">Payment still to give</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">Purchaser Cash Spent</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($reportTotals['cash_out'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">Procurement Expenses</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($reportTotals['procurement'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">Other Expenses</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($reportTotals['other_expenses'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-300 bg-slate-950 p-4 text-white">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-300">Total Purchaser Expenses</p>
                        <p class="mt-2 text-2xl font-black">Rs. {{ number_format($reportTotals['total_purchaser_expenses'], 2) }}</p>
                        <p class="mt-1 text-[10px] font-semibold text-slate-300">Procurement + Other</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">Cash Balance</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($reportTotals['balance'], 2) }}</p>
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-slate-50 p-1">
                    @foreach($reportTabs as $tabKey => $tabLabel)
                        <a href="{{ route('admin.accounting.purchasers.index', array_merge($reportQuery, ['report_tab' => $tabKey])) }}" data-purchaser-report-tab="{{ $tabKey }}" class="inline-flex h-10 shrink-0 items-center rounded-xl px-4 text-xs font-black uppercase tracking-[0.14em] transition {{ $activeReportTab === $tabKey ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-950' }}">
                            {{ $tabLabel }}
                        </a>
                    @endforeach
                </div>

                @if($activeReportTab === 'cash')
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Purchaser Cash</p>
                            <h3 class="mt-1 text-sm font-black text-slate-950">Cash given and cash spent by purchasers</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Purchaser</th>
                                        <th class="px-4 py-3 text-right">Cash In</th>
                                        <th class="px-4 py-3 text-right">Cash Out</th>
                                        <th class="px-4 py-3">Reason</th>
                                        <th class="px-4 py-3">Invoice / Reference</th>
                                        <th class="px-4 py-3">Created By</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($cashTransactions as $transaction)
                                        <tr class="align-top hover:bg-slate-50">
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $transaction->business_date?->format('d M Y') }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $transaction->created_at?->format('h:i A') }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $transaction->purchaser?->name ?? 'Purchaser removed' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $transaction->purchaser?->email }}</p>
                                            </td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">{{ $transaction->type === 'in' ? 'Rs. '.number_format((float) $transaction->amount, 2) : '-' }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">{{ $transaction->type === 'out' ? 'Rs. '.number_format((float) $transaction->amount, 2) : '-' }}</td>
                                            <td class="max-w-sm px-4 py-3">
                                                <p class="font-semibold text-slate-700">{{ $transaction->description ?: ($transaction->type === 'in' ? 'Cash given to purchaser' : 'Cash spent by purchaser') }}</p>
                                                <p class="mt-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">{{ $transaction->type === 'in' ? 'Cash In' : 'Cash Out' }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-800">{{ $transaction->purchaseInvoice?->invoice_number ?? 'Manual cash entry' }}</p>
                                                @if($transaction->purchaseInvoice)
                                                    <p class="mt-1 text-xs font-semibold text-slate-500">Paid {{ $transaction->purchaseInvoice->payment_method ?: 'cash' }} / {{ $transaction->purchaseInvoice->payment_status ?: 'status unknown' }}</p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $transaction->creator?->name ?? 'System' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No cash transactions for the selected filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Company Bill Payments</p>
                            <h3 class="mt-1 text-sm font-black text-slate-950">Online bills paid by company and credit bills still pending</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Purchaser</th>
                                        <th class="px-4 py-3">Supplier</th>
                                        <th class="px-4 py-3">Invoice</th>
                                        <th class="px-4 py-3">Method / Status</th>
                                        <th class="px-4 py-3 text-right">Company Paid</th>
                                        <th class="px-4 py-3 text-right">Credit Pending</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($companyBillTransactions as $row)
                                        <tr class="align-top hover:bg-slate-50">
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $row['date']?->format('d M Y') ?? '-' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['invoice']->updated_at?->format('h:i A') }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $row['purchaser']?->name ?? 'Purchaser removed' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['purchaser']?->email }}</p>
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $row['supplier']?->name ?? 'Supplier not set' }}</td>
                                            <td class="px-4 py-3">
                                                <a href="{{ route('purchasing.invoices.show', $row['invoice']) }}" class="font-black text-blue-700 underline-offset-4 hover:underline">
                                                    {{ $row['invoice']->invoice_number ?: 'Invoice #'.$row['invoice']->id }}
                                                </a>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['invoice']->purchaserCart?->cart_number }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-800">{{ $row['kind'] }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['method'] }} / {{ $row['status'] }}</p>
                                            </td>
                                            <td class="px-4 py-3 text-right font-black text-blue-700">{{ $row['paid_amount'] > 0 ? 'Rs. '.number_format($row['paid_amount'], 2) : '-' }}</td>
                                            <td class="px-4 py-3 text-right font-black text-amber-700">{{ $row['pending_amount'] > 0 ? 'Rs. '.number_format($row['pending_amount'], 2) : '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No company online payments or pending credit bills for the selected filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif($activeReportTab === 'procurement')
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Procurement Expenses</p>
                                <p class="mt-1 text-xl font-black text-slate-950">Rs. {{ number_format($reportTotals['procurement'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Other Expenses</p>
                                <p class="mt-1 text-xl font-black text-slate-950">Rs. {{ number_format($reportTotals['other_expenses'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Purchaser Expenses</p>
                                <p class="mt-1 text-xl font-black text-slate-950">Rs. {{ number_format($reportTotals['total_purchaser_expenses'], 2) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                        @foreach($expenseCategories as $value => $label)
                            @php($categoryRow = $categoryTotals->get($value, ['amount' => 0, 'count' => 0]))
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <p class="truncate text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $label }}</p>
                                <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format($categoryRow['amount'], 2) }}</p>
                                <p class="mt-0.5 text-[10px] font-bold text-slate-500">{{ $categoryRow['count'] }} row(s)</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Purchaser</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Note</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                        <th class="px-4 py-3">Company Expense Ref</th>
                                        <th class="px-4 py-3">Journal Ref</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($procurementTransactions as $expense)
                                        <tr class="align-top hover:bg-slate-50">
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $expense->expense_date?->format('d M Y') }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $expense->created_at?->format('h:i A') }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $expense->purchaser?->name ?? 'Purchaser removed' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $expense->purchaser?->email }}</p>
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $expense->categoryLabel() }}</td>
                                            <td class="max-w-md px-4 py-3 font-semibold text-slate-700">{{ $expense->note ?: 'No note' }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $expense->amount, 2) }}</td>
                                            <td class="px-4 py-3 font-black text-slate-800">{{ $expense->companyAccountingEntry?->reference ?? 'Not posted' }}</td>
                                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $expense->companyAccountingEntry?->journalEntry?->reference ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No procurement expenses for the selected filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif($activeReportTab === 'other')
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Other Expenses</p>
                                <p class="mt-1 text-xl font-black text-slate-950">Rs. {{ number_format($reportTotals['other_expenses'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Procurement Expenses</p>
                                <p class="mt-1 text-xl font-black text-slate-950">Rs. {{ number_format($reportTotals['procurement'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Purchaser Expenses</p>
                                <p class="mt-1 text-xl font-black text-slate-950">Rs. {{ number_format($reportTotals['total_purchaser_expenses'], 2) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                        @foreach($otherExpenseCategories as $value => $label)
                            @php($categoryRow = $otherCategoryTotals->get($value, ['amount' => 0, 'count' => 0]))
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <p class="truncate text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $label }}</p>
                                <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format($categoryRow['amount'], 2) }}</p>
                                <p class="mt-0.5 text-[10px] font-bold text-slate-500">{{ $categoryRow['count'] }} row(s)</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Purchaser</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Note</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                        <th class="px-4 py-3">Company Expense Ref</th>
                                        <th class="px-4 py-3">Journal Ref</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($otherExpenseTransactions as $expense)
                                        <tr class="align-top hover:bg-slate-50">
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $expense->expense_date?->format('d M Y') }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $expense->created_at?->format('h:i A') }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $expense->purchaser?->name ?? 'Purchaser removed' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $expense->purchaser?->email }}</p>
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $expense->categoryLabel() }}</td>
                                            <td class="max-w-md px-4 py-3 font-semibold text-slate-700">{{ $expense->note ?: 'No note' }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $expense->amount, 2) }}</td>
                                            <td class="px-4 py-3 font-black text-slate-800">{{ $expense->companyAccountingEntry?->reference ?? 'Not posted' }}</td>
                                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $expense->companyAccountingEntry?->journalEntry?->reference ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No other expenses for the selected filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Purchaser</th>
                                        <th class="px-4 py-3 text-right">Cash Given</th>
                                        <th class="px-4 py-3 text-right">Cash Spent</th>
                                        <th class="px-4 py-3 text-right">Company Online</th>
                                        <th class="px-4 py-3 text-right">Credit Pending</th>
                                        <th class="px-4 py-3 text-right">Procurement</th>
                                        <th class="px-4 py-3 text-right">Other Expenses</th>
                                        <th class="px-4 py-3 text-right">Total Purchaser Expenses</th>
                                        <th class="px-4 py-3 text-right">Company Out</th>
                                        <th class="px-4 py-3 text-right">Balance</th>
                                        <th class="px-4 py-3">Last Transaction</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($summaryRows as $row)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3">
                                                <p class="font-black text-slate-950">{{ $row['purchaser']->name }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['purchaser']->email }}</p>
                                            </td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['cash_in'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['cash_out'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-blue-700">Rs. {{ number_format($row['company_online'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-amber-700">Rs. {{ number_format($row['credit_pending'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['procurement'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['other_expenses'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_purchaser_expenses'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['company_out'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['balance'], 2) }}</td>
                                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $row['last_activity']?->format('d M Y') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="11" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No purchaser activity for the selected filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <script src="{{ asset('js/accounting-purchasers-export.js') }}" defer></script>
    <script>
        (() => {
            const sectionSelector = '#purchaser-reports-section';
            let activeController = null;

            const loadPurchaserReports = async (url) => {
                const currentSection = document.querySelector(sectionSelector);
                if (! currentSection) return;

                activeController?.abort();
                activeController = new AbortController();
                currentSection.classList.add('opacity-60', 'pointer-events-none');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                        signal: activeController.signal,
                    });

                    if (! response.ok) {
                        window.location.href = url;
                        return;
                    }

                    const html = await response.text();
                    const parsed = new DOMParser().parseFromString(html, 'text/html');
                    const nextSection = parsed.querySelector(sectionSelector);

                    if (! nextSection) {
                        window.location.href = url;
                        return;
                    }

                    currentSection.replaceWith(nextSection);
                    window.history.replaceState({}, '', url);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        window.location.href = url;
                    }
                } finally {
                    document.querySelector(sectionSelector)?.classList.remove('opacity-60', 'pointer-events-none');
                }
            };

            document.addEventListener('click', (event) => {
                const tab = event.target.closest('[data-purchaser-report-tab]');
                if (! tab) return;

                event.preventDefault();
                loadPurchaserReports(tab.href);
            });

            document.addEventListener('submit', (event) => {
                const form = event.target.closest('[data-purchaser-report-form]');
                if (! form) return;

                event.preventDefault();
                const formData = new FormData(form);
                const url = new URL(form.action, window.location.origin);

                formData.forEach((value, key) => {
                    if (value !== '') {
                        url.searchParams.set(key, value.toString());
                    } else {
                        url.searchParams.delete(key);
                    }
                });

                loadPurchaserReports(url.toString());
            });
        })();
    </script>
</x-layouts.accounting>
