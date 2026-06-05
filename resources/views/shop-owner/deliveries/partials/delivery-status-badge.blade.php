@php
    $deliveryStatus = $order->delivery_status ?? ($order->is_delivered ? 'delivered' : 'pending_delivery');
    $tone = match ($deliveryStatus) {
        'delivered' => 'success',
        'partially_delivered' => 'warning',
        'delivery_issue' => 'danger',
        default => 'neutral',
    };
@endphp

@include('shop-owner.components.status-badge', ['label' => str($deliveryStatus)->replace('_', ' ')->title(), 'tone' => $tone])
