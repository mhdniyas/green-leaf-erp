@extends('purchase-manager.layouts.app')

@section('title', 'Edit Purchase Order')
@section('page_title', 'Edit Purchase Order')
@section('page_description', 'Update supplier details, quantities, and pricing before the order is approved or sent.')

@section('content')
    @include('purchase-manager.orders.partials.order-form', [
        'formAction' => route('purchasing.orders.update', $order),
        'formMethod' => 'PUT',
        'cancelHref' => route('purchasing.orders.show', $order),
        'submitLabel' => 'Save Changes',
        'poItems' => old('items', $order->items->toArray()),
        'order' => $order,
    ])
@endsection
