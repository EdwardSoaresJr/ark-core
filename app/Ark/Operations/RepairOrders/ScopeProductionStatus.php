<?php

namespace App\Ark\Operations\RepairOrders;

enum ScopeProductionStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In progress',
            self::WaitingParts => 'Waiting parts',
            self::Completed => 'Completed',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Pending => 'Approved scope not started in the bay yet.',
            self::InProgress => 'Technician is actively working this scope.',
            self::WaitingParts => 'Scope is blocked on parts or an outside dependency.',
            self::Completed => 'This scope is finished — independent of other scopes on the RO.',
        };
    }

    public function countsLaborComplete(): bool
    {
        return $this === self::Completed;
    }

    public function worksheetToneStyle(): string
    {
        return match ($this) {
            self::Pending => 'border-color:#cbd5e1;background-color:#f8fafc;color:#475569;',
            self::InProgress => 'border-color:#38bdf8;background-color:#f0f9ff;color:#0c4a6e;',
            self::WaitingParts => 'border-color:#fcd34d;background-color:#fffbeb;color:#92400e;',
            self::Completed => 'border-color:#86efac;background-color:#ecfdf5;color:#166534;',
        };
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Pending;
    }
}
