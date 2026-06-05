@php
    $tone = match ($order->state) {
        'approved' => 'success',
        'submitted', 'update_requested' => 'warning',
        'rejected' => 'danger',
        default => 'neutral',
    };
@endphp

@include('shop-owner.components.status-badge', ['label' => str($order->state)->replace('_', ' ')->title(), 'tone' => $tone])
