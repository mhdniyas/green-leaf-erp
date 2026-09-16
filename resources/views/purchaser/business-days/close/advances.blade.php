@extends('purchaser.business-days.layouts.app')

@section('title', 'Close Day · Advance Receives — ' . $warehouse->name)
@section('page_title', 'Close Review · Advance Receives')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Top Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center gap-1.5 text-xs font-black text-teal-700 hover:text-teal-900 mb-1">
                <span aria-hidden="true">&larr;</span> Back to Close Day
            </a>
            <h2 class="text-xl font-black text-slate-950">Advance Receives ({{ $advanceReceives->count() }})</h2>
            <p class="text-xs font-semibold text-slate-500">Business Day: {{ $day->business_date->format('d M Y') }} · {{ $warehouse->name }}</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-black text-teal-800">
                Total Advances: {{ $advanceReceives->count() }}
            </span>
        </div>
    </div>

    <!-- Advances Table / Cards -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-5">GRN #</th>
                        <th class="py-3.5 px-4">Products</th>
                        <th class="py-3.5 px-4 text-right">Qty</th>
                        <th class="py-3.5 px-4">Unit</th>
                        <th class="py-3.5 px-4">Received Time</th>
                        <th class="py-3.5 px-5 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse ($advanceReceives as $adv)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-4 px-5 font-black text-slate-900 whitespace-nowrap">
                                <span>{{ $adv->grn_number }}</span>
                                <span class="block text-[10px] font-bold text-slate-400">By {{ $adv->receivedBy?->name ?? 'Receiver' }}</span>
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-800">
                                <div class="space-y-1">
                                    @foreach ($adv->items as $item)
                                        <div>{{ $item->product?->name }}</div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 px-4 text-right font-black text-slate-900">
                                <div class="space-y-1">
                                    @foreach ($adv->items as $item)
                                        <div>{{ (float) $item->received_qty }}</div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-600">
                                <div class="space-y-1">
                                    @foreach ($adv->items as $item)
                                        <div>{{ $item->received_unit ?? $item->product?->unit ?? 'kg' }}</div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 px-4 text-slate-600 whitespace-nowrap">
                                {{ $adv->received_at?->format('h:i A') ?? '—' }}
                            </td>
                            <td class="py-4 px-5 text-center">
                                <span class="inline-flex items-center rounded-full bg-teal-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-teal-800">
                                    {{ $adv->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-xs font-semibold text-slate-400">
                                No warehouse advance receives recorded for this business day.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
