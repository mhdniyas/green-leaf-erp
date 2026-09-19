{{--
    Vendor Purchase Summary Section — Main Cashbook Page
    Renders server-side VP cards grouped by their configured cashbook header.
    Always accurate — no JS dependency.
--}}
@php
    $allVpSettings = ($settings ?? collect())->filter(fn($s) =>
        (bool)($s->is_vendor_purchase ?? false) &&
        (bool)($s->mirror_to_cashbook ?? true)
    );
    if ($allVpSettings->isEmpty()) return;
@endphp

<div class="space-y-3">
    <div class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400 px-1">
        Vendor Purchases
    </div>

    @foreach($allVpSettings as $s)
        @php
            $vpSum = ($vendorPurchaseSummaries ?? [])[$s->id] ?? [
                'total_amount'  => 0.0,
                'cash_amount'   => 0.0,
                'credit_amount' => 0.0,
                'count'         => 0,
                'payment_type'  => $s->vendor_purchase_payment_type ?? '',
            ];
            $paymentType = $s->vendor_purchase_payment_type ?? ($vpSum['payment_type'] ?? '');
            $displayAmt  = match($paymentType) {
                'cash'   => (float) ($vpSum['cash_amount'] ?? 0),
                'credit' => (float) ($vpSum['credit_amount'] ?? 0),
                default  => (float) ($vpSum['total_amount'] ?? 0),
            };
            $count      = (int) ($vpSum['count'] ?? 0);
            $hasEntries = $displayAmt > 0;
            $isCredit   = $paymentType === 'credit';
            $isCash     = $paymentType === 'cash';

            // Determine which header this VP belongs to
            $headerGroup = $headerGroupList->firstWhere('id', $s->header_group_id);
            $headerLabel = $headerGroup ? $headerGroup->name : 'Expenses';
        @endphp

        <div class="rounded-xl sm:rounded-2xl border {{ $hasEntries ? 'border-emerald-200 shadow-sm' : 'border-slate-200' }} bg-white overflow-hidden">
            {{-- Header bar --}}
            <div class="flex items-center justify-between gap-2 px-3.5 pt-3 pb-2.5">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    {{-- Icon --}}
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg {{ $hasEntries ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }} flex items-center justify-center shadow-2xs">
                        <i data-lucide="shopping-bag" class="h-4 w-4"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-xs sm:text-sm font-black text-slate-900">{{ $s->displayName() }}</span>
                            @if($isCash)
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                    Cash Purchase
                                </span>
                            @elseif($isCredit)
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    Credit Purchase
                                </span>
                            @else
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    Vendor Purchase
                                </span>
                            @endif
                        </div>
                        <div class="text-[10px] font-medium text-slate-400 mt-0.5">
                            Under <span class="font-bold text-slate-600">{{ $headerLabel }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <span class="font-mono text-sm sm:text-base font-black {{ $hasEntries ? ($isCredit ? 'text-amber-700' : 'text-rose-700') : 'text-slate-300' }}">
                        ₹{{ number_format($displayAmt, 2) }}
                    </span>
                </div>
            </div>

            {{-- Detail row --}}
            <div class="mx-3.5 mb-2.5 rounded-lg {{ $hasEntries ? 'bg-emerald-50/60 border border-emerald-100' : 'bg-slate-50 border border-slate-100' }} px-3 py-2 flex items-center justify-between gap-3">
                <div class="space-y-0.5">
                    @if($isCash)
                        <div class="text-[11px] font-bold {{ $hasEntries ? 'text-emerald-800' : 'text-slate-400' }}">
                            Cash ₹{{ number_format((float)($vpSum['cash_amount'] ?? 0), 2) }}
                        </div>
                    @elseif($isCredit)
                        <div class="text-[11px] font-bold text-amber-700">
                            Credit ₹{{ number_format((float)($vpSum['credit_amount'] ?? 0), 2) }}
                        </div>
                        <div class="text-[10px] font-medium text-slate-400">
                            Liability &middot; No cash movement
                        </div>
                    @else
                        <div class="text-[11px] font-bold text-slate-700">
                            Cash ₹{{ number_format((float)($vpSum['cash_amount'] ?? 0), 2) }}
                            &middot;
                            Credit ₹{{ number_format((float)($vpSum['credit_amount'] ?? 0), 2) }}
                        </div>
                    @endif
                    <div class="text-[10px] font-medium text-slate-400 flex items-center gap-1">
                        <i data-lucide="receipt" class="h-3 w-3"></i>
                        @if($count > 0)
                            {{ $count }} {{ Str::plural('bill', $count) }}
                        @else
                            No purchases recorded
                        @endif
                    </div>
                </div>
                <a href="{{ route('shop-owner.cashbook.vendor-purchases', ['category_id' => $s->id, 'date' => $selectedDate->toDateString()]) }}"
                   class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg
                          {{ $hasEntries ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm' : 'bg-white hover:bg-slate-50 text-slate-600 border border-slate-200' }}
                          text-[11px] font-bold transition-colors duration-150 cursor-pointer">
                    <span>{{ $hasEntries ? 'View Bills' : 'Add Purchases' }}</span>
                    <i data-lucide="{{ $hasEntries ? 'arrow-right' : 'plus' }}" class="h-3 w-3"></i>
                </a>
            </div>
        </div>
    @endforeach
</div>
