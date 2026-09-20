<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Mail\ArkMailIdentityClient;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Parts\PartsCatalogButtonColor;
use App\Ark\Operations\Parts\PartsCatalogLinks;
use App\Ark\Operations\Parts\PartsCatalogProvider;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopIntegrationSettingsController
{
    use InteractsWithShopSettingsPersistence;

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
            'square_terminal_device_id' => ['nullable', 'string', 'max:128'],
            'square_terminal_enabled' => ['nullable', 'boolean'],
            'square_keyed_enabled' => ['nullable', 'boolean'],
            'square_portal_pay_enabled' => ['nullable', 'boolean'],
            'square_email_pay_enabled' => ['nullable', 'boolean'],
        ]);

        ShopSettings::current()->persistTrusted([
            'square_terminal_device_id' => $this->nullableTrimmedString($data['square_terminal_device_id'] ?? null),
            'square_terminal_enabled' => $request->boolean('square_terminal_enabled'),
            'square_keyed_enabled' => $request->boolean('square_keyed_enabled'),
            'square_portal_pay_enabled' => $request->boolean('square_portal_pay_enabled'),
            'square_email_pay_enabled' => $request->boolean('square_email_pay_enabled'),
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'payments'])
            ->with('status', 'Payment capture surfaces saved.');
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

        $redirect = redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => 'email',
            ])
            ->with('status', 'Email settings saved.');

        if (PlatformConnection::current()->isConnected()) {
            $synced = app(ArkMailIdentityClient::class)->syncShopReplyTo();
            if (! $synced) {
                $redirect->with('warning', 'Reply-To was saved here, but ARK Platform could not be updated right now. Try again from Settings → ARK Platform after Cloud is reachable.');
            }
        }

        return $redirect;
    }

    /** @deprecated Use ShopPlatformSettingsController — redirects preserved for old links. */
    public function enableArkMail(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        return app(ShopPlatformSettingsController::class)->connect($request, $activation);
    }

    /** @deprecated Use ShopPlatformSettingsController */
    public function claimArkMail(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        return app(ShopPlatformSettingsController::class)->claim($request, $activation);
    }

    /** @deprecated Use ShopPlatformSettingsController */
    public function disconnectArkMail(ArkMailActivationClient $activation): RedirectResponse
    {
        return app(ShopPlatformSettingsController::class)->disconnect($activation);
    }

    public function updatePartsTech(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'partstech_color' => ['nullable', 'string', Rule::in(PartsCatalogButtonColor::values())],
        ]);

        if (array_key_exists('partstech_color', $data) && filled($data['partstech_color'])) {
            PartsCatalogButtonColor::persistShopColor(
                PartsCatalogProvider::PartsTech->value,
                (string) $data['partstech_color'],
            );
        }

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'partstech'])
            ->with('status', 'PartsTech button color saved.');
    }

    public function updateRepairLink(Request $request): RedirectResponse
    {
        $rawUrl = trim((string) $request->input('repairlink_url'));
        $normalizedUrl = ShopIntegrationCredentials::normalizedHttpsUrl($rawUrl);

        if ($rawUrl !== '' && $normalizedUrl === null) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'partstech'])
                ->withInput($request->except('repairlink_url'))
                ->withErrors([
                    'repairlink_url' => 'Enter an https:// address. HTTP and URLs with usernames or passwords are not allowed.',
                ]);
        }

        $request->merge([
            'repairlink_url' => $normalizedUrl,
        ]);

        $data = $request->validate([
            'repairlink_enabled' => ['nullable', 'boolean'],
            'repairlink_color' => ['nullable', 'string', Rule::in(PartsCatalogButtonColor::values())],
            'repairlink_url' => [
                Rule::requiredIf($request->boolean('repairlink_enabled')),
                'nullable',
                'string',
                'max:255',
                'url',
                'starts_with:https://',
            ],
        ]);

        ShopSettings::current()->persistTrusted([
            'repairlink_enabled' => $request->boolean('repairlink_enabled'),
            'repairlink_url' => $this->nullableTrimmedString($data['repairlink_url'] ?? null),
        ]);

        PartsCatalogButtonColor::persistShopColor(
            PartsCatalogProvider::RepairLink->value,
            (string) ($data['repairlink_color'] ?? PartsCatalogButtonColor::BLUE),
        );

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'partstech'])
            ->with('status', 'RepairLink settings saved.');
    }

    public function updateNexpart(Request $request): RedirectResponse
    {
        $rawUrl = trim((string) $request->input('nexpart_url'));
        $normalizedUrl = ShopIntegrationCredentials::normalizedHttpsUrl($rawUrl);

        if ($rawUrl !== '' && $normalizedUrl === null) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'partstech'])
                ->withInput($request->except('nexpart_url'))
                ->withErrors([
                    'nexpart_url' => 'Enter an https:// address. HTTP and URLs with usernames or passwords are not allowed.',
                ]);
        }

        $request->merge([
            'nexpart_url' => $normalizedUrl,
        ]);

        $data = $request->validate([
            'nexpart_enabled' => ['nullable', 'boolean'],
            'nexpart_color' => ['nullable', 'string', Rule::in(PartsCatalogButtonColor::values())],
            'nexpart_url' => [
                Rule::requiredIf($request->boolean('nexpart_enabled')),
                'nullable',
                'string',
                'max:255',
                'url',
                'starts_with:https://',
            ],
        ]);

        ShopSettings::current()->persistTrusted([
            'nexpart_enabled' => $request->boolean('nexpart_enabled'),
            'nexpart_url' => $this->nullableTrimmedString($data['nexpart_url'] ?? null),
        ]);

        PartsCatalogButtonColor::persistShopColor(
            PartsCatalogProvider::Nexpart->value,
            (string) ($data['nexpart_color'] ?? PartsCatalogButtonColor::NAVY),
        );

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'partstech'])
            ->with('status', 'Nexpart settings saved.');
    }

    public function updateCatalogLinks(Request $request): RedirectResponse
    {
        $existing = PartsCatalogLinks::customForShop();
        $submitted = $request->input('links', []);
        $create = $request->input('create', []);

        if (! is_array($submitted)) {
            $submitted = [];
        }

        if (is_array($create) && (filled($create['label'] ?? null) || filled($create['url'] ?? null))) {
            $submitted[] = [
                'label' => $create['label'] ?? '',
                'url' => $create['url'] ?? '',
                'mode' => $create['mode'] ?? PartsCatalogLinks::MODE_LINK,
                'color' => $create['color'] ?? null,
            ];
        }

        foreach ($submitted as $row) {
            if (! is_array($row)) {
                continue;
            }

            $delete = $row['delete'] ?? false;

            if ($delete === true || $delete === 1 || $delete === '1' || $delete === 'on') {
                continue;
            }

            $rawUrl = trim((string) ($row['url'] ?? ''));

            if ($rawUrl !== '' && ShopIntegrationCredentials::normalizedHttpsUrl($rawUrl) === null) {
                return redirect()
                    ->route('operations.settings.shop.edit', ['section' => 'partstech'])
                    ->withInput()
                    ->withErrors([
                        'catalog_links' => 'Catalog links must use https://. HTTP and URLs with usernames or passwords are not allowed.',
                    ]);
            }
        }

        PartsCatalogLinks::persistShop(
            PartsCatalogLinks::fromSubmitted($submitted, $existing),
        );

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'partstech'])
            ->with('status', 'Catalog links saved.');
    }
}
