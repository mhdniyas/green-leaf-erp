@extends('admin.cashbook.layouts.app')

@section('title', 'Warehouse Credit Notes — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="warehouse" class="h-5 w-5 text-emerald-600"></i>
    <span>Warehouse Credit Notes</span>
@endsection

@section('header_subtitle')
    Independent warehouse inventory adjustments, Stock IN, Stock OUT, and Damage credit notes.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6">
    @if (session('status'))
        <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
            <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-emerald-600"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800">
            <i data-lucide="alert-circle" class="h-4 w-4 shrink-0 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Top Header Action --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-xs font-medium text-slate-500">
            Summary reflects <span class="font-bold text-slate-700">finalized (Active)</span> notes within the selected filters. Drafts and voided notes are excluded.
        </div>

        @can('create', \App\Models\WarehouseCreditNote::class)
            <a href="{{ route('admin.cashbook.warehouse-credit-notes.create') }}"
               class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700">
                <i data-lucide="plus" class="h-4 w-4"></i>
                <span>New Warehouse Credit Note</span>
            </a>
        @endcan
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Stock IN Value</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i data-lucide="arrow-down-left" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight text-emerald-700">
                ₹{{ number_format($summary['stock_in_total'], 2) }}
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Stock OUT Value</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight text-amber-700">
                ₹{{ number_format($summary['stock_out_total'], 2) }}
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Damage Value</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <i data-lucide="flame" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight text-rose-700">
                ₹{{ number_format($summary['damage_total'], 2) }}
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Net Inventory Change</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <i data-lucide="scale" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight {{ $summary['net_change'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $summary['net_change'] >= 0 ? '+' : '-' }}₹{{ number_format(abs($summary['net_change']), 2) }}
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.cashbook.warehouse-credit-notes.index') }}"
          class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Warehouse</label>
                <select name="warehouse_id" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="">All Authorized Warehouses</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected((int) ($filters['warehouse_id'] ?? 0) === $wh->id)>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">From Date</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">To Date</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</label>
                <select name="status" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}" @selected(($filters['status'] ?? '') === $st->value)>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Action</label>
                <select name="inventory_action" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="">All Actions</option>
                    @foreach ($actions as $act)
                        <option value="{{ $act->value }}" @selected(($filters['inventory_action'] ?? '') === $act->value)>
                            {{ $act->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Reason</label>
                <select name="reason" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="">All Reasons</option>
                    @foreach ($reasons as $rsn)
                        <option value="{{ $rsn->value }}" @selected(($filters['reason'] ?? '') === $rsn->value)>
                            {{ $rsn->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Search</label>
                <div class="flex gap-1.5">
                    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="WCN # or remarks..."
                           class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <button type="submit"
                            class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-bold text-white hover:bg-slate-800">
                        <i data-lucide="search" class="h-3.5 w-3.5"></i>
                    </button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.cashbook.warehouse-credit-notes.index') }}"
                           title="Reset Filters"
                           class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">
                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Credit Note No.</th>
                        <th class="px-4 py-3">Business Date</th>
                        <th class="px-4 py-3">Warehouse</th>
                        <th class="px-4 py-3 text-center">Products</th>
                        <th class="px-4 py-3 text-right">Stock IN</th>
                        <th class="px-4 py-3 text-right">Stock OUT</th>
                        <th class="px-4 py-3 text-right">Damage</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($creditNotes as $note)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3 font-mono font-bold text-slate-900">
                                <a href="{{ route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $note->public_uuid]) }}"
                                   class="hover:text-emerald-700 hover:underline">
                                    {{ $note->credit_note_number ?: 'DRAFT #' . $note->id }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-700">
                                {{ $note->business_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-800">
                                {{ $note->warehouse?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-[11px] font-bold text-slate-700">
                                    {{ $note->items_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">
                                ₹{{ number_format((float) ($note->stock_in_value ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-amber-700">
                                ₹{{ number_format((float) ($note->stock_out_value ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-rose-700">
                                ₹{{ number_format((float) ($note->damage_value ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $badgeClasses = match ($note->status->value) {
                                        'active' => 'bg-emerald-100 text-emerald-800',
                                        'voided' => 'bg-rose-100 text-rose-800',
                                        default => 'bg-amber-100 text-amber-800',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $badgeClasses }}">
                                    {{ $note->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $note->public_uuid]) }}"
                                       class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 hover:bg-slate-50">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        <span>View</span>
                                    </a>
                                    @if ($note->isDraft() && auth()->user()?->can('update', $note))
                                        <a href="{{ route('admin.cashbook.warehouse-credit-notes.edit', ['creditNote' => $note->public_uuid]) }}"
                                           class="inline-flex h-8 items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                            <span>Edit</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-2 text-slate-500">
                                    <i data-lucide="inbox" class="h-8 w-8 text-slate-300"></i>
                                    <p class="text-sm font-bold text-slate-700">No warehouse credit notes found</p>
                                    <p class="text-xs text-slate-500">Try adjusting your filters or create a new warehouse credit note.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($creditNotes->hasPages())
            <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                {{ $creditNotes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
