{{--
    Vendor Purchase Inline Summary — Cashbook Main Page
    Server-rendered so amounts are always accurate (no JS dependency).

    Requires:
        $headerSection       – array with: id, name, type, settings (Collection)
        $vendorPurchaseSummaries – array keyed by setting_id
        $selectedDate        – Carbon date
--}}
@php
    $vpSettings = $headerSection['settings']->filter(fn($s) => (bool)($s->is_vendor_purchase ?? false) && (bool)($s->mirror_to_cashbook ?? true));
    if ($vpSettings->isEmpty()) return;
@endphp

@foreach($vpSettings as $s)
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
    @endphp

    <div class="vp-inline-card rounded-xl border {{ $hasEntries ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-white' }} overflow-hidden transition-all duration-200">
        {{-- Main row --}}
        <div class="flex items-center justify-between gap-2 px-3.5 py-2.5">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <div class="flex-shrink-0 h-7 w-7 rounded-lg {{ $hasEntries ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }} flex items-center justify-center">
                    <i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-black text-slate-900 leading-none">{{ $s->displayName() }}</span>
                        <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $isCash ? 'bg-blue-100 text-blue-700' : ($isCredit ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-700') }}">
                            {{ $isCash ? 'Cash Purchase' : ($isCredit ? 'Credit Purchase' : 'Vendor Purchase') }}
                        </span>
                    </div>
                    <div class="text-[10px] font-semibold mt-0.5 {{ $hasEntries ? ($isCredit ? 'text-amber-700' : 'text-emerald-700') : 'text-slate-400' }}">
                        @if($isCash)
                            Cash ₹{{ number_format((float)($vpSum['cash_amount'] ?? 0), 2) }}
                        @elseif($isCredit)
                            Credit ₹{{ number_format((float)($vpSum['credit_amount'] ?? 0), 2) }}
                            <span class="text-slate-400 font-normal">&middot; Liability (No Cash Impact)</span>
                        @else
                            Cash ₹{{ number_format((float)($vpSum['cash_amount'] ?? 0), 2) }} &middot;
                            Credit ₹{{ number_format((float)($vpSum['credit_amount'] ?? 0), 2) }}
                        @endif
                    </div>
                    <div class="text-[10px] font-medium text-slate-400 mt-0.5">
                        @if($count > 0)
                            {{ $count }} {{ Str::plural('bill', $count) }}
                        @else
                            No purchases recorded
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-right shrink-0">
                <span class="font-mono text-sm font-black {{ $hasEntries ? ($isCredit ? 'text-amber-700' : 'text-rose-700') : 'text-slate-300' }}">
                    ₹{{ number_format($displayAmt, 2) }}
                </span>
            </div>
        </div>
        {{-- Footer --}}
        <div class="border-t {{ $hasEntries ? 'border-emerald-100' : 'border-slate-100' }} px-3.5 py-2 flex items-center justify-between bg-white/70">
            <span class="text-[10px] font-medium text-slate-400">Managed on dedicated page</span>
            <a href="{{ route('shop-owner.cashbook.vendor-purchases', ['category_id' => $s->id, 'date' => $selectedDate->toDateString()]) }}"
               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg {{ $hasEntries ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' }} text-[11px] font-bold shadow-2xs transition-colors duration-150">
                <span>{{ $hasEntries ? 'View Bills' : 'Add Purchases' }}</span>
                <i data-lucide="{{ $hasEntries ? 'arrow-right' : 'plus' }}" class="h-3 w-3"></i>
            </a>
        </div>
    </div>
@endforeach
