@extends('purchaser.business-days.layouts.app')

@section('title', 'Edit Purchase Bill — ' . ($grn->bill_number ?? $grn->grn_number))
@section('page_title', 'Edit Purchase Bill')
@section('page_description', 'Update bill details, quantities, and rates for Business Day ' . $day->business_date->format('d M Y') . '.')

@section('content')
    @include('purchaser.business-days.bills.partials.bill-form')
@endsection
