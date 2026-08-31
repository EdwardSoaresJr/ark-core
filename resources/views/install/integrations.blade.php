@extends('install.layout')

@section('title', 'Integrations')

@section('content')
    <h1>Optional integrations</h1>
    <p class="lead">None of these are required to finish installation. ARK core works without them.</p>

    <div class="cards">
        <div class="opt">
            <strong>Dragon / AI</strong>
            <span>Status: Not configured — Dragon runtime is included; hosted model service is optional via ARK Cloud when available.</span>
        </div>
        <div class="opt">
            <strong>Square</strong>
            <span>Status: Not configured — optional adapter. After install, enable with <code>composer require ark/payments-square</code>, then Settings → Payments. Not bundled in default ARK core.</span>
        </div>
        <div class="opt">
            <strong>Telephony / SMS</strong>
            <span>Status: Not configured — communications providers are optional.</span>
        </div>
        <div class="opt">
            <strong>Mail</strong>
            <span>Status: Not configured — connect ARK Mail from Settings after pairing with ARK Cloud. Local installs use log mailer for development.</span>
        </div>
        <div class="opt">
            <strong>Labor guide</strong>
            <span>Status: Not configured — licensed datasets are never bundled. Import your own later if licensed.</span>
        </div>
    </div>

    <form method="post" action="{{ route('install.integrations.skip') }}">
        @csrf
        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.admin') }}">Back</a>
            <button class="btn btn-primary" type="submit">Skip for now</button>
        </div>
    </form>
@endsection
