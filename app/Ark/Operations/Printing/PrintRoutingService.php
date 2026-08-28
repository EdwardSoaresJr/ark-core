<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

final class PrintRoutingService
{
    public const DOC_KEY_TAG = 'key_tag';

    public const DOC_KEY_TAG_TEST = 'print_test_key_tag';

    public const DOC_OIL_CHANGE_STICKER = 'oil_change_sticker';

    public const DOC_OIL_CHANGE_STICKER_TEST = 'print_test_oil_change_sticker';

    public const DOC_PARTS_LABEL = 'parts_label';

    /** @return list<string> */
    public static function knownDocumentTypes(): array
    {
        return [
            self::DOC_KEY_TAG,
            self::DOC_KEY_TAG_TEST,
            self::DOC_OIL_CHANGE_STICKER,
            self::DOC_OIL_CHANGE_STICKER_TEST,
            self::DOC_PARTS_LABEL,
        ];
    }

    public static function isKnownDocumentType(string $documentType): bool
    {
        return in_array($documentType, self::knownDocumentTypes(), true);
    }

    public static function resolvePrinterName(string $documentType): string
    {
        return match ($documentType) {
            self::DOC_KEY_TAG, self::DOC_KEY_TAG_TEST, self::DOC_PARTS_LABEL => ShopPrintingSettings::keyTagPrinter(),
            self::DOC_OIL_CHANGE_STICKER, self::DOC_OIL_CHANGE_STICKER_TEST => ShopPrintingSettings::oilStickerPrinter(),
            default => ShopPrintingSettings::keyTagPrinter(),
        };
    }
}
