<?php

namespace App\Ark\Runtime\Broadcast;

final class ReverbDeployment
{
    public static function appUrl(): ?string
    {
        $url = config('app.url');

        return filled($url) ? rtrim((string) $url, '/') : null;
    }

    public static function appHost(): ?string
    {
        $host = self::appUrl() !== null ? parse_url(self::appUrl(), PHP_URL_HOST) : null;

        return filled($host) ? (string) $host : null;
    }

    /**
     * Public hostname browsers use for WebSocket connections.
     * Explicit REVERB_HOST overrides; otherwise derived from APP_URL.
     */
    public static function publicHost(): string
    {
        $explicit = env('REVERB_HOST');

        if (filled($explicit)) {
            return (string) $explicit;
        }

        return self::appHost() ?? 'localhost';
    }

    public static function publicHostSource(): string
    {
        if (filled(env('REVERB_HOST'))) {
            return 'REVERB_HOST';
        }

        if (self::appHost() !== null) {
            return 'APP_URL';
        }

        return 'default';
    }

    public static function scheme(): string
    {
        $explicit = strtolower(trim((string) env('REVERB_SCHEME', '')));

        if (filled($explicit)) {
            return $explicit;
        }

        $appScheme = self::appUrl() !== null ? parse_url(self::appUrl(), PHP_URL_SCHEME) : null;

        if (filled($appScheme)) {
            return strtolower((string) $appScheme);
        }

        return 'http';
    }

    public static function port(): int
    {
        if (filled(env('REVERB_PORT'))) {
            return (int) env('REVERB_PORT');
        }

        return self::scheme() === 'https' ? 443 : 80;
    }

    /**
     * Internal host Laravel uses to publish events to the Reverb process.
     */
    public static function broadcastHost(): string
    {
        if (filled(env('REVERB_BROADCAST_HOST'))) {
            return (string) env('REVERB_BROADCAST_HOST');
        }

        return '127.0.0.1';
    }

    public static function broadcastPort(): int
    {
        if (filled(env('REVERB_BROADCAST_PORT'))) {
            return (int) env('REVERB_BROADCAST_PORT');
        }

        return (int) env('REVERB_SERVER_PORT', 8080);
    }

    public static function broadcastScheme(): string
    {
        if (filled(env('REVERB_BROADCAST_SCHEME'))) {
            return strtolower((string) env('REVERB_BROADCAST_SCHEME'));
        }

        return 'http';
    }

    public static function serverPort(): int
    {
        return (int) env('REVERB_SERVER_PORT', 8080);
    }

    public static function websocketUrl(?string $host = null): string
    {
        $host = $host ?? self::publicHost();
        $scheme = self::scheme();
        $port = self::port();
        $wsScheme = $scheme === 'https' ? 'wss' : 'ws';
        $defaultPort = $scheme === 'https' ? 443 : 80;

        if ($port === $defaultPort) {
            return "{$wsScheme}://{$host}";
        }

        return "{$wsScheme}://{$host}:{$port}";
    }

    public static function hostMismatchWarning(): ?string
    {
        $appHost = self::appHost();
        $explicitHost = env('REVERB_HOST');

        if ($appHost === null || ! filled($explicitHost)) {
            return null;
        }

        if (strcasecmp((string) $explicitHost, $appHost) === 0) {
            return null;
        }

        return 'REVERB_HOST ('.$explicitHost.') does not match APP_URL host ('.$appHost.'). Remove REVERB_HOST or align it with APP_URL.';
    }

    /**
     * @return array<string, mixed>
     */
    public static function diagnostics(): array
    {
        $diagnostics = [
            'app_url' => self::appUrl(),
            'reverb_host' => self::publicHost(),
            'reverb_host_source' => self::publicHostSource(),
            'reverb_scheme' => self::scheme(),
            'reverb_port' => self::port(),
            'websocket_url' => self::websocketUrl(),
            'broadcast_connection' => config('broadcasting.default'),
            'reverb_app_key_configured' => filled(config('broadcasting.connections.reverb.key')),
            'reverb_broadcast_host' => self::broadcastHost(),
            'reverb_broadcast_port' => self::broadcastPort(),
            'reverb_server_port' => self::serverPort(),
        ];

        $warning = self::hostMismatchWarning();

        if ($warning !== null) {
            $diagnostics['warning'] = $warning;
        }

        return $diagnostics;
    }
}
