<?php

namespace App\Ark\Website;

/**
 * Permanent redirects for old public URLs that have a real replacement.
 *
 * Paths that Foundry sent to the homepage are listed in HOMEPAGE_ONLY and
 * stay unanswered here. A missing URL is not sent to /.
 */
final class PublicLegacyRedirect
{
    /**
     * Foundry sent these to /. Core does not.
     *
     * @var array<string, string>
     */
    public const HOMEPAGE_ONLY = [
        '/about' => '/',
        '/about-us' => '/',
        '/services' => '/',
        '/from-the-bay' => '/',
        '/request-estimate' => '/',
        '/request-a-quote' => '/',
        '/get-estimate' => '/',
        '/quote' => '/',
        '/shop-albums' => '/',
    ];

    /**
     * @var array<string, string>
     */
    private const EXACT = [
        '/contact-us' => '/contact',
        '/blog' => '/common-problems',
        '/appointment' => '/book',
        '/appointments' => '/book',
        '/schedule' => '/book',
        '/auto-repair' => '/common-problems/auto-repair-colorado-springs',
        '/auto-repair/colorado' => '/common-problems/auto-repair-colorado-springs',
        '/auto-repair-colorado-springs' => '/common-problems/auto-repair-colorado-springs',
        '/brake-repair' => '/common-problems/brake-repair-colorado-springs',
        '/mechanic' => '/common-problems/mechanic-colorado-springs',
        '/mechanic-colorado-springs' => '/common-problems/mechanic-colorado-springs',
        '/car-diagnostics' => '/common-problems/car-diagnostics-colorado-springs',
        '/privacy-policy' => '/privacy',
        '/terms-of-service' => '/terms',
        '/verification-guides' => '/common-problems',
        '/symptoms' => '/common-problems',
        '/diagnostic-concerns' => '/common-problems',
        '/blog/why-your-car-shakes-when-the-check-engine-light-flashes' => '/common-problems/check-engine-light',
        '/blog/honda-ac-compressor-clutch-what-we-check-first' => '/common-problems/ac-not-cold',
        '/blog/what-transmission-slip-feels-like-vs-engine-misfire' => '/common-problems/transmission-slipping',
        '/blog/hyundai-theta-ii-engine-issues-what-we-check-first' => '/common-problems/check-engine-light',
        '/blog/hyundai-dual-clutch-shudder-on-dct-models-what-we-check-first' => '/common-problems/transmission-slipping',
        '/blog/toyota-hybrid-inverter-cooling-what-we-check-first' => '/common-problems/engine-overheating',
        '/blog/flashing-check-engine-light-what-it-means' => '/common-problems/check-engine-light',
        '/blog/steady-check-engine-light-vs-flashing' => '/common-problems/check-engine-light',
        '/blog/brake-pad-wear-patterns-we-see-in-colorado-springs' => '/common-problems/brake-noise',
        '/blog/coolant-loss-without-a-visible-leak' => '/common-problems/engine-overheating',
        '/blog/battery-drain-overnight-what-we-test-first' => '/common-problems/battery-keeps-dying',
        '/blog/no-crank-no-start-quick-checks' => '/common-problems/car-wont-start',
        '/blog/wheel-bearing-hum-vs-tire-noise' => '/common-problems/wheel-bearing-noise',
        '/blog/rough-idle-at-stoplights' => '/common-problems/rough-idle',
        '/blog/abs-light-on-after-battery-replacement' => '/common-problems/abs-light',
        '/blog/transmission-fluid-leak-color-and-smell' => '/common-problems/transmission-slipping',
        '/blog/oil-leak-spots-on-the-driveway' => '/common-problems/oil-leak',
        '/tag/jeep' => '/common-problems/jeep-overheating',
        '/tag/death-wobble' => '/common-problems/jeep-death-wobble',
        '/tag/shake-55-75-mph' => '/common-problems/jeep-death-wobble',
        '/tag/steering-vibration' => '/common-problems/jeep-death-wobble',
    ];

