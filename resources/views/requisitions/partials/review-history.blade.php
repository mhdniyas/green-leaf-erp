@php
    $resolvedRevisions = ($order->relationLoaded('revisions') ? $order->revisions : collect())
        ->whereIn('status', ['applied', 'rejected', 'blocked'])
        ->sortByDesc('revision_no')
        ->values();
    $initialApprovedQty = (float) $order->items->sum(fn ($item): float => (float) ($item->approved_qty ?? 0));
    $initialRequestedQty = (float) $order->items->sum(fn ($item): float => (float) $item->requested_qty);
    $rawInitialNote = $order->manager_note ?: ($order->state === 'rejected' ? $order->update_reason : null);
    $initialNote = ($rawInitialNote && str_contains(strtolower($rawInitialNote), 'automatically approved')) ? null : $rawInitialNote;
    $hasInitialReview = $order->reviewed_at !== null;
    $initialChangedItems = $order->items
        ->filter(function ($item): bool {
            $requestedQty = (float) $item->requested_qty;
            $approvedQty = (float) ($item->approved_qty ?? 0);

            return abs($requestedQty - $approvedQty) > 0.0001;
        })
        ->values();
@endphp

@if ($hasInitialReview || $resolvedRevisions->isNotEmpty())
    <div class="space-y-3">
        @if ($hasInitialReview)
            @php
                $initialLabel = match (true) {
                    $order->state === 'rejected' && $initialApprovedQty > 0.0 => 'Initial Request Partially Accepted',
                    $order->state === 'rejected' => 'Initial Request Rejected',
                    default => 'Initial Request Approved',
                };
                $initialWrapper = match (true) {
                    $order->state === 'rejected' && $initialApprovedQty > 0.0 => 'border-amber-200/80 bg-amber-50/65',
                    $order->state === 'rejected' => 'border-red-200/80 bg-red-50/65',
                    default => 'border-emerald-200/80 bg-emerald-50/65',
                };
            @endphp
            <article class="rounded-[1.75rem] border px-4 py-4 sm:px-5 {{ $initialWrapper }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-base font-black text-slate-950">{{ $initialLabel }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            {{ $order->reviewedBy?->name ?? 'Purchase manager' }} · {{ $order->reviewed_at?->format('d M Y, h:i A') ?? 'Pending' }}
                        </p>
                    </div>
                </div>

                @if ($initialChangedItems->isNotEmpty())
                    <div class="mt-4 space-y-3 font-mono text-sm text-slate-900">
                        @foreach ($initialChangedItems as $item)
                            @php
                                $requestedQty = (float) $item->requested_qty;
                                $approvedQty = (float) ($item->approved_qty ?? 0);
                                $changeLabel = match (true) {
                                    $approvedQty <= 0.0 => 'rejected',
                                    $approvedQty < $requestedQty => 'reduced',
                                    default => 'modified',
                                };
                            @endphp
                            <div class="border-l-2 border-slate-300/80 pl-3">
                                <p class="font-sans text-sm font-bold text-slate-950">{{ $item->product?->name ?? 'Unknown Product' }}</p>
                                <p class="mt-2">{{ str_pad('modifier', 10) }} {{ $changeLabel }}</p>
                                <p>{{ str_pad('requested', 10) }} {{ number_format($requestedQty, 2) }} {{ $item->unit }}</p>
                                <p>{{ str_pad('approved', 10) }} {{ number_format($approvedQty, 2) }} {{ $item->unit }}</p>
                                <p>{{ str_pad('change', 10) }} {{ number_format($approvedQty - $requestedQty, 2) }} {{ $item->unit }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm font-semibold text-slate-700">
                        {{ $order->state === 'rejected' ? 'Purchase manager reviewed this request and rejected it.' : 'Purchase manager approved this request.' }}
                    </p>
                @endif

                @if ($initialNote)
                    <p class="mt-4 text-sm font-semibold text-slate-800">{{ $initialNote }}</p>
                @endif
            </article>
        @endif

        @foreach ($resolvedRevisions as $revision)
            @php
                $revisionWrapper = match ($revision->status) {
                    'rejected' => 'border-red-200/80 bg-red-50/65',
                    'blocked' => 'border-slate-200/80 bg-slate-100/80',
                    default => $revision->isFullyAccepted()
                        ? 'border-emerald-200/80 bg-emerald-50/65'
                        : 'border-amber-200/80 bg-amber-50/65',
                };
                $revisionSummary = match ($revision->status) {
                    'rejected' => 'Update request rejected. Try again in tomorrow\'s order.',
                    'blocked' => 'Update could not be applied because linked goods receipt already started.',
                    default => $revision->isFullyAccepted()
                        ? 'All requested changes accepted.'
                        : $revision->acceptedItemsCount().' of '.$revision->items->count().' item changes accepted.',
                };
            @endphp
            <article class="rounded-[1.75rem] border px-4 py-4 sm:px-5 {{ $revisionWrapper }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-base font-black text-slate-950">{{ $revision->resolvedLabel() }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            {{ $revision->reviewedBy?->name ?? 'Purchase manager' }} · {{ $revision->reviewed_at?->format('d M Y, h:i A') ?? 'Pending' }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 space-y-3 font-mono text-sm text-slate-900">
                    @foreach ($revision->items as $item)
                        @php
                            $oldQty = (float) $item->old_requested_qty;
                            $requestedQty = (float) $item->new_requested_qty;
                            $finalQty = (float) ($item->final_approved_qty ?? $oldQty);
                            $modifierLabel = match (true) {
                                $oldQty <= 0.0 && $finalQty > 0.0 => 'added',
                                $finalQty <= 0.0 => 'rejected',
                                $finalQty > $oldQty => 'increased',
                                $finalQty < $oldQty => 'reduced',
                                abs($finalQty - $requestedQty) > 0.0001 => 'modified',
                                default => 'accepted',
                            };
                        @endphp
                        <div class="border-l-2 border-slate-300/80 pl-3">
                            <p class="font-sans text-sm font-bold text-slate-950">{{ $item->product?->name ?? 'Unknown Product' }}</p>
                            <p class="mt-2">{{ str_pad('modifier', 10) }} {{ $modifierLabel }}</p>
                            <p>{{ str_pad('before', 10) }} {{ number_format($oldQty, 2) }} {{ $item->product?->unit ?? '' }}</p>
                            <p>{{ str_pad('requested', 10) }} {{ number_format($requestedQty, 2) }} {{ $item->product?->unit ?? '' }}</p>
                            <p>{{ str_pad('final', 10) }} {{ number_format($finalQty, 2) }} {{ $item->product?->unit ?? '' }}</p>
                            <p>{{ str_pad('change', 10) }} {{ number_format($finalQty - $oldQty, 2) }} {{ $item->product?->unit ?? '' }}</p>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-sm font-semibold text-slate-800">{{ $revisionSummary }}</p>

                @if ($revision->manager_note)
                    <p class="mt-3 text-sm font-semibold text-slate-800">{{ $revision->manager_note }}</p>
                @endif
            </article>
        @endforeach
    </div>
@endif
