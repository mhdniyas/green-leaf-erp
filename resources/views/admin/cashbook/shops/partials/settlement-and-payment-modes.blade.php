@php
    $settlement = $financialReport['settlement'] ?? [
        'due' => 0.0,
        'received' => 0.0,
        'allocated' => 0.0,
        'unallocated' => 0.0,
        'pending_verification' => 0.0,
        'floating_cheques' => 0.0,
        'pending' => 0.0,
    ];
    $paymentModes = $financialReport['payment_modes'] ?? [];
    $totalReceived = $financialReport['total_received'] ?? 0.0;
@endphp

<section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-6" aria-label="Company Settlement and Payment Mode Breakdown">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
        <!-- Left: Company Settlement -->
        <div class="space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-100 pb-3">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    Company Settlement
                </h2>
                <span class="text-xs font-semibold text-slate-500">Period Settlement Engine</span>
            </div>

            <div class="divide-y divide-slate-100 text-xs font-semibold">
                <div class="flex items-center justify-between py-2.5">
                    <span class="text-slate-600">Due to Company</span>
                    <span class="font-mono font-bold text-slate-900 tabular-nums">₹{{ number_format((float) $settlement['due'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <span class="text-slate-600">Received</span>
                    <span class="font-mono font-bold text-emerald-700 tabular-nums">₹{{ number_format((float) $settlement['received'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <span class="text-slate-600">Allocated</span>
                    <span class="font-mono font-bold text-slate-900 tabular-nums">₹{{ number_format((float) $settlement['allocated'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <span class="text-slate-600">Unallocated</span>
                    <span class="font-mono font-bold text-amber-700 tabular-nums">₹{{ number_format((float) $settlement['unallocated'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <span class="text-slate-600">Pending Verification</span>
                    <span class="font-mono font-bold text-slate-700 tabular-nums">₹{{ number_format((float) $settlement['pending_verification'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <span class="text-slate-600">Floating Cheques</span>
                    <span class="font-mono font-bold text-slate-700 tabular-nums">₹{{ number_format((float) $settlement['floating_cheques'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between pt-3 pb-1">
                    <span class="text-xs font-black uppercase text-slate-950">Pending</span>
                    <span class="font-mono text-sm font-black {{ (float) $settlement['pending'] > 0 ? 'text-indigo-900' : 'text-emerald-700' }} tabular-nums">
                        ₹{{ number_format((float) $settlement['pending'], 2) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Right: Payment Mode Breakdown (Received By) -->
        <div class="space-y-4 lg:border-l lg:border-slate-100 lg:pl-8">
            <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    Received By
                </h3>
                <span class="text-xs font-semibold text-slate-500">Configured Payment Modes</span>
            </div>

            <div class="space-y-3">
                @forelse($paymentModes as $modeItem)
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-2.5 text-xs">
                        <span class="font-bold text-slate-800">{{ $modeItem['label'] }}</span>
                        <span class="font-mono font-extrabold text-slate-950 tabular-nums">
                            ₹{{ number_format((float) $modeItem['amount'], 2) }}
                        </span>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400">
                        No payments recorded for this period.
                    </div>
                @endforelse

                <div class="flex items-center justify-between pt-2 border-t border-slate-100 px-2 text-xs">
                    <span class="font-black uppercase text-slate-900">Total Received</span>
                    <span class="font-mono text-sm font-black text-emerald-800 tabular-nums">
                        ₹{{ number_format((float) $totalReceived, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
