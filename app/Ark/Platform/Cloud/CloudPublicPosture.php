<?php

namespace App\Ark\Platform\Cloud;

/**
 * Company-site posture: what the public Cloud funnel may offer right now.
 *
 * Self-host remains available. Hosted signup and public pricing stay closed until
 * the hosted offering is ready — interest routes to a configurable contact email.
 */
final class CloudPublicPosture
{
    public static function signupsOpen(): bool
    {
        return (bool) config('ark-cloud.public_signups', false);
    }

    public static function pricingPublic(): bool
    {
        return (bool) config('ark-cloud.public_pricing', false);
    }

    public static function interestEmail(): string
    {
        $email = trim((string) config('ark-cloud.interest_email', 'hello@autorepairkeeper.com'));

        return $email !== '' ? $email : 'hello@autorepairkeeper.com';
    }

    public static function interestMailto(string $subject = 'Hosted ARK interest'): string
    {
        return 'mailto:'.self::interestEmail().'?subject='.rawurlencode($subject);
    }

    public static function primaryCtaUrl(): string
    {
        return CloudUrls::login() ?? CloudUrls::dashboard() ?? '/';
    }

    public static function primaryCtaLabel(): string
    {
        return self::signupsOpen() ? 'Start Free Trial' : 'Sign in to ARK Complete';
    }
}
