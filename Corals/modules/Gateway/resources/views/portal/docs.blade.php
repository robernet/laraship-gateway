@extends('Gateway::portal.layout')

@section('title', 'API docs')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
@endpush

@section('content')
    <div class="card">
        <div id="swagger-ui"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function () {
            SwaggerUIBundle({
                url: '{{ route('gateway.portal.docs.spec') }}',
                dom_id: '#swagger-ui',
            });
        };
    </script>
@endpush
