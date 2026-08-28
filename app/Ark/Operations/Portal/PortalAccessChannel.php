<?php

namespace App\Ark\Operations\Portal;

enum PortalAccessChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
}
