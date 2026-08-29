@extends('install.layout')

@section('title', 'Welcome')

@section('content')
    <h1>Welcome to ARK</h1>
    <p class="lead">
        Setup configures this server for shop operations: database, shop identity, and the first administrator.
        Optional integrations (Dragon, payments, telephony, mail) can be configured later in Settings.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('install.system') }}">Begin Setup</a>
    </div>
@endsection
