<?php

namespace App\Ark\Operations\RepairOrders;

final readonly class RepairOrderWorkspaceStripPrimaryAction
{
    public function __construct(
        public string $key,
        public ?string $label,
        public ?string $href,
        public bool $disabled,
        public ?string $title,
        public bool $opensInNewTab = false,
    ) {}

    public static function none(): self
    {
        return new self(
            key: 'none',
            label: null,
            href: null,
            disabled: false,
            title: null,
        );
    }
}