    /**
     * @var array<string, string>
     */
    private const CONCERN_SLUGS = [
        'no-crank-no-start' => 'car-wont-start',
        'no-crank-wont-start' => 'car-wont-start',
        'flashing-check-engine-light' => 'check-engine-light',
        'steady-check-engine-light' => 'check-engine-light',
        'uneven-brake-pad-wear' => 'brake-noise',
        'coolant-loss' => 'engine-overheating',
        'oil-leaks' => 'oil-leak',
        'battery-drain' => 'battery-keeps-dying',
        'misfire-under-load' => 'misfire-under-load',
        'steering-vibration' => 'wheel-bearing-noise',
        'transmission-fluid-leak' => 'transmission-slipping',
        'electrical-diagnostics' => 'electrical-diagnostics',
        'electrical-system-diagnostics' => 'electrical-diagnostics',
        'fluid-services' => 'car-fluid-service',
        'car-fluid-service' => 'car-fluid-service',
        'audi-repair' => 'audi-repair-colorado-springs',
        'auto-repair' => 'auto-repair-colorado-springs',
        'auto-repair-colorado-springs' => 'auto-repair-colorado-springs',
        'mechanic' => 'mechanic-colorado-springs',
        'mechanic-colorado-springs' => 'mechanic-colorado-springs',
        'car-diagnostics' => 'car-diagnostics-colorado-springs',
        'car-diagnostics-colorado-springs' => 'car-diagnostics-colorado-springs',
        'brake-repair' => 'brake-repair-colorado-springs',
        'brake-repair-colorado-springs' => 'brake-repair-colorado-springs',
        'tune-up' => 'tune-up-colorado-springs',
        'tune-up-colorado-springs' => 'tune-up-colorado-springs',
        'no-start-service' => 'car-wont-start',
        'burnt-transmission-fluid' => 'burnt-transmission-fluid',
    ];

    /**
     * @var list<array{match: string, to: string}>
     */
    private const BLOG_PATTERNS = [
        ['match' => '#check-engine|misfire|flashes|theta-ii|steady-check#i', 'to' => '/common-problems/check-engine-light'],
        ['match' => '#ac-compressor|ac-not-cold|not-cold|compressor-clutch#i', 'to' => '/common-problems/ac-not-cold'],
        ['match' => '#transmission|clutch|dct|slip|burnt.transmission|transmission.fluid#i', 'to' => '/common-problems/transmission-slipping'],
        ['match' => '#subaru.*overheat|overheat.*subaru#i', 'to' => '/common-problems/subaru-overheating'],
        ['match' => '#jeep.*overheat|overheat.*jeep|wrangler.*cool#i', 'to' => '/common-problems/jeep-overheating'],
        ['match' => '#overheat|cooling|coolant|inverter#i', 'to' => '/common-problems/engine-overheating'],
        ['match' => '#brake|pad-wear#i', 'to' => '/common-problems/brake-noise'],
        ['match' => '#battery|no-crank|no-start|wont-start|crank#i', 'to' => '/common-problems/car-wont-start'],
        ['match' => '#suspension|control-arm|strut|shock|ball-joint|clunk-over-bump#i', 'to' => '/common-problems/suspension-noise'],
        ['match' => '#wheel-bearing|steering-vibration#i', 'to' => '/common-problems/wheel-bearing-noise'],
        ['match' => '#oil-leak|fluid-leak#i', 'to' => '/common-problems/oil-leak'],
        ['match' => '#abs-light|\babs\b#i', 'to' => '/common-problems/abs-light'],
        ['match' => '#death.wobble|shake.55|steering.vibration|track.bar#i', 'to' => '/common-problems/jeep-death-wobble'],
        ['match' => '#rough-idle|\bidle\b|misfire.under.load#i', 'to' => '/common-problems/rough-idle'],
        ['match' => '#audi.repair|audi.service|audi.parts|audi.oil#i', 'to' => '/common-problems/audi-repair-colorado-springs'],
        ['match' => '#auto.repair.colorado|mechanic.colorado|car.diagnostics|brake.repair.colorado|tune.up.colorado#i', 'to' => '/common-problems/auto-repair-colorado-springs'],
        ['match' => '#fluid.service|fluid.services|brake.fluid|transmission.fluid.change#i', 'to' => '/common-problems/car-fluid-service'],
        ['match' => '#electrical.diagnostics|parasitic.drain|battery.drain#i', 'to' => '/common-problems/electrical-diagnostics'],
        ['match' => '#p0171|system-too-lean|lean-condition#i', 'to' => '/common-problems/p0171'],
        ['match' => '#honda.*timing|timing.belt|timing-belt#i', 'to' => '/common-problems/honda-timing-belt'],
        ['match' => '#wheel.bearing|bearing.hum#i', 'to' => '/common-problems/wheel-bearing-noise'],
    ];

