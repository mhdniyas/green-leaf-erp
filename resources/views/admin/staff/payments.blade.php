<x-layouts.staff title="Staff Payments">
    @php
        $items = $payrollRun?->items ?? collect();
        $totalPayroll = round((float) $items->sum('final_amount'), 2);
        $totalPaid = round((float) $items->sum(fn ($item): float => $item->paidAmount()), 2);
        $totalOfficePaid = round((float) $items->sum(fn ($item): float => $item->officePaidAmount()), 2);
        $totalShopPaid = round((float) $items->sum(fn ($item): float => $item->shopPaidAmount()), 2);
        $totalRemaining = round(max(0, $totalPayroll - $totalPaid), 2);
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Payments</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Record salary payments for finalized payroll amounts without changing payroll calculations.</p>
            </div>

            <form method="GET" action="{{ route('admin.staff.payments.index') }}" class="flex flex-wrap items-end gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <label class="block">
                    <span class="mb-2 block text-sm font-black text-slate-700">Payroll month</span>
                    <input type="month" name="payroll_month" value="{{ $selectedPayrollMonth->format('Y-m') }}" class="rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white">Load payments</button>
            </form>
        </div>

        <section class="grid gap-4 md:grid-cols-5">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Payroll amount</p>
                <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($totalPayroll, 2) }}</p>
            </article>
            <article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700">Paid Total</p>
                <p class="mt-2 text-2xl font-black text-emerald-900">Rs. {{ number_format($totalPaid, 2) }}</p>
            </article>
            <article class="rounded-3xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-cyan-700">Office Paid</p>
                <p class="mt-2 text-2xl font-black text-cyan-900">Rs. {{ number_format($totalOfficePaid, 2) }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Shop Paid</p>
                <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($totalShopPaid, 2) }}</p>
            </article>
            <article class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-amber-700">Remaining</p>
                <p class="mt-2 text-2xl font-black text-amber-900">Rs. {{ number_format($totalRemaining, 2) }}</p>
            </article>
        </section>

        <section>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-black text-slate-950">Contract Worker Payment</h2>
                <form method="POST" action="{{ route('admin.staff.contract-worker-payments.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input type="text" name="worker_name" placeholder="Worker name" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="text" name="work_type" placeholder="Work type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <select name="shop_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="">No shop / general</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                        @endforeach
                    </select>
                    <select name="payment_method" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                    <input type="date" name="worked_on" value="{{ today()->toDateString() }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="date" name="paid_on" value="{{ today()->toDateString() }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="text" name="notes" placeholder="Note" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Record Contract Payment</button>
                    </div>
                </form>

                <div class="mt-5 space-y-2">
                    @forelse($contractPayments as $payment)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-900">{{ $payment->worker_name }}</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ $payment->shop?->name ?? 'General' }} · {{ $payment->paid_on->format('d M Y') }}</p>
                                </div>
                                <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $payment->amount, 2) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No contract worker payments this month.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">{{ $selectedPayrollMonth->format('F Y') }} salary payments</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Use full for the remaining amount, partial for an advance, or custom for a specific correction payment.</p>
                </div>
                @if($payrollRun)
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $payrollRun->status }}</span>
                @endif
            </div>

            @if($payrollRun === null)
                <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm font-semibold text-slate-500">
                    No payroll run exists for {{ $selectedPayrollMonth->format('F Y') }} yet.
                </div>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-[1040px] w-full text-left text-sm">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="px-3 py-3">Employee</th>
                                <th class="px-3 py-3">Category</th>
                                <th class="px-3 py-3 text-right">Salary due</th>
                                <th class="px-3 py-3 text-right">Paid</th>
                                <th class="px-3 py-3 text-right">Remaining</th>
                                <th class="px-3 py-3">Last payment</th>
                                <th class="px-3 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($items as $item)
                                @php
                                    $paidAmount = $item->paidAmount();
                                    $remainingAmount = $item->remainingAmount();
                                    $lastPayment = $item->payments->sortByDesc('paid_on')->first();
                                @endphp
                                <tr>
                                    <td class="px-3 py-4">
                                        <a href="{{ route('admin.staff.show', $item->employee) }}" class="font-black text-slate-900 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $item->employee->name }}</a>
                                    </td>
                                    <td class="px-3 py-4 font-semibold text-slate-600">{{ $item->category?->name ?? 'Uncategorized' }}</td>
                                    <td class="px-3 py-4 text-right font-black text-slate-900">Rs. {{ number_format((float) $item->final_amount, 2) }}</td>
                                    <td class="px-3 py-4 text-right font-black text-emerald-700">Rs. {{ number_format($paidAmount, 2) }}</td>
                                    <td class="px-3 py-4 text-right font-black {{ $remainingAmount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format($remainingAmount, 2) }}</td>
                                    <td class="px-3 py-4 text-sm font-semibold text-slate-500">
                                        {{ $lastPayment ? $lastPayment->paid_on->format('d M Y').' / Rs. '.number_format((float) $lastPayment->amount, 2) : 'No payment yet' }}
                                    </td>
                                    <td class="px-3 py-4 text-right">
                                        @if($remainingAmount > 0)
                                            <button type="button" class="rounded-xl bg-slate-950 px-4 py-2 text-xs font-black text-white" data-payroll-payment-open="payroll-payment-{{ $item->id }}">Update payment</button>
                                        @else
                                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Paid</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @foreach($items as $item)
                    @php($remainingAmount = $item->remainingAmount())
                    @if($remainingAmount > 0)
                        <dialog id="payroll-payment-{{ $item->id }}" class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100dvh-2rem)] w-[min(calc(100vw-2rem),42rem)] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60">
                            <form method="POST" action="{{ route('admin.staff.payments.store') }}" class="max-h-[calc(100dvh-2rem)] overflow-y-auto p-5 sm:p-6">
                                @csrf
                                <input type="hidden" name="payroll_run_item_id" value="{{ $item->id }}">

                                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Salary payment</p>
                                        <h3 class="mt-1 truncate text-xl font-black text-slate-950">{{ $item->employee->name }}</h3>
                                        <p class="mt-1 text-sm font-semibold text-slate-500">Remaining Rs. {{ number_format($remainingAmount, 2) }}</p>
                                    </div>
                                    <button type="button" class="shrink-0 rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600 transition hover:bg-slate-200" data-payroll-payment-close>Close</button>
                                </div>

                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-black text-slate-700">Payment option</span>
                                        <select name="payment_type" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-950 outline-none transition focus:border-cyan-400 focus:ring-4 focus:ring-cyan-100" data-payroll-payment-type data-full-amount="{{ $remainingAmount }}">
                                            <option value="full">Full remaining</option>
                                            <option value="partial">Partial</option>
                                            <option value="custom">Custom</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-black text-slate-700">Office payment source</span>
                                        <select name="payment_method" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-950 outline-none transition focus:border-cyan-400 focus:ring-4 focus:ring-cyan-100">
                                            <option value="cash">Office cash</option>
                                            <option value="bank">Office bank</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-black text-slate-700">Amount</span>
                                        <input type="number" step="0.01" min="0.01" max="{{ $remainingAmount }}" name="amount" value="{{ $remainingAmount }}" class="h-12 w-full rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-950 outline-none transition focus:border-cyan-400 focus:ring-4 focus:ring-cyan-100" data-payroll-payment-amount required>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-black text-slate-700">Payment date</span>
                                        <input type="date" name="paid_on" value="{{ today()->toDateString() }}" class="h-12 w-full rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-950 outline-none transition focus:border-cyan-400 focus:ring-4 focus:ring-cyan-100" required>
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="mb-2 block text-sm font-black text-slate-700">Notes</span>
                                        <textarea name="notes" rows="3" class="w-full resize-none rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-cyan-400 focus:ring-4 focus:ring-cyan-100" placeholder="Optional payment note"></textarea>
                                    </label>
                                </div>

                                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <button type="button" class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-600 transition hover:bg-slate-200" data-payroll-payment-close>Cancel</button>
                                    <button type="submit" class="rounded-xl bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950 transition hover:bg-cyan-400">Save office payment and post journal</button>
                                </div>
                            </form>
                        </dialog>
                    @endif
                @endforeach
            @endif
        </section>

        @if($payrollRun !== null)
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-black text-slate-950">Record Shop Staff Payment</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Use this when HR/admin decides salary or advance should be paid from a selected shop. This updates payroll paid balance and shop cash tracking, without posting another salary expense journal.</p>
                <form method="POST" action="{{ route('admin.staff.shop-staff-payments.store') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <select name="payroll_run_item_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="">Employee payroll item</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->employee?->name }} · remaining Rs. {{ number_format($item->remainingAmount(), 2) }}</option>
                        @endforeach
                    </select>
                    <select name="shop_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="">Pay from shop</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                        @endforeach
                    </select>
                    <select name="payment_type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="salary">Salary</option>
                        <option value="advance">Advance</option>
                    </select>
                    <select name="fund_source" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="petty_cash">Shop cash balance</option>
                        <option value="sales_income">Shop sales</option>
                    </select>
                    <input type="date" name="paid_on" value="{{ today()->toDateString() }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="text" name="notes" placeholder="Note" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Record Shop Payment</button>
                </form>
            </section>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-black text-slate-950">Shop Salary and Advance Payments</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">These reduce shop cash balance or sales cash tracking and do not post a duplicate salary expense journal.</p>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Date</th>
                            <th class="pb-3">Employee</th>
                            <th class="pb-3">Shop</th>
                            <th class="pb-3">Type</th>
                            <th class="pb-3">Source</th>
                            <th class="pb-3 text-right">Amount</th>
                            <th class="pb-3">Journal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($shopStaffPayments as $payment)
                            <tr>
                                <td class="py-3 font-bold text-slate-900">{{ $payment->paid_on->format('d M Y') }}</td>
                                <td class="py-3 font-semibold text-slate-600">{{ $payment->employee?->name }}</td>
                                <td class="py-3 font-semibold text-slate-600">{{ $payment->shop?->name }}</td>
                                <td class="py-3 capitalize">{{ $payment->payment_type }}</td>
                                <td class="py-3">{{ str($payment->fund_source)->replace('_', ' ')->headline() }}</td>
                                <td class="py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="py-3 text-sm font-black text-slate-400">No journal</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm font-semibold text-slate-500">No shop salary or advance payments recorded for this month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-black text-slate-950">Office Payment Journal Ledger</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Date</th>
                            <th class="pb-3">Employee</th>
                            <th class="pb-3">Method</th>
                            <th class="pb-3 text-right">Amount</th>
                            <th class="pb-3">Journal</th>
                            <th class="pb-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($payments as $payment)
                            <tr>
                                <td class="py-3 font-bold text-slate-900">{{ $payment->paid_on->format('d M Y') }}</td>
                                <td class="py-3 font-semibold text-slate-600">{{ $payment->employee?->name }}</td>
                                <td class="py-3 capitalize">
                                    {{ $payment->payment_method }}
                                    <p class="text-xs font-semibold text-slate-500">{{ str($payment->fund_source)->replace('_', ' ')->headline() }}</p>
                                </td>
                                <td class="py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="py-3 text-sm font-semibold text-cyan-700">{{ $payment->journalEntry?->reference ?? 'Pending journal' }}</td>
                                <td class="py-3 text-sm font-semibold text-slate-500">{{ $payment->notes ?: 'No notes' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-sm font-semibold text-slate-500">No salary payments recorded for this month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.staff>
