@extends('install.layout')

@section('title', 'ARK Platform')

@section('content')
    <h1>ARK Platform</h1>
    <p class="lead">
        Connect ARK to managed services such as customer communications, Dragon AI, backups, and other connected services.
        You can connect now or later from Settings. ARK Core runs your shop either way.
    </p>

    <div class="cards">
        <div class="opt">
            <strong>Optional</strong>
            <span>Cloud is not required to finish setup or operate the shop day to day.</span>
        </div>
        <div class="opt">
            <strong>Connect when ready</strong>
            <span>You’ll sign in or create an ARK Platform account, choose your Cloud shop, and approve this installation.</span>
        </div>
    </div>

    <div class="actions" style="flex-wrap:wrap;gap:10px;">
        <a class="btn btn-secondary" href="{{ route('install.admin') }}">Back</a>
        <form method="post" action="{{ route('install.integrations.connect') }}" style="display:inline;">
            @csrf
            <button class="btn btn-primary" type="submit">Connect ARK Platform</button>
        </form>
        <form method="post" action="{{ route('install.integrations.skip') }}" style="display:inline;">
            @csrf
            <button class="btn btn-secondary" type="submit">Set up later</button>
        </form>
    </div>
@endsection
