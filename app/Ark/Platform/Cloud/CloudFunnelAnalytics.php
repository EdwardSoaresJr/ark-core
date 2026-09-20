<?php

namespace App\Ark\Platform\Cloud;

use Illuminate\Support\Facades\Log;

/**
 * Cloud Funnel instrumentation — observe hesitation before provisioning is real.
 *
 * Events: homepage_cta · trial_started · shop_completed · workspace_completed ·
 * account_completed · funnel_completed · open_workspace
 *
 * @see docs/platform/cloud-funnel-v1.md
 */
final class CloudFunnelAnalytics
{
    public const HOMEPAGE_CTA = 'cloud_funnel_homepage_cta';

    public const TRIAL_STARTED = 'cloud_funnel_trial_started';

    public const SHOP_COMPLETED = 'cloud_funnel_shop_completed';

    public const WORKSPACE_COMPLETED = 'cloud_funnel_workspace_completed';

    public const ACCOUNT_COMPLETED = 'cloud_funnel_account_completed';

    public const FUNNEL_COMPLETED = 'cloud_funnel_completed';

    public const OPEN_WORKSPACE = 'cloud_funnel_open_workspace';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function track(string $event, array $context = []): void
    {
        Log::channel('single')->info('cloud_funnel', array_merge([
            'event' => $event,
            'at' => now()->toIso8601String(),
        ], $context));
    }
}
