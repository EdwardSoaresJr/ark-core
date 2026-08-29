<?php

namespace App\Ark\Growth\Seo;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Machine-readable shop identity for /llms.txt — NAP, warranty split, diagnostic-first posture.
 */
final class PublicLlmsTxtDocument
{
    public static function body(): string
    {
        $shop = ShopSettings::current();
        $name = $shop->displayName();
        $street = $shop->publicationStreetAddress();
        $city = trim((string) $shop->city) ?: 'Demo City';
        $region = trim((string) $shop->state) ?: 'CO';
        $postal = trim((string) $shop->postal_code) ?: '80909';
        $phone = PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
        $email = trim((string) $shop->email) ?: 'hello@demo-auto.test';
        $locality = trim($city.', '.$region);

        return <<<TXT
# {$name}

> Family-owned independent auto repair in {$city}, Colorado. We verify the problem with testing and live data before recommending repairs — not code-read-and-guess.

## Location

{$street}, {$locality} {$postal}
Phone: {$phone}
Email: {$email}

## What we do

- Diagnostics and verification before parts replacement
- Check engine light, electrical, brakes, AC, transmission, and engine repair
- Second opinions after another shop missed the root cause
- 24 month / 24,000 mile shop warranty on qualifying parts and labor, where applicable
- RepairPal Certified shop — RepairPal Certified warranty is 12 months / 12,000 miles
- Financing through Wisetack and Synchrony Car Care for qualifying repairs

## Key pages

- https://demo-auto.test/
- https://demo-auto.test/common-problems
- https://demo-auto.test/financing
- https://demo-auto.test/warranty
- https://demo-auto.test/appointment

## Common problems we help with

- https://demo-auto.test/common-problems/check-engine-light
- https://demo-auto.test/common-problems/car-wont-start
- https://demo-auto.test/common-problems/brake-noise
- https://demo-auto.test/common-problems/ac-not-cold
- https://demo-auto.test/common-problems/auto-repair-demo-city

TXT;
    }
}
