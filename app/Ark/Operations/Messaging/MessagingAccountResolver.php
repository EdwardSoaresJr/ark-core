<?php

namespace App\Ark\Operations\Messaging;

interface MessagingAccountResolver
{
    public function current(): ?MessagingAccount;
}
