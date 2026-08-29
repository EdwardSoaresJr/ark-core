@extends('install.layout')

@section('title', 'Ready')

@section('content')
    <h1>ARK is ready.</h1>
    <p class="lead">Your shop has been initialized. Setup is locked. Optional integrations can be configured later in Settings.</p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('login') }}">Enter ARK</a>
    </div>
@endsection
