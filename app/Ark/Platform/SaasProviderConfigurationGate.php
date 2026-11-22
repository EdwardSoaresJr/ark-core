<?php

namespace App\Ark\Platform;

use App\Ark\Platform\Payments\ManagedPaymentsGate;
use Illuminate\Http\RedirectResponse;

/**
 * Standalone Core must not expose SaaS provider configuration.
 * Hosted shops (Platform connection present) keep existing Core settings surfaces
 * until ARK Shop owns those screens fully. Pairing / Box connect is never offered here.
 */
final class SaasProviderConfigurationGate
{
    public static function platformConnected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function allowsCommunicationsProviderSettings(): bool
    {
        return self::platformConnected();
    }

    public static function allowsCardCaptureSettings(): bool
    {
        return ManagedPaymentsGate::platformCapture();
    }

    /**
     * Physical workstations stay in Core. Phone device provisioning / SIP belongs to SaaS.
     */
    public static function allowsPhoneDeviceProvisioning(): bool
    {
        return self::platformConnected();
    }

    /**
     * Legacy Box / ARK Platform pairing is not a Core Settings capability.
     * Landlord and ARK Shop own hosted association.
     */
    public static function allowsBoxPairing(): bool
    {
        return false;
    }

    public static function refuseCommunicationsProviderSettings(): RedirectResponse
    {
        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'general'])
            ->with('status', 'Customer messaging and phone provider settings belong to ARK Shop. They are not available on standalone Core.');
    }

    public static function refuseCardCaptureSettings(): RedirectResponse
    {
        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'payments'])
            ->with('status', 'Card capture settings belong to ARK Shop. Record Payment and manual payment methods stay available here.');
    }

    public static function refusePhoneDeviceProvisioning(): RedirectResponse
    {
        return redirect()
            ->route('operations.shop.communications')
            ->with('status', 'Phone device provisioning belongs to ARK Shop. Physical workstations stay available here.');
    }

    public static function refuseBoxPairing(): RedirectResponse
    {
        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'general'])
            ->with('status', 'ARK Platform Box pairing is not configured from Core Settings. Hosted shops are associated by ARK Shop / Landlord.');
    }
}
