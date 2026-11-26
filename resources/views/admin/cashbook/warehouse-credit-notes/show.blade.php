@extends('admin.cashbook.layouts.app')

@section('title', ($creditNote->credit_note_number ?: 'Draft #' . $creditNote->id) . ' — Warehouse Credit Note — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-text" class="h-5 w-5 text-emerald-600"></i>
    <span>Warehouse Credit Note Details</span>
@endsection

@section('header_subtitle')
    Audited warehouse credit note, batch-level inventory movements, and double-entry accounting records.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6" x-data="{ showVoidModal: false, voidReason: '', isVoiding: false }">
    @if (session('status'))
        <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
            <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-emerald-600"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
            <ul class="list-disc space-y-1 pl-5 font-semibold">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Top Bar --}}
    <div class="flex flex-col gap-3 border-b border-slate-200/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.cashbook.warehouse-credit-notes.index') }}"
               class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
                <span>All Warehouse Credit Notes</span>
            </a>

            <span class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold text-slate-900">
                {{ $creditNote->credit_note_number ?: 'DRAFT #' . $creditNote->id }}
            </span>

            @php
                $badgeClasses = match ($creditNote->status->value) {
                    'active' => 'bg-emerald-100 text-emerald-800',
                    'voided' => 'bg-rose-100 text-rose-800',
                    default => 'bg-amber-100 text-amber-800',
                };
            @endphp
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-extrabold uppercase tracking-wider {{ $badgeClasses }}">
                {{ $creditNote->status->label() }}
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($creditNote->isDraft() && $canManage)
                <a href="{{ route('admin.cashbook.warehouse-credit-notes.edit', ['creditNote' => $creditNote->public_uuid]) }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                    <span>Edit Draft</span>
                </a>

                <form method="POST" action="{{ route('admin.cashbook.warehouse-credit-notes.finalize', ['creditNote' => $creditNote->public_uuid]) }}"
                      onsubmit="return confirm('Finalize this Warehouse Credit Note? Stock and accounting entries will be posted immediately.');">
                    @csrf
                    <button type="submit"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 text-xs font-bold text-white hover:bg-emerald-700">
                        <i data-lucide="check-check" class="h-3.5 w-3.5"></i>
                        <span>Finalize</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.cashbook.warehouse-credit-notes.destroy', ['creditNote' => $creditNote->public_uuid]) }}"
                      onsubmit="return confirm('Delete this draft Warehouse Credit Note?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 text-xs font-bold text-rose-700 hover:bg-rose-100">
                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                        <span>Delete Draft</span>
                    </button>
                </form>
            @endif

            @if ($creditNote->isActive() && $canVoid)
                <button type="button" @click="showVoidModal = true"
                        class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 text-xs font-bold text-rose-700 hover:bg-rose-100">
                    <i data-lucide="ban" class="h-3.5 w-3.5"></i>
                    <span>Void Credit Note</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Void Banner --}}
    @if ($creditNote->isVoided())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-900">
            <div class="flex items-center gap-2 font-black uppercase tracking-wider text-rose-700">
                <i data-lucide="alert-octagon" class="h-4 w-4"></i>
                <span>Voided &amp; Reversed</span>
            </div>
            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div><span class="font-bold">Voided By:</span> {{ $creditNote->voidedBy?->name ?? 'System' }}</div>
                <div><span class="font-bold">Voided At:</span> {{ $creditNote->voided_at?->format('d M Y, H:i') ?? '—' }}</div>
                <div><span class="font-bold">Reason:</span> {{ $creditNote->void_reason ?? '—' }}</div>
            </div>
        </div>
    @endif

    {{-- Metadata + Totals --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs lg:col-span-2">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Document Information</h2>
            <div class="mt-3 grid grid-cols-2 gap-4 text-xs sm:grid-cols-3">
                <div>
                    <div class="text-slate-500">Warehouse</div>
                    <div class="mt-0.5 font-bold text-slate-900">{{ $creditNote->warehouse?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Business Date</div>
                    <div class="mt-0.5 font-bold text-slate-900">{{ $creditNote->business_date?->format('d M Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Reason</div>
                    <div class="mt-0.5 font-bold text-slate-900">{{ $creditNote->reason?->label() ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Created By</div>
                    <div class="mt-0.5 font-semibold text-slate-800">{{ $creditNote->createdBy?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Finalized By</div>
                    <div class="mt-0.5 font-semibold text-slate-800">
                        {{ $creditNote->finalizedBy?->name ?? '—' }}
                        @if ($creditNote->finalized_at)
                            <span class="block text-[11px] text-slate-400">{{ $creditNote->finalized_at->format('d M Y, H:i') }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-slate-500">Remarks</div>
                    <div class="mt-0.5 font-medium text-slate-700">{{ $creditNote->remarks ?: '—' }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-2.5 text-xs">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Valuation Summary</h2>
            <div class="flex justify-between">
                <span class="text-slate-600">Stock IN Total</span>
                <span class="font-mono font-bold text-emerald-700">₹{{ number_format($stockInTotal, 2) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Stock OUT (FIFO)</span>
                <span class="font-mono font-bold text-amber-700">₹{{ number_format($stockOutTotal, 2) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Damage (FIFO)</span>
                <span class="font-mono font-bold text-rose-700">₹{{ number_format($damageTotal, 2) }}</span>
            </div>
            @php $netChange = round($stockInTotal - $stockOutTotal - $damageTotal, 2); @endphp
            <div class="flex justify-between border-t border-slate-200 pt-2.5 text-sm font-black">
                <span class="text-slate-900">Net Inventory Change</span>
                <span class="font-mono {{ $netChange >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $netChange >= 0 ? '+' : '-' }}₹{{ number_format(abs($netChange), 2) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Line Items --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xs">
        <div class="border-b border-slate-200 px-5 py-3.5">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Product Line Items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Grade</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3 text-right">Qty / Unit</th>
                        <th class="px-4 py-3 text-right">Base Qty</th>
                        <th class="px-4 py-3 text-right">Valuation Rate</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($creditNote->items as $item)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ $item->product?->name ?? 'Product #' . $item->product_id }}
                                @if ($item->product?->sku)
                                    <span class="ml-1 font-mono text-[11px] text-slate-400">({{ $item->product->sku }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-700">
                                {{ $item->grade?->label() ?? $item->grade }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">
                                    {{ $item->inventory_action?->label() ?? $item->inventory_action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ number_format((float) $item->quantity, 3) }} {{ $item->unit }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600">
                                {{ number_format((float) $item->base_qty, 3) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                ₹{{ number_format((float) $item->valuation_rate, 4) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format((float) ($item->inventory_action === \App\Enums\Warehouse\WarehouseCreditInventoryAction::In ? $item->line_total : $item->fifo_cost_total), 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Batch & Movement Breakdown (Collapsible / Lazy View) --}}
    @if ($creditNote->items->flatMap->movements->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xs" x-data="{ open: true }">
            <button type="button" @click="open = !open"
                    class="flex w-full items-center justify-between border-b border-slate-200 px-5 py-3.5 text-left">
                <span class="text-xs font-black uppercase tracking-wider text-slate-500">Batch &amp; Movement Traceability</span>
                <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Product</th>
                            <th class="px-4 py-2.5">Batch Ref</th>
                            <th class="px-4 py-2.5">Movement #</th>
                            <th class="px-4 py-2.5 text-right">Qty</th>
                            <th class="px-4 py-2.5 text-right">Cost / Unit</th>
                            <th class="px-4 py-2.5">Reversal Movement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($creditNote->items as $item)
                            @foreach ($item->movements as $mov)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-slate-800">{{ $item->product?->name }}</td>
                                    <td class="px-4 py-2.5 font-mono text-slate-700">{{ $mov->stockBatch?->reference ?? 'Batch #' . $mov->stock_batch_id }}</td>
                                    <td class="px-4 py-2.5 font-mono text-slate-600">#{{ $mov->stock_movement_id }} ({{ $mov->stockMovement?->type?->label() ?? $mov->stockMovement?->type }})</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ number_format((float) $mov->quantity, 3) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">₹{{ number_format((float) $mov->cost_per_unit, 4) }}</td>
                                    <td class="px-4 py-2.5 font-mono text-rose-700">
                                        @if ($mov->reversal_stock_movement_id)
                                            #{{ $mov->reversal_stock_movement_id }} ({{ $mov->reversed_at?->format('d M Y H:i') }})
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Linked Journal Entry --}}
    @if ($canViewJournal && $creditNote->journalEntry)
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xs">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">
                    Journal Entry #{{ $creditNote->journalEntry->id }} ({{ $creditNote->journalEntry->entry_date?->format('d M Y') }})
                </h2>
                <span class="font-mono text-xs font-bold text-slate-600">{{ $creditNote->journalEntry->reference }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Account</th>
                            <th class="px-4 py-2.5 text-right">Debit</th>
                            <th class="px-4 py-2.5 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($creditNote->journalEntry->transactions as $tx)
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-slate-800">
                                    {{ $tx->account?->code }} — {{ $tx->account?->name }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold text-slate-900">
                                    {{ $tx->type === 'debit' ? '₹' . number_format((float) $tx->amount, 2) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold text-slate-900">
                                    {{ $tx->type === 'credit' ? '₹' . number_format((float) $tx->amount, 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Void Confirmation Modal --}}
    @if ($creditNote->isActive() && $canVoid)
        <div x-show="showVoidModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center gap-2 text-rose-700">
                    <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                    <h3 class="text-base font-black">Void Warehouse Credit Note</h3>
                </div>
                <p class="mt-2 text-xs text-slate-600">
                    This will reverse exact batch stock movements, soft-delete any linked wastage entries, and post a reversing journal entry.
                </p>

                <form method="POST"
                      action="{{ route('admin.cashbook.warehouse-credit-notes.void', ['creditNote' => $creditNote->public_uuid]) }}"
                      @submit="isVoiding = true"
                      class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Cancellation Reason <span class="text-rose-500">*</span></label>
                        <textarea name="void_reason" x-model="voidReason" rows="3" required minlength="3"
                                  placeholder="Enter reason for voiding (min 3 characters)..."
                                  class="w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-800 focus:border-rose-500 focus:outline-none"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showVoidModal = false"
                                class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isVoiding || voidReason.trim().length < 3"
                                class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-rose-600 px-4 text-xs font-bold text-white hover:bg-rose-700 disabled:opacity-50">
                            <i data-lucide="ban" class="h-3.5 w-3.5"></i>
                            <span x-text="isVoiding ? 'Voiding...' : 'Confirm Void'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
