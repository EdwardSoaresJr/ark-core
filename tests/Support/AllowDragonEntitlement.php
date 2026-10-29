<?php

namespace Tests\Support;

use Ark\Saas\Capability;
use Ark\Saas\Entitlement\Entitlement;
use Ark\Saas\ShopRef;

final class AllowDragonEntitlement implements Entitlement
{
    public function allows(ShopRef $shop, Capability $capability): bool
    {
        return $capability === Capability::Dragon;
    }
}
