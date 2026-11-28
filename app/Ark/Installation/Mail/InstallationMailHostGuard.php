<?php

namespace App\Ark\Installation\Mail;

/**
 * Blocks SMTP hosts that target cloud metadata or link-local admin endpoints.
 * Private LAN mail servers remain allowed for legitimate self-hosted SMTP.
 * Resolves DNS at check time so rebinding to metadata IPs is rejected.
 */
final class InstallationMailHostGuard
{
    /** @var list<int> */
    private const ALLOWED_PORTS = [25, 465, 587, 2525, 2465, 2587];

    public function assertSafe(string $host, int $port): void
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            throw new \InvalidArgumentException('SMTP hostname is required.');
        }

        if (str_contains($host, '/') || str_contains($host, '\\') || str_contains($host, ' ')) {
            throw new \InvalidArgumentException('SMTP hostname is invalid.');
        }

        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            throw new \InvalidArgumentException('SMTP port must be one of: '.implode(', ', self::ALLOWED_PORTS).'.');
        }

        if ($this->isBlockedHostname($host)) {
            throw new \InvalidArgumentException('That SMTP host is not allowed.');
        }

        $ips = $this->resolve($host);
        if ($ips === []) {
            throw new \InvalidArgumentException('SMTP hostname could not be resolved.');
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new \InvalidArgumentException('That SMTP host resolves to a blocked address.');
            }
        }
    }

    private function isBlockedHostname(string $host): bool
    {
        if (in_array($host, [
            'metadata.google.internal',
            'metadata.goog',
            'instance-data',
            'kubernetes.default',
            'kubernetes.default.svc',
        ], true)) {
            return true;
        }

        return str_ends_with($host, '.metadata.google.internal');
    }

    private function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Cloud metadata / link-local admin (AWS, GCP, Azure IMDS, etc.)
        if (str_starts_with($ip, '169.254.')) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalized = strtolower($ip);
            if ($normalized === '::1' || str_starts_with($normalized, 'fe80:')) {
                return true;
            }
            if (preg_match('/^::ffff:169\.254\./', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            $fallback = @gethostbynamel($host) ?: [];
            $ips = array_values(array_filter($fallback, 'is_string'));
        }

        return array_values(array_unique($ips));
    }
}
