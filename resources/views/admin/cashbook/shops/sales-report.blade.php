@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Sales Report')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- PAGE HEADER & PERIOD SWITCHER -->
    @include('admin.cashbook.shops.partials.header-period-nav')

    <!-- SALES REPORT TAB SECTION -->
    @include('admin.cashbook.shops.partials.sales-report-section')
</div>
@endsection
