@php
    $canViewCreditNoteDetail = auth()->user()?->isMainAdmin()
        || auth()->user()?->hasRole('admin')
        || auth()->user()?->hasRole('super_admin')
        || auth()->user()?->hasRole('accounts');

    $canCreateCreditNote = auth()->user()?->hasRole('admin') && (string) $invoice->status !== 'cancelled';

    $applications = $invoice->creditApplications ?? collect();
    $legacyNotes = $invoice->creditNotes ?? collect();
    $hasAdjustments = $applications->isNotEmpty() || $legacyNotes->isNotEmpty();

    $bill = $finalBill ?? $invoice->finalBill();
    $money = fn (float|int|string|null $amount): string => 'Rs. ' . number_format((float) $amount, 2);
@endphp

<section class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
        <div>
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-950">Applied Credit & Debit Notes</h3>
                @if ($bill->hasAdjustments)
                    @if ($bill->finalizedDebitTotal > $bill->finalizedCreditTotal)
                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800">
                            Net Debit: +{{ $money($bill->netAdjustment) }}
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">
                            Net Credit: -{{ $money(abs($bill->netAdjustment)) }}
                        </span>
                    @endif
                @else
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600">
                        No Adjustments
                    </span>
                @endif
            </div>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">
                Audited post-generation credit notes and additional debit charges applied to this bill.
            </p>
        </div>

        @if ($canCreateCreditNote)
            <form method="POST" action="{{ route('admin.cashbook.reports.shops.credit-notes.select') }}" class="inline-block shrink-0">
                @csrf
                <input type="hidden" name="invoice_ref" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) $invoice->id) }}">
                <button type="submit" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-black text-emerald-800 hover:bg-emerald-100 transition shadow-2xs cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    <span>+ Add Credit / Debit Note</span>
                </button>
            </form>
        @endif
    </div>

    @if ($hasAdjustments)
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-3 py-2.5">Credit / Debit Note #</th>
                        <th class="px-3 py-2.5">Type / Direction</th>
                        <th class="px-3 py-2.5">Reason & Notes</th>
                        <th class="px-3 py-2.5 text-right">Applied Amount</th>
                        <th class="px-3 py-2.5">Applied Date</th>
                        <th class="px-3 py-2.5">Applied By</th>
                        <th class="px-3 py-2.5 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-800 bg-white">
                    {{-- 1. Shop-level credit applications --}}
                    @foreach ($applications as $app)
                        @php
                            $note = $app->creditNote;
                            $isDebit = ($note?->direction === \App\Enums\ShopInvoice\ShopCreditNoteDirection::Debit || $note?->direction === 'debit');
                            $reasonLabel = $note?->reason_type instanceof \App\Enums\ShopInvoice\ShopCreditNoteReason
                                ? $note->reason_type->label()
                                : ($note?->reason_type ? str($note->reason_type)->replace('_', ' ')->title() : 'Adjustment');
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition {{ $app->isReversed() ? 'opacity-60 bg-slate-50/40' : '' }}">
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                @if ($note && $canViewCreditNoteDetail)
                                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.show', $note) }}" target="_blank"
                                        class="font-mono font-bold text-indigo-600 hover:text-indigo-900 hover:underline inline-flex items-center gap-1 group">
                                        <span>{{ $note->credit_note_number }}</span>
                                        <svg class="w-3 h-3 text-indigo-400 group-hover:text-indigo-600 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </a>
                                @elseif ($note)
                                    <span class="font-mono font-bold text-slate-900 block">{{ $note->credit_note_number }}</span>
                                @else
                                    <span class="font-mono font-bold text-slate-900 block">SCN-APP-{{ $app->id }}</span>
                                @endif
                                <span class="text-[10px] text-slate-400 font-mono block">Doc Date: {{ optional($note?->credit_note_date)->format('d M Y') ?: '—' }}</span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                @if ($isDebit)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                        Additional Charge (Debit)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        Credit Note
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-bold text-slate-900">{{ $reasonLabel }}</div>
                                @if ($app->notes)
                                    <div class="text-[11px] text-slate-500 font-normal italic">{{ $app->notes }}</div>
                                @elseif ($note?->notes)
                                    <div class="text-[11px] text-slate-500 font-normal italic">{{ $note->notes }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono font-bold whitespace-nowrap {{ $isDebit ? 'text-amber-700' : 'text-emerald-700' }}">
                                {{ $isDebit ? '+ ' : '- ' }}{{ $money($app->amount) }}
                            </td>
                            <td class="px-3 py-2.5 text-slate-600 font-mono whitespace-nowrap">
                                {{ optional($app->applied_at)->format('d M Y') }}
                            </td>
                            <td class="px-3 py-2.5 text-slate-800 whitespace-nowrap">
                                {{ $app->appliedByUser?->name ?? 'System' }}
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                @if ($app->isReversed())
                                    <span class="inline-flex items-center rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-bold text-rose-800 uppercase">
                                        Reversed
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-800 uppercase">
                                        Active
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    {{-- 2. Legacy invoice-linked credit notes --}}
                    @foreach ($legacyNotes as $cn)
                        @php
                            $isDebit = ($cn->direction === \App\Enums\ShopInvoice\ShopCreditNoteDirection::Debit || $cn->direction === 'debit');
                            $reasonLabel = $cn->reason_type instanceof \App\Enums\ShopInvoice\ShopCreditNoteReason
                                ? $cn->reason_type->label()
                                : ($cn->reason_type ? str($cn->reason_type)->replace('_', ' ')->title() : 'Legacy Adjustment');
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-900 block">{{ $cn->credit_note_number }}</span>
                                <span class="text-[10px] text-slate-400 font-mono block">Doc Date: {{ optional($cn->credit_note_date)->format('d M Y') }}</span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                @if ($isDebit)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                        Additional Charge (Debit)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        Credit Note
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-bold text-slate-900">{{ $reasonLabel }}</div>
                                @if ($cn->notes)
                                    <div class="text-[11px] text-slate-500 font-normal italic">{{ $cn->notes }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono font-bold whitespace-nowrap {{ $isDebit ? 'text-amber-700' : 'text-emerald-700' }}">
                                {{ $isDebit ? '+ ' : '- ' }}{{ $money($cn->total_credit_amount) }}
                            </td>
                            <td class="px-3 py-2.5 text-slate-600 font-mono whitespace-nowrap">
                                {{ optional($cn->created_at)->format('d M Y') }}
                            </td>
                            <td class="px-3 py-2.5 text-slate-800 whitespace-nowrap">
                                {{ $cn->approvedBy?->name ?? 'System' }}
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-800 uppercase">
                                    {{ $cn->status?->value ?? $cn->status ?? 'Approved' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Financial Reconciliation Breakdown --}}
        <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-3 sm:p-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block">Original Invoice</span>
                    <span class="font-mono font-black text-slate-950 text-sm">{{ $money($bill->originalInvoiceTotal) }}</span>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700 block">Total Credits</span>
                    <span class="font-mono font-black text-emerald-700 text-sm">- {{ $money($bill->finalizedCreditTotal) }}</span>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 block">Total Debits</span>
                    <span class="font-mono font-black text-amber-700 text-sm">+ {{ $money($bill->finalizedDebitTotal) }}</span>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700 block">Effective Net Bill</span>
                    <span class="font-mono font-black text-indigo-700 text-sm">{{ $money($bill->finalNetPayable) }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-xs text-slate-500">
            <p class="font-bold text-slate-700">No credit or debit notes have been applied to this invoice.</p>
            <p class="mt-1 text-[11px] text-slate-400">All original quantities, prices, and totals remain at their initial billed figures.</p>
        </div>
    @endif
</section>
