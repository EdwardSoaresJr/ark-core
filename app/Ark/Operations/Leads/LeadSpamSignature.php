<?php

namespace App\Ark\Operations\Leads;

/**
 * A website message the shop already marked spam.
 * Later submissions match the same text, or the same link inside it.
 */
final class LeadSpamSignature
{
    private const BODY_MIN_LENGTH = 24;

    private const LOOKBACK = 400;

    public function matches(string $concern): bool
    {
        $incoming = $this->tokens($concern);
        if ($incoming['body'] === null && $incoming['urls'] === []) {
            return false;
        }

        $known = Lead::query()
            ->spam()
            ->whereNotNull('concern')
            ->orderByDesc('id')
            ->limit(self::LOOKBACK)
            ->pluck('concern');

        foreach ($known as $prior) {
            $tokens = $this->tokens((string) $prior);
            if ($incoming['body'] !== null && $incoming['body'] === $tokens['body']) {
                return true;
            }

            if (array_intersect($incoming['urls'], $tokens['urls']) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{body: ?string, urls: list<string>}
     */
    private function tokens(string $concern): array
    {
        $body = $this->normalizeBody($concern);

        return [
            'body' => mb_strlen($body) >= self::BODY_MIN_LENGTH ? $body : null,
            'urls' => $this->urls($concern),
        ];
    }

    private function normalizeBody(string $concern): string
    {
        $text = mb_strtolower(trim($concern));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function urls(string $concern): array
    {
        $found = [];

        if (preg_match_all('~https?://[^\s<>"\']+~iu', $concern, $matches)) {
            foreach ($matches[0] as $raw) {
                $normalized = $this->normalizeUrl((string) $raw);
                if ($normalized !== null) {
                    $found[] = $normalized;
                }
            }
        }

        if (preg_match_all('~\b(?:[a-z0-9-]+\.)+[a-z]{2,}/[^\s<>"\']+~iu', $concern, $matches)) {
            foreach ($matches[0] as $raw) {
                $normalized = $this->normalizeUrl('https://'.ltrim((string) $raw, '/'));
                if ($normalized !== null) {
                    $found[] = $normalized;
                }
            }
        }

        return array_values(array_unique($found));
    }

    private function normalizeUrl(string $raw): ?string
    {
        $raw = rtrim($raw, '.,);]');
        $parts = parse_url($raw);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $shopHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($shopHost) && strtolower($shopHost) === $host) {
            return null;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if ($path === '') {
            return null;
        }

        return $host.$path;
    }
}
