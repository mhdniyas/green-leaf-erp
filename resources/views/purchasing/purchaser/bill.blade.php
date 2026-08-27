<x-layouts.app title="Purchaser Bill">
    @php
        $billDate = \Illuminate\Support\Carbon::parse($date);
        $discountAmount = (float) old('discount_amount', $cart->discount_amount);
        $payableTotal = max(0, (float) $subtotal - $discountAmount);
        $paidAmount = (float) old('paid_amount', $cart->paid_amount);
        $balanceAmount = max(0, $payableTotal - $paidAmount);
        $defaultBillNumber = old('bill_number', $cart->bill_number);
        $supplier = $cart->supplier;
        $companyName = $companyDetails['name'] ?? 'Green Leaf';
        $companyLines = collect([
            $companyDetails['address'] ?? null,
            $companyDetails['phone'] ?? null ? 'Phone: '.$companyDetails['phone'] : null,
            $companyDetails['email'] ?? null ? 'Email: '.$companyDetails['email'] : null,
        ])->filter()->values();
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 sm:px-2 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 4</p>
                    <h1 class="mt-0.5 text-lg font-black text-slate-950">Bill invoice</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $cart->cart_number }} • {{ $supplier?->name ?: 'Draft Cart' }} • {{ $billDate->format('d M Y') }}</p>
                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ ($cart->purchase_grade ?? 'A') === 'B' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' }}">Grade {{ $cart->purchase_grade ?? 'A' }}</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <a href="{{ route('purchaser.cart', ['date' => $date, 'cart' => $cart->id]) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">Back to Cart</a>
                    <a href="{{ route('purchaser.history', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">History</a>
                </div>
            </div>
        </section>

        @if ($supplier && ! $supplier->credit_approved && ! $supplier->credit_approval_requested_at)
            <form id="bill-credit-request-form" action="{{ route('purchasing.suppliers.credit-request', $supplier) }}" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="credit_approval_note" value="Requested from purchaser bill {{ $cart->cart_number }} for {{ $billDate->format('d M Y') }}.">
            </form>
        @endif

        <form id="bill-main-form" action="{{ route('purchaser.carts.submit') }}" method="POST" onsubmit="if(this.dataset.submitting) return false; this.dataset.submitting='true';" class="space-y-4">
            @csrf
            <input type="hidden" name="business_date" value="{{ $date }}">
            <input type="hidden" name="cart_id" value="{{ $cart->id }}">
            <input type="hidden" name="purchase_grade" value="{{ $cart->purchase_grade ?? 'A' }}">
            <input type="hidden" name="supplier_id" value="{{ $cart->supplier_id }}">
            <input type="hidden" name="return_to" value="vendors">
            <input type="hidden" id="bill_number_hidden" name="bill_number" value="{{ $defaultBillNumber }}">

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="relative mx-auto min-h-[46rem] max-w-[36rem] bg-white px-3 py-5 text-slate-950 sm:px-6 sm:py-7">
                    <div id="bill-status-ribbon" class="absolute -left-11 top-7 w-40 -rotate-45 bg-amber-500 py-1 text-center text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm">
                        {{ old('payment_method', $cart->payment_method ?: 'Cash') === 'Credit' || $balanceAmount > 0 ? 'Unpaid' : 'Paid' }}
                    </div>

                    <header class="border-b border-dashed border-slate-400 pb-3 text-center">
                        <h2 class="text-xl font-black uppercase tracking-wide text-slate-950">Bill Invoice</h2>
                        <p class="mt-2 text-base font-black uppercase leading-tight text-slate-950">{{ $companyName }}</p>
                        @foreach ($companyLines as $line)
                            <p class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-700">{{ $line }}</p>
                        @endforeach
                    </header>

                    <div class="grid grid-cols-1 gap-3 border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:grid-cols-2">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Bill No</p>
                            <p id="bill-number-display" class="mt-0.5 font-mono text-xs font-black text-slate-950">{{ $defaultBillNumber ?: 'Pending' }}</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-600">Cart: {{ $cart->cart_number }}</p>
                        </div>
                        <div class="sm:text-right">
                            <p>Date: {{ $billDate->format('d M Y') }}</p>
                            <p class="mt-1">Source: {{ $cart->purchaseSourceLabel() }}</p>
                        </div>
                    </div>

                    <div class="border-b border-dashed border-slate-400 py-3 text-[11px] text-slate-700">
                        <p class="font-black uppercase tracking-[0.12em] text-slate-500">Vendor</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ $supplier?->name ?: 'Supplier pending' }}</p>
                        <p class="mt-0.5 font-semibold">{{ $supplier?->mobile_number ?: $supplier?->contact ?: 'Mobile pending' }}{{ $supplier?->location ? ' • '.$supplier->location : '' }}</p>
                        @if ($supplier?->payment_terms)
                            <p class="mt-0.5 font-semibold">Terms: {{ $supplier->payment_terms }}</p>
                        @endif
                    </div>

                    @if (($cart->purchase_grade ?? 'A') === 'B')
                        <div class="mb-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-blue-700">
                            Grade B Direct Purchase
                        </div>
                    @endif

                    <div class="overflow-x-auto border-b border-dashed border-slate-400 py-3">
                        <table class="w-full table-fixed text-left text-[11px]">
                            <thead class="border-b border-dashed border-slate-400 text-[10px] font-black uppercase text-slate-950">
                                <tr>
                                    <th class="w-7 py-1 pr-1">SN</th>
                                    <th class="py-1 pr-2">Item</th>
                                    <th class="w-12 py-1 pr-1 text-right">Qty</th>
                                    <th class="w-16 py-1 pr-1 text-right">Price</th>
                                    <th class="w-20 py-1 text-right">Amt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cart->items as $item)
                                    @php
                                        $vendorPriceHint = $vendorPriceHints[$item->product_id] ?? 0;
                                    @endphp
                                    <tr class="align-top">
                                        <td class="py-2 pr-1 font-bold">{{ $loop->iteration }}</td>
                                        <td class="py-2 pr-2">
                                            <p class="font-black text-slate-950">{{ $item->product->name }}</p>
                                            <p class="mt-0.5 text-[10px] font-semibold text-slate-500">
                                                {{ $item->product->unit }}{{ $vendorPriceHint > 0 ? ' • Prev Rs. '.number_format((float) $vendorPriceHint, 2) : '' }}
                                                @if (($item->grade ?? 'A') === 'B')
                                                    <span class="ml-1 inline-flex items-center rounded px-1 py-0.5 bg-blue-100 text-blue-700 font-black uppercase tracking-wide text-[9px]">Grade B</span>
                                                @endif
                                            </p>
                                        </td>
                                        <td class="py-2 pr-1 text-right font-bold">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td class="py-2 pr-1 text-right">
                                            <input id="price-{{ $item->id }}" type="number" step="0.01" min="0.01" name="items[{{ $item->id }}][unit_price]" value="{{ old("items.{$item->id}.unit_price", number_format((float) $item->unit_price, 2, '.', '')) }}" class="bill-price h-7 w-14 rounded-md border border-slate-200 bg-slate-50 px-1 text-right text-[11px] font-bold text-slate-950 focus:bg-white focus:outline-none" data-quantity="{{ number_format((float) $item->quantity, 3, '.', '') }}">
                                        </td>
                                        <td class="py-2 text-right font-black text-slate-950">
                                            <span class="bill-line-total">Rs. {{ number_format((float) $item->line_total, 2) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="ml-auto w-full max-w-full border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:max-w-[20rem]">
                        <div class="flex items-center justify-between font-black text-slate-950 text-sm">
                            <span>Total Bill</span>
                            <span id="bill-subtotal" data-base-subtotal="{{ number_format($subtotal, 2, '.', '') }}">₹{{ number_format($subtotal, 2) }}</span>
                        </div>
                    </div>

                    <footer class="pt-3 text-center">
                        <p class="text-xs font-black text-slate-800">Thank You</p>
                    </footer>
                </div>
            </section>

            <section class="mx-auto w-full max-w-[36rem] rounded-2xl border border-slate-200 bg-white p-3 shadow-sm print:hidden">
                <div>
                    <label for="notes" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Notes</label>
                    <input id="notes" type="text" name="notes" value="{{ old('notes', $cart->notes) }}" placeholder="Optional note..." class="mt-1 h-9 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>
            </section>

            <input type="hidden" id="paid_amount" name="paid_amount" value="{{ old('paid_amount', $cart->paid_amount ?? 0) }}">
            <input type="hidden" id="discount_amount" name="discount_amount" value="{{ old('discount_amount', $cart->discount_amount ?? 0) }}">
            <input type="hidden" id="payment_method" name="payment_method" value="{{ old('payment_method', $cart->payment_method ?: 'Cash') }}">
            <input type="hidden" id="payment_note" name="payment_note" value="{{ old('payment_note', $cart->payment_note) }}">

            <div class="sticky bottom-20 z-20 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-lg backdrop-blur print:hidden sm:static sm:mx-auto sm:w-full sm:max-w-[36rem]">
                <div class="flex items-center gap-2">
                    <a href="{{ route('purchaser.history', ['date' => $date]) }}" class="inline-flex h-11 w-24 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">History</a>
                    <button type="button" onclick="openBillPaymentModal()" class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-sm hover:bg-teal-500 active:scale-95 transition-all">
                        Save Prices & Pay
                    </button>
                </div>
            </div>

            <!-- Payment Update Modal for Bill Submission -->
            <div id="payment-update-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overscroll-none touch-none" onclick="if (event.target === this) closePaymentModal()">
                <div class="w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-4 shadow-xl text-xs font-semibold text-slate-800 touch-pan-y">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div>
                            <h3 class="text-sm font-black text-slate-950">Submit Payment</h3>
                            <p class="text-[11px] font-semibold text-slate-500 truncate mt-0.5">{{ $cart->cart_number }} • {{ $supplier?->name ?: 'Vendor pending' }}</p>
                        </div>
                        <button type="button" onclick="closePaymentModal()" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
                    </div>

                    <div class="mt-3 space-y-3">
                        <!-- Total Amount & Balance Card -->
                        <div class="rounded-xl border border-slate-900 bg-slate-950 p-3 text-white shadow-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Bill</span>
                                <span id="payment-modal-total" class="font-mono text-base font-black text-white">₹0.00</span>
                            </div>
                            <div id="payment-modal-credit-discount-row" class="hidden flex items-center justify-between text-[11px] font-bold text-rose-400">
                                <span>Discount</span>
                                <span id="payment-modal-credit-discount" class="font-mono font-bold text-rose-400">-₹0.00</span>
                            </div>
                            <div id="payment-modal-credit-amount-row" class="hidden flex items-center justify-between text-[11px] font-bold text-teal-300">
                                <span>Credit Amount</span>
                                <span id="payment-modal-credit-amount" class="font-mono font-bold text-teal-300">₹0.00</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-300">
                                <span>Remaining Balance</span>
                                <span id="payment-modal-balance" class="font-mono font-black text-amber-400">₹0.00</span>
                            </div>
                        </div>

                        <!-- Paid Amount Input (Cash / Online / GPay) -->
                        <div id="paid-amount-container">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Paid Amount (₹)</label>
                            <input id="additional_paid_amount" type="number" step="0.01" min="0" placeholder="Enter amount paid" class="mt-1 h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 font-mono text-sm font-black text-slate-950 focus:bg-white focus:border-teal-600 focus:outline-none">
                        </div>

                        <!-- Auto Difference Action (Discount vs Balance for Cash) -->
                        <div id="diff-action-container" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-2.5 space-y-2">
                            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <span>Unpaid Difference</span>
                                <span id="diff-amount-label" class="font-mono text-slate-900 font-bold">₹0.00</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <button type="button" id="diff-btn-discount" onclick="setDiffMode('discount')" class="h-8 rounded-lg border text-[10px] font-black transition-all">
                                    Discount
                                </button>
                                <button type="button" id="diff-btn-balance" onclick="setDiffMode('balance')" class="h-8 rounded-lg border text-[10px] font-black transition-all">
                                    Balance
                                </button>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Payment Method</label>
                            <div class="mt-1 grid grid-cols-4 gap-1.5">
                                <button type="button" onclick="selectPaymentMethod('Cash')" id="pm-btn-Cash" class="h-8 rounded-lg border border-teal-600 bg-teal-600 text-[11px] font-black text-white shadow-2xs transition-all">Cash</button>
                                <button type="button" onclick="selectPaymentMethod('Online')" id="pm-btn-Online" class="h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all">Online</button>
                                <button type="button" onclick="selectPaymentMethod('GPay')" id="pm-btn-GPay" class="h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all">GPay</button>
                                <button type="button" onclick="selectPaymentMethod('Credit')" id="pm-btn-Credit" class="h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all">Credit</button>
                            </div>
                        </div>

                        <!-- Credit Discount & Optional Note Section -->
                        <div id="credit-discount-container" class="hidden space-y-2.5 rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="credit_discount_input" class="text-[10px] font-black uppercase tracking-wider text-slate-700">Discount (₹)</label>
                                    <span class="text-[10px] font-bold text-slate-400">Settlement discount</span>
                                </div>
                                <input id="credit_discount_input" type="number" step="0.01" min="0" placeholder="0.00" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 font-mono text-sm font-black text-slate-950 focus:border-teal-600 focus:outline-none">
                            </div>
                            <div>
                                <label for="credit_discount_note_input" class="text-[10px] font-black uppercase tracking-wider text-slate-700">Discount Note <span class="text-slate-400 font-normal">(Optional)</span></label>
                                <input id="credit_discount_note_input" type="text" placeholder="reason / settlement note" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-900 focus:border-teal-600 focus:outline-none">
                            </div>
                        </div>

                        <!-- Bill Ref No -->
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Bill Ref No (Optional)</label>
                            <input id="modal_bill_number" type="text" placeholder="Enter bill ref no" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none" oninput="window.syncBillNumber(this.value)">
                        </div>

                        <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 active:scale-95 transition-all shadow-xs">
                            Submit Payment
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const priceInputs = Array.from(document.querySelectorAll('.bill-price'));
            const subtotalNode = document.getElementById('bill-subtotal');
            let currentSubtotal = 0;
            let currentDiffMode = 'discount';
            let userToggledDiffMode = false;
            let currentPaymentMethod = 'Cash';

            const formatCurrency = (value) => `₹${Number(value).toFixed(2)}`;

            const recalculateSubtotal = () => {
                let subtotal = 0;
                priceInputs.forEach((input) => {
                    const quantity = Number(input.dataset.quantity || 0);
                    const unitPrice = Number(input.value || 0);
                    const lineTotal = quantity * unitPrice;
                    subtotal += lineTotal;

                    const row = input.closest('tr');
                    const lineTotalNode = row ? row.querySelector('.bill-line-total') : null;
                    if (lineTotalNode) {
                        lineTotalNode.textContent = formatCurrency(lineTotal);
                    }
                });
                currentSubtotal = subtotal;
                if (subtotalNode) subtotalNode.textContent = formatCurrency(subtotal);
            };

            priceInputs.forEach((input) => input.addEventListener('input', recalculateSubtotal));
            recalculateSubtotal();

            window.lockBackgroundScroll = () => {
                document.documentElement.classList.add('overflow-hidden', 'touch-none');
                document.body.classList.add('overflow-hidden', 'touch-none');
            };

            window.unlockBackgroundScroll = () => {
                document.documentElement.classList.remove('overflow-hidden', 'touch-none');
                document.body.classList.remove('overflow-hidden', 'touch-none');
            };

            window.syncBillNumber = (val) => {
                const hiddenInput = document.getElementById('bill_number_hidden');
                const displayNode = document.getElementById('bill-number-display');
                if (hiddenInput) hiddenInput.value = val;
                if (displayNode) displayNode.textContent = val.trim() ? val.trim() : 'Pending';
            };

            window.setDiffMode = (mode) => {
                userToggledDiffMode = true;
                currentDiffMode = mode;
                window.updatePaymentModalStatus();
            };

            window.selectPaymentMethod = (method) => {
                currentPaymentMethod = method;
                const methods = ['Cash', 'Online', 'GPay', 'Credit'];
                const input = document.getElementById('payment_method');
                if (input) input.value = method;

                methods.forEach(m => {
                    const btn = document.getElementById(`pm-btn-${m}`);
                    if (!btn) return;
                    if (m === method) {
                        btn.className = 'h-8 rounded-lg border border-teal-600 bg-teal-600 text-[11px] font-black text-white shadow-2xs transition-all';
                    } else {
                        btn.className = 'h-8 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition-all';
                    }
                });

                const creditSection = document.getElementById('credit-discount-container');
                const paidAmountContainer = document.getElementById('paid-amount-container');
                const creditDiscRow = document.getElementById('payment-modal-credit-discount-row');
                const creditAmtRow = document.getElementById('payment-modal-credit-amount-row');
                const creditDiscInput = document.getElementById('credit_discount_input');
                const creditNoteInput = document.getElementById('credit_discount_note_input');
                const hiddenNoteInput = document.getElementById('payment_note');

                if (method === 'Credit') {
                    if (creditSection) creditSection.classList.remove('hidden');
                    if (paidAmountContainer) paidAmountContainer.classList.add('hidden');
                    if (creditDiscRow) creditDiscRow.classList.remove('hidden');
                    if (creditAmtRow) creditAmtRow.classList.remove('hidden');

                    const hiddenPaidInput = document.getElementById('paid_amount');
                    if (hiddenPaidInput) hiddenPaidInput.value = '0.00';
                } else {
                    if (creditSection) creditSection.classList.add('hidden');
                    if (paidAmountContainer) paidAmountContainer.classList.remove('hidden');
                    if (creditDiscRow) creditDiscRow.classList.add('hidden');
                    if (creditAmtRow) creditAmtRow.classList.add('hidden');

                    if (creditDiscInput) creditDiscInput.value = '';
                    if (creditNoteInput) creditNoteInput.value = '';
                    if (hiddenNoteInput) hiddenNoteInput.value = '';
                }

                window.updatePaymentModalStatus();
            };

            window.openBillPaymentModal = () => {
                recalculateSubtotal();
                userToggledDiffMode = false;

                const hiddenBillInput = document.getElementById('bill_number_hidden');
                const modalBillInput = document.getElementById('modal_bill_number');
                if (hiddenBillInput && modalBillInput) {
                    modalBillInput.value = hiddenBillInput.value || '';
                }

                const totalNode = document.getElementById('payment-modal-total');
                if (totalNode) totalNode.textContent = formatCurrency(currentSubtotal);

                const addPaidInput = document.getElementById('additional_paid_amount');
                if (addPaidInput) addPaidInput.value = '';

                const creditDiscInput = document.getElementById('credit_discount_input');
                const creditNoteInput = document.getElementById('credit_discount_note_input');
                if (creditDiscInput) creditDiscInput.value = '';
                if (creditNoteInput) creditNoteInput.value = '';

                selectPaymentMethod('Cash');
                window.updatePaymentModalStatus();

                const modal = document.getElementById('payment-update-modal');
                if (modal) {
                    lockBackgroundScroll();
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            };

            window.closePaymentModal = () => {
                unlockBackgroundScroll();
                const modal = document.getElementById('payment-update-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            };

            window.updatePaymentModalStatus = () => {
                const addPaidInput = document.getElementById('additional_paid_amount');
                const hiddenPaidInput = document.getElementById('paid_amount');
                const discInput = document.getElementById('discount_amount');
                const diffContainer = document.getElementById('diff-action-container');
                const diffAmtNode = document.getElementById('diff-amount-label');
                const btnDiscount = document.getElementById('diff-btn-discount');
                const btnBalance = document.getElementById('diff-btn-balance');
                const creditDiscInput = document.getElementById('credit_discount_input');
                const creditNoteInput = document.getElementById('credit_discount_note_input');
                const hiddenNoteInput = document.getElementById('payment_note');
                const creditDiscNode = document.getElementById('payment-modal-credit-discount');
                const creditAmtNode = document.getElementById('payment-modal-credit-amount');
                const balanceNode = document.getElementById('payment-modal-balance');

                const totalBill = currentSubtotal;

                if (currentPaymentMethod === 'Credit') {
                    if (diffContainer) diffContainer.classList.add('hidden');

                    let discountVal = Math.max(0, Number(creditDiscInput?.value || 0));
                    if (discountVal > totalBill) {
                        discountVal = totalBill;
                        if (creditDiscInput) creditDiscInput.value = totalBill.toFixed(2);
                    }

                    const creditAmount = Math.max(0, totalBill - discountVal);
                    const remainingBalance = creditAmount;

                    if (discInput) discInput.value = discountVal.toFixed(2);
                    if (hiddenPaidInput) hiddenPaidInput.value = '0.00';
                    if (hiddenNoteInput) hiddenNoteInput.value = creditNoteInput?.value || '';

                    if (creditDiscNode) creditDiscNode.textContent = `-₹${discountVal.toFixed(2)}`;
                    if (creditAmtNode) creditAmtNode.textContent = formatCurrency(creditAmount);
                    if (balanceNode) {
                        balanceNode.textContent = formatCurrency(remainingBalance);
                        balanceNode.className = 'font-mono font-black text-amber-400';
                    }
                    return;
                }

                // Cash / Online / GPay
                const paidVal = Math.max(0, Number(addPaidInput?.value || 0));
                if (hiddenPaidInput) hiddenPaidInput.value = paidVal.toFixed(2);

                const rawDiff = Math.max(0, totalBill - paidVal);
                const diffPercent = totalBill > 0 ? (rawDiff / totalBill) * 100 : 0;

                if (rawDiff > 0.01 && paidVal > 0) {
                    if (diffContainer) diffContainer.classList.remove('hidden');
                    if (diffAmtNode) diffAmtNode.textContent = formatCurrency(rawDiff);

                    if (btnDiscount) btnDiscount.textContent = `Discount (${formatCurrency(rawDiff)})`;
                    if (btnBalance) btnBalance.textContent = `Balance (${formatCurrency(rawDiff)})`;

                    if (!userToggledDiffMode) {
                        currentDiffMode = diffPercent <= 5 ? 'discount' : 'balance';
                    }

                    if (currentDiffMode === 'discount') {
                        if (discInput) discInput.value = rawDiff.toFixed(2);
                        if (btnDiscount) btnDiscount.className = 'h-8 rounded-lg border border-teal-600 bg-teal-600 text-[10px] font-black text-white shadow-2xs transition-all';
                        if (btnBalance) btnBalance.className = 'h-8 rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-black text-slate-700 hover:bg-slate-100 transition-all';
                    } else {
                        if (discInput) discInput.value = '0.00';
                        if (btnBalance) btnBalance.className = 'h-8 rounded-lg border border-amber-600 bg-amber-600 text-[10px] font-black text-white shadow-2xs transition-all';
                        if (btnDiscount) btnDiscount.className = 'h-8 rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-black text-slate-700 hover:bg-slate-100 transition-all';
                    }
                } else {
                    if (diffContainer) diffContainer.classList.add('hidden');
                    if (discInput) discInput.value = '0.00';
                }

                const discountVal = Math.max(0, Number(discInput?.value || 0));
                const netDue = Math.max(0, totalBill - discountVal);
                const balance = Math.max(0, netDue - paidVal);

                if (balanceNode) {
                    balanceNode.textContent = formatCurrency(balance);
                    balanceNode.className = balance > 0 ? 'font-mono font-black text-amber-400' : 'font-mono font-black text-emerald-400';
                }
            };

            let isSubmittingBill = false;
            window.submitBillPaymentForm = (btn) => {
                if (isSubmittingBill) return false;

                const form = document.getElementById('bill-main-form');
                if (!form) return false;

                if (form.dataset.submitting === 'true') return false;
                form.dataset.submitting = 'true';
                isSubmittingBill = true;

                const modalBillInput = document.getElementById('modal_bill_number');
                const hiddenBillInput = document.getElementById('bill_number_hidden');
                if (modalBillInput && hiddenBillInput) {
                    hiddenBillInput.value = modalBillInput.value;
                }

                const submitBtn = btn || document.getElementById('confirm-submit-btn') || document.querySelector('#paymentModal button[onclick*="submitBillPaymentForm"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'pointer-events-none');
                    submitBtn.innerText = 'Submitting...';
                }

                form.submit();
            };

            document.getElementById('additional_paid_amount')?.addEventListener('input', window.updatePaymentModalStatus);
            document.getElementById('credit_discount_input')?.addEventListener('input', window.updatePaymentModalStatus);
            document.getElementById('credit_discount_note_input')?.addEventListener('input', window.updatePaymentModalStatus);
        })();
    </script>
</x-layouts.app>
