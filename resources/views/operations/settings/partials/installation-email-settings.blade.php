@php
    $mailConfig = app(\App\Ark\Installation\Mail\InstallationMailConfiguration::class);
    $form = $mailConfig->formDefaults();
    $audit = app(\App\Ark\Installation\Mail\InstallationMailAudit::class)->recent(12);
    $outbound = app(\App\Ark\Mail\OutboundTransactionalMail::class);
    $mode = old('installation_mail_mode', $form['mode']);
@endphp

<div class="d-flex flex-column gap-3">
    <div>
        <div class="subheader">Customer email</div>
        <p class="text-secondary small mb-2">
            Send estimates, invoices, inspections, and statements through this installation’s mail server.
            No ARK account is required. SMTP acceptance is not confirmed delivery to the recipient’s inbox.
        </p>
        <dl class="row small mb-0">
            <dt class="col-sm-4 text-secondary">Configuration mode</dt>
            <dd class="col-sm-8 fw-semibold">{{ $form['mode_label'] }}</dd>
            <dt class="col-sm-4 text-secondary">Delivery status</dt>
            <dd class="col-sm-8 fw-semibold">{{ $outbound->statusLabel() }}</dd>
            <dt class="col-sm-4 text-secondary">Active transport</dt>
            <dd class="col-sm-8">
                @if ($form['active_source'] === 'shop_settings')
                    Installation Settings
                @elseif ($form['active_source'] === 'environment')
                    Environment Managed (MAIL_*)
                @else
                    None — customer email is disabled
                @endif
            </dd>
        </dl>
        @if (! $outbound->isReady())
            <p class="alert alert-warning small mt-2 mb-0">
                Customer email is not ready. Choose Installation Settings or Environment Managed with a complete smtp/sendmail configuration.
            </p>
        @endif
        @if (filled($form['last_error']))
            <p class="alert alert-danger small mt-2 mb-0">Last transport error: {{ $form['last_error'] }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.installation-mail.update') }}" class="border rounded p-3 d-flex flex-column gap-3" autocomplete="off">
        @csrf
        @method('PATCH')

        <label>
            <span class="form-label">Configuration mode</span>
            <select name="installation_mail_mode" class="form-select @error('installation_mail_mode') is-invalid @enderror">
                <option value="disabled" @selected($mode === 'disabled')>Disabled — do not send customer email</option>
                <option value="shop_settings" @selected($mode === 'shop_settings')>Installation Settings — use the SMTP form below</option>
                <option value="environment" @selected($mode === 'environment') @disabled(! $form['environment_available'] && $mode !== 'environment')>
                    Environment Managed — use server MAIL_* only
                </option>
            </select>
            @error('installation_mail_mode') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            <span class="form-hint">
                Disabled never falls back to environment SMTP. Invalid Installation Settings never fall back to another transport.
            </span>
        </label>

        <div class="row g-3">
            <label class="col-12 col-md-4">
                <span class="form-label">Mailer</span>
                <select name="installation_mail_mailer" class="form-select">
                    <option value="smtp" @selected(old('installation_mail_mailer', $form['mailer']) === 'smtp')>SMTP</option>
                    <option value="sendmail" @selected(old('installation_mail_mailer', $form['mailer']) === 'sendmail')>Sendmail</option>
                </select>
            </label>
            <label class="col-12 col-md-5">
                <span class="form-label">SMTP hostname</span>
                <input type="text" name="installation_mail_host" value="{{ old('installation_mail_host', $form['host']) }}" class="form-control @error('installation_mail_host') is-invalid @enderror" placeholder="mail.yourshop.com" autocomplete="off">
                @error('installation_mail_host') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </label>
            <label class="col-6 col-md-3">
                <span class="form-label">Port</span>
                <input type="number" name="installation_mail_port" value="{{ old('installation_mail_port', $form['port']) }}" class="form-control" min="1" max="65535">
            </label>
            <label class="col-6 col-md-4">
                <span class="form-label">Encryption</span>
                <select name="installation_mail_encryption" class="form-select">
                    @php $enc = old('installation_mail_encryption', $form['encryption'] ?: 'tls'); @endphp
                    <option value="tls" @selected($enc === 'tls')>TLS (STARTTLS required)</option>
                    <option value="ssl" @selected($enc === 'ssl')>SSL (SMTPS)</option>
                    <option value="none" @selected($enc === 'none' || $enc === '')>None</option>
                </select>
            </label>
            <label class="col-12 col-md-4">
                <span class="form-label">Username</span>
                <input type="text" name="installation_mail_username" value="{{ old('installation_mail_username', $form['username']) }}" class="form-control" autocomplete="off">
            </label>
            <label class="col-12 col-md-4">
                <span class="form-label">Password</span>
                <input type="password" name="installation_mail_password" value="" class="form-control" autocomplete="new-password" placeholder="{{ $form['password_configured'] ? '••••••••' : '' }}">
                <span class="form-hint">Leave blank to keep the current password. Passwords are never shown or flashed back.</span>
            </label>
            <label class="col-12 form-check">
                <input type="checkbox" name="replace_password" value="1" class="form-check-input" @checked(old('replace_password'))>
                <span class="form-check-label">Replace stored password (required to clear or change it)</span>
            </label>
            <label class="col-12 col-md-6">
                <span class="form-label">Sender email</span>
                <input type="email" name="installation_mail_from_address" value="{{ old('installation_mail_from_address', $form['from_address']) }}" class="form-control @error('installation_mail_from_address') is-invalid @enderror">
                @error('installation_mail_from_address') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </label>
            <label class="col-12 col-md-6">
                <span class="form-label">Sender name</span>
                <input type="text" name="installation_mail_from_name" value="{{ old('installation_mail_from_name', $form['from_name']) }}" class="form-control">
            </label>
            <label class="col-12 col-md-6">
                <span class="form-label">Reply-to (optional)</span>
                <input type="email" name="installation_mail_reply_to" value="{{ old('installation_mail_reply_to', $form['reply_to']) }}" class="form-control">
            </label>
        </div>

        <div class="d-flex justify-content-end border-top pt-3">
            <button type="submit" class="btn btn-primary">Save email settings</button>
        </div>
    </form>

    <form method="POST" action="{{ route('operations.settings.shop.installation-mail.test') }}" class="border rounded p-3 d-flex flex-column gap-3" autocomplete="off">
        @csrf
        <div>
            <div class="subheader">Test email</div>
            <p class="text-secondary small mb-0">
                Sends through the selected installation transport only (never ARK Email / Platform). Limited to 3 tests per minute.
                Success means the mail server accepted the message.
            </p>
        </div>
        <div class="row g-3 align-items-end">
            <label class="col-12 col-md-8">
                <span class="form-label">Recipient</span>
                <input type="email" name="test_recipient" value="{{ old('test_recipient', auth()->user()?->email) }}" class="form-control @error('test_recipient') is-invalid @enderror" required>
                @error('test_recipient') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </label>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-outline-primary w-100" @disabled($form['mode'] === 'disabled')>Send test email</button>
            </div>
        </div>
        @if ($form['last_tested_at'])
            <p class="small text-secondary mb-0">
                Last test: {{ \Illuminate\Support\Carbon::parse($form['last_tested_at'])->timezone(config('app.timezone'))->toDayDateTimeString() }}
                @if ($form['last_test_status']) ({{ $form['last_test_status'] }}) @endif
            </p>
        @else
            <p class="small text-secondary mb-0">No successful test for the current connection settings.</p>
        @endif
    </form>

    @if ($audit !== [])
        <div class="border rounded p-3">
            <div class="subheader mb-2">Configuration history</div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Who</th>
                            <th>Action</th>
                            <th>Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audit as $event)
                            <tr>
                                <td class="text-secondary small">{{ $event->created_at?->timezone(config('app.timezone'))->toDayDateTimeString() }}</td>
                                <td class="small">{{ $event->actor?->name ?? 'System' }}</td>
                                <td class="small">{{ $event->action }}</td>
                                <td class="small">{{ $event->summary }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
