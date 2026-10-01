<?php

namespace App\Ark\Operations\RepairOrders;

enum RepairActionStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case WaitingApproval = 'waiting_approval';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::WaitingParts => 'Waiting Parts',
            self::WaitingApproval => 'Waiting Approval',
            self::Complete => 'Complete',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Pending => 'Owned Repair Action not started in the bay yet.',
            self::InProgress => 'Technician is actively working this Repair Action.',
            self::WaitingParts => 'Blocked on parts or an outside dependency.',
            self::WaitingApproval => 'Waiting on customer or advisor decision.',
            self::Complete => 'This Repair Action is finished.',
        };
    }

    public function worksheetToneStyle(): string
    {
        return match ($this) {
            self::Pending => 'border-color:#cbd5e1;background-color:#f8fafc;color:#475569;',
            self::InProgress => 'border-color:#38bdf8;background-color:#f0f9ff;color:#0c4a6e;',
            self::WaitingParts => 'border-color:#fcd34d;background-color:#fffbeb;color:#92400e;',
            self::WaitingApproval => 'border-color:#c4b5fd;background-color:#f5f3ff;color:#5b21b6;',
            self::Complete => 'border-color:#86efac;background-color:#ecfdf5;color:#166534;',
        };
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Pending;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
