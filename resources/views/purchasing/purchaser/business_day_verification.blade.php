@extends('purchaser.layouts.app')

@section('title', 'Business Day Verification & Calendar')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-5xl">
    <!-- Main Top Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Purchaser Business Day</h1>
            <p class="text-sm text-gray-600">Verification calendar and daily reconciliation for {{ $reconciliation['purchaser_name'] }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('purchaser.cart') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold shadow-sm transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add / Update Bill
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Monthly Calendar Section -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 md:p-6 mb-8">
        <!-- Month Header Navigation -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('purchaser.business-day.show', array_filter(['month' => $calendar['prev_month'], 'purchaser_id' => auth()->user()->hasAnyRole(['admin', 'manager', 'accounts', 'accountant']) ? $purchaserId : null])) }}"
                   class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition" title="Previous Month">
                    ‹
                </a>
                <h2 class="text-lg md:text-xl font-bold text-gray-900">{{ $calendar['month_label'] }}</h2>
                <a href="{{ route('purchaser.business-day.show', array_filter(['month' => $calendar['next_month'], 'purchaser_id' => auth()->user()->hasAnyRole(['admin', 'manager', 'accounts', 'accountant']) ? $purchaserId : null])) }}"
                   class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition" title="Next Month">
                    ›
                </a>
            </div>

            <!-- Month Summary Pills -->
            <div class="hidden sm:flex items-center gap-2 text-xs font-semibold">
                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full">Submitted: {{ $calendar['submitted_count'] }}</span>
                <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full">Complete: {{ $calendar['complete_count'] }}</span>
                <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full">Pending: {{ $calendar['pending_count'] }}</span>
                <span class="px-2.5 py-1 bg-gray-100 text-gray-600 border border-gray-200 rounded-full">Not Submitted: {{ $calendar['not_submitted_count'] }}</span>
            </div>
        </div>

        <!-- 7-Column Calendar Grid -->
        <div class="grid grid-cols-7 gap-1 md:gap-2 text-center">
            <!-- Day Names -->
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                <div class="py-1 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ $dayName }}</div>
            @endforeach

            <!-- Blank leading days -->
            @for ($i = 1; $i < $calendar['start_day_of_week']; $i++)
                <div class="h-20 bg-gray-50/50 rounded-lg border border-transparent"></div>
            @endfor

            <!-- Calendar Days -->
            @foreach ($calendar['days'] as $day)
                @php
                    $isSel = $day['is_selected'];
                    $isToday = $day['is_today'];

                    $bgClass = match($day['state']) {
                        'submitted_complete' => 'bg-emerald-50 hover:bg-emerald-100 border-emerald-200 text-emerald-950',
                        'submitted_completed_later' => 'bg-blue-50 hover:bg-blue-100 border-blue-200 text-blue-950',
                        'submitted_pending' => 'bg-amber-50 hover:bg-amber-100 border-amber-300 text-amber-950',
                        'not_submitted' => 'bg-orange-50/60 hover:bg-orange-100 border-orange-200 text-orange-950',
                        'unit_mismatch' => 'bg-red-50 hover:bg-red-100 border-red-300 text-red-950',
                        default => 'bg-gray-50 hover:bg-gray-100 border-gray-200 text-gray-500',
                    };

                    if ($isSel) {
                        $bgClass .= ' ring-2 ring-emerald-600 font-bold shadow-sm';
                    }
                @endphp

                <a href="{{ route('purchaser.business-day.show', array_filter(['month' => $calendar['year_month'], 'date' => $day['date'], 'purchaser_id' => auth()->user()->hasAnyRole(['admin', 'manager', 'accounts', 'accountant']) ? $purchaserId : null])) }}"
                   class="h-20 p-1.5 rounded-xl border flex flex-col justify-between transition relative text-left {{ $bgClass }}">

                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold {{ $isToday ? 'w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center' : 'text-gray-700' }}">
                            {{ $day['day_number'] }}
                        </span>
                        @if ($day['has_submission'])
                            <span class="text-[10px] font-bold text-emerald-700">✓</span>
                        @endif
                    </div>

                    <div class="mt-auto">
                        @if ($day['state'] === 'no_operations')
                            <span class="text-[10px] text-gray-400 block truncate">No Ops</span>
                        @else
                            <div class="text-xs font-black tracking-tight truncate">
                                {{ $day['coverage_display'] }}
                            </div>
                            <div class="text-[9px] font-medium opacity-80 truncate">
                                {{ $day['label'] }}
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Daily Verification Detail Section -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 md:p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between pb-4 mb-6 border-b border-gray-100 gap-2">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 block">Daily Verification Detail</span>
                <h2 class="text-xl font-bold text-gray-900">{{ \Illuminate\Support\Carbon::parse($businessDate)->format('l, d F Y') }}</h2>
            </div>

            <div class="flex items-center gap-2">
                @if ($isSubmitted)
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-full text-xs font-bold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Submitted ✓
                    </span>
                @else
                    <span class="px-3 py-1 bg-amber-100 text-amber-800 border border-amber-300 rounded-full text-xs font-bold">
                        Not Submitted
                    </span>
                @endif
            </div>
        </div>

        <!-- Submission Banner (if submitted) -->
        @if ($isSubmitted && $submission)
            <div class="mb-6 bg-emerald-900 text-white p-5 rounded-xl shadow-sm border border-emerald-800">
                <div class="flex items-center justify-between border-b border-emerald-800 pb-3 mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-emerald-100 uppercase tracking-wider">Submission Snapshot Comparison</h3>
                        <p class="text-xs text-emerald-300">
                            Submitted at {{ $submission->submitted_at?->format('d M Y, h:i A') }}
                            by {{ $submission->submittedBy?->name ?? 'Purchaser' }}
                        </p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-0.5 bg-emerald-800 text-emerald-200 rounded border border-emerald-700">Snapshot Preserved</span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="bg-emerald-950/60 p-3 rounded-lg border border-emerald-800/80">
                        <span class="text-[11px] text-emerald-300 block mb-0.5">Coverage at Submission</span>
                        <span class="text-xl font-black text-white">{{ number_format((float) $submission->submission_coverage_percentage, 1) }}%</span>
                    </div>
                    <div class="bg-emerald-950/60 p-3 rounded-lg border border-emerald-800/80">
                        <span class="text-[11px] text-emerald-300 block mb-0.5">Pending at Submission</span>
                        <span class="text-xl font-bold text-amber-300">{{ number_format((float) $submission->submitted_pending_summary, 2) }}</span>
                    </div>
                    <div class="bg-emerald-950/60 p-3 rounded-lg border border-emerald-800/80">
                        <span class="text-[11px] text-emerald-300 block mb-0.5">Current Coverage (Live)</span>
                        <span class="text-xl font-black text-emerald-200">{{ number_format((float) $reconciliation['coverage_percentage'], 1) }}%</span>
                    </div>
                    <div class="bg-emerald-950/60 p-3 rounded-lg border border-emerald-800/80">
                        <span class="text-[11px] text-emerald-300 block mb-0.5">Current Pending (Live)</span>
                        <span class="text-xl font-bold text-emerald-300">{{ number_format((float) $reconciliation['total_pending_qty'], 2) }}</span>
                    </div>
                </div>

                @if ($submission->note)
                    <div class="mt-4 pt-3 border-t border-emerald-800/80 text-xs">
                        <span class="font-semibold text-emerald-200 block mb-1">Submission Note (Read-Only):</span>
                        <p class="italic bg-emerald-950/50 p-2.5 rounded border border-emerald-800 text-emerald-100">{{ $submission->note }}</p>
                    </div>
                @endif
            </div>
        @endif

        <!-- Reconciliation Top Cards (Live) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1">Warehouse Received</span>
                <span class="text-xl md:text-2xl font-bold text-gray-900">{{ number_format((float) $reconciliation['total_received_qty'], 2) }}</span>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1">Billed Qty</span>
                <span class="text-xl md:text-2xl font-bold text-emerald-600">{{ number_format((float) $reconciliation['total_billed_qty'], 2) }}</span>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1">Pending Qty</span>
                <span class="text-xl md:text-2xl font-bold text-amber-600">{{ number_format((float) $reconciliation['total_pending_qty'], 2) }}</span>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1">
                    @if ($reconciliation['has_mixed_units'])
                        Products Covered
                    @else
                        Current Coverage
                    @endif
                </span>
                @if ($reconciliation['has_mixed_units'])
                    <span class="text-xl md:text-2xl font-bold text-blue-600">
                        {{ collect($reconciliation['products'])->where('is_fully_covered', true)->count() }} / {{ count($reconciliation['products']) }}
                    </span>
                @else
                    <span class="text-xl md:text-2xl font-black text-blue-600">{{ number_format((float) $reconciliation['coverage_percentage'], 1) }}%</span>
                @endif
            </div>
        </div>

        <!-- Product Breakdown Table -->
        <div class="overflow-x-auto rounded-xl border border-gray-200 mb-6">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider border-b border-gray-200">
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Received</th>
                        <th class="px-4 py-3 text-right">Billed</th>
                        <th class="px-4 py-3 text-right">Pending</th>
                        <th class="px-4 py-3 text-right">Coverage</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($reconciliation['products'] as $product)
                        @php
                            $subItem = $submission?->items->firstWhere('product_id', $product['product_id']);
                        @endphp
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-4 py-3.5 font-medium text-gray-900">
                                {{ $subItem?->product_name ?? $product['product_name'] }}
                                <span class="text-xs text-gray-400 block font-normal">{{ $subItem?->unit ?? $product['unit'] }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-gray-900">
                                {{ number_format((float) $product['received_qty'], 2) }}
                                @if ($subItem)
                                    <span class="text-[10px] text-gray-400 block font-normal">Snap: {{ number_format((float) $subItem->received_qty, 2) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-emerald-600">
                                {{ number_format((float) $product['billed_qty'], 2) }}
                                @if ($subItem)
                                    <span class="text-[10px] text-gray-400 block font-normal">Snap: {{ number_format((float) $subItem->billed_qty, 2) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-amber-600">
                                {{ number_format((float) $product['pending_qty'], 2) }}
                                @if ($subItem)
                                    <span class="text-[10px] text-gray-400 block font-normal">Snap: {{ number_format((float) $subItem->pending_qty, 2) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right font-bold text-blue-600">
                                {{ number_format((float) $product['coverage_percentage'], 1) }}%
                                @if ($subItem)
                                    <span class="text-[10px] text-gray-400 block font-normal">Snap: {{ number_format((float) $subItem->coverage_percentage, 1) }}%</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if (($product['excess_qty'] ?? 0) > 0.0001)
                                    <span class="px-2.5 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold">Excess Billed (+{{ number_format((float) $product['excess_qty'], 2) }})</span>
                                @elseif ($product['is_fully_covered'])
                                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-semibold">100% Covered</span>
                                @elseif ($product['billed_qty'] > 0)
                                    <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold">Partially Billed</span>
                                @else
                                    <span class="px-2.5 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">Pending Bill</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No physical warehouse receipts found for this business day.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pending Bills & Completed Later Breakdown -->
        @if ($isSubmitted && $submission)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Pending at Submission Snapshot -->
                <div class="p-4 bg-amber-50/70 border border-amber-200 rounded-xl">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-amber-800 mb-2">Pending at Submission Snapshot</h4>
                    <div class="space-y-1 text-xs">
                        @forelse ($submission->items->where('pending_qty', '>', 0.0001) as $sItem)
                            <div class="flex items-center justify-between text-amber-950 font-medium">
                                <span>{{ $sItem->product_name }}</span>
                                <span class="font-bold">{{ number_format((float) $sItem->pending_qty, 2) }} {{ $sItem->unit }}</span>
                            </div>
                        @empty
                            <p class="text-amber-700 italic">No pending items at time of submission (100% covered).</p>
                        @endforelse
                    </div>
                </div>

                <!-- Completed Later Breakdown -->
                <div class="p-4 bg-blue-50/70 border border-blue-200 rounded-xl">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-blue-800 mb-2">Late Bill Resolutions (Completed Later)</h4>
                    <div class="space-y-1 text-xs">
                        @php $hasCompletedLater = false; @endphp
                        @foreach ($submission->items as $sItem)
                            @php
                                $liveProd = collect($reconciliation['products'])->firstWhere('product_id', $sItem->product_id);
                                $livePending = (float) ($liveProd['pending_qty'] ?? 0.0);
                                $submittedPending = (float) $sItem->pending_qty;
                            @endphp
                            @if ($submittedPending > 0.0001 && $livePending < $submittedPending)
                                @php $hasCompletedLater = true; @endphp
                                <div class="flex items-center justify-between text-blue-950 font-medium border-b border-blue-100 pb-1">
                                    <span>{{ $sItem->product_name }}</span>
                                    <span class="text-right">
                                        <span class="line-through text-blue-400 mr-1">{{ number_format($submittedPending, 2) }} {{ $sItem->unit }}</span>
                                        <span class="font-bold text-emerald-700">→ {{ number_format($livePending, 2) }} pending</span>
                                    </span>
                                </div>
                            @endif
                        @endforeach
                        @if (! $hasCompletedLater)
                            <p class="text-blue-700 italic">No late bill resolutions recorded for this day yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <!-- Unsubmitted Action Box -->
            @php
                $pendingProducts = collect($reconciliation['products'])->where('pending_qty', '>', 0.0001);
            @endphp

            <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                <h3 class="text-base font-bold text-gray-900 mb-2">Verify & Submit Business Day</h3>
                <p class="text-xs text-gray-600 mb-4">
                    Review physical receipts vs bills above. You can submit now even if some bills are pending.
                </p>

                @if ($pendingProducts->isNotEmpty())
                    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs">
                        <span class="font-bold uppercase tracking-wider text-amber-800 block mb-1">Current Pending Bills ({{ $pendingProducts->count() }} products)</span>
                        <div class="space-y-1">
                            @foreach ($pendingProducts as $p)
                                <div class="flex items-center justify-between text-amber-900">
                                    <span>{{ $p['product_name'] }}</span>
                                    <span class="font-bold">{{ number_format((float) $p['pending_qty'], 2) }} {{ $p['unit'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('purchaser.business-day.submit') }}">
                    @csrf
                    <input type="hidden" name="business_date" value="{{ $businessDate }}">
                    <input type="hidden" name="purchaser_id" value="{{ $purchaserId }}">
                    @if ($warehouseId)
                        <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
                    @endif

                    <div class="mb-4">
                        <label for="note" class="block text-xs font-semibold text-gray-700 mb-1">Submission Note (Optional)</label>
                        <textarea id="note" name="note" rows="2" class="w-full rounded-lg border-gray-300 text-xs focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g. Vendor invoice for Potato pending"></textarea>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('purchaser.cart') }}" class="px-4 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg text-xs hover:bg-gray-50 transition">
                            Add / Update Bill
                        </a>

                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-sm text-xs transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Verify & Submit Business Day
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
