@extends('purchase-manager.layouts.app')

@section('title', 'Create Purchase Order')
@section('page_title', 'Create Purchase Order')
@section('page_description', 'Build a draft purchase order with a supplier, line items, and expected pricing in one fast screen.')

@section('content')
    @include('purchase-manager.orders.partials.order-form', [
        'formAction' => route('purchasing.orders.store'),
        'formMethod' => 'POST',
        'cancelHref' => route('purchasing.orders.index'),
        'submitLabel' => 'Create Draft Order',
        'poItems' => old('items', [[]]),
        'order' => null,
    ])
@endsection
