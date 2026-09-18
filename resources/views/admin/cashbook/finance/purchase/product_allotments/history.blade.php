@extends('admin.cashbook.layouts.app')

@section('title', 'Allotment History - ' . $product->name)
@section('header_title')
    <i data-lucide="history" class="h-5 w-5 text-emerald-600"></i> Allotment History
@endsection

@section('header_subtitle')
    Historical purchaser responsibility timeline for {{ $product->name }}
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-black text-slate-950">{{ $product->name }}</h1>
                @if($product->sku)
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono font-bold text-slate-600">{{ $product->sku }}</span>
                @endif
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                    {{ $product->category?->name ?? 'Uncategorized' }}
                </span>
            </div>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">
                Complete timeline of assigned purchasers and effective periods.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.finance.purchase.product-allotments.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Allotments List
            </a>
        </div>
    </div>

    <!-- History Timeline Table -->
    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="text-xs font-black uppercase text-slate-700">Allotment Audit Timeline</h3>
            <span class="text-xs font-bold text-slate-500">{{ $history->count() }} Total Period(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-100 text-[10px] font-black uppercase text-slate-500">
                        <th class="p-3">Purchaser</th>
                        <th class="p-3">Effective From</th>
                        <th class="p-3">Effective To</th>
                        <th class="p-3">Period Status</th>
                        <th class="p-3">Assigned By</th>
                        <th class="p-3">Assigned On</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($history as $item)
                        @php
                            $today = now()->toDateString();
                            $isActive = $item->effective_from->toDateString() <= $today && ($item->effective_to === null || $item->effective_to->toDateString() >= $today);
                        @endphp
                        <tr class="{{ $isActive ? 'bg-emerald-50/50 hover:bg-emerald-50' : 'hover:bg-slate-50/75' }}">
                            <td class="p-3 font-bold text-slate-950">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-xs font-black">
                                        {{ strtoupper(substr($item->purchaser?->name ?? 'U', 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ $item->purchaser?->name ?? 'Unknown' }}</div>
                                        <div class="text-[10px] font-normal text-slate-400">{{ $item->purchaser?->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 font-bold text-slate-900">
                                {{ $item->effective_from->format('d M Y') }}
                            </td>
                            <td class="p-3 text-slate-600">
                                @if($item->effective_to)
                                    {{ $item->effective_to->format('d M Y') }}
                                @else
                                    <span class="font-bold text-emerald-700">Current (Open)</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if($isActive)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span> Active Now
                                    </span>
                                @elseif($item->effective_from->toDateString() > $today)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-blue-800">
                                        Scheduled (Future)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                                        Ended / Closed
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-500">
                                {{ $item->createdBy?->name ?? 'System' }}
                            </td>
                            <td class="p-3 text-slate-400">
                                {{ $item->created_at?->format('d M Y, h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                No purchaser allotments recorded for this product yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
