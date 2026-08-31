@extends('install.layout')

@section('title', 'Database')

@section('content')
    <h1>Database</h1>

    @if ($managed)
        <p class="lead">ARK already created the database for this installation. Confirm the application URL, then continue.</p>
    @else
        <p class="lead">
            Connect an empty MySQL database. ARK will test the connection without changing any tables.
            @if ($envMode === 'immutable')
                This host uses an environment that cannot be written during setup. Connection values below come from the runtime configuration.
            @endif
        </p>
    @endif

    @if ($errors->has('database'))
        <div class="error-box" role="alert">{{ $errors->first('database') }}</div>
    @endif

    @if (session('install_http_warning'))
        <div class="warn-box">You entered an HTTP URL. Production shops should use HTTPS.</div>
    @endif

    <form method="post" action="{{ route('install.database.test') }}">
        @csrf

        <label for="app_url">Application URL</label>
        <input id="app_url" name="app_url" type="url" required value="{{ old('app_url', $suggestedUrl) }}">
        <p class="hint">Suggested from this request. Correct if behind a reverse proxy.</p>

        @if ($managed)
            <div class="db-status {{ $databaseStatus === 'connected' ? 'is-ok' : 'is-fail' }}" role="status">
                <strong>Database</strong>
                @if ($databaseStatus === 'connected')
                    <span>Connected</span>
                @else
                    <span>{{ $databaseMessage ?: 'Could not connect.' }}</span>
                @endif
            </div>
            <p class="hint">Credentials stay on this server. You do not need to enter them.</p>

            <div class="actions">
                <a class="btn btn-secondary" href="{{ route('install.system') }}">Back</a>
                <button class="btn btn-primary" type="submit" @disabled($databaseStatus !== 'connected')>Continue</button>
            </div>
            <p class="hint">
                <a href="{{ route('install.database', ['manual' => 1]) }}">Use a different database</a>
            </p>
        @else
            @if ($runtimeManaged)
                <input type="hidden" name="manual" value="1">
            @endif

            <label for="db_host">Database host</label>
            <input id="db_host" name="db_host" required value="{{ old('db_host', $defaults['db_host']) }}">

            <div class="row">
                <div>
                    <label for="db_port">Port</label>
                    <input id="db_port" name="db_port" type="number" required value="{{ old('db_port', $defaults['db_port']) }}">
                </div>
                <div>
                    <label for="db_database">Database name</label>
                    <input id="db_database" name="db_database" required value="{{ old('db_database', $defaults['db_database']) }}">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="db_username">Username</label>
                    <input id="db_username" name="db_username" required value="{{ old('db_username', $defaults['db_username']) }}" autocomplete="off">
                </div>
                <div>
                    <label for="db_password">Password</label>
                    <input id="db_password" name="db_password" type="password" value="" autocomplete="new-password">
                    @if ($defaults['runtime_password_configured'])
                        <p class="hint">Already configured on this host. Leave blank to keep using it.</p>
                    @endif
                </div>
            </div>

            <div class="actions">
                <a class="btn btn-secondary" href="{{ route('install.system') }}">Back</a>
                <button class="btn btn-primary" type="submit">Test Connection</button>
            </div>
            @if ($runtimeManaged)
                <p class="hint">
                    <a href="{{ route('install.database') }}">Use the database ARK created</a>
                </p>
            @endif
        @endif
    </form>
@endsection
