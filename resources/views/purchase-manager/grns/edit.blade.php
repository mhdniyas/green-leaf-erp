@extends('purchase-manager.layouts.app')

@section('title', 'Edit Goods Receipt')
@section('page_title', 'Edit Goods Receipt')
@section('page_description', 'Correct received quantities and landed costs before the warehouse receipt is finalized again.')

@section('content')
    @include('purchase-manager.grns.partials.form', [
        'formAction' => route('purchasing.grns.update', $grn),
        'formMethod' => 'PUT',
        'cancelHref' => route('purchasing.grns.show', $grn),
        'submitLabel' => 'Save Receipt',
        'sourceOrder' => $grn->purchaseOrder,
        'grn' => $grn,
    ])
@endsection
