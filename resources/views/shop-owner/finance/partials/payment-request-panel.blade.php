@php
    /** @var \App\Models\ShopInvoice|null $invoice */
    $invoice = $invoice ?? null;
    $context = $context ?? 'finance';
    $latestRequest = $invoice?->paymentRequests->first();
    $hasPendingRequest = $latestRequest && $latestRequest->status === 'pending';
    $fullDue = $invoice ? number_format((float) $invoice->balance_amount, 2, '.', '') : '0.00';
    $halfDue = $invoice ? number_format(round((float) $invoice->balance_amount / 2, 2), 2, '.', '') : '0.00';
@endphp

<section data-payment-request-panel class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Payment Request</p>
            <h2 class="mt-2 text-xl font-black text-slate-950">Send paid amount for approval</h2>
            <p class="mt-2 text-sm text-slate-600">Any paid amount update from shop owner must be approved by admin or purchase manager before the invoice payment changes.</p>
        </div>
        @if ($invoice)
            <div class="grid grid-cols-2 gap-3 lg:min-w-[260px]">
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid Now</p>
                    <p class="mt-1 text-sm font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance Due</p>
                    <p class="mt-1 text-sm font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                </div>
            </div>
        @endif
    </div>

    @if (! $invoice)
        <div class="mt-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-black text-amber-900">Invoice not generated yet.</p>
            <p class="mt-2 text-sm text-amber-800">Finish delivery review first. Payment approval options will appear after the shop invoice is ready.</p>
        </div>
    @elseif ((float) $invoice->balance_amount <= 0)
        <div class="mt-5 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-black text-emerald-800">This invoice is already fully settled.</p>
        </div>
    @elseif ($hasPendingRequest)
        <div class="mt-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-black text-amber-900">A payment request is already waiting for approval.</p>
                    <p class="mt-1 text-sm font-semibold text-amber-800">Requested Rs. {{ number_format((float) $latestRequest->requested_amount, 2) }} on {{ $latestRequest->created_at?->format('d M Y h:i A') }}</p>
                    @if ($latestRequest->shop_note)
                        <p class="mt-2 text-sm text-slate-700">{{ $latestRequest->shop_note }}</p>
                    @endif
                </div>
                @include('shop-owner.components.status-badge', ['label' => $latestRequest->statusLabel(), 'tone' => $latestRequest->statusTone()])
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Quick Options</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <input type="radio" name="amount_mode" value="balance_due" {{ old('amount_mode', 'balance_due') === 'balance_due' ? 'checked' : '' }} class="h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid Full</span>
                                <span class="mt-1 block text-sm font-black text-slate-950">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</span>
                            </span>
                        </label>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-100"
                            data-payment-fill
                            data-payment-amount="{{ $halfDue }}"
                        >
                            50% Due
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-100"
                            data-payment-fill
                            data-payment-amount="{{ $fullDue }}"
                        >
                            Fill Full Due
                        </button>
                    </div>
                </div>

                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                    <label class="inline-flex items-center gap-3">
                        <input type="radio" name="amount_mode" value="custom" {{ old('amount_mode') === 'custom' ? 'checked' : '' }} data-payment-custom-radio class="h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Custom Amount</span>
                    </label>
                    <label class="block">
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            max="{{ $fullDue }}"
                            name="amount"
                            value="{{ old('amount') }}"
                            placeholder="Enter amount to request"
                            data-payment-amount-input
                            class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none"
                        >
                    </label>
                    <p class="mt-2 text-xs font-semibold text-slate-500">Entering a value here marks this request as custom.</p>
                </div>
            </div>

            <label class="block">
                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Shop Note</span>
                <textarea name="shop_note" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Mention cash given, UPI, remaining balance, or any payment note...">{{ old('shop_note') }}</textarea>
            </label>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs font-semibold text-slate-500">
                    {{ $context === 'delivery' ? 'Delivery quantities can be submitted separately. This payment request only updates money after approval.' : 'Invoice payment stays unchanged until approval is completed.' }}
                </p>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                    Send For Approval
                </button>
            </div>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const sections = document.querySelectorAll('[data-payment-request-panel]');

                sections.forEach((section) => {
                    const amountInput = section.querySelector('[data-payment-amount-input]');
                    const quickFillButtons = section.querySelectorAll('[data-payment-fill]');
                    const fullRadio = section.querySelector('input[type="radio"][name="amount_mode"][value="balance_due"]');
                    const customRadio = section.querySelector('[data-payment-custom-radio]');

                    fullRadio?.addEventListener('change', function () {
                        if (this.checked) {
                            amountInput.value = '';
                        }
                    });

                    amountInput?.addEventListener('input', function () {
                        if (this.value.trim() !== '') {
                            customRadio.checked = true;
                        }
                    });

                    quickFillButtons.forEach((button) => {
                        button.addEventListener('click', function () {
                            amountInput.value = this.dataset.paymentAmount || '';
                            customRadio.checked = true;
                            amountInput.focus();
                        });
                    });
                });
            });
        </script>
    @endif
</section>
