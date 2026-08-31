<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopIntegrationSettingsController
{
    use Concerns\InteractsWithShopSettingsPersistence;
    public function __construct(
        private readonly EstimateDocumentService $estimateDocumentService,
        private readonly EstimateTotalsCalculator $estimateTotalsCalculator,
    ) {}

    protected function estimateDocuments(): EstimateDocumentService
    {
        return $this->estimateDocumentService;
    }

    protected function totalsCalculator(): EstimateTotalsCalculator
    {
        return $this->estimateTotalsCalculator;
    }

public function updatePayments(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'square_application_id' => ['nullable', 'string', 'max:128'],
            'square_access_token' => ['nullable', 'string', 'max:512'],
            'square_location_id' => ['nullable', 'string', 'max:64'],
            'square_webhook_signature_key' => ['nullable', 'string', 'max:512'],
            'square_environment' => ['nullable', Rule::in(['sandbox', 'production'])],
            'square_enabled' => ['nullable', 'boolean'],
            'square_terminal_device_id' => ['nullable', 'string', 'max:128'],
            'square_terminal_enabled' => ['nullable', 'boolean'],
            'square_keyed_enabled' => ['nullable', 'boolean'],
            'square_portal_pay_enabled' => ['nullable', 'boolean'],
            'square_email_pay_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = ShopSettings::current();
        $updates = [
            'square_enabled' => $request->boolean('square_enabled'),
            'square_application_id' => $this->nullableTrimmedString($data['square_application_id'] ?? null),
            'square_location_id' => $this->nullableTrimmedString($data['square_location_id'] ?? null),
            'square_environment' => filled($data['square_environment'] ?? null)
                ? (string) $data['square_environment']
                : $settings->square_environment,
            'square_terminal_device_id' => filled($data['square_terminal_device_id'] ?? null)
                ? trim((string) $data['square_terminal_device_id'])
                : null,
            'square_terminal_enabled' => $request->boolean('square_terminal_enabled'),
            'square_keyed_enabled' => $request->boolean('square_keyed_enabled'),
            'square_portal_pay_enabled' => $request->boolean('square_portal_pay_enabled'),
            'square_email_pay_enabled' => $request->boolean('square_email_pay_enabled'),
        ];

        $this->mergeSecretField($updates, 'square_access_token', $data['square_access_token'] ?? null);
        $this->mergeSecretField($updates, 'square_webhook_signature_key', $data['square_webhook_signature_key'] ?? null);

        $settings->persistTrusted($updates);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'payments'])
            ->with('status', 'Square payment settings saved.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'postmark_reply_to' => ['nullable', 'email', 'max:255'],
            'postmark_reply_to_name' => ['nullable', 'string', 'max:255'],
        ]);

        ShopSettings::current()->persistTrusted([
            'postmark_reply_to' => $this->nullableTrimmedString($data['postmark_reply_to'] ?? null),
            'postmark_reply_to_name' => $this->nullableTrimmedString($data['postmark_reply_to_name'] ?? null),
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => 'email',
            ])
            ->with('status', 'Email settings saved.');
    }

    public function enableArkMail(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        $data = $request->validate([
            'ark_mail_service_url' => ['nullable', 'url', 'max:255'],
        ]);

        $redirect = redirect()->route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]);

        try {
            $serviceUrl = filled($data['ark_mail_service_url'] ?? null)
                ? rtrim((string) $data['ark_mail_service_url'], '/')
                : null;
            $started = $activation->activate($serviceUrl);
        } catch (\Throwable $e) {
            ShopSettings::current()->persistTrusted([
                'ark_mail_status' => 'error',
                'cloud_status' => 'error',
            ]);

            return $redirect->with('status', 'Could not start connecting: '.$e->getMessage());
        }

        $code = $started['pairing_code'] ?? '';

        return $redirect->with(
            'status',
            $code !== ''
                ? "Pairing code {$code}. Approve it in ARK Cloud, then finish connecting here."
                : ($started['message'] ?? 'Approve the pairing code in ARK Cloud, then finish connecting here.')
        );
    }

    public function claimArkMail(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        $data = $request->validate([
            'pairing_public_id' => ['nullable', 'uuid'],
        ]);

        $redirect = redirect()->route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]);

        try {
            $activation->claimPairing($data['pairing_public_id'] ?? null);
        } catch (\Throwable $e) {
            return $redirect->with('status', 'Could not finish connecting: '.$e->getMessage());
        }

        return $redirect->with('status', 'ARK Mail connected. Replies go to your shop email.');
    }

    public function disconnectArkMail(ArkMailActivationClient $activation): RedirectResponse
    {
        $activation->disconnect();

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => 'email',
            ])
            ->with('status', 'ARK Mail disconnected.');
    }
}
