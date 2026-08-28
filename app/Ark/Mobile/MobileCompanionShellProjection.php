<?php

namespace App\Ark\Mobile;

use App\Models\User;

/**
 * Companion v1 shell — parallel to legacy mobile navigation until new app ships.
 */
final class MobileCompanionShellProjection
{
    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly MobileTelephonyDialProjection $telephonyDialProjection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, string $operationalProfile): array
    {
        return [
            'product' => 'companion_v1',
            'default_home_route' => $this->defaultHomeRoute($user, $operationalProfile),
            'default_home_deep_link' => $this->defaultHomeDeepLink($user, $operationalProfile),
            'navigation' => $this->navigation($user, $operationalProfile),
            'phone_status_label' => $this->phoneStatusLabel($user),
        ];
    }

    private function defaultHomeRoute(User $user, string $operationalProfile): string
    {
        if ($operationalProfile === 'technician') {
            return 'my_work';
        }

        return $this->access->canAccessShopCommunications($user) ? 'comms' : 'home';
    }

    private function defaultHomeDeepLink(User $user, string $operationalProfile): string
    {
        if ($operationalProfile === 'technician') {
            return 'companion://my-work';
        }

        return $this->access->canAccessShopCommunications($user)
            ? 'companion://communications'
            : MobileCompanionDeepLink::home();
    }

    /**
     * @return list<array{key: string, label: string, enabled: bool, deep_link: string}>
     */
    private function navigation(User $user, string $profile): array
    {
        if (! $this->access->canUseMobile($user)) {
            return [];
        }

        if ($profile === 'technician') {
            return [
                ['key' => 'my_work', 'label' => 'My Work', 'enabled' => true, 'deep_link' => 'companion://my-work'],
                ['key' => 'more', 'label' => 'More', 'enabled' => true, 'deep_link' => 'companion://more'],
            ];
        }

        $comms = $this->access->canAccessShopCommunications($user);

        return [
            ['key' => 'home', 'label' => 'Home', 'enabled' => true, 'deep_link' => MobileCompanionDeepLink::home()],
            ['key' => 'comms', 'label' => 'Comms', 'enabled' => $comms, 'deep_link' => 'companion://communications'],
            ['key' => 'search', 'label' => 'Search', 'enabled' => true, 'deep_link' => MobileCompanionDeepLink::search()],
            ['key' => 'schedule', 'label' => 'Schedule', 'enabled' => true, 'deep_link' => MobileCompanionDeepLink::schedule()],
            ['key' => 'more', 'label' => 'More', 'enabled' => true, 'deep_link' => 'companion://more'],
        ];
    }

    private function phoneStatusLabel(User $user): string
    {
        $telephony = $this->telephonyDialProjection->shellTelephony($user);
        $voice = is_array($telephony['voice'] ?? null) ? $telephony['voice'] : [];
        $inAppReady = (bool) ($voice['in_app_ready'] ?? false);
        $dialMethod = (string) ($telephony['dial_method'] ?? 'native');

        if ($inAppReady) {
            return 'Phone ready';
        }

        if ($dialMethod === 'shop_callback') {
            return 'Callback ready';
        }

        if ($dialMethod === 'in_app') {
            return 'Phone connecting';
        }

        return 'Phone offline';
    }
}
