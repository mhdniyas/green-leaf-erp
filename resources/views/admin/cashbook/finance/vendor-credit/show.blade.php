@extends('admin.cashbook.layouts.app')

@section('title', $supplier->name . ' — Vendor Credit Details')

@section('header_title')
    <i data-lucide="truck" class="h-5 w-5 text-emerald-600"></i> {{ $supplier->name }}
@endsection

@section('header_subtitle')
    Credit purchase bills, settlement payments, and outstanding liability for this supplier.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2"><a href="#vendor-bills" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-800 shadow-sm hover:bg-emerald-100"><i data-lucide="receipt" class="h-4 w-4"></i><span>View Bills</span></a><a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50"><i data-lucide="arrow-left" class="h-4 w-4"></i><span>Back to Vendors</span></a></div>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-6" x-data="vendorSettlement({{ Js::from($settlementCandidates) }})">
        <!-- KPI METRICS FOR THIS SUPPLIER -->
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Credit Purchases</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($kpi['total_invoiced'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-slate-500">{{ number_format($kpi['invoice_count']) }} bills total</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Settled</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($kpi['total_paid'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-emerald-600">Cleared payments</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Current Outstanding</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-rose-700">₹{{ number_format($kpi['total_outstanding'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-rose-600">Due to supplier</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Supplier Info</span>
                <div class="mt-2 text-base font-extrabold text-slate-900 truncate">{{ $supplier->name }}</div>
                <span class="mt-1 block text-xs font-bold text-slate-500">{{ $supplier->mobile_number ?: ($supplier->contact ?: 'No phone recorded') }}</span>
            </div>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Admin Vendor Credit Settlement</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Enter payment amount to auto-select bills, or customize bill selection and difference treatment.</p>
                </div>
                <span class="rounded-lg bg-sky-50 px-3 py-1.5 font-mono text-xs font-extrabold text-sky-800">Available Advance: ₹{{ number_format($availableVendorAdvance, 2) }}</span>
            </div>

            <form method="POST" action="{{ route('admin.cashbook.finance.vendor-credit.settle', $supplier) }}" class="space-y-4">
                @csrf
                <div class="rounded-xl border border-slate-200 p-3 bg-slate-50/50">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Payment Source</span>
                    <div class="mt-2 flex flex-wrap gap-4 text-xs font-bold">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input x-model="paymentSource" value="company" type="radio" class="text-emerald-600 focus:ring-emerald-500"> Company Account
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input x-model="paymentSource" value="statement" type="radio" class="text-emerald-600 focus:ring-emerald-500"> Existing Statement Transaction
                        </label>
                    </div>
                </div>

                {{-- Amount & Primary Controls (Amount-First) --}}
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Actual Cash/Bank Payment (₹) <span class="text-rose-600">*</span></label>
                        <input x-model.number="cash" @input="onCashInput()" name="actual_payment_amount" type="number" min="0" step="0.01" required :readonly="paymentSource === 'statement'" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 font-mono text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none shadow-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Date <span class="text-rose-600">*</span></label>
                        <input x-model="paymentDate" name="payment_date" type="date" value="{{ now()->toDateString() }}" required :readonly="paymentSource === 'statement'" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 font-mono text-xs font-bold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Method</label>
                        <select name="payment_method" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="Bank">Bank Transfer / NEFT / RTGS</option>
                            <option value="Cash">Cash in Hand</option>
                            <option value="Online">Online / Net Banking</option>
                            <option value="GPay">GPay / UPI</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Company Account</label>
                        <select x-model="companyAccountId" name="company_account_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="">Optional: finalize Cashbook movement</option>
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($acc, old('company_account_id'), $companyAccounts))>{{ $acc->name }} ({{ strtoupper($acc->account_type) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="autoSelectByAmount()" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition shadow-2xs">
                            <i data-lucide="sparkles" class="h-3.5 w-3.5 text-emerald-600"></i> Auto Select
                        </button>
                        <button type="button" @click="selectVisible()" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Select All Visible</button>
                        <button type="button" @click="clearSelection()" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Clear</button>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-bold">
                        <label class="flex items-center gap-2 cursor-pointer text-slate-700">
                            <input x-model="useAdvance" @change="autoSelectByAmount()" name="use_vendor_advance" value="1" type="checkbox" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 rounded"> Use Vendor Advance
                        </label>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-slate-700 font-mono" x-text="`${selectedRows.length} Bills Selected`"></span>
                        <span class="rounded-lg bg-slate-900 px-2.5 py-1 text-white font-mono" x-text="`Total: ${rupees(selectedTotal)}`"></span>
                    </div>
                </div>

                {{-- Compact Bill Selection Table --}}
                <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-2xs max-h-72 custom-scrollbar">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-100 text-[10px] font-black uppercase text-slate-500 z-10">
                            <tr class="border-b border-slate-200">
                                <th class="p-2.5 text-center w-10">Select</th>
                                <th class="p-2.5">Bill Number</th>
                                <th class="p-2.5">Date</th>
                                <th class="p-2.5 text-right">Outstanding</th>
                                <th class="p-2.5 text-right">Cash Allocation</th>
                                <th class="p-2.5 text-right">Advance / Discount</th>
                                <th class="p-2.5 text-right">Remaining Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="row in preview" :key="row.id">
                                <tr class="hover:bg-slate-50 transition" :class="row.selected ? 'bg-emerald-50/40' : ''">
                                    <td class="p-2.5 text-center">
                                        <input type="checkbox" :checked="row.selected" @change="toggleBillRow(row.id)" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 rounded cursor-pointer">
                                    </td>
                                    <td class="p-2.5 font-mono font-bold text-slate-900" x-text="row.number"></td>
                                    <td class="p-2.5 text-slate-600 font-mono text-[11px]" x-text="row.date"></td>
                                    <td class="p-2.5 text-right font-mono font-bold text-slate-900" x-text="rupees(row.outstanding)"></td>
                                    <td class="p-2.5 text-right font-mono font-bold text-emerald-700" x-text="rupees(row.cash)"></td>
                                    <td class="p-2.5 text-right font-mono font-bold text-amber-700" x-text="rupees(row.advance + row.discount)"></td>
                                    <td class="p-2.5 text-right font-mono font-extrabold" :class="row.remaining > 0 ? 'text-rose-700' : 'text-slate-400'" x-text="rupees(row.remaining)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Statement Transaction Selector (when paymentSource === 'statement') --}}
                <div x-show="paymentSource === 'statement'" class="rounded-xl border border-sky-200 bg-sky-50/70 p-3.5">
                    <label class="block text-xs font-bold text-slate-700">Existing OUT Statement Transaction <span class="text-rose-600">*</span>
                        <select x-model="statementEntryId" name="statement_entry_id" @change="selectStatement($event)" class="mt-1 min-h-11 w-full rounded-xl border border-sky-300 bg-white px-3 font-mono text-xs focus:border-sky-500 focus:outline-none">
                            <option value="">Select statement transaction</option>
                            @foreach($statementTransactions as $statement)
                                <option value="{{ $statement->id }}" data-account="{{ $statement->company_account_id }}" data-amount="{{ $statement->amount }}" data-date="{{ $statement->transaction_date?->toDateString() }}" data-reference="{{ $statement->reference }}">
                                    {{ $statement->companyAccount?->name }} | {{ $statement->transaction_date?->format('d M') }} | {{ $statement->reference ?: $statement->narration }} | OUT | ₹{{ number_format($statement->amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <p class="mt-1 text-[11px] font-semibold text-sky-800">Statement amount, date, account, and reference become authoritative and avoid duplicate company cashbook movements.</p>
                </div>

                {{-- Real-Time Difference & Settlement Discount Choice --}}
                <div x-show="difference > 0.01" class="rounded-2xl border border-amber-300 bg-amber-50/90 p-4 shadow-2xs">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-amber-200/80 pb-3">
                        <div>
                            <span class="text-[10px] font-black uppercase text-amber-800 tracking-wider">Unallocated Bill Remainder</span>
                            <div class="font-mono text-xl font-black text-amber-950" x-text="rupees(difference)"></div>
                        </div>
                        <div class="space-y-1">
                            <span class="block text-xs font-bold text-slate-800">How should this difference be treated?</span>
                            <div class="flex flex-wrap gap-4 text-xs font-bold">
                                <label class="flex items-center gap-2 cursor-pointer text-slate-900">
                                    <input x-model="differenceTreatment" name="difference_treatment" value="outstanding" type="radio" class="text-amber-700 focus:ring-amber-600"> Keep as Vendor Outstanding
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-slate-900">
                                    <input x-model="differenceTreatment" name="difference_treatment" value="discount" type="radio" class="text-amber-700 focus:ring-amber-600"> Apply as Settlement Discount
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Live Financial Monetary Feedback Box --}}
                    <div class="mt-3 rounded-xl p-3 text-xs font-bold border" :class="differenceTreatment === 'discount' ? 'border-emerald-300 bg-emerald-100/60 text-emerald-950' : 'border-amber-300 bg-amber-100/60 text-amber-950'">
                        <template x-if="differenceTreatment === 'discount'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="block uppercase text-[10px] text-emerald-800 font-black">Settlement Discount Applied</span>
                                    <span class="font-mono text-sm font-extrabold text-emerald-900" x-text="rupees(difference)"></span>
                                </div>
                                <div class="text-right">
                                    <span class="block uppercase text-[10px] text-emerald-800 font-black">Vendor Outstanding After Settlement</span>
                                    <span class="font-mono text-sm font-extrabold text-emerald-900">₹0.00 (Fully Settled)</span>
                                </div>
                            </div>
                        </template>
                        <template x-if="differenceTreatment === 'outstanding'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="block uppercase text-[10px] text-amber-800 font-black">Settlement Discount</span>
                                    <span class="font-mono text-sm font-extrabold text-amber-900">₹0.00</span>
                                </div>
                                <div class="text-right">
                                    <span class="block uppercase text-[10px] text-amber-800 font-black">Remaining Vendor Outstanding</span>
                                    <span class="font-mono text-sm font-extrabold text-rose-700" x-text="rupees(difference)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <input x-show="difference <= 0.01" type="hidden" name="difference_treatment" value="outstanding">
                <template x-for="row in selectedRows" :key="row.id">
                    <input type="hidden" name="invoice_ids[]" :value="row.id">
                </template>

                {{-- Floating Calculation Summary Strip --}}
                <aside class="sticky bottom-3 z-10 rounded-2xl bg-slate-950 p-4 text-white shadow-2xl border border-slate-800">
                    <div class="grid gap-3 text-xs font-bold sm:grid-cols-4 xl:grid-cols-7">
                        <div><span class="text-[9px] text-slate-400 uppercase font-black">SELECTED BILLS</span><b class="block font-mono text-sm text-slate-100" x-text="rupees(selectedTotal)"></b></div>
                        <div><span class="text-[9px] text-slate-400 uppercase font-black">VENDOR ADVANCE</span><b class="block font-mono text-sm text-amber-400" x-text="rupees(advanceUsed)"></b></div>
                        <div><span class="text-[9px] text-slate-400 uppercase font-black">CASH/BANK PAYMENT</span><b class="block font-mono text-sm text-emerald-400" x-text="rupees(cash)"></b></div>
                        <div><span class="text-[9px] text-slate-400 uppercase font-black">SETTLEMENT DISCOUNT</span><b class="block font-mono text-sm text-sky-400" x-text="rupees(discount)"></b></div>
                        <div><span class="text-[9px] text-slate-400 uppercase font-black">TOTAL SETTLED</span><b class="block font-mono text-sm text-white" x-text="rupees(totalSettled)"></b></div>
                        <div><span class="text-[9px] text-slate-400 uppercase font-black">REMAINING OUTSTANDING</span><b class="block font-mono text-sm text-rose-400" x-text="rupees(remaining)"></b></div>
                        <div><span class="text-[9px] text-slate-400 uppercase font-black">NEW VENDOR ADVANCE</span><b class="block font-mono text-sm text-emerald-300" x-text="rupees(newAdvance)"></b></div>
                    </div>
                </aside>

                <div class="flex justify-end pt-2">
                    <button type="submit" :disabled="selectedRows.length === 0" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-xs font-extrabold text-white shadow-md hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-400 transition">
                        <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                        <span x-text="`Confirm ${rupees(cash)} Vendor Settlement`"></span>
                    </button>
                </div>
            </form>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Settlement History</h2>
                    <p class="text-xs font-semibold text-slate-500">Actual payments, discounts, bill allocations, payment sources, and admin correction status.</p>
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100 text-[10px] font-black uppercase text-slate-500">
                        <tr class="border-b border-slate-200">
                            <th class="p-2.5">Date / Ref</th>
                            <th class="p-2.5 text-right">Actual Payment</th>
                            <th class="p-2.5 text-right">Discount</th>
                            <th class="p-2.5 text-right">Advance Used</th>
                            <th class="p-2.5">Bills</th>
                            <th class="p-2.5 text-center">Result</th>
                            <th class="p-2.5">Source</th>
                            <th class="p-2.5 text-center">Status</th>
                            <th class="p-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($settlementHistory as $history)
                            @php
                                $allocationsCount = $history->allocations_count ?? $history->allocations->count();
                                $totalSettledAmount = (float) ($history->allocations_sum_total_settled ?? $history->allocations->sum('total_settled'));
                                $newAdvance = (float) $history->new_vendor_advance_amount;
                                $discountAmount = (float) $history->settlement_discount_amount;
                                $actualPayment = (float) $history->actual_payment_amount;

                                $remainingInvoicesDue = (float) $history->allocations->sum(function($alloc) {
                                    $inv = $alloc->purchaseInvoice;
                                    if (!$inv) return 0;
                                    $allSettled = (float) $inv->vendorSettlementAllocations()->sum('total_settled');
                                    return max(0, (float) $inv->amount - (float) $inv->discount_amount - $allSettled);
                                });
                            @endphp
                            <tr class="hover:bg-slate-50 transition">
                                {{-- Date / Ref --}}
                                <td class="p-2.5">
                                    <div class="font-mono font-bold text-slate-900">{{ $history->payment_date?->format('d M Y') }}</div>
                                    <span class="block font-mono text-[11px] font-bold text-slate-600 truncate max-w-[140px]" title="{{ $history->reference ?: ('VENDOR-SETTLEMENT-'.$history->id) }}">
                                        {{ $history->reference ?: ('VENDOR-SETTLEMENT-'.$history->id) }}
                                    </span>
                                </td>

                                {{-- Actual Payment --}}
                                <td class="p-2.5 text-right font-mono font-extrabold text-emerald-700">
                                    ₹{{ number_format($actualPayment, 2) }}
                                </td>

                                {{-- Discount --}}
                                <td class="p-2.5 text-right font-mono">
                                    <div class="font-bold text-sky-700">₹{{ number_format($discountAmount, 2) }}</div>
                                    @if($discountAmount > 0.009)
                                        <span class="inline-block rounded bg-sky-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-sky-800">
                                            Settlement Discount
                                        </span>
                                    @endif
                                </td>

                                {{-- Advance Used / New Advance --}}
                                <td class="p-2.5 text-right font-mono">
                                    <div class="font-bold text-amber-700">₹{{ number_format((float) $history->vendor_advance_used_amount, 2) }}</div>
                                    @if($newAdvance > 0.009)
                                        <span class="block text-[10px] font-bold text-emerald-600">+ ₹{{ number_format($newAdvance, 2) }} New</span>
                                    @endif
                                </td>

                                {{-- Bills / Allocation --}}
                                <td class="p-2.5">
                                    <div class="font-bold text-slate-800">{{ $allocationsCount }} {{ Str::plural('Bill', $allocationsCount) }}</div>
                                    <span class="block font-mono text-[11px] text-slate-500 font-semibold">₹{{ number_format($totalSettledAmount, 2) }} settled</span>
                                </td>

                                {{-- Result --}}
                                <td class="p-2.5 text-center">
                                    @if($newAdvance > 0.009)
                                        <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                            ₹{{ number_format($newAdvance, 2) }} New Advance
                                        </span>
                                    @elseif($remainingInvoicesDue <= 0.009)
                                        <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                            Fully Settled
                                        </span>
                                    @else
                                        <span class="inline-block rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-amber-800">
                                            ₹{{ number_format($remainingInvoicesDue, 2) }} Outstanding
                                        </span>
                                    @endif
                                </td>

                                {{-- Source --}}
                                <td class="p-2.5">
                                    <div class="font-bold text-slate-800 text-[11px] truncate max-w-[130px]">
                                        {{ $history->companyAccount?->name ?: ($history->payment_method ? 'Company Account' : 'Direct') }}
                                    </div>
                                    <span class="block text-[10px] font-bold text-slate-500 uppercase">{{ $history->payment_method ?: 'Bank' }}</span>
                                </td>

                                {{-- Status --}}
                                <td class="p-2.5 text-center">
                                    @if($history->status === 'reversed')
                                        <span class="inline-block rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-rose-800">Reversed</span>
                                    @elseif($history->status === 'corrected')
                                        <span class="inline-block rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-sky-800">Corrected</span>
                                    @else
                                        <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">Finalized</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="p-2.5 text-right">
                                    <div class="flex items-center justify-end gap-2 text-xs">
                                        @unless($history->is_finalized)
                                            <a class="font-bold text-emerald-700 hover:underline" href="{{ route('admin.cashbook.finance.vendor-credit.settlements.show', $history) }}#reconcile">Reconcile</a>
                                        @endunless
                                        <a class="font-bold text-slate-700 hover:text-emerald-700 hover:underline" href="{{ route('admin.cashbook.finance.vendor-credit.settlements.show', $history) }}">View</a>
                                        <button type="button" @click="openEditSettlementModal({{ $history->id }}, '{{ $history->reference }}', '{{ $history->note }}', {{ (float) $history->actual_payment_amount }}, '{{ $history->payment_date?->format('Y-m-d') }}')" class="font-bold text-amber-700 hover:text-amber-900 hover:underline">
                                            Edit
                                        </button>
                                        <button type="button" @click="openDeleteSettlementModal({{ $history->id }}, '{{ $supplier->public_uuid }}', '{{ $history->payment_date?->format('Y-m-d') }}', {{ (float) $history->actual_payment_amount }}, {{ (float) $history->settlement_discount_amount }})" class="font-bold text-rose-700 hover:text-rose-900 hover:underline">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-6 text-center text-xs font-bold text-slate-400">No settlement history found for this supplier.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- FILTERS TOOLBAR -->
        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('admin.cashbook.finance.vendor-credit.show', $supplier) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Payment Status</label>
                    <select name="status" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach(['all' => 'All Invoices', 'unpaid' => 'Unpaid Only', 'partially_paid' => 'Partially Paid', 'paid' => 'Fully Paid'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-600">From Date</label>
                        <x-cashbook.previous-month-button mode="range" size="xs" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                    </div>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">To Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Search Bill / Ref</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Invoice #, cart #, note..." class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-500">
                        <i data-lucide="filter" class="h-4 w-4"></i> Filter
                    </button>
                    @if($status !== 'all' || $startDate !== '' || $endDate !== '' || $search !== '')
                        <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $supplier) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50" title="Clear Filters">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <!-- INVOICES TABLE -->
        <section id="vendor-bills" class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Purchase Invoices & Bills</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Individual credit purchase bills and direct settlement action.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $invoices->total() }} invoices</span>
            </div>

            <!-- DESKTOP TABLE -->
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Invoice #</th>
                            <th class="px-3 py-3">Reference / Cart</th>
                            <th class="px-3 py-3 text-right">Gross Amount</th>
                            <th class="px-3 py-3 text-right">Discount</th>
                            <th class="px-3 py-3 text-right">Net Amount</th>
                            <th class="px-3 py-3 text-right">Paid Amount</th>
                            <th class="px-3 py-3 text-right">Outstanding</th>
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-3 py-3 text-center">Journal Link</th>
                            <th class="px-3 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $inv)
                            @php
                                $net = max(0, (float) $inv->amount - (float) $inv->discount_amount);
                                $settled = (float) ($inv->vendor_settlement_allocations_sum_total_settled ?? $inv->paid_amount);
                                $due = max(0, $net - $settled);
                                $linkedJe = $journalEntries->get($inv->id);
                            @endphp
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">
                                    {{ $inv->purchaserCart?->business_date?->format('Y-m-d') ?: $inv->created_at?->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-3 font-mono font-bold text-slate-900">
                                    {{ $inv->invoice_number ?: ('#'.$inv->id) }}
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-600">
                                    {{ $inv->purchaserCart?->bill_number ?: ($inv->purchaserCart?->cart_number ?: '—') }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-slate-600">₹{{ number_format($inv->amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono text-slate-500">₹{{ number_format($inv->discount_amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($net, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-emerald-700">₹{{ number_format($settled, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-extrabold {{ $due > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                    ₹{{ number_format($due, 2) }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($due <= 0.01)
                                        <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[9px] font-black uppercase">Paid</span>
                                    @elseif($settled > 0)
                                        <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[9px] font-black uppercase">Partial</span>
                                    @else
                                        <span class="rounded-full bg-rose-100 text-rose-800 px-2 py-0.5 text-[9px] font-black uppercase">Unpaid</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($linkedJe)
                                        <a href="{{ route('admin.cashbook.finance.journal.entry-show', $linkedJe->id) }}" class="font-mono text-[11px] font-extrabold text-emerald-700 hover:underline">
                                            {{ $linkedJe->formatted_reference }}
                                        </a>
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @if($due > 0.01)
                                        <button type="button"
                                                @click="selectedInvoice = {{ json_encode(['id' => $inv->id, 'invoice_number' => $inv->invoice_number ?: ('#'.$inv->id), 'due' => $due, 'pay_url' => route('admin.cashbook.finance.vendor-credit.pay', $inv)]) }}; payAmount = {{ $due }}; showPayModal = true"
                                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                                            <i data-lucide="credit-card" class="h-3.5 w-3.5"></i>
                                            <span>Settle</span>
                                        </button>
                                    @else
                                        <span class="text-emerald-700 text-xs font-bold">Settled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-8 text-center text-sm font-bold text-slate-400">No credit invoices found for this supplier.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        </section>

        <!-- SETTLE VENDOR CREDIT MODAL -->
        <div x-show="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" style="display: none;" x-cloak>
            <div @click.away="showPayModal = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">Settle Vendor Credit</h3>
                        <p class="text-xs text-slate-500 font-semibold" x-text="'Recording company payment for ' + (selectedInvoice ? selectedInvoice.invoice_number : '')"></p>
                    </div>
                    <button @click="showPayModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form :action="selectedInvoice ? selectedInvoice.pay_url : '#'" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Amount to Pay (₹)</label>
                        <input type="number" step="0.01" min="0.01" :max="selectedInvoice ? selectedInvoice.due : 999999" x-model="payAmount" name="payment_amount" required class="min-h-11 w-full rounded-xl border border-slate-300 px-3 font-mono text-sm font-bold text-slate-900">
                        <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500 font-semibold">
                            <span>Outstanding: <strong class="font-mono text-rose-700" x-text="'₹' + (selectedInvoice ? selectedInvoice.due.toFixed(2) : '0.00')"></strong></span>
                            <button type="button" @click="payAmount = selectedInvoice.due" class="text-emerald-700 hover:underline">Pay Full Due</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Method</label>
                        <select name="payment_method" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="Bank">Bank Transfer / NEFT / RTGS</option>
                            <option value="Online">Online / Net Banking</option>
                            <option value="GPay">UPI / GPay</option>
                            <option value="Cash">Cash in Hand</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Company Account (Optional)</label>
                        <select name="company_account_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="">Select Account (Default)</option>
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($acc, old('company_account_id'), $companyAccounts))>{{ $acc->name }} ({{ strtoupper($acc->account_type) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Note / Bank Ref</label>
                        <input type="text" name="payment_note" placeholder="Cheque #, UTR, transaction ref..." class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showPayModal = false" class="min-h-10 rounded-xl border border-slate-300 px-4 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                            <i data-lucide="check" class="h-4 w-4"></i> Confirm Settlement
                        </button>
                    </div>
                </form>
            </div>
        </div>
        {{-- EDIT SETTLEMENT REVERSAL MODAL --}}
        <div x-show="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;" x-cloak>
            <div @click.away="showEditModal = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">Edit Vendor Settlement</h3>
                        <p class="text-xs text-amber-600 font-semibold" x-text="`Correcting settlement #${editTarget?.id}`"></p>
                    </div>
                    <button @click="showEditModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <template x-if="editTarget">
                    <form :action="`/admin/cashbook/finance/vendor-credit/settlements/${editTarget.id}/update`" method="POST" class="mt-4 space-y-4">
                        @csrf
                        <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-xs text-amber-900 font-medium">
                            <span class="block font-black text-amber-950 uppercase text-[10px]">Financial Correction Notice</span>
                            <p class="mt-1 text-[11px] font-semibold text-amber-800">Financial edits (amount, date, account, discount choice) perform an atomic reversal of previous allocations and journals before applying corrected values.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Reference</label>
                            <input type="text" name="reference" x-model="editTarget.reference" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Payment Note</label>
                            <input type="text" name="note" x-model="editTarget.note" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Correction Reason <span class="text-rose-600">*</span></label>
                            <select x-model="editReason" name="reason" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="">Select reason for edit</option>
                                <option value="Correction of Note / Reference">Correction of Note / Reference</option>
                                <option value="Wrong Payment Amount">Wrong Payment Amount</option>
                                <option value="Wrong Company Account">Wrong Company Account</option>
                                <option value="Wrong Date">Wrong Date</option>
                                <option value="Wrong Discount Application">Wrong Discount Application</option>
                                <option value="Other / Data Correction">Other / Data Correction</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button type="button" @click="showEditModal = false" class="min-h-10 rounded-xl border border-slate-300 px-4 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" :disabled="!editReason" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-amber-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-amber-500 disabled:bg-slate-400">
                                <i data-lucide="check" class="h-4 w-4"></i> Save Correction
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        {{-- DELETE SETTLEMENT REVERSAL MODAL --}}
        <div x-show="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;" x-cloak>
            <div @click.away="showDeleteModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">Delete Vendor Settlement</h3>
                        <p class="text-xs text-rose-600 font-semibold" x-text="`Reversing settlement #${deleteTarget?.id}`"></p>
                    </div>
                    <button @click="showDeleteModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <template x-if="deleteTarget">
                    <form :action="`/admin/cashbook/finance/vendor-credit/settlements/${deleteTarget.id}/delete`" method="POST" class="mt-4 space-y-4">
                        @csrf
                        <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-xs text-rose-900 font-medium">
                            <span class="block font-black text-rose-950 uppercase text-[10px]">Reversal Summary</span>
                            <div class="mt-1 font-mono text-xs space-y-1">
                                <div>Date: <strong x-text="deleteTarget.date"></strong></div>
                                <div>Cash Paid: <strong x-text="rupees(deleteTarget.cash)"></strong></div>
                                <div>Discount Waived: <strong x-text="rupees(deleteTarget.discount)"></strong></div>
                            </div>
                            <p class="mt-2 text-[11px] font-semibold text-rose-800">Deleting this settlement will restore affected bill outstanding balances, restore vendor advance balances, unmatch statement reconciliations, and reverse GL entries.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Correction Reason <span class="text-rose-600">*</span></label>
                            <select x-model="deleteReason" name="reason" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="">Select reason for deletion</option>
                                <option value="Duplicate Entry">Duplicate Entry</option>
                                <option value="Wrong Payment Amount">Wrong Payment Amount</option>
                                <option value="Wrong Company Account">Wrong Company Account</option>
                                <option value="Wrong Date">Wrong Date</option>
                                <option value="Wrong Discount Application">Wrong Discount Application</option>
                                <option value="Other / Data Correction">Other / Data Correction</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Additional Notes (Optional)</label>
                            <input type="text" x-model="deleteNotes" name="notes" placeholder="Explain correction..." class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button type="button" @click="showDeleteModal = false" class="min-h-10 rounded-xl border border-slate-300 px-4 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" :disabled="!deleteReason" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-rose-500 disabled:bg-slate-400">
                                <i data-lucide="trash-2" class="h-4 w-4"></i> Confirm Reversal & Delete
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
    <script>
        function vendorSettlement(candidates) {
            return {
                showPayModal: false, selectedInvoice: null, payAmount: 0, cash: 0, paymentSource: 'company', statementEntryId: '', companyAccountId: '{{ App\Models\Cashbook\CompanyAccount::resolveSelectedId(old('company_account_id'), $companyAccounts) ?? '' }}', paymentDate: '{{ now()->toDateString() }}', reference: '',
                useAdvance: false, differenceTreatment: 'outstanding', allocationOrder: 'oldest',
                showEditModal: false, editTarget: null, editReason: '',
                showDeleteModal: false, deleteTarget: null, deleteReason: '', deleteNotes: '',
                rows: candidates.map(row => ({ ...row, selected: false })),
                rupees(value) { return `₹${Number(value || 0).toFixed(2)}` },
                get selectedRows() { return this.rows.filter(row => row.selected) },
                get selectedTotal() { return this.selectedRows.reduce((sum, row) => sum + Number(row.outstanding), 0) },
                get advanceUsed() { return this.useAdvance ? Math.min({{ $availableVendorAdvance }}, this.selectedTotal) : 0 },
                get cashForInvoices() { return Math.min(Number(this.cash || 0), Math.max(0, this.selectedTotal - this.advanceUsed)) },
                get difference() { return Math.max(0, this.selectedTotal - this.advanceUsed - this.cashForInvoices) },
                get discount() { return this.differenceTreatment === 'discount' ? this.difference : 0 },
                get totalSettled() { return this.cashForInvoices + this.advanceUsed + this.discount },
                get remaining() { return Math.max(0, this.selectedTotal - this.totalSettled) },
                get newAdvance() { return Math.max(0, Number(this.cash || 0) - this.cashForInvoices) },
                get preview() {
                    let cash = this.cashForInvoices; let advance = this.advanceUsed; let discount = this.discount;
                    let rows = this.allocationOrder === 'newest' ? [...this.rows].reverse() : this.rows;
                    return rows.map(row => {
                        if (!row.selected) {
                            return { ...row, cash: 0, advance: 0, discount: 0, remaining: row.outstanding };
                        }
                        const paidCash = Math.min(cash, row.outstanding); cash -= paidCash; const afterCash = row.outstanding - paidCash; const paidAdvance = Math.min(advance, afterCash); advance -= paidAdvance; const afterAdvance = afterCash - paidAdvance; const paidDiscount = Math.min(discount, afterAdvance); discount -= paidDiscount; return { ...row, cash: paidCash, advance: paidAdvance, discount: paidDiscount, remaining: afterAdvance - paidDiscount };
                    });
                },
                onCashInput() {
                    if (this.cash > 0 && this.selectedRows.length === 0) {
                        this.autoSelectByAmount();
                    }
                },
                autoSelectByAmount() {
                    let target = Number(this.cash || 0) + (this.useAdvance ? {{ $availableVendorAdvance }} : 0);
                    let accumulated = 0;
                    this.rows.forEach(row => {
                        if (target > 0 && accumulated < target) {
                            row.selected = true;
                            accumulated += Number(row.outstanding);
                        } else if (this.cash > 0) {
                            row.selected = false;
                        }
                    });
                },
                toggleBillRow(id) {
                    const row = this.rows.find(r => r.id === id);
                    if (row) row.selected = !row.selected;
                },
                selectVisible() { this.rows.forEach(row => { row.selected = true }) },
                clearSelection() { this.rows.forEach(row => { row.selected = false }) },
                selectStatement(event) { const option = event.target.options[event.target.selectedIndex]; if (!option || !option.value) return; this.cash = Number(option.dataset.amount); this.companyAccountId = option.dataset.account; this.paymentDate = option.dataset.date; this.reference = option.dataset.reference; this.autoSelectByAmount(); },
                openEditSettlementModal(id, reference, note, cash, date) {
                    this.editTarget = { id, reference: reference || '', note: note || '', cash, date };
                    this.editReason = '';
                    this.showEditModal = true;
                },
                openDeleteSettlementModal(id, uuid, date, cash, discount) {
                    this.deleteTarget = { id, uuid, date, cash, discount };
                    this.deleteReason = '';
                    this.deleteNotes = '';
                    this.showDeleteModal = true;
                }
            }
        }
    </script>
@endsection
