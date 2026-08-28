<?php

namespace App\Ark\Operations\Documents;

enum DocumentEventType: string
{
    case Uploaded = 'uploaded';
    case Scanned = 'scanned';
    case Generated = 'generated';
    case Presented = 'presented';
    case Emailed = 'emailed';
    case Attached = 'attached';
    case Shared = 'shared';
    case Unshared = 'unshared';
    case Rotated = 'rotated';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Scanned => 'Scanned',
            self::Generated => 'Generated',
            self::Presented => 'Presented',
            self::Emailed => 'Emailed',
            self::Attached => 'Attached',
            self::Shared => 'Shared',
            self::Unshared => 'Unshared',
            self::Rotated => 'Rotated',
            self::Retired => 'Retired',
        };
    }
}
