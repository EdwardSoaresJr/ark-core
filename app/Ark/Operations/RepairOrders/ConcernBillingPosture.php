<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;

enum ConcernBillingPosture: string
{
    case Default = 'default';
    case CustomerPay = 'customer_pay';
    /** @deprecated Legacy value — migrated to {@see self::WarrantyOther}. */
    case Warranty = 'warranty';
    /** @deprecated Legacy value — migrated to {@see self::RepairPal}. */
    case WarrantyRepairPal = 'warranty_repairpal';
    case WarrantyOther = 'warranty_other';
    case Fleet = 'fleet';
    case RepairPal = 'repairpal';
    case Internal = 'internal';
    case Wholesale = 'wholesale';
    case Comeback = 'comeback';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Shop default',
            self::CustomerPay => 'Customer pay',
            self::Warranty, self::WarrantyOther => 'Warranty',
            self::WarrantyRepairPal, self::RepairPal => 'RepairPal',
            self::Fleet => 'Fleet',
            self::Internal => 'Internal',
            self::Wholesale => 'Wholesale',
            self::Comeback => 'Comeback',
        };
    }

    public function shortLabel(): string
    {
        return $this->label();
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Default => 'Standard shop fees and parts matrix for this scope.',
            self::CustomerPay => 'Customer pays; shop fees apply on eligible lines at the shop rate.',
            self::Warranty, self::WarrantyOther => 'No shop or hazmat fees; warranty parts matrix; standard warranty labor rate.',
            self::WarrantyRepairPal, self::RepairPal => 'RepairPal program labor rate and parts profile; no shop fees.',
            self::Fleet => 'Fleet fee rate and parts matrix from shop settings.',
            self::Internal => 'Internal shop vehicle; no customer shop fees.',
            self::Wholesale => 'Wholesale parts profile and fee rules from shop settings.',
            self::Comeback => 'Comeback rework at $0 labor; enter part cost for tracking and set sell manually — often $0.',
        };
    }

    public function isWarranty(): bool
    {
        return in_array($this, [self::Warranty, self::WarrantyOther], true);
    }

    public function isProgramBilling(): bool
    {
        return in_array($this, [self::RepairPal, self::WarrantyRepairPal], true);
    }

    public function isInternalTracking(): bool
    {
        return in_array($this, [self::Internal, self::Comeback], true);
    }

    /**
     * Comeback and warranty scopes need independent cost vs sell control — often $0 customer sell with shop cost tracked.
     */
    public function prefersManualPartPricing(): bool
    {
        return $this->isInternalTracking() || $this->isWarranty();
    }

    /**
     * @return list<self>
     */
    public static function advisorSelectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $posture): bool => ! in_array($posture, [self::Warranty, self::WarrantyRepairPal], true),
        ));
    }

    public static function advisorHelpOverview(): string
    {
        return collect(self::advisorHelpOverviewItems())
            ->map(fn (array $item): string => $item['label'].' — '.$item['detail'])
            ->implode("\n");
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function advisorHelpOverviewItems(): array
    {
        return collect(self::advisorSelectableCases())
            ->map(fn (self $posture): array => [
                'label' => $posture->label(),
                'detail' => $posture->helpText(),
            ])
            ->all();
    }

    public static function defaultForCustomer(?Customer $customer): self
    {
        if ($customer === null) {
            return self::Default;
        }

        return self::defaultForCustomerTag($customer->customer_type);
    }

    public static function defaultForCustomerTag(?string $customerTag): self
    {
        return match (mb_strtolower(trim((string) $customerTag))) {
            'fleet' => self::Fleet,
            'warranty' => self::WarrantyOther,
            'repairpal' => self::RepairPal,
            'internal' => self::Internal,
            'wholesale' => self::Wholesale,
            'comeback' => self::Comeback,
            default => self::Default,
        };
    }

    public static function fromStored(?string $value): self
    {
        if ($value === self::WarrantyRepairPal->value) {
            return self::RepairPal;
        }

        if ($value === self::Warranty->value) {
            return self::WarrantyOther;
        }

        return self::tryFrom((string) $value) ?? self::Default;
    }

    public static function fromLegacyShopFeePolicy(string $value): self
    {
        return match ($value) {
            'inherit' => self::Default,
            'apply' => self::CustomerPay,
            'waive' => self::WarrantyOther,
            default => self::fromStored($value),
        };
    }

    public function billingClassName(): ?string
    {
        return match ($this) {
            self::Fleet => 'Fleet',
            self::Warranty, self::WarrantyOther => 'Warranty',
            self::RepairPal, self::WarrantyRepairPal => 'RepairPal',
            self::Internal => 'Internal',
            self::Wholesale => 'Wholesale',
            self::Comeback => 'Comeback',
            default => null,
        };
    }

    /**
     * @return array{enabled: bool, rate: string|null}
     */
    public function shopFeePolicy(ShopSettings $settings): array
    {
        $billingClass = $this->billingClassName();

        if ($billingClass !== null) {
            return $settings->shopFeePolicyForCustomerType($billingClass);
        }

        return match ($this) {
            self::Default, self::CustomerPay => $settings->shopFeePolicyForBillingDefault(),
            default => $settings->shopFeePolicyForBillingDefault(),
        };
    }

    public function acceptsStandingDiscount(): bool
    {
        return ! $this->isWarranty()
            && ! $this->isProgramBilling()
            && ! $this->isInternalTracking();
    }

    public function defaultPartsMatrix(ShopSettings $settings): array
    {
        $billingClass = $this->billingClassName();

        if ($billingClass !== null) {
            return $settings->partsMatrixForCustomerTypeProfile($billingClass)
                ?? $settings->defaultPartsMatrix();
        }

        return $settings->defaultPartsMatrix();
    }
}
