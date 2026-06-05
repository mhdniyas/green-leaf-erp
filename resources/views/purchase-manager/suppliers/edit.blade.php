@extends('purchase-manager.layouts.app')

@section('title', 'Edit Supplier')
@section('page_title', 'Edit Supplier')
@section('page_description', 'Update vendor contact details, payment terms, and quality score used by the purchase team.')

@section('content')
    @include('purchase-manager.suppliers.partials.form', [
        'formAction' => route('purchasing.suppliers.update', $supplier),
        'formMethod' => 'PUT',
        'cancelHref' => route('purchasing.suppliers.index'),
        'submitLabel' => 'Save Supplier',
        'supplier' => $supplier,
    ])
@endsection
