@extends('Gateway::portal.layout')

@section('title', 'Log in')

@section('content')
    <div class="card" style="max-width: 360px; margin: 3rem auto;">
        <h2>Issuer Portal</h2>

        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('gateway.portal.login.attempt') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required style="width: 100%;">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required style="width: 100%;">
            </div>
            <button type="submit">Log in</button>
        </form>
    </div>
@endsection
