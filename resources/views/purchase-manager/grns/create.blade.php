@extends('purchase-manager.layouts.app')

@section('title', 'Record Goods Receipt')
@section('page_title', 'Record Goods Receipt')
@section('page_description', 'Capture actual received weight, transport cost, labour cost, and stock receipt notes against a purchase order.')

@section('content')
    @include('purchase-manager.grns.partials.form', [
        'formAction' => route('purchasing.grns.store'),
        'formMethod' => 'POST',
        'cancelHref' => route('purchasing.orders.show', $po),
        'submitLabel' => 'Record GRN',
        'sourceOrder' => $po,
        'grn' => null,
    ])
@endsection
