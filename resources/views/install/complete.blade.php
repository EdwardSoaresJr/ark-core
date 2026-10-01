@extends('install.layout')

@section('title', 'Ready')

@section('content')
    <h1>ARK is ready.</h1>
    <p class="lead">Your shop has been initialized. Setup is locked.</p>

    @if (! empty($connectCloudAfterInstall))
        <p class="lead">
            Next, connect ARK Platform. Sign in with the shop admin account you just created, then continue the Cloud connection.
        </p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('login', ['redirect' => route('operations.cloud.connect-after-setup')]) }}">
                Sign in to connect ARK Platform
            </a>
            <a class="btn btn-secondary" href="{{ route('login') }}">Enter ARK without connecting</a>
        </div>
    @else
        <p class="lead">Optional Cloud services can be connected later in Settings → ARK Platform.</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('login') }}">Enter ARK</a>
        </div>
    @endif
@endsection
