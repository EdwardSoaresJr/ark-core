<?php

namespace App\Ark\Operations\Leads;

final class LeadContactNameParser
{
    /** Stored when the customer only gave a first name (website lead, SMS, etc.). */
    public const PLACEHOLDER_LAST_NAME = '—';

    /**
     * @return array{first_name: string, last_name: string}
     */
    public static function split(?string $contactName): array
    {
        $name = trim((string) $contactName);

        if ($name === '' || strcasecmp($name, 'unknown') === 0) {
            return [
                'first_name' => '',
                'last_name' => '',
            ];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [
            'first_name' => trim((string) ($parts[0] ?? '')),
            'last_name' => trim((string) ($parts[1] ?? '')),
        ];
    }

    public static function isPlaceholderLastName(?string $lastName): bool
    {
        return trim((string) $lastName) === self::PLACEHOLDER_LAST_NAME;
    }

    public static function normalizeLastName(?string $lastName): string
    {
        $trimmed = trim((string) $lastName);

        if ($trimmed === '' || self::isPlaceholderLastName($trimmed)) {
            return self::PLACEHOLDER_LAST_NAME;
        }

        return $trimmed;
    }

    public static function formatFullName(?string $firstName, ?string $lastName): string
    {
        $first = trim((string) $firstName);
        $last = trim((string) $lastName);

        if ($last === '' || self::isPlaceholderLastName($last)) {
            return $first;
        }

        return trim($first.' '.$last);
    }

    public static function contactNameFromParts(?string $firstName, ?string $lastName): string
    {
        return self::formatFullName($firstName, $lastName);
    }

    public static function allowsOptionalLastName(?Lead $lead = null): bool
    {
        if ($lead === null) {
            return false;
        }

        if ($lead->source === LeadSource::Website) {
            return true;
        }

        $parts = self::split($lead->contact_name);

        return $parts['first_name'] !== '' && $parts['last_name'] === '';
    }
}
