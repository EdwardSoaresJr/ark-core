@extends('install.layout')

@section('title', 'System check')

@section('content')
    <h1>System check</h1>
    <p class="lead">ARK verified the requirements this codebase actually needs.</p>

    @foreach ($checks as $check)
        <div class="check">
            <span class="badge badge-{{ $check['status'] }}">{{ $check['status'] }}</span>
            <div class="meta">
                <strong>{{ $check['label'] }}</strong>
                <span>{{ $check['detail'] }}</span>
            </div>
        </div>
    @endforeach

    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('install.welcome') }}">Back</a>
        @if ($blocked)
            <button class="btn btn-primary" type="button" disabled>Fix failures to continue</button>
        @else
            <a class="btn btn-primary" href="{{ route('install.database') }}">Continue</a>
        @endif
    </div>
@endsection
