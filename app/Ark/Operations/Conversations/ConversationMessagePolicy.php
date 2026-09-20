<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Communications\OperationalCommunicationType;

final class ConversationMessagePolicy
{
    /**
     * Human communication acts belong on the conversation timeline.
     * Customer unreachable stays workflow-only until a dedicated surface exists.
     */
    public static function recordsFromManualLog(OperationalCommunicationType $type): bool
    {
        return match ($type) {
            OperationalCommunicationType::CustomerUnreachable => false,
            default => true,
        };
    }
}
