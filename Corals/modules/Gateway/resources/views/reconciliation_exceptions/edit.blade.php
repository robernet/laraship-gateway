@extends('layouts.crud.show')

@section('content_header')
    @component('components.content_header')
        @slot('page_title')
            {{ $title_singular }} #{{ $exception->id }}
        @endslot
        @slot('breadcrumb')
        @endslot
    @endcomponent
@endsection

@section('content')
    @component('components.box', ['box_class' => 'box-primary'])
        <div class="row">
            <div class="col-md-12">
                <table class="table">
                    <tr>
                        <th>Type</th>
                        <td>{{ $exception->type }}</td>
                    </tr>
                    <tr>
                        <th>State</th>
                        <td>{{ $exception->state }}</td>
                    </tr>
                    <tr>
                        <th>Assignee</th>
                        <td>{{ $exception->assignee ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Refs</th>
                        <td><pre>{{ json_encode($exception->refs, JSON_PRETTY_PRINT) }}</pre></td>
                    </tr>
                    <tr>
                        <th>Resolution</th>
                        <td>{{ $exception->resolution ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <h4>Assign</h4>
                <form method="POST" action="{{ route('gateway.reconciliation-exceptions.update', $exception->hashed_id) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="workflow_action" value="assign">
                    <div class="form-group">
                        <input type="text" name="assignee" class="form-control" placeholder="Assignee" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </form>
            </div>

            @if($exception->state === 'open')
                <div class="col-md-4">
                    <h4>Investigate</h4>
                    <form method="POST" action="{{ route('gateway.reconciliation-exceptions.update', $exception->hashed_id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="workflow_action" value="investigate">
                        <button type="submit" class="btn btn-info">Start investigating</button>
                    </form>
                </div>
            @endif

            @if($exception->state !== 'resolved')
                <div class="col-md-4">
                    <h4>Resolve</h4>
                    <form method="POST" action="{{ route('gateway.reconciliation-exceptions.update', $exception->hashed_id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="workflow_action" value="resolve">
                        <div class="form-group">
                            <textarea name="resolution" class="form-control" placeholder="Resolution notes" required></textarea>
                        </div>
                        <div class="form-group">
                            <input type="text" name="reverse_posting_id" class="form-control"
                                   placeholder="Posting id to reverse (optional)">
                            <small class="form-text text-muted">
                                Only set this if the resolution reverses a booked ledger posting.
                            </small>
                        </div>
                        <button type="submit" class="btn btn-success">Resolve</button>
                    </form>
                </div>
            @endif
        </div>
    @endcomponent
@endsection
