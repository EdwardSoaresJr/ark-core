<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Customer\CustomerSurfaceUrls;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;

final class ReviewRequestCopy
{
    /**
     * Shop Google review destination (Core shop setting).
     */
    public static function reviewUrl(): string
    {
        return trim((string) (ShopSettings::current()->google_reviews_url ?? ''));
    }

    public static function shopName(): string
    {
        return ShopSettings::current()->shop_name ?: (string) config('app.name', 'ARK-SMS');
    }

    /**
     * Equal Contact Us path for every customer — never a sentiment branch.
     *
     * @see docs/communications/review-request-no-gating-v1.md
     */
    public static function contactUrl(): string
    {
        return CustomerSurfaceUrls::portalAccess();
    }

    public static function shopPhoneDisplay(): ?string
    {
        $raw = ShopSettings::current()->telephony_inbound_number;

        if (! filled($raw)) {
            return null;
        }

        return PhoneNumber::display((string) $raw) ?? trim((string) $raw);
    }

    public static function smsBody(?string $reviewUrl = null, ?string $shopName = null): string
    {
        $shop = $shopName ?? self::shopName();
        $url = $reviewUrl ?? self::reviewUrl();
        $contactUrl = self::contactUrl();
        $phone = self::shopPhoneDisplay();

        $lines = [
            "Thank you for choosing {$shop}!",
            '',
            'We appreciate the opportunity to service your vehicle.',
            '',
            "If you were happy with your experience, we'd be grateful if you could take a moment to share it with others by leaving us a Google review.",
            '',
            'Leave a Google Review:',
            $url,
            '',
            "Questions about your repair? We're always happy to help.",
            '',
            $contactUrl,
        ];

        if (filled($phone)) {
            $lines[] = $phone;
        }

        return implode("\n", $lines);
    }

    public static function emailSubject(?string $shopName = null): string
    {
        $shop = $shopName ?? self::shopName();

        return "Thank You for Choosing {$shop}";
    }

    /**
     * Read-only advisor preview of the email the customer receives.
     */
    public static function emailPreviewBody(
        ?string $reviewUrl = null,
        ?string $shopName = null,
        ?string $vehicleLabel = null,
        ?string $contactUrl = null,
    ): string {
        $shop = $shopName ?? self::shopName();
        $url = $reviewUrl ?? self::reviewUrl();
        $contact = $contactUrl ?? self::contactUrl();
        $phone = self::shopPhoneDisplay();

        $body = "Thank you for trusting {$shop} with your vehicle.\n\n"
            ."We truly appreciate the opportunity to earn your business.\n\n"
            ."If you were happy with your visit, we'd be grateful if you could take a moment to share your experience by leaving us a Google review. Your feedback helps other drivers find a repair shop they can trust.\n\n"
            ."Leave a Google Review\n"
            ."{$url}\n\n"
            ."If you have any questions or concerns about your repair, please don't hesitate to reach out. We're always happy to help.\n\n"
            ."{$contact}";

        if (filled($phone)) {
            $body .= "\n\n{$phone}";
        }

        $body .= "\n\nThank you again,\n\nThe {$shop} Team";

        return $body;
    }
}
