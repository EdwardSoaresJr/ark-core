<?php

namespace App\Ark\Operations\Messaging;

final readonly class MessagingAccount
{
    public function __construct(
        public string $accountSid,
        public string $authToken,
        public string $from,
        public string $apiBase = 'https://api.twilio.com',
    ) {}

    public function complete(): bool
    {
        return $this->accountSid !== ''
            && $this->authToken !== ''
            && $this->from !== ''
            && $this->apiBase !== '';
    }
}
