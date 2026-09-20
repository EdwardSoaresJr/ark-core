<?php

namespace App\Ark\Operations\Parts;

enum PartsCatalogProvider: string
{
    case PartsTech = 'partstech';
    case RepairLink = 'repairlink';
    case Nexpart = 'nexpart';

    public function label(): string
    {
        return match ($this) {
            self::PartsTech => 'PartsTech',
            self::RepairLink => 'RepairLink',
            self::Nexpart => 'Nexpart',
        };
    }

    public function openLabel(): string
    {
        return 'Open '.$this->label();
    }

    public function cartLabel(): ?string
    {
        return match ($this) {
            self::PartsTech => 'Pull Cart',
            self::Nexpart => null,
            self::RepairLink => null,
        };
    }
}
