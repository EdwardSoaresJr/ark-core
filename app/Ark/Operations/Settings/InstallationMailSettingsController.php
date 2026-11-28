<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Installation\Mail\CustomerOutboundMail;
use App\Ark\Installation\Mail\InstallationMailAudit;
use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailHostGuard;
use App\Ark\Runtime\HostedRuntime;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'installation_mail_enabled' => ['nullable', 'boolean'],
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

        $enabled = $request->boolean('installation_mail_enabled');
        $mailer = (string) $data['installation_mail_mailer'];
        $host = $this->nullableTrimmedString($data['installation_mail_host'] ?? null);
        $port = isset($data['installation_mail_port']) ? (int) $data['installation_mail_port'] : null;
        $encryption = ($data['installation_mail_encryption'] ?? 'tls') === 'none'
            ? null
            : ($data['installation_mail_encryption'] ?? 'tls');
        $from = $this->nullableTrimmedString($data['installation_mail_from_address'] ?? null);

        if ($enabled) {
            if ($from === null) {
                return $this->backWithErrors(['installation_mail_from_address' => 'Sender email is required when email is enabled.']);
            }
            if ($mailer === 'smtp') {
                if ($host === null || $port === null) {
                    return $this->backWithErrors(['installation_mail_host' => 'SMTP hostname and port are required when email is enabled.']);
                }
                try {
                    $hostGuard->assertSafe($host, $port);
                } catch (\InvalidArgumentException $e) {
                    return $this->backWithErrors(['installation_mail_host' => $e->getMessage()]);
                }
            }
        }

        $settings = ShopSettings::current();
        $payload = [
            'installation_mail_enabled' => $enabled,
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

        /** @var User|null $actor */
        $actor = $request->user();
        $audit->record($actor, $enabled ? 'updated' : 'disabled', $enabled
            ? 'Installation email settings saved.'
            : 'Installation email disabled.', [
                'mailer' => $mailer,
                'host' => $host,
                'port' => $port,
                'from_address' => $from,
                'password_replaced' => $replacePassword,
                'active_source' => $configuration->activeSource(),
            ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'email'])
            ->with('status', 'Email settings saved.');
    }

    public function test(
        Request $request,
        CustomerOutboundMail $mail,
        InstallationMailConfiguration $configuration,
        InstallationMailAudit $audit,
    ): RedirectResponse {
        $this->assertStandalone();

        $data = $request->validate([
            'test_recipient' => ['required', 'email', 'max:255'],
        ]);

        if (! $configuration->allowsCustomerDelivery()) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'email'])
                ->with('warning', 'Enable and save a complete SMTP or sendmail configuration before sending a test.');
        }

        $result = $mail->send(
            operation: 'installation.mail.test',
            recipientEmail: (string) $data['test_recipient'],
            variables: [
                'shop_name' => ShopSettings::current()->shop_name ?: config('app.name', 'ARK'),
            ],
        );

        ShopSettings::current()->persistTrusted([
            'installation_mail_last_tested_at' => now(),
            'installation_mail_last_test_status' => $result->status,
            'installation_mail_last_error' => $result->ok() ? null : $result->operatorMessage(),
        ]);

        /** @var User|null $actor */
        $actor = $request->user();
        $audit->record($actor, 'test_send', $result->operatorMessage(), [
            'recipient' => $data['test_recipient'],
            'status' => $result->status,
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
            ->withInput();
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
