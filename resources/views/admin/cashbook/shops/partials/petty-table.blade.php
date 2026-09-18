@php
    $isFull = $isFull ?? false;
@endphp

<div class="border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Type / Description</th>
                    <th class="py-3 px-4">Source / Account</th>
                    <th class="py-3 px-4 text-right">Petty Movement</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4">Recorded By</th>
                    @if($isFull)
                        <th class="py-3 px-4 text-right">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white font-mono">
                @forelse($pettyTransactions as $tx)
                    @php
                        $delta = (float) $tx->petty_delta;
                        $isPositive = $delta > 0;
                        $isNegative = $delta < 0;
                        $isVoid = in_array($tx->status, ['void', 'voided', \App\Enums\Cashbook\TransactionStatus::Void->value], true);
                        $isReversed = in_array($tx->status, ['reversed', \App\Enums\Cashbook\TransactionStatus::Reversed->value], true);
                    @endphp
                    <tr class="hover:bg-slate-50/80 transition-colors {{ ($isVoid || $isReversed) ? 'opacity-50 bg-slate-50/50 line-through' : '' }}">
                        <!-- Date -->
                        <td class="py-3 px-4 font-mono font-medium text-slate-700 whitespace-nowrap">
                            {{ \Illuminate\Support\Carbon::parse($tx->business_date)->format('d M Y') }}
                        </td>

                        <!-- Description -->
                        <td class="py-3 px-4 font-sans">
                            <div class="font-bold text-slate-800">
                                {{ $tx->entryType?->name ?: ($tx->entry_type_code === 'company_to_petty' ? 'Company to Petty Funding' : $tx->entry_type_code) }}
                            </div>
                            @if($tx->notes)
                                <div class="text-[11px] text-slate-500 truncate max-w-xs">{{ $tx->notes }}</div>
                            @endif
                        </td>

                        <!-- Source / Account -->
                        <td class="py-3 px-4 font-sans whitespace-nowrap">
                            @if($tx->companyAccount)
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md">
                                    <i data-lucide="building" class="w-3 h-3 text-slate-400"></i>
                                    {{ $tx->companyAccount->name }}
                                </span>
                            @elseif($tx->funding_source === 'petty')
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                                    <i data-lucide="coins" class="w-3 h-3 text-emerald-500"></i>
                                    Shop Petty Float
                                </span>
                            @else
                                <span class="text-[11px] text-slate-400 font-mono">{{ $tx->funding_source ?: '—' }}</span>
                            @endif
                        </td>

                        <!-- Petty Movement Delta -->
                        <td class="py-3 px-4 text-right font-mono font-black whitespace-nowrap">
                            @if($isPositive)
                                <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                                    +₹{{ number_format($delta, 2) }}
                                </span>
                            @elseif($isNegative)
                                <span class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md">
                                    -₹{{ number_format(abs($delta), 2) }}
                                </span>
                            @else
                                <span class="text-slate-400">₹0.00</span>
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="py-3 px-4 text-center font-sans whitespace-nowrap">
                            @if($isVoid)
                                <span class="text-[10px] font-black px-2 py-0.5 rounded bg-rose-100 text-rose-800">VOID</span>
                            @elseif($isReversed)
                                <span class="text-[10px] font-black px-2 py-0.5 rounded bg-amber-100 text-amber-800">REVERSED</span>
                            @elseif(in_array($tx->status, ['approved', \App\Enums\Cashbook\TransactionStatus::Approved->value], true))
                                <span class="text-[10px] font-black px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">APPROVED</span>
                            @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase">{{ $tx->status ?: 'POSTED' }}</span>
                            @endif
                        </td>

                        <!-- Recorded By -->
                        <td class="py-3 px-4 text-slate-500 text-[11px] font-sans whitespace-nowrap">
                            {{ $tx->enteredBy?->name ?: 'System' }}
                        </td>

                        @if($isFull)
                            <td class="py-3 px-4 text-right font-sans">
                                <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => \Illuminate\Support\Carbon::parse($tx->business_date)->format('Y-m-d')]) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                    <i data-lucide="eye" class="w-3 h-3"></i>
                                    <span>View Day</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isFull ? 7 : 6 }}" class="py-8 text-center text-slate-400 font-medium font-sans">
                            <i data-lucide="inbox" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                            No petty cash transactions recorded.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
