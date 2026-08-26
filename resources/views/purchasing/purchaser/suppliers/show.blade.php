<x-layouts.app title="Purchaser Vendor Details">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-5xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        {{-- ═══════════════════════════════════════════════════════════
             ROW 1 · Compact Page Header  (matches report/history style)
        ════════════════════════════════════════════════════════════════ --}}
        <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs lg:rounded-2xl">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">Vendor Detail</span>
                    <h1 class="text-base font-black text-slate-950">{{ $supplier->name }}</h1>
                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] {{ $supplier->credit_approved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $supplier->credit_approved ? 'Credit Approved' : 'Cash Terms' }}
                    </span>
                </div>
                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">
                    {{ $supplier->mobile_number ?: 'Mobile pending' }}{{ $supplier->location ? ' • ' . $supplier->location : '' }}{{ $supplier->credit_terms ? ' • ' . $supplier->credit_terms : '' }}
                </p>
            </div>

            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                <a href="{{ route('purchaser.suppliers', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 hover:bg-white shrink-0">
                    ← Vendor Hub
                </a>
                <a href="{{ route('purchaser.history', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 hover:bg-white shrink-0">
                    Purchase Report
                </a>
                <a href="{{ route('purchaser.finance', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center rounded-lg bg-slate-950 px-3 text-xs font-black text-white hover:bg-slate-800 shrink-0">
                    Finance Desk
                </a>

                @php
                    // Calculate pending bills for bulk payment
                    $pendingBills = collect();
                    $totalPendingAmount = 0.0;
                    foreach ($vendorHistory ?? [] as $day) {
                        foreach ($day['entries'] as $entry) {
                            if (!empty($entry['invoice_id'])) {
                                $entryPending = max(0.0, (float) $entry['amount'] - (float) $entry['paid_amount']);
                                if ($entryPending > 0) {
                                    $pendingBills->push([
                                        'id' => $entry['invoice_id'],
                                        'invoice_number' => $entry['invoice_number'] ?: 'PENDING-' . $entry['cart_number'],
                                        'cart_number' => $entry['cart_number'],
                                        'date' => $day['date_label'],
                                        'amount' => (float) $entry['amount'],
                                        'paid' => (float) $entry['paid_amount'],
                                        'pending' => $entryPending,
                                    ]);
                                    $totalPendingAmount += $entryPending;
                                }
                            }
                        }
                    }
                @endphp

                @if ($pendingBills->isNotEmpty())
                    <a
                        href="{{ route('purchaser.suppliers.bulk-payment.show', ['supplier' => $supplier, 'date' => $date]) }}"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-gradient-to-r from-teal-600 to-emerald-600 px-3 text-xs font-black text-white hover:from-teal-500 hover:to-emerald-500 shrink-0 ml-auto"
                    >
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/><circle cx="12" cy="12" r="3"/></svg>
                        Pay Bills
                        <span class="rounded-full bg-white/20 px-1.5 py-0.5 text-[9px]">₹{{ number_format($totalPendingAmount, 0) }}</span>
                    </a>
                @endif
            </div>

            @if (filled($supplier->bank_details))
                <div class="mt-3 rounded-xl border border-teal-200 bg-teal-50/70 px-3 py-2.5">
                    <p class="text-[9px] font-black uppercase tracking-[0.14em] text-teal-800">Banking Details / Notes</p>
                    <p class="mt-1 whitespace-pre-line text-xs font-bold text-teal-950">{{ $supplier->bank_details }}</p>
                </div>
            @endif
        </section>

        @if ($vendorHistory->isEmpty() && $cancelledHistory->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem]">
                No vendor history found yet.
            </div>
        @else
            @if ($vendorHistory->isNotEmpty())
                {{-- Desktop Table View --}}
                <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block lg:rounded-[2rem]">
                    @foreach ($vendorHistory as $day)
                        @foreach ($day['entries'] as $entry)
                            @php
                                $entryPending = max(0.0, (float) $entry['amount'] - (float) $entry['paid_amount']);
                                $billModalPayload = [
                                    'supplierName'   => $supplier->name,
                                    'supplierMobile' => $supplier->mobile_number ?: '',
                                    'billRef'        => $entry['cart_number'],
                                    'invoiceNumber'  => $entry['invoice_number'] ?: ('PENDING-' . $entry['cart_number']),
                                    'date'           => $day['date_label'],
                                    'paymentStatus'  => $entry['payment_status'],
                                    'isCancelled'    => false,
                                    'totalAmount'    => '₹' . number_format((float) $entry['amount'], 2),
                                    'cashAmount'     => '₹' . number_format((float) $entry['paid_amount'], 2),
                                    'creditAmount'   => '₹' . number_format($entryPending, 2),
                                    'grnNumber'      => $entry['receipt_notes'] ? 'See notes below' : 'Pending',
                                    'items'          => collect($entry['item_summary'])->values()->all(),
                                ];
                            @endphp
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50/80">
                                <div class="flex-1">
                                    <p class="text-sm font-black text-slate-950">{{ $day['date_label'] }}</p>
                                    <p class="text-[11px] font-semibold text-slate-500">{{ $entry['cart_number'] }} · {{ $entry['item_count'] }} items</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($entryPending > 0)
                                        <div class="text-right">
                                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Pending</p>
                                            <p class="text-xs font-black text-amber-700">₹{{ number_format($entryPending, 0) }}</p>
                                        </div>
                                    @endif
                                    <button
                                        type="button"
                                        onclick='openVendorBillModal(@json($billModalPayload))'
                                        class="inline-flex h-8 items-center rounded-lg bg-teal-600 px-3 text-[11px] font-black text-white hover:bg-teal-500 shadow-2xs"
                                    >
                                        View Bill
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>

                {{-- Mobile Compact Cards --}}
                <div class="space-y-2 lg:hidden">
                    @foreach ($vendorHistory as $day)
                        @foreach ($day['entries'] as $entry)
                            @php
                                $mobileEntryPending = max(0.0, (float) $entry['amount'] - (float) $entry['paid_amount']);
                                $mobileBillModalPayload = [
                                    'supplierName'   => $supplier->name,
                                    'supplierMobile' => $supplier->mobile_number ?: '',
                                    'billRef'        => $entry['cart_number'],
                                    'invoiceNumber'  => $entry['invoice_number'] ?: ('PENDING-' . $entry['cart_number']),
                                    'date'           => $day['date_label'],
                                    'paymentStatus'  => $entry['payment_status'],
                                    'isCancelled'    => false,
                                    'totalAmount'    => '₹' . number_format((float) $entry['amount'], 2),
                                    'cashAmount'     => '₹' . number_format((float) $entry['paid_amount'], 2),
                                    'creditAmount'   => '₹' . number_format($mobileEntryPending, 2),
                                    'grnNumber'      => $entry['receipt_notes'] ? 'See notes below' : 'Pending',
                                    'items'          => collect($entry['item_summary'])->values()->all(),
                                ];
                            @endphp
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-black text-slate-950">{{ $day['date_label'] }}</p>
                                    <p class="text-[11px] font-semibold text-slate-500">{{ $entry['cart_number'] }} · {{ $entry['item_count'] }} items</p>
                                    @if ($mobileEntryPending > 0)
                                        <p class="mt-1 text-xs font-black text-amber-700">Pending: ₹{{ number_format($mobileEntryPending, 0) }}</p>
                                    @endif
                                </div>
                                <button
                                    type="button"
                                    onclick='openVendorBillModal(@json($mobileBillModalPayload))'
                                    class="inline-flex h-8 items-center rounded-lg bg-teal-600 px-3 text-[11px] font-black text-white hover:bg-teal-500 shadow-2xs shrink-0"
                                >
                                    View Bill
                                </button>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endif

            {{-- Section 2: Cancelled Purchases (Separate Section) --}}
            @if ($cancelledHistory->isNotEmpty())
                <section class="mt-4 rounded-xl border border-rose-200 bg-white p-3 shadow-xs lg:rounded-2xl lg:p-4">
                    <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="rounded-md bg-rose-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-rose-700">Cancelled</span>
                            <h2 class="text-sm font-black text-slate-950">Cancelled Purchases</h2>
                        </div>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black text-rose-700">
                            {{ $cancelledHistory->sum('record_count') }} {{ Str::plural('cart', $cancelledHistory->sum('record_count')) }}
                        </span>
                    </div>

                    {{-- Desktop Table View --}}
                    <div class="hidden overflow-hidden rounded-xl border border-rose-100 bg-white shadow-2xs lg:block">
                        @foreach ($cancelledHistory as $day)
                            @foreach ($day['entries'] as $entry)
                                @php
                                    $billModalPayload = [
                                        'supplierName'   => $supplier->name,
                                        'supplierMobile' => $supplier->mobile_number ?: '',
                                        'billRef'        => $entry['cart_number'],
                                        'invoiceNumber'  => $entry['invoice_number'] ?: ('CANCELLED-' . $entry['cart_number']),
                                        'date'           => $day['date_label'],
                                        'paymentStatus'  => 'Cancelled',
                                        'isCancelled'    => true,
                                        'totalAmount'    => '₹' . number_format((float) $entry['amount'], 2),
                                        'cashAmount'     => '₹0.00',
                                        'creditAmount'   => '₹0.00',
                                        'grnNumber'      => 'Cancelled',
                                        'items'          => collect($entry['item_summary'])->values()->all(),
                                    ];
                                @endphp
                                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-rose-50/30">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-black text-slate-950">{{ $day['date_label'] }}</p>
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-rose-700">Cancelled</span>
                                        </div>
                                        <p class="text-[11px] font-semibold text-slate-500">{{ $entry['cart_number'] }} · {{ $entry['item_count'] }} items · ₹{{ number_format((float) $entry['amount'], 2) }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onclick='openVendorBillModal(@json($billModalPayload))'
                                            class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-[11px] font-black text-rose-700 hover:bg-rose-100 shadow-2xs"
                                        >
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>

                    {{-- Mobile Compact Cards --}}
                    <div class="space-y-2 lg:hidden">
                        @foreach ($cancelledHistory as $day)
                            @foreach ($day['entries'] as $entry)
                                @php
                                    $mobileBillModalPayload = [
                                        'supplierName'   => $supplier->name,
                                        'supplierMobile' => $supplier->mobile_number ?: '',
                                        'billRef'        => $entry['cart_number'],
                                        'invoiceNumber'  => $entry['invoice_number'] ?: ('CANCELLED-' . $entry['cart_number']),
                                        'date'           => $day['date_label'],
                                        'paymentStatus'  => 'Cancelled',
                                        'isCancelled'    => true,
                                        'totalAmount'    => '₹' . number_format((float) $entry['amount'], 2),
                                        'cashAmount'     => '₹0.00',
                                        'creditAmount'   => '₹0.00',
                                        'grnNumber'      => 'Cancelled',
                                        'items'          => collect($entry['item_summary'])->values()->all(),
                                    ];
                                @endphp
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-white p-3 shadow-2xs">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-black text-slate-950">{{ $day['date_label'] }}</p>
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-rose-700">Cancelled</span>
                                        </div>
                                        <p class="text-[11px] font-semibold text-slate-500">{{ $entry['cart_number'] }} · {{ $entry['item_count'] }} items · ₹{{ number_format((float) $entry['amount'], 2) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onclick='openVendorBillModal(@json($mobileBillModalPayload))'
                                        class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-[11px] font-black text-rose-700 hover:bg-rose-100 shadow-2xs shrink-0"
                                    >
                                        View
                                    </button>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         Bill Details Bottom Sheet Modal
    ════════════════════════════════════════════════════════════════ --}}
    <div id="vendor-bill-modal" class="fixed inset-0 z-[100] hidden flex-col justify-end bg-slate-900/60 backdrop-blur-xs overscroll-none touch-none transition-opacity duration-200" onclick="if (event.target === this) closeVendorBillModal()">
        <div class="relative flex max-h-[85vh] w-full max-w-lg mx-auto flex-col rounded-t-3xl bg-white shadow-2xl transition-transform duration-300 touch-pan-y">
            <!-- Sticky Header -->
            <div class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-100 bg-white px-4 py-3 rounded-t-3xl">
                <h3 class="text-sm font-black text-slate-950">Bill Details</h3>
                <button type="button" onclick="closeVendorBillModal()" class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 text-xs font-bold">✕</button>
            </div>
            <!-- Scrollable Body -->
            <div class="flex-1 overflow-y-auto overscroll-contain [-webkit-overflow-scrolling:touch] p-4 space-y-3 text-xs font-semibold text-slate-700">
                <!-- Vendor Info -->
                <div class="border-b border-slate-100 pb-2">
                    <h4 id="vb-supplier-name" class="text-sm font-black text-slate-950"></h4>
                    <p id="vb-supplier-mobile" class="mt-0.5 text-[11px] text-slate-500 font-medium"></p>
                </div>
                <!-- Reference & Details Grid -->
                <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-2.5 text-xs border border-slate-100">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Bill Ref</p>
                        <p id="vb-bill-ref" class="font-mono font-bold text-slate-900 mt-0.5 text-[11px]"></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Date</p>
                        <p id="vb-date" class="font-bold text-slate-900 mt-0.5 text-[11px]"></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Invoice</p>
                        <p id="vb-invoice" class="font-mono font-bold text-teal-700 mt-0.5 text-[11px] break-all"></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Payment Status</p>
                        <p id="vb-payment-status" class="font-black text-amber-700 mt-0.5 text-[11px]"></p>
                    </div>
                </div>
                <!-- Amount Summary -->
                <div class="rounded-xl border border-slate-900 bg-slate-950 p-3 text-white shadow-xs">
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Amount Summary</p>
                    <div class="mt-1.5 space-y-1 text-xs">
                        <div class="flex justify-between font-black text-xs text-white">
                            <span>Total</span>
                            <span id="vb-total"></span>
                        </div>
                        <div id="vb-paid-row" class="flex justify-between text-emerald-400 font-bold text-[11px]">
                            <span>Paid</span>
                            <span id="vb-paid"></span>
                        </div>
                        <div id="vb-credit-row" class="flex justify-between text-amber-400 font-bold text-[11px]">
                            <span>Pending</span>
                            <span id="vb-credit"></span>
                        </div>
                    </div>
                </div>
                <!-- Items Section -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                        <h4 class="font-black text-slate-950 text-xs">Items — <span id="vb-items-count">0</span></h4>
                    </div>
                    <div id="vb-items-list" class="mt-1 divide-y divide-slate-100 text-xs max-h-72 overflow-y-auto overscroll-contain [-webkit-overflow-scrolling:touch] pr-1"></div>
                    <!-- Items Total Summary -->
                    <div class="mt-2 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-600">Items Total</span>
                        <span id="vb-items-total" class="font-mono text-sm font-black text-slate-950">₹0.00</span>
                    </div>
                </div>
                <!-- GRN -->
                <div class="border-t border-slate-200 pt-2">
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">GRN</p>
                    <p id="vb-grn" class="font-mono font-bold text-slate-800 mt-0.5 text-[11px]"></p>
                </div>
            </div>
            <!-- Sticky Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-100 bg-white p-3">
                <button type="button" onclick="closeVendorBillModal()" class="w-full inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-xs font-black text-slate-700 hover:bg-slate-200">Close</button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         Payment Update Modal
    ════════════════════════════════════════════════════════════════ --}}
    <div id="vendor-history-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeVendorHistoryPaymentModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Payment Update</h3>
                    <p id="vendor-history-payment-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeVendorHistoryPaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="vendor-history-payment-form" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_to" value="supplier_detail">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                <input type="hidden" name="payment_paid_by" value="purchaser">

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Total Bill</span>
                        <span id="vendor-history-payment-total" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Discount</span>
                        <span id="vendor-history-payment-discount" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Net Payable</span>
                        <span id="vendor-history-payment-net" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Already Paid</span>
                        <span id="vendor-history-payment-current-paid" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Remaining</span>
                        <span id="vendor-history-payment-balance" class="text-amber-700"></span>
                    </div>
                    <p id="vendor-history-payment-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount</label>
                    <input id="vendor_history_discount_amount" type="number" step="0.01" min="0" name="discount_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                    <select id="vendor_history_payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="GPay">GPay</option>
                        <option value="Credit">Credit</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Add Payment Now</label>
                    <input id="vendor_history_additional_paid_amount" type="number" step="0.01" min="0" name="additional_paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    <p class="mt-1 text-[10px] font-semibold text-slate-500">Enter only the extra amount received now.</p>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Notes</label>
                    <input id="vendor_history_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500">Save Payment Update</button>
            </form>
        </div>
    </div>

    <script>
        function lockVendorBillBackgroundScroll() {
            document.documentElement.classList.add('overflow-hidden', 'touch-none');
            document.body.classList.add('overflow-hidden', 'touch-none');
        }

        function unlockVendorBillBackgroundScroll() {
            document.documentElement.classList.remove('overflow-hidden', 'touch-none');
            document.body.classList.remove('overflow-hidden', 'touch-none');
        }

        // Bill Details Modal
        function openVendorBillModal(data) {
            document.getElementById('vb-supplier-name').textContent = data.supplierName;
            document.getElementById('vb-supplier-mobile').textContent = data.supplierMobile || 'Mobile pending';
            document.getElementById('vb-bill-ref').textContent = data.billRef;
            document.getElementById('vb-date').textContent = data.date;
            document.getElementById('vb-invoice').textContent = data.invoiceNumber;
            const isCancelled = Boolean(data.isCancelled || data.paymentStatus === 'Cancelled');
            const statusNode = document.getElementById('vb-payment-status');
            if (statusNode) {
                statusNode.textContent = data.paymentStatus;
                statusNode.className = isCancelled 
                    ? 'font-black text-rose-600 mt-0.5 text-[11px] uppercase'
                    : 'font-black text-amber-700 mt-0.5 text-[11px]';
            }
            document.getElementById('vb-total').textContent = data.totalAmount;
            document.getElementById('vb-paid').textContent = data.cashAmount;
            document.getElementById('vb-credit').textContent = data.creditAmount;
            document.getElementById('vb-grn').textContent = data.grnNumber;

            const paidRow = document.getElementById('vb-paid-row');
            const creditRow = document.getElementById('vb-credit-row');
            const paidAmount = Number(String(data.cashAmount || '').replace(/[^0-9.-]/g, ''));
            const creditAmount = Number(String(data.creditAmount || '').replace(/[^0-9.-]/g, ''));

            if (paidRow) {
                paidRow.classList.toggle('hidden', isCancelled);
            }
            if (creditRow) {
                creditRow.classList.toggle('hidden', isCancelled || !(creditAmount > 0));
            }

            const countNode = document.getElementById('vb-items-count');
            if (countNode) countNode.textContent = data.items.length;

            const itemsList = document.getElementById('vb-items-list');
            if (itemsList) {
                if (data.items.length > 0) {
                    itemsList.innerHTML = data.items.map(item => `
                        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-0 text-xs">
                            <div class="min-w-0 pr-2">
                                <p class="font-black text-slate-950 truncate">${item.name}</p>
                                <p class="text-[10px] font-semibold text-slate-500">${item.quantity || '0'} ${item.unit || ''} × ₹${item.price || '0.00'}</p>
                            </div>
                            <span class="font-mono font-black text-slate-950 shrink-0">₹${item.total || '0.00'}</span>
                        </div>
                    `).join('');
                    
                    // Calculate and display items total
                    const itemsTotal = data.items.reduce((sum, item) => {
                        const itemTotal = parseFloat(String(item.total || '0').replace(/,/g, '')) || 0;
                        return sum + itemTotal;
                    }, 0);
                    const itemsTotalNode = document.getElementById('vb-items-total');
                    if (itemsTotalNode) {
                        itemsTotalNode.textContent = `₹${itemsTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
                    }
                } else {
                    itemsList.innerHTML = '<p class="py-1.5 text-slate-500 font-semibold italic text-xs">No items listed</p>';
                    const itemsTotalNode = document.getElementById('vb-items-total');
                    if (itemsTotalNode) {
                        itemsTotalNode.textContent = '₹0.00';
                    }
                }
            }

            const modal = document.getElementById('vendor-bill-modal');
            if (modal) {
                lockVendorBillBackgroundScroll();
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeVendorBillModal() {
            const modal = document.getElementById('vendor-bill-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                unlockVendorBillBackgroundScroll();
            }
        }

        // Payment Modal
        let vendorHistoryPaymentAmount = 0;
        let vendorHistoryCreditApproved = false;
        let vendorHistoryCurrentPaidAmount = 0;

        function openVendorHistoryPaymentModal(invoice, actionUrl) {
            vendorHistoryPaymentAmount = Number(invoice.amount || 0);
            vendorHistoryCreditApproved = Boolean(invoice.creditApproved);
            vendorHistoryCurrentPaidAmount = Number(invoice.paidAmount || 0);

            document.getElementById('vendor-history-payment-form').action = actionUrl;
            document.getElementById('vendor-history-payment-title').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
            document.getElementById('vendor-history-payment-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
            document.getElementById('vendor_history_discount_amount').value = Number(invoice.discountAmount || 0).toFixed(2);
            document.getElementById('vendor_history_payment_method').value = invoice.paymentMethod || 'Cash';
            document.getElementById('vendor-history-payment-current-paid').textContent = `₹${vendorHistoryCurrentPaidAmount.toFixed(2)}`;
            document.getElementById('vendor_history_additional_paid_amount').value = '';
            document.getElementById('vendor_history_payment_note').value = invoice.paymentNote || '';

            updateVendorHistoryPaymentStatus();
            document.getElementById('vendor-history-payment-modal').classList.remove('hidden');
            document.getElementById('vendor-history-payment-modal').classList.add('flex');
        }

        function closeVendorHistoryPaymentModal() {
            document.getElementById('vendor-history-payment-modal').classList.add('hidden');
            document.getElementById('vendor-history-payment-modal').classList.remove('flex');
        }

        function updateVendorHistoryPaymentStatus() {
            const method = document.getElementById('vendor_history_payment_method').value;
            const discountAmount = Math.max(0, Number(document.getElementById('vendor_history_discount_amount').value || 0));
            const additionalPaidAmount = Math.max(0, Number(document.getElementById('vendor_history_additional_paid_amount').value || 0));
            const paidAmount = vendorHistoryCurrentPaidAmount + additionalPaidAmount;
            const netAmount = Math.max(0, vendorHistoryPaymentAmount - discountAmount);
            const balance = Math.max(0, netAmount - paidAmount);
            const warningNode = document.getElementById('vendor-history-payment-warning');
            const balanceNode = document.getElementById('vendor-history-payment-balance');

            document.getElementById('vendor-history-payment-discount').textContent = `₹${discountAmount.toFixed(2)}`;
            document.getElementById('vendor-history-payment-net').textContent = `₹${netAmount.toFixed(2)}`;
            balanceNode.textContent = `₹${balance.toFixed(2)}`;
            balanceNode.className = balance > 0 ? 'text-amber-700' : 'text-emerald-700';

            if (method === 'Credit') {
                warningNode.textContent = vendorHistoryCreditApproved
                    ? 'Credit selected. Payment will stay pending until it is cleared in full.'
                    : 'Credit selected but supplier credit is not approved yet.';
                return;
            }

            warningNode.textContent = balance > 0
                ? 'Payment is not done fully. Remaining balance will stay pending.'
                : 'Full purchaser payment entered. Purchaser balance will be reduced.';
        }

        document.getElementById('vendor_history_discount_amount')?.addEventListener('input', updateVendorHistoryPaymentStatus);
        document.getElementById('vendor_history_payment_method')?.addEventListener('change', updateVendorHistoryPaymentStatus);
        document.getElementById('vendor_history_additional_paid_amount')?.addEventListener('input', updateVendorHistoryPaymentStatus);

        // ═══════════════════════════════════════════════════════════
        // Bulk Payment Modal Functions
        // ═══════════════════════════════════════════════════════════
        function openBulkPaymentModal() {
            const modal = document.getElementById('bulk-payment-modal');
            if (modal) {
                lockVendorBillBackgroundScroll();
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                updateBulkPaymentSummary();
            }
        }

        function closeBulkPaymentModal() {
            const modal = document.getElementById('bulk-payment-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                unlockVendorBillBackgroundScroll();
                // Reset form
                document.querySelectorAll('.bulk-bill-checkbox').forEach(cb => cb.checked = false);
                document.querySelectorAll('.bulk-bill-discount').forEach(input => input.value = '');
                document.getElementById('bulk-payment-amount').value = '';
                updateBulkPaymentSummary();
            }
        }

        function updateBulkPaymentSummary() {
            const checkboxes = document.querySelectorAll('.bulk-bill-checkbox:checked');
            const paymentAmount = Number(document.getElementById('bulk-payment-amount')?.value || 0);

            let totalPending = 0;
            let totalDiscount = 0;

            checkboxes.forEach(checkbox => {
                const billId = checkbox.value;
                const pending = Number(checkbox.dataset.pending || 0);
                const discountInput = document.querySelector(`.bulk-bill-discount[data-bill-id="${billId}"]`);
                const discount = Number(discountInput?.value || 0);

                totalPending += pending;
                totalDiscount += discount;
            });

            const netPending = Math.max(0, totalPending - totalDiscount);
            const remaining = Math.max(0, netPending - paymentAmount);

            // Update summary
            document.getElementById('bulk-summary-count').textContent = checkboxes.length;
            document.getElementById('bulk-summary-pending').textContent = `₹${totalPending.toFixed(2)}`;
            document.getElementById('bulk-summary-discount').textContent = `₹${totalDiscount.toFixed(2)}`;
            document.getElementById('bulk-summary-paying').textContent = `₹${paymentAmount.toFixed(2)}`;
            document.getElementById('bulk-summary-remaining').textContent = `₹${remaining.toFixed(2)}`;

            // Update remaining color
            const remainingEl = document.getElementById('bulk-summary-remaining');
            if (remainingEl) {
                remainingEl.className = remaining > 0 ? 'text-amber-700' : 'text-emerald-700';
            }

            // Update warning
            const warningEl = document.getElementById('bulk-summary-warning');
            if (warningEl) {
                if (checkboxes.length === 0) {
                    warningEl.textContent = 'Please select at least one bill to pay.';
                } else if (paymentAmount === 0) {
                    warningEl.textContent = 'Please enter the payment amount.';
                } else if (remaining > 0) {
                    warningEl.textContent = `Partial payment. ₹${remaining.toFixed(2)} will remain pending across selected bills.`;
                } else if (paymentAmount > netPending) {
                    warningEl.textContent = 'Payment amount exceeds total pending. Excess will be ignored.';
                } else {
                    warningEl.textContent = 'Full payment covers all selected bills.';
                }
            }
        }

        function submitBulkPayment() {
            const checkboxes = document.querySelectorAll('.bulk-bill-checkbox:checked');
            const paymentAmount = Number(document.getElementById('bulk-payment-amount')?.value || 0);

            if (checkboxes.length === 0) {
                alert('Please select at least one bill to pay.');
                return;
            }

            if (paymentAmount <= 0) {
                alert('Please enter a valid payment amount.');
                return;
            }

            // Collect bill IDs and discounts
            const form = document.getElementById('bulk-payment-form');
            const billIds = [];
            const discounts = {};

            checkboxes.forEach(checkbox => {
                const billId = checkbox.value;
                billIds.push(billId);

                const discountInput = document.querySelector(`.bulk-bill-discount[data-bill-id="${billId}"]`);
                const discount = Number(discountInput?.value || 0);
                if (discount > 0) {
                    discounts[billId] = discount;
                }
            });

            // Add hidden inputs for bill IDs
            billIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bill_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            // Add hidden inputs for discount allocations
            Object.entries(discounts).forEach(([billId, discount]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `discount_allocations[${billId}]`;
                input.value = discount;
                form.appendChild(input);
            });

            // Submit form
            form.submit();
        }
    </script>
</x-layouts.app>
