<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Installation\Mail\InstallationMailAudit;
use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailHostGuard;
use App\Ark\Installation\Mail\InstallationMailSender;
use App\Ark\Runtime\HostedRuntime;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class InstallationMailSettingsController
{
    public function update(
        Request $request,
        InstallationMailConfiguration $configuration,
        InstallationMailHostGuard $hostGuard,
        InstallationMailAudit $audit,
    ): RedirectResponse {
        $this->assertStandalone();

        $data = $request->validate([
            'installation_mail_mode' => ['required', Rule::in([
                InstallationMailConfiguration::MODE_DISABLED,
                InstallationMailConfiguration::MODE_SHOP,
                InstallationMailConfiguration::MODE_ENVIRONMENT,
            ])],
            'installation_mail_mailer' => ['required', Rule::in(['smtp', 'sendmail'])],
            'installation_mail_host' => ['nullable', 'string', 'max:255'],
            'installation_mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'installation_mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'installation_mail_username' => ['nullable', 'string', 'max:255'],
            'installation_mail_password' => ['nullable', 'string', 'max:255'],
            'replace_password' => ['nullable', 'boolean'],
            'installation_mail_from_address' => ['nullable', 'email', 'max:255'],
            'installation_mail_from_name' => ['nullable', 'string', 'max:255'],
            'installation_mail_reply_to' => ['nullable', 'email', 'max:255'],
        ]);

        $mode = (string) $data['installation_mail_mode'];
        $mailer = (string) $data['installation_mail_mailer'];
        $host = $this->nullableTrimmedString($data['installation_mail_host'] ?? null);
        $port = isset($data['installation_mail_port']) ? (int) $data['installation_mail_port'] : null;
        $encryption = ($data['installation_mail_encryption'] ?? 'tls') === 'none'
            ? null
            : ($data['installation_mail_encryption'] ?? 'tls');
        $from = $this->nullableTrimmedString($data['installation_mail_from_address'] ?? null);

        if ($mode === InstallationMailConfiguration::MODE_SHOP) {
            if ($from === null) {
                return $this->backWithErrors(['installation_mail_from_address' => 'Sender email is required for Installation Settings.']);
            }
            if ($mailer === 'smtp') {
                if ($host === null || $port === null) {
                    return $this->backWithErrors(['installation_mail_host' => 'SMTP hostname and port are required for Installation Settings.']);
                }
                try {
                    $hostGuard->assertSafe($host, $port);
                } catch (\InvalidArgumentException $e) {
                    return $this->backWithErrors(['installation_mail_host' => $e->getMessage()]);
                }
            }
        }

        if ($mode === InstallationMailConfiguration::MODE_ENVIRONMENT && $configuration->environmentResolved() === null) {
            return $this->backWithErrors([
                'installation_mail_mode' => 'Environment MAIL_* is not a usable smtp/sendmail configuration. Fix the environment or choose Installation Settings.',
            ]);
        }

        $settings = ShopSettings::current();
        $previousFingerprint = $configuration->connectionFingerprint();

        $payload = [
            'installation_mail_mode' => $mode,
            'installation_mail_enabled' => $mode === InstallationMailConfiguration::MODE_SHOP,
            'installation_mail_mailer' => $mailer,
            'installation_mail_host' => $host,
            'installation_mail_port' => $port,
            'installation_mail_encryption' => $encryption,
            'installation_mail_username' => $this->nullableTrimmedString($data['installation_mail_username'] ?? null),
            'installation_mail_from_address' => $from,
            'installation_mail_from_name' => $this->nullableTrimmedString($data['installation_mail_from_name'] ?? null),
            'installation_mail_reply_to' => $this->nullableTrimmedString($data['installation_mail_reply_to'] ?? null),
        ];

        $replacePassword = $request->boolean('replace_password');
        $password = $data['installation_mail_password'] ?? null;
        if ($replacePassword) {
            if (is_string($password) && $password !== '') {
                $payload['installation_mail_password'] = $password;
                $payload['installation_mail_password_set_at'] = now();
            } else {
                $payload['installation_mail_password'] = null;
                $payload['installation_mail_password_set_at'] = null;
            }
        }

        $settings->persistTrusted($payload);
        $settings->refresh();

        $nextFingerprint = $configuration->connectionFingerprint();
        if ($previousFingerprint !== $nextFingerprint) {
            $settings->persistTrusted([
                'installation_mail_last_tested_at' => null,
                'installation_mail_last_test_status' => null,
                'installation_mail_last_error' => null,
            ]);
        }

        /** @var User|null $actor */
        $actor = $request->user();
        $audit->record($actor, 'mode_'.$mode, match ($mode) {
            InstallationMailConfiguration::MODE_SHOP => 'Installation email set to Installation Settings.',
            InstallationMailConfiguration::MODE_ENVIRONMENT => 'Installation email set to Environment Managed.',
            default => 'Installation email disabled.',
        }, [
            'mode' => $mode,
            'mailer' => $mailer,
            'host' => $host,
            'port' => $port,
            'from_address' => $from,
            'password_replaced' => $replacePassword,
            'active_source' => $configuration->activeSource(),
            // Never include password.
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'email'])
            ->with('status', 'Email settings saved.');
    }

    public function test(
        Request $request,
        InstallationMailSender $sender,
        InstallationMailConfiguration $configuration,
        InstallationMailAudit $audit,
    ): RedirectResponse {
        $this->assertStandalone();

        /** @var User $actor */
        $actor = $request->user();
        $rateKey = 'installation-mail-test:'.$actor->id;
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            throw ValidationException::withMessages([
                'test_recipient' => 'Too many test emails. Wait a minute and try again.',
            ]);
        }
        RateLimiter::hit($rateKey, 60);

        $data = $request->validate([
            'test_recipient' => ['required', 'email', 'max:255'],
        ]);

        $mode = $configuration->mode();
        $config = match ($mode) {
            InstallationMailConfiguration::MODE_SHOP => $configuration->shopResolved(),
            InstallationMailConfiguration::MODE_ENVIRONMENT => $configuration->environmentResolved(),
            default => null,
        };

        if ($config === null) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'email'])
                ->with('warning', 'Choose Installation Settings or Environment Managed with a complete configuration before sending a test.');
        }

        // Test the selected installation transport only — never Platform/managed mail.
        $result = $sender->send(
            operation: 'installation.mail.test',
            recipientEmail: (string) $data['test_recipient'],
            variables: [
                'shop_name' => ShopSettings::current()->shop_name ?: config('app.name', 'ARK'),
            ],
            configOverride: $config,
        );

        ShopSettings::current()->persistTrusted([
            'installation_mail_last_tested_at' => now(),
            'installation_mail_last_test_status' => $result->status,
            'installation_mail_last_error' => $result->ok() ? null : $result->operatorMessage(),
        ]);

        $audit->record($actor, 'test_send', $result->operatorMessage(), [
            'recipient' => $data['test_recipient'],
            'status' => $result->status,
            'source' => $config['source'],
        ]);

        $redirect = redirect()->route('operations.settings.shop.edit', ['section' => 'email']);

        if ($result->ok()) {
            return $redirect->with('status', $result->operatorMessage());
        }

        return $redirect->with('warning', $result->operatorMessage());
    }

    private function assertStandalone(): void
    {
        abort_if(HostedRuntime::isHostedProduct(), 404);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function backWithErrors(array $errors): RedirectResponse
    {
        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'email'])
            ->withErrors($errors)
            // Never flash the SMTP password into the session.
            ->withInput($this->safeOldInput());
    }

    /**
     * @return array<string, mixed>
     */
    private function safeOldInput(): array
    {
        return collect(request()->except([
            'installation_mail_password',
            'password',
            '_token',
        ]))->all();
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
