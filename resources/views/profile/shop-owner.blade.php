@extends('shop-owner.layouts.app')

@section('title', 'Profile')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        @include('profile.partials.form', ['user' => $user])
    </div>
@endsection
