@extends('install.layout')

@section('title', 'Welcome')

@section('content')
    <h1>Welcome to ARK</h1>
    <p class="lead">
        Setup configures this server for shop operations: database, shop identity, and the first administrator.
        After setup, use Settings for ordinary shop configuration. Card capture, customer messaging, hosted phone, and Dragon belong to ARK Shop when that product is connected.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('install.system') }}">Begin Setup</a>
    </div>
@endsection
