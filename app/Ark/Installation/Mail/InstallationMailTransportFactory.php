<?php

namespace App\Ark\Installation\Mail;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\SendmailTransport;

/**
 * Builds Symfony transports with explicit TLS policy for Symfony Mailer 8+.
 *
 * Note: DSN option `encryption=tls` is ignored by EsmtpTransportFactory in Symfony 8.
 * STARTTLS is enforced via setRequireTls(true). Certificate verification stays on.
 */
class InstallationMailTransportFactory
{
    public function __construct(
        private readonly InstallationMailHostGuard $hostGuard = new InstallationMailHostGuard,
    ) {}

    /**
     * @param  array{
     *     mailer: string,
     *     host: ?string,
     *     port: ?int,
     *     encryption: ?string,
     *     username: ?string,
     *     password: ?string,
     * }  $config
     */
    public function make(array $config): TransportInterface
    {
        if (($config['mailer'] ?? '') === 'sendmail') {
            $path = (string) config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i');

            return new SendmailTransport($path);
        }

        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 0);
        $encryption = $config['encryption'] ?? null;

        $this->hostGuard->assertSafe($host, $port);
        // Re-resolve immediately before connect to reduce DNS-rebinding windows.
        $this->hostGuard->assertSafe($host, $port);

        $implicitTls = $encryption === 'ssl';
        $transport = new EsmtpTransport($host, $port, $implicitTls);

        if ($encryption === 'tls') {
            // Require STARTTLS; refuse plaintext if the server omits it.
            $transport->setAutoTls(true);
            $transport->setRequireTls(true);
        } elseif ($encryption === null) {
            $transport->setAutoTls(false);
            $transport->setRequireTls(false);
        } else {
            // ssl / smtps: TLS from connect; still verify peer certificates.
            $transport->setAutoTls(false);
            $transport->setRequireTls(true);
        }

        /** @var SocketStream $stream */
        $stream = $transport->getStream();
        $options = $stream->getStreamOptions();
        $options['ssl']['verify_peer'] = true;
        $options['ssl']['verify_peer_name'] = true;
        $options['ssl']['allow_self_signed'] = false;
        $stream->setStreamOptions($options);

        if (filled($config['username'] ?? null)) {
            $transport->setUsername((string) $config['username']);
        }
        if (filled($config['password'] ?? null)) {
            $transport->setPassword((string) $config['password']);
        }

        return $transport;
    }
}
