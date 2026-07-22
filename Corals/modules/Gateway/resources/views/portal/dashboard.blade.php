@extends('Gateway::portal.layout')

@section('title', 'Payment intents')

@section('content')
    <div id="gateway-portal-app"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/gateway/portal.js') }}"></script>
@endpush
