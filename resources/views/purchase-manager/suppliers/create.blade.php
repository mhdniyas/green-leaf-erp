@extends('purchase-manager.layouts.app')

@section('title', 'Add Supplier')
@section('page_title', 'Add Supplier')
@section('page_description', 'Create a supplier record with contact, payment, and quality details for purchasing operations.')

@section('content')
    @include('purchase-manager.suppliers.partials.form', [
        'formAction' => route('purchasing.suppliers.store'),
        'formMethod' => 'POST',
        'cancelHref' => route('purchasing.suppliers.index'),
        'submitLabel' => 'Create Supplier',
        'supplier' => null,
    ])
@endsection
