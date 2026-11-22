<?php

namespace App\Ark\Platform;

use App\Ark\Platform\Payments\ManagedPaymentsGate;
use App\Ark\Runtime\HostedRuntime;
use Illuminate\Http\RedirectResponse;

/**
 * Standalone Core must not expose SaaS provider configuration.
 *
 * Hosted vs standalone is decided by the Landlord/SaaS tenant-catalog
 * contract ({@see HostedRuntime::catalogConfigured()}), not by discontinued
 * ARK Platform / Box pairing state. Per-service capture and delivery stay
 * behind the Managed* gates that ARK Shop replaces with LocalShop entitlements.
 */
final class SaasProviderConfigurationGate
{
    /**
     * True when this process is a hosted shop image (tenant catalog present).
     * Standalone Core installs do not set ARK_HOSTED_TENANTS.
     */
    public static function isHostedRuntime(): bool
    {
        return HostedRuntime::catalogConfigured();
    }

    public static function allowsCommunicationsProviderSettings(): bool
    {
        return self::isHostedRuntime() && HostedRuntime::tenantDatabaseSelected();
    }

    public static function allowsCardCaptureSettings(): bool
    {
        if (! self::isHostedRuntime()) {
            return false;
        }

        // Stock Core ManagedPaymentsGate is legacy; composed ARK Shop replaces it
        // with LocalShop::connected('card-capture') entitlement checks.
        return ManagedPaymentsGate::platformCapture();
    }

    /**
     * Physical workstations stay in Core. Phone device provisioning / SIP belongs to SaaS.
     */
    public static function allowsPhoneDeviceProvisioning(): bool
    {
        return self::isHostedRuntime() && HostedRuntime::tenantDatabaseSelected();
    }

    /**
     * Legacy Box pairing is not a Core Settings capability.
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
            ->with('status', 'Box pairing is not configured from Core Settings. Hosted shops are associated by ARK Shop and Landlord.');
    }
}
