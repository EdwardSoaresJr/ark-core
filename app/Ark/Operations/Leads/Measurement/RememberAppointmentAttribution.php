<?php

namespace App\Ark\Operations\Leads\Measurement;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the ad click that landed on this public site.
 * Later empty requests do not erase a click id already stored.
 */
final class RememberAppointmentAttribution
{
    public const SESSION_KEY = 'ark.appointment_request_attribution';

    /**
     * @var list<string>
     */
    private const KEYS = [
        'gclid',
        'gbraid',
        'wbraid',
        'gad_source',
        'gad_campaignid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = self::fromQuery($request);
        if ($incoming !== []) {
            $existing = $request->session()->get(self::SESSION_KEY, []);
            if (! is_array($existing)) {
                $existing = [];
            }

            $request->session()->put(self::SESSION_KEY, array_merge(self::onlyAllowed($existing), $incoming));
        }

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    public static function current(Request $request): array
    {
        $stored = $request->session()->get(self::SESSION_KEY, []);

        return is_array($stored) ? self::onlyAllowed($stored) : [];
    }

    /**
     * @return array<string, string>
     */
    private static function fromQuery(Request $request): array
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $raw = $request->query($key);
            if (! is_string($raw)) {
                continue;
            }

            $clean = self::clean($raw);
            if ($clean !== null) {
                $values[$key] = $clean;
            }
        }

        return $values;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, string>
     */
    private static function onlyAllowed(array $values): array
    {
        $clean = [];
        foreach (self::KEYS as $key) {
            if (! isset($values[$key]) || ! is_string($values[$key])) {
                continue;
            }

            $value = self::clean($values[$key]);
            if ($value !== null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private static function clean(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9._~:+\/-]{1,200}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
