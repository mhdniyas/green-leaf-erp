<x-layouts.app title="Purchaser Vendor Hub">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Vendor Hub</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Purchaser vendors</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Search vendors, review recent purchases, and open pending payment follow-up from one mobile-friendly desk.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <a href="{{ route('purchaser.vendors', ['date' => $date]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-white">
                        Open Carts
                    </a>
                    <form action="{{ route('purchaser.suppliers') }}" method="GET">
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search vendor, mobile, location..." class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none sm:w-72">
                    </form>
                </div>
            </div>
        </section>

        @php
            $hasProblems = $issueSections->contains(fn (array $section): bool => (int) $section['count'] > 0);
        @endphp

        <section class="rounded-2xl border {{ $hasProblems ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-col gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] {{ $hasProblems ? 'text-rose-700' : 'text-emerald-700' }}">Problems</p>
                    <h2 class="mt-1 text-lg font-black {{ $hasProblems ? 'text-rose-900' : 'text-emerald-900' }}">
                        @if ($hasProblems)
                            Resolve issues vendor by vendor
                        @else
                            All purchaser issues resolved
                        @endif
                    </h2>
                    <p class="mt-1 text-xs font-semibold {{ $hasProblems ? 'text-rose-800' : 'text-emerald-800' }}">
                        @if ($hasProblems)
                            Open the right vendor from each issue section and finish the action from the popup.
                        @else
                            No pending bill, receipt, or overdue issue is left for this purchaser flow.
                        @endif
                    </p>
                </div>

                <div class="grid gap-3">
                    @foreach ($issueSections as $section)
                        @php
                            $sectionHasRows = (int) $section['count'] > 0;
                        @endphp
                        <article class="rounded-2xl border {{ $sectionHasRows ? 'border-rose-200 bg-white' : 'border-emerald-200 bg-white/90' }} p-3 shadow-sm lg:rounded-[1.75rem] lg:p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-black text-slate-950">{{ $section['label'] }}</h3>
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black {{ $sectionHasRows ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                            {{ $section['count'] }} {{ \Illuminate\Support\Str::plural('issue', (int) $section['count']) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ $section['description'] }}</p>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-2">
                                @forelse ($section['rows'] as $row)
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate text-sm font-black text-slate-950">{{ $row['supplier']->name }}</p>
                                                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-600">
                                                        {{ $row['cart']->cart_number }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                                    Business date {{ $row['cart']->business_date->format('d M Y') }}
                                                    @if ($row['supplier']->mobile_number)
                                                        • {{ $row['supplier']->mobile_number }}
                                                    @endif
                                                </p>
                                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $row['popup_message'] }}</p>
                                            </div>
                                            @if (($row['action_type'] ?? '') === 'confirm_receipt')
                                                <form
                                                    action="{{ $row['route'] }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Are you sure you want to confirm warehouse receipt for this cart?')"
                                                    data-confirm-title="{{ $row['popup_title'] }}"
                                                    data-confirm-button="{{ $row['button'] }}"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="flag" value="goods_received">
                                                    <input type="hidden" name="return_to" value="suppliers">
                                                    <input type="hidden" name="date" value="{{ $date }}">
                                                    <button
                                                        type="submit"
                                                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white transition hover:bg-teal-700 cursor-pointer"
                                                    >
                                                        {{ $row['button'] }}
                                                    </button>
                                                </form>
                                            @elseif (($row['action_type'] ?? '') === 'process_bill')
                                                <button
                                                    type="button"
                                                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 px-4 text-xs font-black text-white transition hover:bg-rose-700 cursor-pointer"
                                                    onclick="openDirectProcessBillModal(this)"
                                                    data-cart-id="{{ $row['cart']->id }}"
                                                    data-supplier-id="{{ $row['supplier']->id }}"
                                                    data-cart-number="{{ $row['cart']->cart_number }}"
                                                    data-supplier-name="{{ $row['supplier']->name }}"
                                                    data-credit-approved="{{ $row['supplier']->credit_approved ? 'true' : 'false' }}"
                                                    data-cart-items="{{ json_encode($row['cart_items']) }}"
                                                    data-business-date="{{ $row['cart']->business_date->format('Y-m-d') }}"
                                                >
                                                    {{ $row['button'] }}
                                                </button>
                                            @elseif (($row['action_type'] ?? '') === 'update_payment')
                                                <button
                                                    type="button"
                                                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white transition hover:bg-slate-800 cursor-pointer"
                                                    onclick="openDirectPaymentModal(this)"
                                                    data-invoice-id="{{ $row['invoice']['id'] }}"
                                                    data-invoice-number="{{ $row['invoice']['number'] }}"
                                                    data-supplier-name="{{ $row['invoice']['supplier'] }}"
                                                    data-amount="{{ $row['invoice']['amount'] }}"
                                                    data-discount-amount="{{ $row['invoice']['discountAmount'] }}"
                                                    data-paid-amount="{{ $row['invoice']['paidAmount'] }}"
                                                    data-payment-method="{{ $row['invoice']['paymentMethod'] }}"
                                                    data-payment-note="{{ $row['invoice']['paymentNote'] }}"
                                                    data-payment-details="{{ $row['invoice']['paymentDetails'] }}"
                                                    data-credit-approved="{{ $row['invoice']['creditApproved'] ? 'true' : 'false' }}"
                                                    data-payment-route="{{ $row['payment_route'] }}"
                                                >
                                                    {{ $row['button'] }}
                                                </button>
                                            @else
                                                <a
                                                    href="{{ $row['route'] }}"
                                                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 px-4 text-xs font-black text-white transition hover:bg-rose-700"
                                                >
                                                    {{ $row['button'] }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 px-3 py-4 text-center text-xs font-bold text-emerald-700">
                                        {{ $section['empty'] }}
                                    </div>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="grid gap-3">
            @forelse ($suppliers as $supplier)
                @php
                    $recentInvoice = $supplier->purchaseInvoices->first();
                    $pendingCount = $supplier->purchaseInvoices->where('payment_status', '!=', 'paid')->count();
                    $recentCart = $supplier->purchaserCarts->first();
                @endphp
                <a href="{{ route('purchaser.suppliers.show', ['supplier' => $supplier, 'date' => $date]) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-teal-200 hover:bg-slate-50 lg:rounded-[2rem]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-sm font-black text-slate-950">{{ $supplier->name }}</h2>
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $supplier->credit_approved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $supplier->credit_approved ? 'Credit Approved' : 'Cash / Review' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-600">{{ $supplier->mobile_number ?: 'Mobile pending' }}{{ $supplier->location ? ' • '.$supplier->location : '' }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-700">{{ $pendingCount }} pending</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Recent bill</p>
                            <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $recentInvoice?->invoice_number ?: 'None yet' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Last amount</p>
                            <p class="mt-1 truncate text-[11px] font-black text-slate-900">₹{{ number_format((float) ($recentInvoice?->amount ?? 0), 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Last cart</p>
                            <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $recentCart?->cart_number ?: 'No cart yet' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Updated</p>
                            <p class="mt-1 truncate text-[11px] font-black text-slate-900">{{ $recentInvoice?->updated_at?->format('d M') ?: '—' }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem]">
                    No vendors found for this purchaser history yet.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Direct Bill Modal -->
    <div id="direct-bill-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeDirectProcessBillModal()">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-4 shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Process Bill</h3>
                    <p id="direct-bill-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeDirectProcessBillModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="direct-bill-form" action="{{ route('purchaser.carts.submit') }}" method="POST" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="return_to" value="suppliers">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="business_date" id="direct-bill-business-date">
                <input type="hidden" name="cart_id" id="direct-bill-cart-id">
                <input type="hidden" name="supplier_id" id="direct-bill-supplier-id">

                <!-- Cart Items Container -->
                <div class="space-y-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Items Price Entry</p>
                    <div id="direct-bill-items-container" class="space-y-1.5 max-h-48 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50 p-2">
                        <!-- Dynamic items will be added here -->
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                        <span>Subtotal</span>
                        <span id="direct-bill-subtotal" class="font-bold text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <label for="direct-bill-discount" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500 shrink-0">Discount</label>
                        <input id="direct-bill-discount" type="number" step="0.01" min="0" name="discount_amount" value="0.00" class="h-8 w-24 rounded-md border border-slate-200 bg-white px-2 text-right text-xs font-semibold text-slate-900 focus:outline-none">
                    </div>
                    <div class="mt-2.5 border-t border-slate-200/60 pt-2 flex items-center justify-between text-sm font-black text-slate-950">
                        <span>Total Payable</span>
                        <span id="direct-bill-total">₹ 0.00</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div>
                        <label for="direct-bill-number" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Bill / Invoice Number</label>
                        <input id="direct-bill-number" type="text" name="bill_number" class="mt-1 h-8 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Payment Method</p>
                        <div class="mt-1 grid grid-cols-4 gap-1.5">
                            @foreach (['Cash' => 'Paid', 'GPay' => 'UPI', 'Online' => 'Transfer', 'Credit' => 'Later'] as $method => $caption)
                                <label class="flex cursor-pointer items-center gap-1 rounded-md border border-slate-200 bg-slate-50 p-1.5 hover:bg-slate-100">
                                    <input type="radio" name="payment_method" value="{{ $method }}" class="direct-bill-payment-method h-3.5 w-3.5 border-slate-300 text-teal-600 focus:ring-teal-500" @checked($loop->first)>
                                    <span class="min-w-0">
                                        <span class="block text-[10px] font-black text-slate-900 leading-none">{{ $method }}</span>
                                        <span class="block text-[8px] font-semibold text-slate-500 leading-none mt-0.5 truncate">{{ $caption }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p id="direct-bill-credit-warning" class="hidden mt-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-[10px] font-semibold text-amber-800">Credit is blocked for this supplier until approval.</p>
                    </div>
                    <div>
                        <label for="direct-bill-paid" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Amount Paid Now</label>
                        <input id="direct-bill-paid" type="number" step="0.01" min="0" name="paid_amount" value="0.00" class="mt-1 h-8 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    </div>
                    <div>
                        <label for="direct-bill-payment-note" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                        <input id="direct-bill-payment-note" type="text" name="payment_note" class="mt-1 h-8 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    </div>
                    <div>
                        <label for="direct-bill-payment-details" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Reference / Settlement Details</label>
                        <textarea id="direct-bill-payment-details" name="payment_details" rows="2" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label for="direct-bill-notes" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Notes</label>
                        <textarea id="direct-bill-notes" name="notes" rows="2" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
                    </div>
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-sm cursor-pointer">Submit Purchase</button>
            </form>
        </div>
    </div>

    <!-- Direct Payment Modal -->
    <div id="direct-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeDirectPaymentModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Payment Update</h3>
                    <p id="direct-payment-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeDirectPaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="direct-payment-form" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_to" value="suppliers">
                <input type="hidden" name="date" value="{{ $date }}">

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Total Bill</span>
                        <span id="direct-payment-total" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Discount</span>
                        <span id="direct-payment-discount-val" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Net Payable</span>
                        <span id="direct-payment-net" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Paid Amount</span>
                        <span id="direct-payment-paid-val" class="text-slate-900">₹ 0.00</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Remaining</span>
                        <span id="direct-payment-balance" class="text-amber-700">₹ 0.00</span>
                    </div>
                    <p id="direct-payment-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
                </div>

                <div>
                    <label for="direct_payment_discount_amount" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount</label>
                    <input id="direct_payment_discount_amount" type="number" step="0.01" min="0" name="discount_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="direct_payment_method" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                    <select id="direct_payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="GPay">GPay</option>
                        <option value="Credit">Credit</option>
                    </select>
                </div>

                <div>
                    <label for="direct_payment_paid_amount" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Paid Amount</label>
                    <input id="direct_payment_paid_amount" type="number" step="0.01" min="0" name="paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="direct_payment_note" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                    <input id="direct_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="direct_payment_details" class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Details</label>
                    <textarea id="direct_payment_details" name="payment_details" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 cursor-pointer">Save Payment Update</button>
            </form>
        </div>
    </div>

    <script>
        // ── Direct Process Bill Modal logic ──
        function openDirectProcessBillModal(btn) {
            const cartId = btn.dataset.cartId;
            const supplierId = btn.dataset.supplierId;
            const cartNumber = btn.dataset.cartNumber;
            const supplierName = btn.dataset.supplierName;
            const creditApproved = btn.dataset.creditApproved === 'true';
            const businessDate = btn.dataset.businessDate;
            const items = JSON.parse(btn.dataset.cartItems || '[]');

            document.getElementById('direct-bill-title').textContent = `${cartNumber} • ${supplierName}`;
            document.getElementById('direct-bill-cart-id').value = cartId;
            document.getElementById('direct-bill-supplier-id').value = supplierId;
            document.getElementById('direct-bill-business-date').value = businessDate;

            const container = document.getElementById('direct-bill-items-container');
            container.innerHTML = '';

            items.forEach((item) => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'flex items-center justify-between gap-3 bg-white p-2 rounded-lg border border-slate-100';
                
                const prevPriceSpan = item.prev_price > 0 
                    ? `<span class="text-[8px] font-bold text-amber-700">Prev ₹${item.prev_price.toFixed(2)}</span>`
                    : '';

                itemDiv.innerHTML = `
                    <div class="min-w-0 flex-1">
                        <p class="font-black text-slate-950 truncate text-[11px]">${item.product_name}</p>
                        <p class="text-[9px] font-semibold text-slate-500">${item.quantity} ${item.unit}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col items-center">
                            <input type="number" step="0.01" min="0" 
                                name="items[${item.id}][unit_price]" 
                                value="${item.unit_price || ''}" 
                                class="direct-bill-item-price h-7 w-16 text-center text-[10px] font-semibold border border-slate-200 rounded-md focus:outline-none" 
                                data-quantity="${item.quantity}">
                            ${prevPriceSpan}
                        </div>
                        <span class="text-[10px] font-black text-slate-700 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded-md min-w-16 text-right direct-bill-item-total">
                            ₹ 0.00
                        </span>
                    </div>
                `;
                container.appendChild(itemDiv);
            });

            // Credit warning display
            const creditWarning = document.getElementById('direct-bill-credit-warning');
            const paymentMethods = document.querySelectorAll('.direct-bill-payment-method');

            const updateCreditWarning = () => {
                const selectedMethod = document.querySelector('.direct-bill-payment-method:checked')?.value;
                if (selectedMethod === 'Credit' && !creditApproved) {
                    creditWarning.classList.remove('hidden');
                } else {
                    creditWarning.classList.add('hidden');
                }
            };

            paymentMethods.forEach((input) => input.addEventListener('change', updateCreditWarning));

            const formatCurrency = (val) => `₹ ${val.toFixed(2)}`;

            const recalculateBill = () => {
                let subtotal = 0;
                document.querySelectorAll('.direct-bill-item-price').forEach((input) => {
                    const qty = parseFloat(input.dataset.quantity || '0');
                    const price = parseFloat(input.value || '0');
                    const total = qty * price;
                    subtotal += total;
                    input.closest('.flex.items-center.gap-2').querySelector('.direct-bill-item-total').textContent = formatCurrency(total);
                });

                const discountInput = document.getElementById('direct-bill-discount');
                const discount = parseFloat(discountInput.value || '0');
                const netPayable = Math.max(0, subtotal - discount);

                document.getElementById('direct-bill-subtotal').textContent = formatCurrency(subtotal);
                document.getElementById('direct-bill-total').textContent = formatCurrency(netPayable);

                // Auto-fill paid amount if payment method is Cash/UPI/Online and not Credit
                const selectedMethod = document.querySelector('.direct-bill-payment-method:checked')?.value;
                const paidInput = document.getElementById('direct-bill-paid');
                if (selectedMethod !== 'Credit' && (paidInput.dataset.manuallyEdited !== 'true')) {
                    paidInput.value = netPayable.toFixed(2);
                }
            };

            const priceInputs = document.querySelectorAll('.direct-bill-item-price');
            priceInputs.forEach((input) => input.addEventListener('input', recalculateBill));

            const discountInput = document.getElementById('direct-bill-discount');
            discountInput.addEventListener('input', recalculateBill);

            const paidInput = document.getElementById('direct-bill-paid');
            paidInput.dataset.manuallyEdited = 'false';
            paidInput.addEventListener('input', () => {
                paidInput.dataset.manuallyEdited = 'true';
            });

            paymentMethods.forEach((radio) => {
                radio.addEventListener('change', () => {
                    updateCreditWarning();
                    const selectedMethod = radio.value;
                    const paidInput = document.getElementById('direct-bill-paid');
                    if (selectedMethod === 'Credit') {
                        paidInput.value = '0.00';
                    } else if (paidInput.dataset.manuallyEdited !== 'true') {
                        const netPayableText = document.getElementById('direct-bill-total').textContent;
                        const netPayableVal = parseFloat(netPayableText.replace('₹', '').trim()) || 0;
                        paidInput.value = netPayableVal.toFixed(2);
                    }
                });
            });

            document.getElementById('direct-bill-modal').classList.remove('hidden');
            document.getElementById('direct-bill-modal').classList.add('flex');
            recalculateBill();
            updateCreditWarning();
        }

        function closeDirectProcessBillModal() {
            document.getElementById('direct-bill-modal').classList.add('hidden');
            document.getElementById('direct-bill-modal').classList.remove('flex');
        }

        // ── Direct Payment Modal logic ──
        function openDirectPaymentModal(btn) {
            const invoiceId = btn.dataset.invoiceId;
            const invoiceNumber = btn.dataset.invoiceNumber;
            const supplierName = btn.dataset.supplierName;
            const amount = parseFloat(btn.dataset.amount || '0');
            const initialDiscount = parseFloat(btn.dataset.discountAmount || '0');
            const initialPaid = parseFloat(btn.dataset.paidAmount || '0');
            const paymentMethod = btn.dataset.paymentMethod;
            const paymentNote = btn.dataset.paymentNote || '';
            const paymentDetails = btn.dataset.paymentDetails || '';
            const creditApproved = btn.dataset.creditApproved === 'true';
            const actionRoute = btn.dataset.paymentRoute;

            document.getElementById('direct-payment-title').textContent = `${invoiceNumber} • ${supplierName}`;
            document.getElementById('direct-payment-form').action = actionRoute;

            document.getElementById('direct-payment-total').textContent = `₹ ${amount.toFixed(2)}`;

            const discountInput = document.getElementById('direct_payment_discount_amount');
            discountInput.value = initialDiscount.toFixed(2);

            const paidInput = document.getElementById('direct_payment_paid_amount');
            paidInput.value = initialPaid.toFixed(2);

            const selectMethod = document.getElementById('direct_payment_method');
            selectMethod.value = paymentMethod;

            document.getElementById('direct_payment_note').value = paymentNote;
            document.getElementById('direct_payment_details').value = paymentDetails;

            const recalculatePayment = () => {
                const discount = parseFloat(discountInput.value || '0');
                const paid = parseFloat(paidInput.value || '0');
                const netPayable = Math.max(0, amount - discount);
                const balance = Math.max(0, netPayable - paid);

                document.getElementById('direct-payment-discount-val').textContent = `₹ ${discount.toFixed(2)}`;
                document.getElementById('direct-payment-net').textContent = `₹ ${netPayable.toFixed(2)}`;
                document.getElementById('direct-payment-paid-val').textContent = `₹ ${paid.toFixed(2)}`;
                document.getElementById('direct-payment-balance').textContent = `₹ ${balance.toFixed(2)}`;

                const warning = document.getElementById('direct-payment-warning');
                if (selectMethod.value === 'Credit' && !creditApproved) {
                    warning.textContent = 'This vendor is not approved for credit.';
                } else if (balance > 0) {
                    warning.textContent = `Partial payment. ₹ ${balance.toFixed(2)} remaining.`;
                } else {
                    warning.textContent = '';
                }
            };

            discountInput.addEventListener('input', recalculatePayment);
            paidInput.addEventListener('input', recalculatePayment);
            selectMethod.addEventListener('change', recalculatePayment);

            document.getElementById('direct-payment-modal').classList.remove('hidden');
            document.getElementById('direct-payment-modal').classList.add('flex');
            recalculatePayment();
        }

        function closeDirectPaymentModal() {
            document.getElementById('direct-payment-modal').classList.add('hidden');
            document.getElementById('direct-payment-modal').classList.remove('flex');
        }
    </script>
</x-layouts.app>
