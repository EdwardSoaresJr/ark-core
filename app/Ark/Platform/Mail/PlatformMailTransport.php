<?php

namespace App\Ark\Platform\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Laravel's mailer resolves a transport. Core has no email product on that transport.
 */
final class PlatformMailTransport extends AbstractTransport
{
    public function __construct(private readonly ArkMailClient $client)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        throw new TransportException('Core does not render or deliver email.');
    }

    public function __toString(): string
    {
        return 'ark-platform://platform';
    }
}
