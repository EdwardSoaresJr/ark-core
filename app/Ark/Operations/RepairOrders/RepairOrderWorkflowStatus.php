<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use Stringable;

/** Catalog-backed workflow slug — system enum cases and shop custom statuses. */
final class RepairOrderWorkflowStatus implements Stringable
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $this->value = self::normalizeSlug($value);
    }

    public static function from(mixed $status): self
    {
        if ($status instanceof self) {
            return $status;
        }

        if ($status instanceof RepairOrderStatus) {
            return new self($status->value);
        }

        return new self((string) $status);
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        return match ($slug) {
            'awaiting_approval' => RepairOrderStatus::WaitingApproval->value,
            default => $slug,
        };
    }

    public function enum(): ?RepairOrderStatus
    {
        return RepairOrderStatus::tryFrom($this->value);
    }

    public function is(self|RepairOrderStatus|string $status): bool
    {
        $slug = match (true) {
            $status instanceof self => $status->value,
            $status instanceof RepairOrderStatus => $status->value,
            default => self::normalizeSlug((string) $status),
        };

        return $this->value === $slug;
    }

    public function isOneOf(array $statuses): bool
    {
        foreach ($statuses as $status) {
            if ($this->is($status)) {
                return true;
            }
        }

        return false;
    }

    public function isNoneOf(array $statuses): bool
    {
        return ! $this->isOneOf($statuses);
    }

    public function label(): string
    {
        return app(RepairOrderStatusCatalog::class)->labelForSlug($this->value);
    }

    public function isTerminal(): bool
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            return $catalog->isTerminalSlug($this->value);
        }

        return $this->is(RepairOrderStatus::Closed);
    }

    public function isIntake(): bool
    {
        return $this->isOneOf([RepairOrderStatus::Draft, RepairOrderStatus::Estimate]);
    }

    public static function isWaitingCustomerApproval(self|RepairOrderStatus|string $status): bool
    {
        return self::from($status)->is(RepairOrderStatus::WaitingApproval);
    }

    public function allowedOperationalTransitionSlugs(?\App\Models\User $actor = null): array
    {
        return app(RepairOrderStatusCatalog::class)->allowedTargetSlugs($this->value, $actor);
    }

    public function indexTone(): string
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            return $catalog->chipToneForSlug($this->value);
        }

        $enum = $this->enum();

        if ($enum !== null) {
            return $enum->indexTone();
        }

        return 'move';
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
