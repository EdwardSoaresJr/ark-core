<?php

namespace App\Ark\Platform\Mail;

use Illuminate\Mail\MailManager;
use RuntimeException;

/**
 * Customer delivery stays off Laravel Mail (ArkMailClient / ARK Shop / ARK_SAAS_MAIL_*).
 *
 * Staff framework mail (password reset, email verification) uses the process MAIL_*
 * configuration (log, array, smtp, sendmail). On hosted SaaS that is the shared
 * node platform SMTP — not a per-tenant credential store.
 */
final class PlatformAwareMailManager extends MailManager
{
    /** @var list<string> */
    private const STAFF_MAILERS = ['log', 'array', 'smtp', 'sendmail'];

    public function mailer($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        if (in_array($name, self::STAFF_MAILERS, true)) {
            return parent::mailer($name);
        }

        // Named provider mailers (postmark, ses, …) cannot be selected.
        return parent::mailer('ark-platform');
    }

    protected function createPostmarkTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email through a provider SDK.');
    }

    protected function createSesTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email through a provider SDK.');
    }

    protected function createMailgunTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email through a provider SDK.');
    }

    protected function createResendTransport(array $config)
    {
        throw new RuntimeException('Core cannot deliver email through a provider SDK.');
    }
}
