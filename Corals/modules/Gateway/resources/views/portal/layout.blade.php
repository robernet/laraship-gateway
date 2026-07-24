<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Issuer Portal - @yield('title', 'Dashboard')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: -apple-system, Arial, sans-serif; margin: 0; background: #f5f6f8; color: #222; }
        header { background: #1c2733; color: #fff; padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        header a { color: #fff; text-decoration: none; margin-left: 1rem; }
        header .brand { font-weight: 600; }
        main { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border: 1px solid #dde1e6; border-radius: 6px; padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        input, select, button { padding: 0.4rem 0.6rem; font-size: 0.9rem; }
        button { background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: #b91c1c; font-size: 0.85rem; }
        .status { color: #15803d; font-size: 0.85rem; }
        .field { margin-bottom: 0.75rem; }
        label { display: block; font-size: 0.85rem; margin-bottom: 0.25rem; }
    </style>
    @stack('head')
</head>
<body>
@auth('issuer')
    <header>
        <span class="brand">{{ auth('issuer')->user()->name }} &middot; Issuer Portal</span>
        <nav>
            <a href="{{ route('gateway.portal.dashboard') }}">Payment intents</a>
            <a href="{{ route('gateway.portal.simulator.index') }}">POS simulator</a>
            <a href="{{ route('gateway.portal.api-keys.index') }}">API keys</a>
            <a href="{{ route('gateway.portal.webhook.edit') }}">Webhook</a>
            <a href="{{ route('gateway.portal.docs') }}">API docs</a>
            <form action="{{ route('gateway.portal.logout') }}" method="POST" style="display:inline">
                @csrf
                <a href="#" onclick="event.preventDefault(); this.closest('form').submit();">Log out</a>
            </form>
        </nav>
    </header>
@endauth
<main>
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
