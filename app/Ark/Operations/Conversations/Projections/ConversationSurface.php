<?php

namespace App\Ark\Operations\Conversations\Projections;

enum ConversationSurface: string
{
    case Mobile = 'mobile';
    case Web = 'web';
}
