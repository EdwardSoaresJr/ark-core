<?php

namespace App\Ark\Operations\Documents;

enum DocumentSource: string
{
    case Upload = 'upload';
    case Scan = 'scan';
    case Generated = 'generated';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Uploaded',
            self::Scan => 'Scanned',
            self::Generated => 'Generated',
        };
    }
}
