<?php

namespace App\Ark\Operations\Payments;

/**
 * Presence of the optional ark/payments-square adapter (not merely square/square).
 * Core must not require square/square — this is the opt-in gate.
 */
final class SquareSdk
{
    /**
     * True only when the optional Composer package (and thus Square PHP SDK) is present.
     */
    public static function adapterPackagePresent(): bool
    {
        return class_exists(SquareApiPaymentsClient::class);
    }

    /**
     * Feature gate for “Square capture is available.”
     * In PHPUnit/Pest, FakeSquarePaymentsClient covers capture without installing OSL into the lockfile.
     */
    public static function installed(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        return self::adapterPackagePresent();
    }

    public static function adapterPackageHint(): string
    {
        return 'composer require ark/payments-square';
    }
}
