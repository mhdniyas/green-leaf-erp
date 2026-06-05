@php
    $tone = match ($order->payment_status ?? 'unpaid') {
        'paid' => 'success',
        'partially_paid' => 'warning',
        'unpaid' => 'danger',
        default => 'neutral',
    };
@endphp

@include('shop-owner.components.status-badge', ['label' => str($order->payment_status ?? 'unpaid')->replace('_', ' ')->title(), 'tone' => $tone])
