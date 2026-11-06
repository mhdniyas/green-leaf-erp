@php
    $status = method_exists($invoice, 'effectivePaymentStatus') ? $invoice->effectivePaymentStatus() : ($invoice->payment_status ?? 'unpaid');
    $tone = match ($status) {
        'paid' => 'success',
        'partially_paid' => 'warning',
        'unpaid' => 'danger',
        default => 'neutral',
    };
@endphp

@include('shop-owner.components.status-badge', ['label' => str($status)->replace('_', ' ')->title(), 'tone' => $tone])
