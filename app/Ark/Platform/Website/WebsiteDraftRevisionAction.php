<?php

namespace App\Ark\Platform\Website;

enum WebsiteDraftRevisionAction: string
{
    case Import = 'import';
    case Edit = 'edit';
    case AcceptCore = 'accept_core';
    case KeepPlatform = 'keep_platform';
    case Publish = 'publish';
    case SwitchAuthority = 'switch_authority';
}
