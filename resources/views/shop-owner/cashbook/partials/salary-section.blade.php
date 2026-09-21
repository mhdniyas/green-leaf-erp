{{-- SALARY SECTION CARD --}}
{{-- Rendered server-side. No JS calculation. Accounting effects are handled by the existing --}}
{{-- BalanceCalculator / FundingSourceEffectResolver and are not changed here. --}}
@php
    use App\DTO\Cashbook\SalarySectionData;
    use App\DTO\Cashbook\SalaryGroup;
    use App\DTO\Cashbook\SalaryTransactionRow;

    /** @var SalarySectionData $salarySectionData */
@endphp

<div class="rounded-2xl border border-indigo-200 bg-white p-4 sm:p-5 shadow-xs space-y-4 select-none">

    {{-- Section Header --}}
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <div>
            <div class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-950 flex items-center gap-1.5">
                <i data-lucide="users" class="h-4 w-4 text-indigo-600"></i>
                <span>SALARY</span>
            </div>
            @if($salarySectionData->grandTotal > 0)
                <div class="text-[11px] font-bold text-slate-400 mt-0.5">
                    Total: ₹{{ number_format($salarySectionData->grandTotal, 2) }}
                </div>
            @endif
        </div>
        <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-700">
            <i data-lucide="shield-check" class="h-2.5 w-2.5"></i>
            Auto
        </span>
    </div>

    {{-- Type Groups --}}
    <div class="space-y-4">
        @foreach($salarySectionData->groups as $group)
            @php /** @var SalaryGroup $group */ @endphp
            <div>
                {{-- Group Header --}}
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wide text-slate-600">
                        {{ $group->typeLabel }}
                    </span>
                    <span class="font-mono text-xs font-black text-rose-700">
                        −₹{{ number_format($group->total, 2) }}
                    </span>
                </div>

                {{-- Transaction Rows --}}
                <div class="space-y-1.5">
                    @foreach($group->transactions as $row)
                        @php /** @var SalaryTransactionRow $row */ @endphp
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-100 px-3 py-2 text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <i data-lucide="user" class="h-3 w-3 text-slate-400 shrink-0"></i>
                                <span class="font-semibold text-slate-800 truncate">{{ $row->employeeName }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 ml-2">
                                {{-- Funding source badge --}}
                                @php
                                    $fundingBadge = match($row->fundingSource) {
                                        'petty'   => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'company' => 'bg-purple-100 text-purple-800 border-purple-200',
                                        default   => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[9px] font-bold {{ $fundingBadge }}">
                                    {{ $row->fundingLabel }}
                                </span>
                                <span class="font-mono font-black text-slate-900">
                                    ₹{{ number_format($row->amount, 2) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Group subtotal (more than 1 row) --}}
                @if(count($group->transactions) > 1)
                    <div class="flex justify-between items-center pt-1.5 px-1 text-[10px] font-bold text-slate-500">
                        <span>{{ $group->typeLabel }} Total</span>
                        <span class="font-mono">₹{{ number_format($group->total, 2) }}</span>
                    </div>
                @endif
            </div>

            @if(!$loop->last)
                <div class="border-t border-dashed border-slate-200 my-3"></div>
            @endif
        @endforeach
    </div>

    {{-- Funding Breakdown (only if multiple sources are used) --}}
    @if(count($salarySectionData->fundingBreakdown) > 1)
        <div class="border-t border-slate-100 pt-3">
            <div class="text-[10px] font-black uppercase tracking-wide text-slate-400 mb-2">Paid From</div>
            <div class="space-y-1">
                @foreach($salarySectionData->fundingBreakdown as $label => $amount)
                    <div class="flex justify-between text-[11px] text-slate-600">
                        <span>{{ $label }}</span>
                        <span class="font-mono font-bold">₹{{ number_format($amount, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Section Grand Total --}}
    <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
        <span class="text-xs font-black uppercase tracking-wide text-slate-700">Section Total</span>
        <span class="font-mono text-sm font-black text-rose-700">
            −₹{{ number_format($salarySectionData->grandTotal, 2) }}
        </span>
    </div>
</div>
