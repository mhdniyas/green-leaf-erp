<x-layouts.app title="Invoice {{ $invoice->invoice_number }}">

    <x-slot:actions>
        <a href="{{ route('sales.invoices.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Invoices
        </a>
    </x-slot:actions>

    <div class="max-w-4xl mx-auto space-y-6">

        {{-- Invoice Header --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-semibold text-gray-900">{{ $invoice->invoice_number }}</h2>
                    <span class="inline-flex items-center text-xs font-medium border px-2.5 py-0.5 rounded-full {{ $invoice->status->color() }}">
                        {{ $invoice->status->label() }}
                    </span>
                </div>
                <a href="{{ route('sales.orders.show', $invoice->salesOrder) }}" class="text-xs text-brand-600 hover:underline font-mono">
                    {{ $invoice->salesOrder->so_number }}
                </a>
            </div>

            <div class="grid grid-cols-4 divide-x divide-gray-100 px-6 py-4">
                <div class="pr-6">
                    <p class="text-xs text-gray-400 mb-0.5">Customer</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $invoice->customer->name }}</p>
                    <p class="text-xs text-gray-400">{{ $invoice->customer->payment_terms }}</p>
                </div>
                <div class="px-6">
                    <p class="text-xs text-gray-400 mb-0.5">Invoice Amount</p>
                    <p class="text-xl font-bold text-gray-900">INR {{ number_format((float) $invoice->amount, 2) }}</p>
                </div>
                <div class="px-6">
                    <p class="text-xs text-gray-400 mb-0.5">Paid</p>
                    <p class="text-xl font-bold text-green-600">INR {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                </div>
                <div class="pl-6">
                    <p class="text-xs text-gray-400 mb-0.5">Outstanding</p>
                    @php $outstanding = $invoice->outstanding_amount; @endphp
                    <p class="text-xl font-bold {{ $outstanding > 0 ? 'text-red-600' : 'text-green-600' }}">
                        INR {{ number_format($outstanding, 2) }}
                    </p>
                    <p class="text-xs text-gray-400">Due {{ $invoice->due_date->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Order Items Summary --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Items</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Grade</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Price</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($invoice->salesOrder->items as $item)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $item->product->name }}</td>
                            <td class="px-6 py-3">
                                @php $badge = $item->grade->badge(); @endphp
                                <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full bg-{{ $badge }}-100 text-{{ $badge }}-700">
                                    {{ $item->grade->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ number_format((float) $item->quantity, 3) }} kg</td>
                            <td class="px-6 py-3 text-right text-gray-700">INR {{ number_format((float) $item->unit_price, 4) }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-gray-900">INR {{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="4" class="px-6 py-3 text-right text-sm font-semibold text-gray-700">Total</td>
                            <td class="px-6 py-3 text-right text-base font-bold text-gray-900">INR {{ number_format((float) $invoice->amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Payments --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Payments</h3>
            </div>

            @if($invoice->payments->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Reference</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Recorded by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($invoice->payments as $payment)
                        <tr>
                            <td class="px-6 py-3 text-gray-700">{{ $payment->paid_at->format('d M Y') }}</td>
                            <td class="px-6 py-3 text-gray-700">{{ $payment->payment_method->label() }}</td>
                            <td class="px-6 py-3 text-gray-400 font-mono text-xs">{{ $payment->reference ?? '—' }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-green-700">INR {{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="px-6 py-3 text-gray-500 text-xs">{{ $payment->createdBy?->name }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="py-8 text-center">
                <p class="text-sm text-gray-400">No payments recorded yet.</p>
            </div>
            @endif

            {{-- Record Payment Form --}}
            @if(!$invoice->isFullyPaid())
            @can('recordPayment', $invoice)
            <div class="border-t border-gray-100 px-6 py-5">
                <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Record Payment</h4>
                <form method="POST" action="{{ route('sales.invoices.payments.store', $invoice) }}" class="grid grid-cols-2 gap-4">
                    @csrf

                    <div>
                        <label for="amount" class="block text-xs font-medium text-gray-600 mb-1">Amount (INR) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" id="payment-amount" value="{{ old('amount', number_format($invoice->outstanding_amount, 2, '.', '')) }}" min="0.01" step="0.01" required
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="payment_method" class="block text-xs font-medium text-gray-600 mb-1">Method <span class="text-red-500">*</span></label>
                        <select name="payment_method" id="payment_method" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                            @foreach(\App\Enums\Sales\PaymentMethod::cases() as $method)
                                <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="paid_at" class="block text-xs font-medium text-gray-600 mb-1">Payment Date <span class="text-red-500">*</span></label>
                        <input type="date" name="paid_at" id="paid_at" value="{{ old('paid_at', now()->format('Y-m-d')) }}" required
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    </div>

                    <div>
                        <label for="reference" class="block text-xs font-medium text-gray-600 mb-1">Reference</label>
                        <input type="text" name="reference" id="reference" value="{{ old('reference') }}" placeholder="Cheque #, Transfer ref…"
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    </div>

                    <div class="col-span-2 flex justify-end">
                        <button type="submit" id="record-payment-btn"
                                class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
            @endcan
            @endif
        </div>

    </div>

</x-layouts.app>
