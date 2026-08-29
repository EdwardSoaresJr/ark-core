@extends('install.layout')

@section('title', 'Database')

@section('content')
    <h1>Database</h1>
    <p class="lead">
        Connect an empty MySQL database. ARK will test the connection without changing any tables.
        @if ($envMode === 'immutable')
            This host uses an immutable environment — supply DB credentials via the platform if the form cannot persist them.
        @endif
    </p>

    @if (session('install_http_warning'))
        <div class="warn-box">You entered an HTTP URL. Production shops should use HTTPS.</div>
    @endif

    <form method="post" action="{{ route('install.database.test') }}">
        @csrf
        <label for="app_url">Application URL</label>
        <input id="app_url" name="app_url" type="url" required value="{{ old('app_url', $suggestedUrl) }}">
        <p class="hint">Suggested from this request. Correct if behind a reverse proxy.</p>

        <label for="db_host">Database host</label>
        <input id="db_host" name="db_host" required value="{{ old('db_host', $draft['db_host'] ?? '127.0.0.1') }}">

        <div class="row">
            <div>
                <label for="db_port">Port</label>
                <input id="db_port" name="db_port" type="number" required value="{{ old('db_port', $draft['db_port'] ?? 3306) }}">
            </div>
            <div>
                <label for="db_database">Database name</label>
                <input id="db_database" name="db_database" required value="{{ old('db_database', $draft['db_database'] ?? 'ark') }}">
            </div>
        </div>

        <div class="row">
            <div>
                <label for="db_username">Username</label>
                <input id="db_username" name="db_username" required value="{{ old('db_username', $draft['db_username'] ?? '') }}" autocomplete="off">
            </div>
            <div>
                <label for="db_password">Password</label>
                <input id="db_password" name="db_password" type="password" value="" autocomplete="new-password">
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.system') }}">Back</a>
            <button class="btn btn-primary" type="submit">Test Connection</button>
        </div>
    </form>
@endsection
