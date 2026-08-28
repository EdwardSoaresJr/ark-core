<?php

namespace App\Ark\Operations\RepairOrders;

final readonly class RepairOrderFooterAction
{
    public function __construct(
        public string $key,
        public ?string $label,
        public ?string $href,
        public bool $disabled,
        public ?string $title,
        public bool $opensInNewTab = false,
        public bool $opensModal = false,
        public ?string $modalTask = null,
        public bool $isPrint = false,
        public ?string $printDocument = null,
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

    public static function modal(string $key, string $label, ?string $title = null, string $modalTask = 'add-work'): self
    {
        return new self(
            key: $key,
            label: $label,
            href: null,
            disabled: false,
            title: $title,
            opensModal: true,
            modalTask: $modalTask,
        );
    }

    public static function link(
        string $key,
        string $label,
        string $href,
        bool $opensInNewTab = false,
        ?string $title = null,
        bool $isPrint = false,
        ?string $printDocument = null,
    ): self {
        return new self(
            key: $key,
            label: $label,
            href: $href,
            disabled: false,
            title: $title,
            opensInNewTab: $opensInNewTab,
            isPrint: $isPrint,
            printDocument: $printDocument,
        );
    }
}
