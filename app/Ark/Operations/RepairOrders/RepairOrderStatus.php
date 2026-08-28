<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;

enum RepairOrderStatus: string
{
    case Draft = 'draft';
    case Estimate = 'estimate';
    case WaitingApproval = 'waiting_approval';
    case Approved = 'approved';
    case WaitingParts = 'waiting_parts';
    case ReadyForWork = 'ready_for_work';
    case InProgress = 'in_progress';
    case QualityCheck = 'quality_check';
    case Completed = 'completed';
    case Invoiced = 'invoiced';
    case ReadyPickup = 'ready_pickup';
    case Closed = 'closed';

    public static function fromSlug(string $slug): self
    {
        $slug = RepairOrderWorkflowStatus::normalizeSlug($slug);

        return self::tryFrom($slug) ?? self::Draft;
    }

    public static function isWaitingCustomerApproval(self|RepairOrderWorkflowStatus|string $status): bool
    {
        return RepairOrderWorkflowStatus::from($status)->is(self::WaitingApproval);
    }

    public function label(): string
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            return $catalog->labelForSlug($this->value);
        }

        return match ($this) {
            self::Draft => 'Draft',
            self::Estimate => 'Building Estimate',
            self::WaitingApproval => 'Waiting Approval',
            self::Approved => 'Approved',
            self::WaitingParts => 'Waiting Parts',
            self::ReadyForWork => 'Ready for Work',
            self::InProgress => 'In Progress',
            self::QualityCheck => 'Quality Check',
            self::Completed => 'Completed',
            self::Invoiced => 'Invoiced',
            self::ReadyPickup => 'Ready for Pickup',
            self::Closed => 'Closed',
        };
    }

    public function pressureQuestion(): string
    {
        return match ($this) {
            self::Draft => 'What is in draft?',
            self::Estimate => 'What estimate is being built?',
            self::WaitingApproval => 'What is waiting approval?',
            self::Approved => 'What was approved?',
            self::WaitingParts => 'What is blocked on parts?',
            self::ReadyForWork => 'What is ready to work?',
            self::InProgress => 'What is in progress?',
            self::QualityCheck => 'What is in quality check?',
            self::Completed => 'What is completed?',
            self::Invoiced => 'What is invoiced?',
            self::ReadyPickup => 'What is waiting pickup?',
            self::Closed => 'What closed recently?',
        };
    }

    /**
     * Open RO statuses where estimate portal views are expected noise — car is already in workflow.
     *
     * @return list<string>
     */
    public static function estimateViewAttentionSuppressedSlugs(): array
    {
        return [
            self::Approved->value,
            self::WaitingParts->value,
            self::ReadyForWork->value,
            self::InProgress->value,
            self::QualityCheck->value,
            self::ReadyPickup->value,
        ];
    }

    /**
     * Open statuses where a technician is capturing DVI — including estimate / waiting approval.
     *
     * @return list<string>
     */
    public static function techDviQueueValues(): array
    {
        return [
            self::Estimate->value,
            self::WaitingApproval->value,
            self::Approved->value,
            self::WaitingParts->value,
            self::ReadyForWork->value,
            self::InProgress->value,
            self::QualityCheck->value,
        ];
    }

    public static function operationalQueueValues(): array
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            return $catalog->advisorBoardSlugs();
        }

        return [
            self::Draft->value,
            self::Estimate->value,
            self::WaitingApproval->value,
            self::Approved->value,
            self::WaitingParts->value,
            self::ReadyForWork->value,
            self::InProgress->value,
            self::ReadyPickup->value,
        ];
    }

    /**
     * @return list<self>
     */
    public static function operationalQueue(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $slug): ?self => self::tryFrom($slug),
            self::operationalQueueValues(),
        )));
    }

    /**
     * @return list<string>
     */
    public function allowedOperationalTransitionSlugs(?\App\Models\User $actor = null): array
    {
        return app(RepairOrderStatusCatalog::class)->allowedTargetSlugs($this->value, $actor);
    }

    /**
     * @return list<self>
     */
    public function legacyAllowedOperationalTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Estimate],
            self::Estimate => [self::WaitingApproval, self::Draft],
            self::WaitingApproval => [self::Approved, self::Estimate, self::Closed],
            self::Approved => [self::WaitingParts, self::ReadyForWork, self::InProgress],
            self::WaitingParts => [self::ReadyForWork, self::InProgress, self::Approved],
            self::ReadyForWork => [self::InProgress, self::WaitingParts],
            self::InProgress => [self::ReadyPickup, self::QualityCheck, self::WaitingParts],
            self::QualityCheck => [self::Completed, self::ReadyPickup],
            self::Completed => [self::Invoiced, self::ReadyPickup],
            self::Invoiced => [self::Closed, self::ReadyPickup],
            self::ReadyPickup => [self::Closed, self::InProgress],
            default => [],
        };
    }

    public function isIntake(): bool
    {
        return in_array($this, [self::Draft, self::Estimate], true);
    }

    public function isTerminal(): bool
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            return $catalog->isTerminalSlug($this->value);
        }

        return $this === self::Closed;
    }

    public function indexTone(): string
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            return $catalog->chipToneForSlug($this->value);
        }

        return match ($this) {
            self::WaitingApproval => 'approval',
            self::WaitingParts => 'blocked',
            self::InProgress, self::ReadyForWork, self::Approved, self::QualityCheck => 'motion',
            self::ReadyPickup, self::Completed, self::Invoiced => 'ready',
            self::Closed => 'closed',
            default => 'move',
        };
    }
}
