<?php

namespace App\Ark\Operations\Financial;

enum RepairOrderCollectionDisposition: string
{
    case Retail = 'retail';
    case Courtesy = 'courtesy';
    case Trade = 'trade';
    case Goodwill = 'goodwill';
    case BadDebt = 'bad_debt';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail',
            self::Courtesy => 'Courtesy',
            self::Trade => 'Trade',
            self::Goodwill => 'Goodwill',
            self::BadDebt => 'Bad debt',
        };
    }

    public function requiresReason(): bool
    {
        return $this !== self::Retail;
    }

    /**
     * Courtesy / trade / goodwill keep retail invoice history but must not inflate posted sales.
     */
    public function excludesFromPostedSales(): bool
    {
        return in_array($this, [self::Courtesy, self::Trade, self::Goodwill], true);
    }

    public function waiverCustomerLabel(): ?string
    {
        return match ($this) {
            self::Courtesy => 'Courtesy — balance waived',
            self::Trade => 'Trade — balance waived',
            self::Goodwill => 'Goodwill — balance waived',
            self::BadDebt, self::Retail => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function waiveOptions(): array
    {
        return [
            self::Courtesy,
            self::Trade,
            self::Goodwill,
            self::BadDebt,
        ];
    }

    public static function tryFromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return self::tryFrom($value) ?? self::Retail;
        }

        return self::Retail;
    }
}
