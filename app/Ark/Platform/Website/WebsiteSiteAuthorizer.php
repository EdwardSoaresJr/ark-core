<?php

namespace App\Ark\Platform\Website;

use App\Ark\Platform\Shop;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class WebsiteSiteAuthorizer
{
    public function canManage(User $user, Shop $shop): bool
    {
        if ($user->can(ArkCapability::SettingsManage->value)) {
            return true;
        }

        return (int) $shop->owner_user_id === (int) $user->id;
    }

    public function assertCanManage(User $user, WebsiteSite $site): void
    {
        abort_unless((bool) config('platform_website.management_enabled', true), 404);
        abort_unless($site->management_enabled, 404);

        $shop = $site->shop;
        abort_unless($shop !== null && $this->canManage($user, $shop), 403);
    }
}
