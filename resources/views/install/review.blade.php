@extends('install.layout')

@section('title', 'Review')

@section('content')
    <h1>Review</h1>
    <p class="lead">ARK will initialize the schema, create your administrator, apply shop settings, and lock setup. Secrets are never shown here.</p>

    @if ($httpWarning)
        <div class="warn-box">Application URL uses HTTP. Prefer HTTPS in production.</div>
    @endif

    <dl class="review">
        <dt>Application URL</dt>
        <dd>{{ $draft['app_url'] ?? '—' }}</dd>
        <dt>Database</dt>
        <dd>@if (!empty($draft['db_managed'])) Connected @else {{ ($draft['db_username'] ?? '') }}@{{ ($draft['db_host'] ?? '') }}:{{ ($draft['db_port'] ?? '') }} / {{ ($draft['db_database'] ?? '') }} @endif</dd>
        <dt>Shop</dt>
        <dd>{{ $draft['shop_name'] ?? '—' }} · {{ $draft['shop_timezone'] ?? '—' }}</dd>
        <dt>Administrator</dt>
        <dd>{{ $draft['admin_email'] ?? '—' }}</dd>
        <dt>Workstation</dt>
        <dd>{{ !empty($draft['create_workstation']) ? 'Main Shop (default)' : 'None' }}</dd>
        <dt>ARK Services</dt>
        <dd>Connect ARK Platform after installation in Settings</dd>
    </dl>

    <form method="post" action="{{ route('install.run') }}" id="install-run-form">
        @csrf
        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.integrations') }}">Back</a>
            <button class="btn btn-primary" type="submit" id="install-run-button">Install ARK</button>
        </div>
    </form>

    <script>
        document.getElementById('install-run-form')?.addEventListener('submit', function () {
            const button = document.getElementById('install-run-button');
            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.textContent = 'Installing…';
        });
    </script>
@endsection
