@php
    $isFull = $isFull ?? false;
    $currentShop = $currentShop ?? null;
    $shopKey = $currentShop ? ($currentShop->slug ?: $currentShop->shop_id) : null;
@endphp

<div class="overflow-x-auto rounded-2xl border border-slate-200">
    <table class="w-full text-left text-xs border-collapse">
        <thead>
            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                <th class="py-3 px-4 rounded-l-xl">Date &amp; Ref</th>
                <th class="py-3 px-4">Method &amp; Account</th>
                <th class="py-3 px-4 text-right">Payment Amount</th>
                <th class="py-3 px-4 text-right">Allocated</th>
                <th class="py-3 px-4 text-right">Remaining</th>
                <th class="py-3 px-4 text-center">Allocations</th>
                <th class="py-3 px-4 text-center">Status</th>
                @if($isFull)
                    <th class="py-3 px-4">Notes</th>
                @endif
                <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono">
            @forelse($payments as $payment)
                @php
                    $isModel = $payment instanceof \App\Models\ShopInvoicePaymentRequest;
                    $ref = $isModel ? $payment->payment_reference : ($payment['reference'] ?? null);
                    $date = $isModel ? ($payment->payment_date ? \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y') : '—') : ($payment['date'] ?? '');
                    $method = $isModel ? ucfirst($payment->payment_method ?? 'bank') : ($payment['method'] ?? '');
                    $account = $isModel ? ($payment->reconciliations->first()?->companyAccount?->name ?? 'Company Account') : ($payment['account'] ?? '');
                    $received = (float) ($isModel ? ($payment->payment_total_calc ?? $payment->requested_amount) : ($payment['amount'] ?? 0));
                    $allocated = (float) ($isModel ? ($payment->allocated_amount_calc ?? ($payment->ledgerAllocations->sum('amount') ?? 0)) : ($payment['allocated'] ?? 0));
                    $unallocated = (float) ($isModel ? ($payment->unallocated_amount_calc ?? max(0, $received - $allocated)) : ($payment['unallocated'] ?? 0));
                    $paymentId = $isModel ? $payment->id : ($payment['id'] ?? null);
                    $chequeStatus = $isModel ? $payment->cheque_status : ($payment['cheque_status'] ?? null);
                    
                    $ledgerAllocs = $isModel ? $payment->ledgerAllocations : collect($payment['allocations'] ?? []);
                    $companyExpenseAllocs = $isModel ? ($payment->companyExpenseAllocations ?? collect()) : collect();
                    $allocCount = $ledgerAllocs->count() + $companyExpenseAllocs->count();
                    
                    $statusLabel = match(true) {
                        $chequeStatus === 'pending' => 'Pending Cheque',
                        $allocated >= $received && $received > 0.01 => 'Fully Allocated',
                        $allocated > 0.01 => 'Partially Allocated',
                        default => 'Unallocated',
                    };
                    $statusClass = match(true) {
                        $chequeStatus === 'pending' => 'bg-violet-50 text-violet-800 border-violet-200',
                        $allocated >= $received && $received > 0.01 => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        $allocated > 0.01 => 'bg-sky-50 text-sky-800 border-sky-200',
                        default => 'bg-amber-50 text-amber-800 border-amber-200',
                    };
                @endphp
                <tr class="hover:bg-slate-50/80 transition-colors {{ $unallocated > 0.01 ? 'bg-amber-50/20' : '' }}">
                    <td class="py-3 px-4 font-sans">
                        <span class="font-extrabold text-slate-900 text-sm block">{{ $date }}</span>
                        <span class="text-[10px] text-slate-400 font-mono">{{ $ref ?: 'No reference' }}</span>
                    </td>
                    <td class="py-3 px-4 font-sans">
                        <span class="font-bold text-slate-800 uppercase text-[11px] block">{{ $method }}</span>
                        <span class="text-[10px] text-slate-500 font-mono">{{ $account }}</span>
                    </td>
                    <td class="py-3 px-4 text-right font-bold text-slate-900">
                        ₹{{ number_format($received, 2) }}
                    </td>
                    <td class="py-3 px-4 text-right font-bold text-emerald-700">
                        ₹{{ number_format($allocated, 2) }}
                    </td>
                    <td class="py-3 px-4 text-right font-black {{ $unallocated > 0.01 ? 'text-amber-700' : 'text-slate-400' }}">
                        ₹{{ number_format($unallocated, 2) }}
                    </td>
                    <td class="py-3 px-4 text-center font-sans">
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full {{ $allocCount > 0 ? 'bg-slate-100 text-slate-800' : 'bg-slate-50 text-slate-400' }}">
                            <i data-lucide="layers" class="w-3 h-3 text-slate-400"></i>
                            <span class="font-mono">{{ $allocCount }}</span>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center font-sans">
                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-lg border {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    @if($isFull)
                        <td class="py-3 px-4 font-sans text-slate-500 text-[11px] max-w-xs truncate">
                            {{ $isModel ? ($payment->shop_note ?: $payment->admin_note) : ($payment['notes'] ?? '—') }}
                        </td>
                    @endif
                    <td class="py-3 px-4 text-right font-sans">
                        <div class="flex items-center justify-end gap-1.5">
                            @if($isFull && $paymentId)
                                <!-- View Allocation Details Toggle -->
                                <button type="button"
                                        @click="toggleExpandPayment({{ $paymentId }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold transition cursor-pointer"
                                        :class="expandedPaymentId === {{ $paymentId }} ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'">
                                    <i data-lucide="split" class="w-3 h-3"></i>
                                    <span>{{ $allocCount > 0 ? 'Allocations' : 'Details' }}</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform duration-200" :class="expandedPaymentId === {{ $paymentId }} ? 'rotate-180' : ''"></i>
                                </button>
                            @endif

                            @if($isModel && $shopKey)
                                <a href="{{ route('admin.cashbook.shop.show', ['shop' => $shopKey, 'month' => \Illuminate\Support\Carbon::parse($payment->payment_date)->format('Y-m')]) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
                                   title="View Shop Day in Operation Center">
                                    <i data-lucide="eye" class="w-3 h-3"></i>
                                    <span>Month</span>
                                </a>

                                @if($unallocated > 0.01 && $chequeStatus !== 'pending')
                                    <button type="button"
                                            @click="openAllocateModal({{ json_encode($payment) }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition shadow-xs cursor-pointer"
                                            title="Allocate unallocated amount">
                                        <i data-lucide="check-square" class="w-3 h-3"></i>
                                        <span>Allocate</span>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>

                @if($isFull && $paymentId)
                    <!-- Expandable Allocation Relationships Breakdown Row -->
                    <tr x-show="expandedPaymentId === {{ $paymentId }}"
                        x-cloak
                        class="bg-slate-50/80 border-b border-slate-200">
                        <td colspan="{{ $isFull ? 9 : 8 }}" class="p-4">
                            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 space-y-3 font-sans">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="p-1.5 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700">
                                            <i data-lucide="split" class="w-4 h-4"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-black text-slate-900 uppercase tracking-wide">
                                                Connected Settlement Allocations
                                            </h4>
                                            <p class="text-[11px] text-slate-500 font-medium">
                                                Source: <span class="font-bold text-slate-700">{{ $method }}</span> · <span class="font-mono">{{ $ref ?: 'No ref' }}</span> (Received: ₹{{ number_format($received, 2) }})
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 font-mono text-xs">
                                        <div>
                                            <span class="text-[10px] text-slate-400 font-sans font-bold uppercase">Allocated:</span>
                                            <span class="font-bold text-emerald-700">₹{{ number_format($allocated, 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-slate-400 font-sans font-bold uppercase">Remaining:</span>
                                            <span class="font-black {{ $unallocated > 0.01 ? 'text-amber-700' : 'text-slate-400' }}">₹{{ number_format($unallocated, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                @if($allocCount === 0)
                                    <div class="py-5 text-center text-slate-400 rounded-xl bg-slate-50/50 border border-dashed border-slate-200 text-xs">
                                        <i data-lucide="info" class="w-4 h-4 mx-auto mb-1 text-slate-400"></i>
                                        No settlement allocations recorded for this payment yet.
                                        @if($unallocated > 0.01 && $chequeStatus !== 'pending')
                                            <div class="mt-2">
                                                <button type="button"
                                                        @click="openAllocateModal({{ json_encode($payment) }})"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                                                    <i data-lucide="check-square" class="w-3.5 h-3.5"></i>
                                                    <span>Allocate Now</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                                        <table class="w-full text-left text-xs border-collapse font-sans">
                                            <thead>
                                                <tr class="border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/80">
                                                    <th class="py-2 px-3">Allocation Date</th>
                                                    <th class="py-2 px-3 text-right">Amount</th>
                                                    <th class="py-2 px-3">Direction &amp; Relation</th>
                                                    <th class="py-2 px-3">Destination (Settlement)</th>
                                                    <th class="py-2 px-3 text-center">Mode</th>
                                                    <th class="py-2 px-3">Created By</th>
                                                    <th class="py-2 px-3 text-center">Status</th>
                                                    @if(auth()->user() && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                                        <th class="py-2 px-3 text-right">Action</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 font-mono text-xs">
                                                <!-- Shop -> Company Allocations -->
                                                @foreach($ledgerAllocs as $alloc)
                                                    @php
                                                        $allocDate = $alloc->created_at ? $alloc->created_at->format('d M Y') : '—';
                                                        $allocAmt = (float) $alloc->amount;
                                                        $destDate = $alloc->ledgerTransaction?->business_date ? $alloc->ledgerTransaction->business_date->format('d M Y') : '—';
                                                        $destCategory = $alloc->ledgerTransaction?->entryType?->name ?? 'Daily Settlement';
                                                        $destTxId = $alloc->ledgerTransaction?->id;
                                                        $isAuto = !empty($alloc->batch_uuid);
                                                        $reconciledByName = $alloc->reconciledBy?->name ?? 'Admin';
                                                    @endphp
                                                    <tr class="hover:bg-slate-50 transition-colors">
                                                        <td class="py-2 px-3 font-sans font-bold text-slate-800">
                                                            {{ $allocDate }}
                                                        </td>
                                                        <td class="py-2 px-3 text-right font-black text-emerald-700">
                                                            ₹{{ number_format($allocAmt, 2) }}
                                                        </td>
                                                        <td class="py-2 px-3 font-sans">
                                                            <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-sky-800 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-100">
                                                                Shop → Company
                                                            </span>
                                                        </td>
                                                        <td class="py-2 px-3 font-sans">
                                                            <span class="font-extrabold text-slate-900 block">{{ $destDate }}</span>
                                                            <span class="text-[10px] text-slate-500">{{ $destCategory }} (Tx #{{ $destTxId }})</span>
                                                        </td>
                                                        <td class="py-2 px-3 text-center font-sans">
                                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md {{ $isAuto ? 'bg-purple-50 text-purple-800 border border-purple-100' : 'bg-slate-100 text-slate-700' }}">
                                                                <i data-lucide="{{ $isAuto ? 'zap' : 'user' }}" class="w-3 h-3 {{ $isAuto ? 'text-purple-600' : 'text-slate-500' }}"></i>
                                                                <span>{{ $isAuto ? 'Auto' : 'Manual' }}</span>
                                                            </span>
                                                        </td>
                                                        <td class="py-2 px-3 font-sans text-slate-600 text-[11px]">
                                                            {{ $reconciledByName }}
                                                        </td>
                                                        <td class="py-2 px-3 text-center font-sans">
                                                            <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                                Settled
                                                            </span>
                                                        </td>
                                                        @if(auth()->user() && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                                            <td class="py-2 px-3 text-right font-sans">
                                                                @if($shopKey)
                                                                    <form method="POST"
                                                                          action="{{ route('admin.cashbook.shop.allocations.remove', ['shop' => $shopKey, 'allocation' => $alloc->id]) }}"
                                                                          onsubmit="return confirm('Are you sure you want to remove this settlement allocation of ₹{{ number_format($allocAmt, 2) }}?');"
                                                                          class="inline-block">
                                                                        @csrf
                                                                        <button type="submit"
                                                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold text-rose-700 hover:bg-rose-50 border border-rose-200 transition cursor-pointer"
                                                                                title="Remove allocation">
                                                                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                                                                            <span>Remove</span>
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach

                                                <!-- Company -> Shop Allocations (if any) -->
                                                @foreach($companyExpenseAllocs as $compAlloc)
                                                    @php
                                                        $cDate = $compAlloc->allocation_date ? $compAlloc->allocation_date->format('d M Y') : ($compAlloc->created_at ? $compAlloc->created_at->format('d M Y') : '—');
                                                        $cAmt = (float) $compAlloc->allocated_amount;
                                                        $cDestDate = $compAlloc->ledgerTransaction?->business_date ? $compAlloc->ledgerTransaction->business_date->format('d M Y') : '—';
                                                        $cDestCat = $compAlloc->ledgerTransaction?->entryType?->name ?? 'Shop Expense';
                                                        $cStatus = $compAlloc->status ?? 'active';
                                                    @endphp
                                                    <tr class="hover:bg-slate-50 transition-colors">
                                                        <td class="py-2 px-3 font-sans font-bold text-slate-800">
                                                            {{ $cDate }}
                                                        </td>
                                                        <td class="py-2 px-3 text-right font-black text-indigo-700">
                                                            ₹{{ number_format($cAmt, 2) }}
                                                        </td>
                                                        <td class="py-2 px-3 font-sans">
                                                            <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-indigo-800 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                                                                Company → Shop
                                                            </span>
                                                        </td>
                                                        <td class="py-2 px-3 font-sans">
                                                            <span class="font-extrabold text-slate-900 block">{{ $cDestDate }}</span>
                                                            <span class="text-[10px] text-slate-500">{{ $cDestCat }}</span>
                                                        </td>
                                                        <td class="py-2 px-3 text-center font-sans">
                                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">
                                                                <i data-lucide="building" class="w-3 h-3 text-slate-500"></i>
                                                                <span>Company</span>
                                                            </span>
                                                        </td>
                                                        <td class="py-2 px-3 font-sans text-slate-600 text-[11px]">
                                                            System
                                                        </td>
                                                        <td class="py-2 px-3 text-center font-sans">
                                                            <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-md {{ $cStatus === 'active' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                                                {{ ucfirst($cStatus) }}
                                                            </span>
                                                        </td>
                                                        @if(auth()->user() && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                                            <td class="py-2 px-3 text-right font-sans">
                                                                <span class="text-[10px] text-slate-400 font-bold">—</span>
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="{{ $isFull ? 9 : 8 }}" class="py-8 text-center text-slate-400 font-medium font-sans">
                        <i data-lucide="wallet" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                        No payment records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
