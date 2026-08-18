@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop #'.$currentShop->shop_id).' - Accept Payment')

@section('header_title')
    <i data-lucide="store" class="h-5 w-5 text-emerald-600"></i> {{ $currentShop->name ?: 'Shop #'.$currentShop->shop_id }}
@endsection

@section('header_subtitle')
    Record received cash or cheque details, then apply approved balance against monthly shop relations.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.accept-payment', ['month' => $month]) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Shops
    </a>
@endsection

@section('content')
    <div x-data="acceptShopPaymentPage()" class="mx-auto max-w-[96rem] space-y-5">
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid grid-cols-2 gap-3 xl:grid-cols-6">
            @foreach([
                ['label' => 'Received', 'value' => $shopCard['received_amount'], 'tone' => 'text-slate-950'],
                ['label' => 'Approved', 'value' => $shopCard['approved_amount'], 'tone' => 'text-emerald-700'],
                ['label' => 'Floating', 'value' => $shopCard['floating_amount'], 'tone' => 'text-amber-700'],
                ['label' => 'Pending', 'value' => $shopCard['pending_amount'], 'tone' => 'text-orange-700'],
                ['label' => 'Payable', 'value' => $shopCard['payable_balance'], 'tone' => 'text-sky-700'],
                ['label' => 'After Balance', 'value' => $shopCard['after_balance'], 'tone' => 'text-rose-700'],
            ] as $total)
                <div class="white-card rounded-lg border border-slate-200 p-3 shadow-sm">
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $total['label'] }}</span>
                    <strong class="mt-2 block break-words font-mono text-lg font-extrabold {{ $total['tone'] }}">₹{{ number_format($total['value'], 2) }}</strong>
                </div>
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="white-card rounded-lg border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h2 class="text-base font-extrabold text-slate-950">Accept Payment</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Cash approves directly into Cash in Hand. Cheque, UPI, bank transfer, and other methods go to reconciliation as floating.</p>
                </div>

                <form data-no-loader @submit.prevent="submitPayment" class="space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-slate-500">Payment Date</span>
                            <input type="date" x-model="form.business_date" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-slate-500">Method</span>
                            <select x-model="form.payment_method" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="online_upi">UPI</option>
                                <option value="other">Other</option>
                            </select>
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-slate-500">Amount Received</span>
                        <input type="number" step="0.01" min="0.01" x-model="form.settle_amount" class="min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3 font-mono text-base font-extrabold text-slate-900" placeholder="0.00">
                    </label>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block" x-show="form.payment_method === 'cash'">
                            <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-slate-500">Cash In Hand</span>
                            <select x-model="form.company_account_id" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="">Select cash account</option>
                                @foreach($cashAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} / ₹{{ number_format($account->current_balance, 2) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-slate-500">Reference</span>
                            <input type="text" x-model="form.payment_reference" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Cheque no, UPI id, bank ref">
                        </label>
                    </div>

                    <div x-show="form.payment_method === 'cheque'" class="grid grid-cols-1 gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-amber-700">Cheque Bank</span>
                            <input type="text" x-model="form.cheque_bank_name" class="min-h-10 w-full rounded-lg border border-amber-300 bg-white px-3 text-xs font-bold text-slate-800">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-amber-700">Cheque Date</span>
                            <input type="date" x-model="form.cheque_date" class="min-h-10 w-full rounded-lg border border-amber-300 bg-white px-3 text-xs font-bold text-slate-800">
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-slate-500">Note</span>
                        <textarea rows="3" x-model="form.notes" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800" placeholder="Payment details"></textarea>
                    </label>

                    <button type="submit" :disabled="submitting" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-extrabold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-60">
                        <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                        <span x-text="submitting ? 'Saving...' : 'Save Payment'"></span>
                    </button>
                </form>
            </div>

            <div class="white-card rounded-lg border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Recent Payment Flow</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ \Carbon\Carbon::parse($monthStart)->format('F Y') }} shop payment requests.</p>
                    </div>
                    <a href="{{ route('admin.cashbook.finance.journal', ['payment_method' => 'all']) }}" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="book-open-check" class="h-4 w-4"></i> Journal
                    </a>
                </div>

                <div class="space-y-2">
                    @forelse($paymentRequests as $payment)
                        <a href="{{ route('admin.cashbook.finance.journal.secure-show', $payment->secureRouteKey()) }}" class="block rounded-lg border border-slate-200 bg-slate-50 p-3 hover:bg-white">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <strong class="font-mono text-sm text-slate-950">₹{{ number_format($payment->requested_amount, 2) }}</strong>
                                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black uppercase text-slate-600">{{ $payment->paymentMethodLabel() }}</span>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-700">{{ $payment->reconciliationStatusLabel() }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $payment->payment_reference ?: $payment->shop_note ?: 'No reference' }}</p>
                                </div>
                                <div class="text-left text-[11px] font-bold text-slate-400 sm:text-right">
                                    {{ $payment->payment_date?->format('Y-m-d') ?: $payment->created_at?->format('Y-m-d') }}
                                    <div class="font-mono text-emerald-700">Approved ₹{{ number_format($payment->reconciled_amount, 2) }}</div>
                                    <div class="font-mono text-amber-700">Floating ₹{{ number_format($payment->floating_amount, 2) }}</div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No payments recorded this month.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="white-card rounded-lg border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Apply Approved Balance To Bills & Expenses</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Oldest rows first. Use search and date filters when there are many entries.</p>
                </div>
                <form method="GET" action="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->slug ?: $currentShop->shop_id]) }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[auto_auto_1fr_auto]">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <input type="search" name="search" value="{{ $search }}" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Search entries">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white hover:bg-slate-800">
                        <i data-lucide="filter" class="h-4 w-4"></i> Filter
                    </button>
                </form>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
                @forelse($payableDetails['groups'] as $group)
                    <a href="{{ route('admin.cashbook.reports.shop', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => $group['first_date'] ?: $dateFrom]) }}" class="rounded-lg border border-slate-200 bg-slate-50 p-3 hover:bg-white">
                        <span class="block truncate text-xs font-extrabold text-slate-950">{{ $group['name'] }}</span>
                        <span class="mt-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $group['count'] }} entries / {{ $group['first_date'] ?: '-' }} to {{ $group['last_date'] ?: '-' }}</span>
                        <strong class="mt-2 block font-mono text-sm font-extrabold {{ $group['total'] < 0 ? 'text-rose-700' : 'text-sky-700' }}">₹{{ number_format($group['total'], 2) }}</strong>
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-200 p-5 text-center text-xs font-bold text-slate-400 md:col-span-2 xl:col-span-4">No grouped payable rows in this filter.</div>
                @endforelse
            </div>

            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs font-bold text-slate-500">
                    Selected <span class="font-mono text-slate-900" x-text="selectedIds.length"></span> rows /
                    <span class="font-mono text-emerald-700" x-text="'₹' + selectedTotal().toFixed(2)"></span>
                </div>
                <button type="button" @click="toggleAll" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="list-checks" class="h-4 w-4"></i> Select All
                </button>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[760px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="w-12 px-3 py-3">Pick</th>
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Entry</th>
                            <th class="px-3 py-3">Note</th>
                            <th class="px-3 py-3 text-right">Amount</th>
                            <th class="px-3 py-3 text-right">Payable Effect</th>
                            <th class="px-3 py-3 text-right">Link</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($payableDetails['rows'] as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <input type="checkbox" value="{{ $row->id }}" data-amount="{{ $row->signed_amount }}" x-model="selectedIds" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                                </td>
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ $row->business_date?->format('Y-m-d') }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-extrabold text-slate-900">{{ $row->entryType?->name ?: $row->entry_type_code }}</div>
                                    <div class="text-[10px] font-bold uppercase text-slate-400">{{ $row->entryType?->code ?: 'entry' }}</div>
                                </td>
                                <td class="max-w-md px-3 py-3">
                                    <div class="truncate font-semibold text-slate-500">{{ $row->notes ?: '-' }}</div>
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($row->amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-extrabold {{ $row->signed_amount < 0 ? 'text-rose-700' : 'text-sky-700' }}">₹{{ number_format($row->signed_amount, 2) }}</td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('admin.cashbook.shop.show', $currentShop->slug ?: $currentShop->shop_id) }}" class="inline-flex min-h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2 font-bold text-slate-700 hover:bg-slate-50">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm font-bold text-slate-400">No payable rows found for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    function acceptShopPaymentPage() {
        return {
            submitting: false,
            selectedIds: [],
            form: {
                shop_id: {{ (int) $currentShop->shop_id }},
                business_date: '{{ today()->toDateString() }}',
                payment_method: 'cash',
                company_account_id: '{{ $cashAccounts->first()?->id ?? '' }}',
                settle_amount: '',
                petty_amount: 0,
                payment_reference: '',
                cheque_bank_name: '',
                cheque_date: '{{ today()->toDateString() }}',
                notes: '',
            },
            selectedTotal() {
                return Array.from(document.querySelectorAll('input[type="checkbox"][data-amount]:checked'))
                    .reduce((sum, input) => sum + Math.abs(parseFloat(input.dataset.amount || '0')), 0);
            },
            toggleAll() {
                const boxes = Array.from(document.querySelectorAll('input[type="checkbox"][data-amount]'));
                if (this.selectedIds.length === boxes.length) {
                    this.selectedIds = [];
                } else {
                    this.selectedIds = boxes.map(box => box.value);
                }
            },
            async submitPayment() {
                if (!parseFloat(this.form.settle_amount || 0)) {
                    showToast('Enter payment amount', 'error');
                    return;
                }

                if (this.form.payment_method === 'cash' && !this.form.company_account_id) {
                    showToast('Select Cash in Hand account', 'error');
                    return;
                }

                this.submitting = true;

                try {
                    const response = await fetch('{{ route('admin.cashbook.api.accept-payment') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(this.form),
                    });
                    const data = await response.json();

                    if (!data.success) {
                        showToast(data.message || 'Payment could not be saved', 'error');
                        return;
                    }

                    showToast(data.message || 'Payment saved', 'success');
                    window.setTimeout(() => window.location.reload(), 650);
                } catch (error) {
                    showToast('Server error saving payment', 'error');
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
@endpush
