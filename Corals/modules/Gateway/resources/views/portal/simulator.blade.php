@extends('Gateway::portal.layout')

@section('title', 'POS simulator')

@section('content')
    @if (session('simError'))
        <p class="error">{{ session('simError') }}</p>
    @endif

    <div class="card">
        <h3>Demo wallet</h3>
        <p>network_id <code>{{ $wallet->network_id }}</code> &middot; store_id <code>{{ $wallet->external_store_id }}</code></p>
        <p>Available: {{ number_format(($wallet->balance_centavos - $wallet->reserved_centavos) / 100, 2) }} MXN
            (balance {{ number_format($wallet->balance_centavos / 100, 2) }}, reserved {{ number_format($wallet->reserved_centavos / 100, 2) }})</p>
    </div>

    <div class="card">
        <h3>Create a test intent</h3>
        <form method="POST" action="{{ route('gateway.portal.simulator.intents.store') }}">
            @csrf
            <div class="field">
                <label>Invoice ID</label>
                <input name="invoice_id" required>
            </div>
            <div class="field">
                <label>Merchant MID</label>
                <input name="mid" required maxlength="9">
            </div>
            <div class="field">
                <label>Mode</label>
                <select name="mode">
                    <option value="one_time">one_time</option>
                    <option value="reusable">reusable</option>
                </select>
            </div>
            <div class="field">
                <label>Amount policy</label>
                <select name="amount_policy[type]">
                    <option value="fixed">fixed</option>
                    <option value="variable">variable</option>
                </select>
            </div>
            <div class="field">
                <label>Fixed amount (centavos)</label>
                <input type="number" name="amount_policy[amount]" min="0">
            </div>
            <div class="field">
                <label>Variable min / max (centavos)</label>
                <input type="number" name="amount_policy[min]" min="0" style="width: 45%;">
                <input type="number" name="amount_policy[max]" min="0" style="width: 45%;">
            </div>
            <div class="field">
                <label style="font-weight: normal;">
                    <input type="checkbox" name="amount_policy[allow_partial]" value="1"> Allow partial (variable only)
                </label>
            </div>
            <button type="submit">Create intent</button>
        </form>
    </div>

    @foreach ($intents as $intent)
        @php $reference = $intent->paymentReferences->firstWhere('status', 'active'); @endphp
        <div class="card">
            <h3>{{ $intent->invoice_id }} &middot; {{ $intent->merchant->mid }}</h3>
            <p>state {{ $intent->state }} &middot; mode {{ $intent->mode }} &middot; policy {{ json_encode($intent->amount_policy) }}</p>

            @if (! $reference)
                <p class="error">No active reference left to collect against.</p>
            @else
                <p>reference <code>{{ $reference->human_reference }}</code></p>

                <form method="POST" action="{{ route('gateway.portal.simulator.validate') }}" style="display:inline-block; margin-right: 1rem;">
                    @csrf
                    <input type="hidden" name="intent_id" value="{{ $intent->public_id }}">
                    <div class="field">
                        <label>Validate: amount attempted (centavos)</label>
                        <input type="number" name="amount_attempt" required min="0">
                    </div>
                    <button type="submit">Validate</button>
                </form>

                <form method="POST" action="{{ route('gateway.portal.simulator.confirm') }}" style="display:inline-block;">
                    @csrf
                    <input type="hidden" name="intent_id" value="{{ $intent->public_id }}">
                    <div class="field">
                        <label>Confirm: amount paid (centavos)</label>
                        <input type="number" name="amount_paid" required min="0">
                    </div>
                    <div class="field">
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="is_partial" value="1"> Partial payment
                        </label>
                    </div>
                    <button type="submit">Confirm</button>
                </form>
            @endif
        </div>
    @endforeach

    @if (session('simResult'))
        @php $sim = session('simResult'); @endphp
        <div class="card">
            <h3>Last {{ $sim['step'] }} result</h3>
            <p>Request sent to the mock-realtime adapter:</p>
            <pre>{{ json_encode($sim['request'], JSON_PRETTY_PRINT) }}</pre>
            <p>Response:</p>
            <pre>{{ json_encode($sim['response'], JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
@endsection