    public static function resolve(string $path, PublishedWebsite $website): ?string
    {
        $normalized = '/'.trim($path, '/');
        if ($normalized === '/') {
            return null;
        }

        if (isset(self::HOMEPAGE_ONLY[$normalized])) {
            return null;
        }

        if (isset(self::EXACT[$normalized])) {
            return self::accept($website, $normalized, self::EXACT[$normalized]);
        }

        $concern = self::concernTarget($normalized, $website);
        if ($concern !== null) {
            return $concern;
        }

        if (str_starts_with(ltrim($normalized, '/'), 'blog/')) {
            return self::blogTarget($normalized, $website);
        }

        $relative = ltrim($normalized, '/');
        if (! str_contains($relative, '/') && $website->problem($relative) !== null) {
            return self::accept($website, $normalized, '/common-problems/'.$relative);
        }

        return null;
    }

    private static function concernTarget(string $normalized, PublishedWebsite $website): ?string
    {
        $relative = ltrim($normalized, '/');
        $slug = null;
        if (str_starts_with($relative, 'common-problems/')) {
            $slug = substr($relative, strlen('common-problems/'));
            if ($slug === '' || str_contains($slug, '/')) {
                return null;
            }
        } elseif (! str_contains($relative, '/')) {
            $slug = $relative;
        }

        if ($slug === null || ! isset(self::CONCERN_SLUGS[$slug])) {
            return null;
        }

        return self::accept($website, $normalized, '/common-problems/'.self::CONCERN_SLUGS[$slug]);
    }

    private static function blogTarget(string $normalized, PublishedWebsite $website): ?string
    {
        $slug = substr(ltrim($normalized, '/'), strlen('blog/'));
        if ($slug === '') {
            return self::accept($website, $normalized, '/common-problems');
        }

        if (isset(self::CONCERN_SLUGS[$slug])) {
            $mapped = self::accept($website, $normalized, '/common-problems/'.self::CONCERN_SLUGS[$slug]);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        if ($website->problem($slug) !== null) {
            return self::accept($website, $normalized, '/common-problems/'.$slug);
        }

        foreach (self::BLOG_PATTERNS as $pattern) {
            if (preg_match($pattern['match'], $slug) === 1) {
                $matched = self::accept($website, $normalized, $pattern['to']);
                if ($matched !== null) {
                    return $matched;
                }
            }
        }

        return self::accept($website, $normalized, '/common-problems');
    }

    private static function accept(PublishedWebsite $website, string $from, string $target): ?string
    {
        if ($target === '/' || $target === $from) {
            return null;
        }

        if (preg_match('#^/common-problems/([a-z0-9-]+)$#', $target, $matches) === 1) {
            if ($website->problem($matches[1]) === null) {
                return null;
            }
        }

        return $target;
    }
}
