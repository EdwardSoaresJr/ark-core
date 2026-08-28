<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Customer\CustomerSurfaceFooterData;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Disposable Get in Touch hub projection — packages NAP, hours, maps, social, FAQs once per render.
 */
final class ContactPageProjection
{
    /**
     * @return array{
     *     shop_name: string,
     *     phone_display: string,
     *     phone_tel: string,
     *     sms_href: string,
     *     email: string|null,
     *     mailto_href: string|null,
     *     street_address: string,
     *     city_state: string,
     *     postal_code: string,
     *     address_multiline: string,
     *     business_hours_label: string|null,
     *     visit_notes: string|null,
     *     google_maps_url: string,
     *     google_maps_embed_url: string,
     *     book_href: string,
     *     message_href: string,
     *     request_service_href: string,
     *     social_links: list<array{key: string, label: string, description: string, href: string, brand: string}>,
     *     faqs: list<array{question: string, answer: string}>,
     *     reach_methods: list<array{key: string, label: string, description: string, href: string, external: bool}>
     * }
     */
    public static function forDisplay(): array
    {
        $shop = ShopSettings::current();
        $publicSurface = PublicSurfaceSettings::current();
        $footer = CustomerSurfaceFooterData::viewData();

        $shopName = $shop->displayName();
        $phoneDisplay = PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
        $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
        $email = self::publicEmail($shop->email ?? null);

        $street = (string) ($footer['street_address'] ?? '');
        $cityState = (string) ($footer['city_state'] ?? '');
        $postal = trim((string) ($shop->postal_code ?? ''));

        $addressLines = array_values(array_filter([
            $street !== '' ? $street : null,
            trim($cityState.($postal !== '' ? ' '.$postal : '')) ?: null,
        ]));

        $mapsUrl = (string) ($footer['google_maps_url'] ?? '');
        $embedUrl = self::mapsEmbedUrl($mapsUrl, $shopName, $street, $cityState, $postal);

        $bookHref = route('public.book');
        $messageHref = route('public.contact').'#send-a-message';

        $reachMethods = [
            [
                'key' => 'call',
                'label' => 'Call',
                'description' => 'Best during business hours.',
                'href' => 'tel:'.$phoneTel,
                'external' => false,
            ],
            [
                'key' => 'text',
                'label' => 'Text',
                'description' => 'Short questions, photos, or a quick note.',
                'href' => 'sms:'.$phoneTel,
                'external' => false,
            ],
            [
                'key' => 'book',
                'label' => 'Book an Appointment',
                'description' => 'Pick a day for service. We’ll confirm the time.',
                'href' => $bookHref,
                'external' => false,
            ],
        ];

        if ($email !== null) {
            $reachMethods[] = [
                'key' => 'email',
                'label' => 'Email',
                'description' => 'Write us when a call isn’t convenient.',
                'href' => 'mailto:'.$email,
                'external' => false,
            ];
        }

        $reachMethods[] = [
            'key' => 'directions',
            'label' => 'Get directions',
            'description' => 'Open us in Google Maps.',
            'href' => $mapsUrl,
            'external' => true,
        ];

        return [
            'shop_name' => $shopName,
            'phone_display' => $phoneDisplay,
            'phone_tel' => $phoneTel,
            'sms_href' => 'sms:'.$phoneTel,
            'email' => $email,
            'mailto_href' => $email !== null ? 'mailto:'.$email : null,
            'street_address' => $street,
            'city_state' => $cityState,
            'postal_code' => $postal,
            'address_multiline' => implode("\n", $addressLines),
            'business_hours_label' => $publicSurface['business_hours_label'] ?? null,
            'visit_notes' => filled($publicSurface['contact_visit_notes'] ?? null)
                ? (string) $publicSurface['contact_visit_notes']
                : null,
            'google_maps_url' => $mapsUrl,
            'google_maps_embed_url' => $embedUrl,
            'book_href' => $bookHref,
            'message_href' => $messageHref,
            'request_service_href' => $messageHref,
            'social_links' => ShopSocialProfiles::forDisplay($publicSurface),
            'faqs' => $publicSurface['contact_faqs'] ?? [],
            'reach_methods' => $reachMethods,
        ];
    }

    private static function publicEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = trim($value);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function mapsEmbedUrl(
        string $mapsUrl,
        string $shopName,
        string $street,
        string $cityState,
        string $postal,
    ): string {
        $query = trim(implode(' ', array_filter([
            $shopName,
            $street,
            $cityState,
            $postal,
        ])));

        if ($query === '' && $mapsUrl !== '') {
            return 'https://maps.google.com/maps?q='.urlencode($mapsUrl).'&z=15&output=embed';
        }

        return 'https://maps.google.com/maps?q='.urlencode($query !== '' ? $query : 'Colorado Springs, CO').'&z=15&output=embed';
    }
}
