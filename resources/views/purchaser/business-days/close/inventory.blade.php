@extends('purchaser.business-days.layouts.app')

@section('title', 'Close Day · Inventory Reconciliation — ' . $warehouse->name)
@section('page_title', 'Close Review · Inventory Reconciliation')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Top Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center gap-1.5 text-xs font-black text-teal-700 hover:text-teal-900 mb-1">
                <span aria-hidden="true">&larr;</span> Back to Close Day
            </a>
            <h2 class="text-xl font-black text-slate-950">Inventory Reconciliation ({{ $comparisonRows->count() }})</h2>
            <p class="text-xs font-semibold text-slate-500">Business Day: {{ $day->business_date->format('d M Y') }} · {{ $warehouse->name }}</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800">
                Fully Matched: {{ $summary['fully_billed_count'] ?? 0 }}
            </span>
            @if (($summary['pending_count'] ?? 0) > 0)
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800">
                    Pending: {{ $summary['pending_count'] }}
                </span>
            @endif
        </div>
    </div>

    <!-- Inventory Table / Cards -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-3 text-right">Advance</th>
                        <th class="py-3.5 px-3 text-right">Bill</th>
                        <th class="py-3.5 px-3 text-right">Matched</th>
                        <th class="py-3.5 px-3 text-right">Pending</th>
                        <th class="py-3.5 px-3 text-right">Inventory Balance</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse ($comparisonRows as $row)
                        @php
                            $status = $row['status'] ?? 'pending';
                            $advQty = (float) ($row['advance_qty'] ?? 0);
                            $billQty = (float) ($row['bill_qty'] ?? 0);
                            $matchedQty = (float) ($row['matched_bill_qty'] ?? 0);
                            $pendingQty = max(0.0, $advQty - $matchedQty);
                            $invBal = (float) ($row['inventory_balance'] ?? 0);
                            $unit = $row['unit'] ?? 'kg';
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-4 px-5 font-black text-slate-900">
                                {{ $row['product_name'] }}
                            </td>
                            <td class="py-4 px-3 text-right font-bold text-slate-700">
                                {{ $advQty }} {{ $unit }}
                            </td>
                            <td class="py-4 px-3 text-right font-bold text-slate-700">
                                {{ $billQty }} {{ $unit }}
                            </td>
                            <td class="py-4 px-3 text-right font-bold text-emerald-600">
                                {{ $matchedQty }} {{ $unit }}
                            </td>
                            <td class="py-4 px-3 text-right font-bold {{ $pendingQty > 0.0001 ? 'text-amber-600' : 'text-slate-400' }}">
                                {{ $pendingQty > 0.0001 ? $pendingQty . ' ' . $unit : '0' }}
                            </td>
                            <td class="py-4 px-3 text-right font-black text-slate-900">
                                {{ $invBal }} {{ $unit }}
                            </td>
                            <td class="py-4 px-4 text-center">
                                @if ($status === 'fully_matched')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                        Matched
                                    </span>
                                @elseif ($status === 'partial')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-800">
                                        Partial
                                    </span>
                                @elseif ($status === 'unit_mismatch')
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800">
                                        Unit Issue
                                    </span>
                                @elseif ($status === 'advance_only')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-800">
                                        Advance Only
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700">
                                        {{ str_replace('_', ' ', $status) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-xs font-semibold text-slate-400">
                                No inventory movements recorded for this business day.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
