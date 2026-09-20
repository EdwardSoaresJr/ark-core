<?php

namespace App\Ark\Operations\Portal;

enum PortalEstimateAuthorizationMode: string
{
    case PerConcern = 'per_concern';
    case None = 'none';
}
