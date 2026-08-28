<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopCustomerHoursPresentation;
use App\Ark\Operations\Settings\ShopSettings;
use RuntimeException;

/**
 * Shop-configured extras for Message Actions (tow / wifi / after-hours pickup).
 * Configuration — not authority.
 *
 * @phpstan-type MessageActionsConfig array{
 *     tow_company?: ?string,
 *     tow_phone?: ?string,
 *     tow_notes?: ?string,
 *     wifi_ssid?: ?string,
 *     wifi_password?: ?string,
 *     after_hours_pickup?: ?string
 * }
 */
final class MessageActionsSettings
{
    /**
     * @return MessageActionsConfig
     */
    public static function current(?ShopSettings $shop = null): array
    {
        $shop ??= ShopSettings::current();
        $raw = $shop->message_actions;

        return is_array($raw) ? $raw : [];
    }

    public static function canSend(MessageActionKey $action, ?ShopSettings $shop = null): bool
    {
        $shop ??= ShopSettings::current();

        return match ($action) {
            MessageActionKey::Address => filled(trim((string) ($shop->address_line_1 ?? '')))
                || filled(trim((string) ($shop->address_line_2 ?? ''))),
            MessageActionKey::Hours => filled(ShopCustomerHoursPresentation::summaryForShop($shop)),
            MessageActionKey::Pickup => self::canSend(MessageActionKey::Address, $shop),
            MessageActionKey::Tow => filled(trim((string) (self::current($shop)['tow_phone'] ?? ''))),
            MessageActionKey::Wifi => filled(trim((string) (self::current($shop)['wifi_ssid'] ?? ''))),
            default => false,
        };
    }

    public static function unavailableReason(MessageActionKey $action, ?ShopSettings $shop = null): string
    {
        return match ($action) {
            MessageActionKey::Address, MessageActionKey::Pickup => 'Shop address is not configured in Settings.',
            MessageActionKey::Hours => 'Shop hours are not configured in Settings.',
            MessageActionKey::Tow => 'Tow info is not configured in Settings → Communications.',
            MessageActionKey::Wifi => 'Wi-Fi is not configured in Settings → Communications.',
            default => 'This message action is not available.',
        };
    }

    public static function body(MessageActionKey $action, ?ShopSettings $shop = null): string
    {
        $shop ??= ShopSettings::current();

        if (! self::canSend($action, $shop)) {
            throw new RuntimeException(self::unavailableReason($action, $shop));
        }

        return match ($action) {
            MessageActionKey::Address => ShopAddressSmsCopy::body($shop),
            MessageActionKey::Hours => self::hoursBody($shop),
            MessageActionKey::Pickup => self::pickupBody($shop),
            MessageActionKey::Tow => self::towBody($shop),
            MessageActionKey::Wifi => self::wifiBody($shop),
            default => throw new RuntimeException('This message action cannot be sent manually.'),
        };
    }

    private static function hoursBody(ShopSettings $shop): string
    {
        $name = trim((string) ($shop->shop_name ?? '')) ?: 'Our shop';
        $hours = ShopCustomerHoursPresentation::summaryForShop($shop);
        $phone = PhoneNumber::display($shop->phone) ?: null;

        return implode("\n", array_filter([
            $name.' hours:',
            $hours,
            $phone !== null ? 'Call: '.$phone : null,
        ]));
    }

    private static function pickupBody(ShopSettings $shop): string
    {
        $config = self::current($shop);
        $afterHours = trim((string) ($config['after_hours_pickup'] ?? ''));
        $hours = ShopCustomerHoursPresentation::summaryForShop($shop);
        $phone = PhoneNumber::display($shop->phone) ?: null;

        return implode("\n\n", array_filter([
            ShopAddressSmsCopy::body($shop),
            $hours !== null ? "Hours:\n{$hours}" : null,
            $phone !== null ? "Questions? Call {$phone}" : null,
            $afterHours !== '' ? "After-hours pickup:\n{$afterHours}" : null,
        ]));
    }

    private static function towBody(ShopSettings $shop): string
    {
        $config = self::current($shop);
        $company = trim((string) ($config['tow_company'] ?? '')) ?: 'Tow';
        $phone = PhoneNumber::display($config['tow_phone'] ?? null) ?: trim((string) ($config['tow_phone'] ?? ''));
        $notes = trim((string) ($config['tow_notes'] ?? ''));
        $shopPhone = PhoneNumber::display($shop->phone) ?: null;

        return implode("\n\n", array_filter([
            ShopAddressSmsCopy::body($shop),
            "Tow:\n{$company}\n{$phone}",
            $shopPhone !== null ? "Shop phone: {$shopPhone}" : null,
            $notes !== '' ? $notes : null,
        ]));
    }

    private static function wifiBody(ShopSettings $shop): string
    {
        $config = self::current($shop);
        $ssid = trim((string) ($config['wifi_ssid'] ?? ''));
        $password = trim((string) ($config['wifi_password'] ?? ''));
        $name = trim((string) ($shop->shop_name ?? '')) ?: 'Our shop';

        return implode("\n", array_filter([
            $name.' waiting room Wi-Fi:',
            'Network: '.$ssid,
            $password !== '' ? 'Password: '.$password : null,
        ]));
    }
}
