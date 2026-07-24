@extends('Gateway::portal.layout')

@section('title', 'Webhook')

@section('content')
    <div class="card">
        <h3>Webhook endpoint</h3>

        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('gateway.portal.webhook.update') }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="webhook_url">Webhook URL</label>
                <input id="webhook_url" name="webhook_url" type="url" value="{{ old('webhook_url', $issuer->webhook_url) }}" required style="width: 100%;">
            </div>
            <div class="field">
                <label>Current signing secret</label>
                <code>{{ $issuer->webhook_secret }}</code>
            </div>
            <div class="field">
                <label style="font-weight: normal;">
                    <input type="checkbox" name="regenerate_secret" value="1"> Regenerate signing secret
                </label>
            </div>
            <button type="submit">Save</button>
        </form>
    </div>

    <div class="card">
        <h3>Webhook tester</h3>
        <p>Sends a synthetic <code>payment.confirmed</code> event to your webhook URL through the same signing and delivery path as a real payment.</p>
        @if (session('testResult'))
            <p class="{{ session('testResult.status') === 'delivered' ? 'status' : 'error' }}">
                Result: {{ session('testResult.status') ?? 'no delivery attempted' }}
                @if (session('testResult.last_error'))
                    &mdash; {{ session('testResult.last_error') }}
                @endif
            </p>
        @endif
        <form method="POST" action="{{ route('gateway.portal.webhook.test') }}">
            @csrf
            <button type="submit">Send test webhook</button>
        </form>
    </div>
@endsection
