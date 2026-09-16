@extends('purchaser.business-days.layouts.app')

@section('title', 'Record Purchase Bill — ' . $warehouse->name)
@section('page_title', 'Record Purchase Bill')
@section('page_description', 'Purchase bill entry for ' . $warehouse->name . ' on ' . $day->business_date->format('d M Y') . '.')

@section('content')
    @include('purchaser.business-days.bills.partials.bill-form')
@endsection
