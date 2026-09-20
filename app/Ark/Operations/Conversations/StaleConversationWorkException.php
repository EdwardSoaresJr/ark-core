<?php

namespace App\Ark\Operations\Conversations;

use RuntimeException;

final class StaleConversationWorkException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This conversation was updated by someone else. Refresh and try again.');
    }
}
