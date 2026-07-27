<x-layouts.staff title="Staff Payroll">
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Payroll</h1>
                <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">Choose a month, create a draft, check each person’s pay, and mark it as final when everything looks right.</p>
            </div>
        </div>

        <section class="rounded-3xl border border-cyan-100 bg-cyan-50 p-5 sm:p-6">
            <div class="flex gap-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cyan-500 text-sm font-black text-white">1</div>
                <div>
                    <h2 class="font-black text-slate-950">Start with the payroll month</h2>
                    <p class="mt-1 text-sm font-semibold leading-6 text-slate-600">First choose the month. If there is no draft yet, create one to calculate pay from attendance and leave records.</p>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <form method="GET" action="{{ route('admin.staff.payroll.index') }}" class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="mb-2 block text-sm font-black text-slate-700">Payroll month</span>
                        <input type="month" name="payroll_month" value="{{ $selectedPayrollMonth->format('Y-m') }}" class="rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                    </label>
                    <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700">View this month</button>
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.staff.payroll.store') }}">
                        @csrf
                        <input type="hidden" name="payroll_month" value="{{ $selectedPayrollMonth->format('Y-m') }}">
                        <button type="submit" class="rounded-xl bg-cyan-500 px-4 py-3 text-sm font-black text-slate-950">Create payroll draft</button>
                    </form>

                    @if($payrollRuns->isNotEmpty())
                        <a href="{{ route('admin.staff.payroll.export.excel', ['payroll_month' => $selectedPayrollMonth->format('Y-m')]) }}" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-700">Download Excel <span class="sr-only">Export Excel</span></a>
                        <a href="{{ route('admin.staff.payroll.export.pdf', ['payroll_month' => $selectedPayrollMonth->format('Y-m')]) }}" target="_blank" class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-black text-cyan-700">Open printable PDF <span class="sr-only">PDF View</span></a>
                    @else
                        <span class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-black text-slate-400">Create a draft to export</span>
                    @endif
                </div>
            </div>
        </section>

        <div class="space-y-4">
            @forelse($payrollRuns as $run)
                @php($runSerial = ($payrollRuns->currentPage() - 1) * $payrollRuns->perPage() + $loop->iteration)
                @php($categorySummary = $run->items->groupBy(fn ($item) => $item->category?->name ?? 'Uncategorized'))
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" data-payroll-run="{{ $run->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 min-w-10 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-2 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">#{{ $runSerial }}</span>
                            <div>
                                <h2 class="text-lg font-black text-slate-950">{{ $run->period_start->format('F Y') }}</h2>
                                <p class="text-sm font-semibold text-slate-500">Created by {{ $run->generatedBy?->name ?? 'System' }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $run->status === 'draft' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                                        {{ $run->status === 'draft' ? 'Needs review' : 'Finalized' }}
                                    </span>
                                    @if($run->finalizedBy)
                                        <span class="text-xs font-semibold text-slate-500">Finalized by {{ $run->finalizedBy->name }}</span>
                                    @endif
                                </div>
                                @if($run->journalEntry)
                                    <p class="mt-1 text-xs font-semibold text-cyan-700">Posted as expense entry {{ $run->journalEntry->reference }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Green Leaf to pay</p>
                            <p class="text-xl font-black text-slate-950" data-payroll-run-total>Rs. {{ number_format((float) $run->net_amount, 2) }}</p>
                            @if($run->status === 'draft')
                                <div class="mt-3 flex flex-wrap justify-end gap-2">
                                    <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50" data-payroll-save-all>Save all changes</button>
                                    <form method="POST" action="{{ route('admin.staff.payroll.finalize', $run) }}">
                                        @csrf
                                        <input type="hidden" name="payroll_month" value="{{ $selectedPayrollMonth->format('Y-m') }}">
                                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white">Mark payroll as final</button>
                                    </form>
                                </div>
                                <p class="mt-2 text-xs font-semibold text-slate-500" data-payroll-run-status aria-live="polite"></p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($categorySummary as $categoryName => $items)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $categoryName }}</p>
                                <p class="mt-2 text-lg font-black text-slate-950" data-payroll-category-total="{{ $categoryName }}">Rs. {{ number_format((float) $items->sum(fn ($item) => $item->greenLeafPayableAmount()), 2) }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $items->count() }} people</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-100">
                        <table class="w-full min-w-[1180px] table-fixed text-left text-sm">
                            <colgroup>
                                <col class="w-12">
                                <col class="w-52">
                                <col class="w-36">
                                <col class="w-20">
                                <col class="w-32">
                                <col class="w-32">
                                <col class="w-32">
                                <col class="w-[30rem]">
                                <col class="w-32">
                            </colgroup>
                            <thead class="text-slate-500">
                                <tr>
                                    <th class="px-3 py-3">No.</th>
                                    <th class="px-3 py-3">Employee</th>
                                    <th class="px-3 py-3">Category</th>
                                    <th class="px-3 py-3">Absent days</th>
                                    <th class="px-3 py-3">Green Leaf Pay</th>
                                    <th class="px-3 py-3">Client Shop Pay</th>
                                    <th class="px-3 py-3">Paid Split</th>
                                    <th class="px-3 py-3">Change pay</th>
                                    <th class="px-3 py-3 text-right">Pay this person</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($run->items as $item)
                                    <tr>
                                        <td class="px-3 py-3 font-black text-slate-500">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-3"><a href="{{ route('admin.staff.show', $item->employee) }}" class="block truncate font-bold text-slate-900 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $item->employee->name }}</a></td>
                                        <td class="px-3 py-3">{{ $item->category?->name ?? 'Uncategorized' }}</td>
                                        <td class="px-3 py-3">{{ $item->absent_days }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <p class="font-black text-slate-950">Rs. {{ number_format($item->greenLeafPayableAmount(), 2) }}</p>
                                            <p class="mt-1 text-[11px] font-bold text-slate-500">{{ number_format((float) $item->green_leaf_payable_units, 2) }} day units</p>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <p class="font-black text-slate-950">Rs. {{ number_format($item->clientShopPayableAmount(), 2) }}</p>
                                            <p class="mt-1 text-[11px] font-bold text-slate-500">{{ number_format((float) $item->client_shop_payable_units, 2) }} day units</p>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <p class="font-bold text-emerald-700">GL Rs. {{ number_format($item->officePaidAmount(), 2) }}</p>
                                            <p class="mt-1 text-[11px] font-bold text-cyan-700">Shop Rs. {{ number_format($item->shopPaidAmount(), 2) }}</p>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            @if($run->status === 'draft')
                                                @php($shouldUseOldInput = (int) old('payroll_run_item_id') === $item->id)
                                                <form method="POST" action="{{ route('admin.staff.payroll.items.update', [$run, $item]) }}" class="grid gap-2 rounded-2xl bg-slate-50 p-3 sm:grid-cols-[9rem_1fr_auto] sm:items-end" data-payroll-override-form data-initial-override="{{ $item->override_amount }}" data-initial-reason="{{ $shouldUseOldInput ? old('override_reason') : '' }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="payroll_month" value="{{ $selectedPayrollMonth->format('Y-m') }}">
                                                    <input type="hidden" name="payroll_run_item_id" value="{{ $item->id }}">
                                                    <label class="block text-xs font-bold text-slate-600">
                                                        New amount
                                                        <input type="number" step="0.01" min="0" name="override_amount" value="{{ $shouldUseOldInput ? old('override_amount') : $item->override_amount }}" placeholder="System" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-800" data-payroll-override-amount>
                                                    </label>
                                                    <label class="block text-xs font-bold text-slate-600">
                                                        Reason
                                                        <input type="text" name="override_reason" value="{{ $shouldUseOldInput ? old('override_reason') : '' }}" placeholder="Required" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required data-payroll-override-reason>
                                                    </label>
                                                    <button type="submit" class="rounded-xl bg-slate-200 px-3 py-2 text-xs font-black text-slate-700 transition hover:bg-slate-300" data-payroll-save-button>Save</button>
                                                    <p class="text-xs font-semibold text-slate-500 sm:col-span-3" data-payroll-form-status aria-live="polite"></p>
                                                </form>
                                            @else
                                                <span class="text-sm font-semibold text-slate-500">{{ $item->override_amount !== null ? 'Rs. '.number_format((float) $item->override_amount, 2) : 'System amount' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right font-black text-slate-900 whitespace-nowrap" data-payroll-item-final="{{ $item->id }}">Rs. {{ number_format($item->remainingGreenLeafAmount(), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-sm font-semibold text-slate-500">
                    No payroll run found for {{ $selectedPayrollMonth->format('F Y') }}. Generate this month to review or export it.
                </div>
            @endforelse
        </div>

        <div>{{ $payrollRuns->links() }}</div>
    </div>
</x-layouts.staff>
