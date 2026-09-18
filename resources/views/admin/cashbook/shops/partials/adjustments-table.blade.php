@php
    $isFull = $isFull ?? false;
@endphp

<div class="overflow-x-auto rounded-2xl border border-slate-200">
    <table class="w-full text-left text-xs border-collapse">
        <thead>
            <tr class="border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                <th class="py-2.5 px-3 rounded-l-xl">Date &amp; Time</th>
                <th class="py-2.5 px-3">Type</th>
                <th class="py-2.5 px-3">Note / Details</th>
                <th class="py-2.5 px-3 text-right">Amount</th>
                <th class="py-2.5 px-3 text-right">Outstanding Effect</th>
                <th class="py-2.5 px-3">Admin</th>
                <th class="py-2.5 px-3 text-center">Status</th>
                <th class="py-2.5 px-3 text-right rounded-r-xl">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100/80 font-mono">
            @forelse($adjustments as $adj)
                @php
                    $isModel = $adj instanceof \App\Models\Cashbook\ShopLedgerTransaction;
                    $id = $isModel ? $adj->id : ($adj['id'] ?? null);
                    $date = $isModel ? \Illuminate\Support\Carbon::parse($adj->business_date)->format('d M Y') : ($adj['time'] ?? '');
                    $typeName = $isModel ? ($adj->entryType?->name ?: $adj->entry_type_code) : ($adj['type'] ?? '');
                    $note = $isModel ? ($adj->notes ?: 'Adjustment') : ($adj['note'] ?? '');
                    $amount = (float) ($isModel ? $adj->amount : ($adj['amount'] ?? 0));
                    $settlementDelta = (float) ($isModel ? $adj->settlement_delta : ($adj['effect_on_payable'] ?? 0));
                    $admin = $isModel ? ($adj->enteredBy?->name ?? 'Admin') : ($adj['admin'] ?? 'Admin');
                    $isReversed = $isModel ? in_array($adj->status, ['reversed', \App\Enums\Cashbook\TransactionStatus::Reversed->value], true) : !empty($adj['is_reversed']);
                    $isReversal = $isModel ? ($adj->reference_type === \App\Models\Cashbook\ShopLedgerTransaction::class && filled($adj->reference_id)) : !empty($adj['is_reversal']);
                    $canReverse = $isModel ? (!$isReversed && !$isReversal && !in_array($adj->status, ['void', 'voided'])) : !empty($adj['can_reverse']);
                @endphp
                <tr class="hover:bg-slate-50/60 transition {{ $isReversed ? 'opacity-60 bg-slate-50/30' : '' }}">
                    <td class="py-3 px-3 font-mono text-slate-500 font-semibold whitespace-nowrap">{{ $date }}</td>
                    <td class="py-3 px-3 font-sans">
                        <span class="font-extrabold text-slate-900 block">{{ $typeName }}</span>
                    </td>
                    <td class="py-3 px-3 max-w-[180px] truncate text-slate-600 font-sans font-medium" title="{{ $note }}">
                        {{ $note }}
                    </td>
                    <td class="py-3 px-3 text-right font-black font-mono text-slate-900">
                        ₹{{ number_format($amount, 2) }}
                    </td>
                    <td class="py-3 px-3 text-right font-black font-mono {{ $settlementDelta < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ $settlementDelta < 0 ? '-' : '+' }}₹{{ number_format(abs($settlementDelta), 2) }}
                    </td>
                    <td class="py-3 px-3 text-slate-600 font-sans font-medium">{{ $admin }}</td>
                    <td class="py-3 px-3 text-center font-sans">
                        @if($isReversed)
                            <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200">
                                REVERSED
                            </span>
                        @elseif($isReversal)
                            <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200">
                                REVERSAL
                            </span>
                        @else
                            <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $isModel ? ucfirst((string) $adj->status) : ($adj['status_label'] ?? 'POSTED') }}
                            </span>
                        @endif
                    </td>
                    <td class="py-3 px-3 text-right font-sans">
                        @if($canReverse)
                            <button type="button"
                                    @click="openReverse({{ $id }}, '{{ addslashes($typeName) }}', '{{ number_format($amount, 2) }}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-extrabold transition cursor-pointer border border-rose-200">
                                <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                                <span>Reverse</span>
                            </button>
                        @else
                            <a href="{{ route('admin.cashbook.transaction.show', $id) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-[11px] font-bold transition">
                                <span>View</span>
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-8 text-center text-slate-400 font-medium font-sans">
                        <i data-lucide="sliders-horizontal" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                        No adjustment records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
