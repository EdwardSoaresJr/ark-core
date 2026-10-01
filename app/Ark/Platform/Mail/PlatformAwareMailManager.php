<?php

namespace App\Ark\Platform\Mail;

use Illuminate\Mail\MailManager;
use RuntimeException;

/**
 * Every Laravel mailer name resolves to Platform. MAIL_MAILER cannot select a provider.
 */
final class PlatformAwareMailManager extends MailManager
{
    public function mailer($name = null)
    {
        return parent::mailer('ark-platform');
    }

    protected function createSmtpTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email directly.');
    }

    protected function createPostmarkTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email directly.');
    }

    protected function createSesTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email directly.');
    }

    protected function createMailgunTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email directly.');
    }

    protected function createResendTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email directly.');
    }

    protected function createSendmailTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email directly.');
    }

    protected function createMailTransport()
    {
        throw new RuntimeException('Core cannot deliver email directly.');
    }
}
