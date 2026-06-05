@extends('purchase-manager.layouts.app')

@section('title', 'Purchase Orders')
@section('page_title', 'Purchase Orders')
@section('page_description', 'Review requisition output, approve draft orders, monitor supplier buying, and track monthly spend from one purchasing workspace.')

@section('page_actions')
    @can('purchasing.order.create')
        <x-purchase-manager.components.action-button :href="route('purchasing.orders.create')" variant="success">
            Create Order
        </x-purchase-manager.components.action-button>
    @endcan
@endsection

@section('content')
    <div data-orders-active-tab="{{ $activeTab }}">
        @include('purchase-manager.orders.partials.stats-cards')
        @include('purchase-manager.orders.partials.order-tabs')
        @include('purchase-manager.orders.partials.all-orders')
        @include('purchase-manager.orders.partials.pending-orders')
        @include('purchase-manager.orders.partials.approval-history')
        @include('purchase-manager.orders.partials.analytics')
    </div>
@endsection
