<?php

namespace App\Ark\Operations\Messaging;

final class ShopMessagingAccountResolver implements MessagingAccountResolver
{
    public function __construct(private readonly MessagingCredentialStore $store) {}

    public function current(): ?MessagingAccount
    {
        return $this->store->current();
    }
}
