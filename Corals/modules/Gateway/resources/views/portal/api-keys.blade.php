@extends('Gateway::portal.layout')

@section('title', 'API keys')

@section('content')
    @if (session('plainTextToken'))
        <div class="card">
            <p class="status">New token issued — copy it now, it will not be shown again:</p>
            <code>{{ session('plainTextToken') }}</code>
        </div>
    @endif

    <div class="card">
        <h3>Issue a new token</h3>
        <form method="POST" action="{{ route('gateway.portal.api-keys.store') }}">
            @csrf
            <div class="field">
                <label for="ttl_minutes">Lifetime (minutes)</label>
                <input id="ttl_minutes" name="ttl_minutes" type="number" min="1" value="{{ config('gateway.issuer_token_ttl_minutes') }}">
            </div>
            <div class="field">
                <label>Abilities</label>
                @foreach ($abilities as $ability)
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="abilities[]" value="{{ $ability }}" checked> {{ $ability }}
                    </label>
                @endforeach
            </div>
            <div class="field">
                <label style="font-weight: normal;">
                    <input type="checkbox" name="sandbox" value="1"> Sandbox key (tags created payment intents as test data, not real money movement)
                </label>
            </div>
            <button type="submit">Issue token</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing tokens</h3>
        <table>
            <thead>
                <tr><th>Name</th><th>Abilities</th><th>Expires</th><th>Last used</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($tokens as $token)
                    <tr>
                        <td>{{ $token->name }}</td>
                        <td>
                            {{ implode(', ', $token->abilities) }}
                            @if (in_array('sandbox', $token->abilities))
                                <span style="color: #b45309;">(sandbox)</span>
                            @endif
                        </td>
                        <td>{{ optional($token->expires_at)->toDayDateTimeString() ?? 'never' }}</td>
                        <td>{{ optional($token->last_used_at)->diffForHumans() ?? 'never' }}</td>
                        <td>
                            <form method="POST" action="{{ route('gateway.portal.api-keys.destroy', $token->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Revoke</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
