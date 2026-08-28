<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\Request;

/**
 * Who may see a note line. Advisor worksheet always lists notes for management;
 * Technician = tech sheet; Customer = estimate PDF / portal.
 */
final class NoteAudience
{
    public function __construct(
        public readonly bool $advisor,
        public readonly bool $technician,
        public readonly bool $customer,
    ) {}

    public static function none(): self
    {
        return new self(false, false, false);
    }

    public static function defaultsFromShop(): self
    {
        return new self(
            advisor: true,
            technician: false,
            customer: ! (bool) ShopSettings::current()->default_notes_private,
        );
    }

    public static function fromLegacyPrivate(bool $isPrivate): self
    {
        return new self(
            advisor: true,
            technician: true,
            customer: ! $isPrivate,
        );
    }

    public static function fromRequest(Request $request, RepairOrderLineType $type): self
    {
        if (! $type->isNote()) {
            return self::none();
        }

        if (
            $request->exists('visible_to_advisor')
            || $request->exists('visible_to_technician')
            || $request->exists('visible_to_customer')
        ) {
            $advisor = $request->boolean('visible_to_advisor');
            $technician = $request->boolean('visible_to_technician');
            $customer = $request->boolean('visible_to_customer');

            if (! $advisor && ! $technician && ! $customer) {
                $advisor = true;
            }

            return new self($advisor, $technician, $customer);
        }

        if ($request->exists('is_private')) {
            return self::fromLegacyPrivate($request->boolean('is_private'));
        }

        return self::defaultsFromShop();
    }

    public static function fromLine(RepairOrderLine $line): self
    {
        if (! $line->type->isNote()) {
            return self::none();
        }

        return new self(
            advisor: (bool) $line->visible_to_advisor,
            technician: (bool) $line->visible_to_technician,
            customer: (bool) $line->visible_to_customer,
        );
    }

    /**
     * @return array{
     *     visible_to_advisor: bool,
     *     visible_to_technician: bool,
     *     visible_to_customer: bool,
     *     is_private: bool
     * }
     */
    public function persistenceAttributes(): array
    {
        return [
            'visible_to_advisor' => $this->advisor,
            'visible_to_technician' => $this->technician,
            'visible_to_customer' => $this->customer,
            'is_private' => ! $this->customer,
        ];
    }
}
