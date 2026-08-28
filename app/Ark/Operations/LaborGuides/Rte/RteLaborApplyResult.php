<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Support\Collection;

final readonly class RteLaborApplyResult
{
    /**
     * @param  list<RepairOrderLine>  $lines
     */
    public function __construct(
        private array $lines,
    ) {}

    public function primaryLine(): RepairOrderLine
    {
        return $this->lines[0];
    }

    /**
     * @return list<RepairOrderLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function includedLineCount(): int
    {
        return max(0, count($this->lines) - 1);
    }

    public function totalEnteredHours(): float
    {
        return round(
            Collection::make($this->lines)
                ->sum(fn (RepairOrderLine $line): float => (float) ($line->labor_entered_hours ?? $line->quantity)),
            2,
        );
    }

    public function statusMessage(): string
    {
        $primary = $this->primaryLine();
        $primaryHours = number_format((float) ($primary->labor_entered_hours ?? $primary->quantity), 2);

        if ($this->includedLineCount() === 0) {
            return sprintf(
                RepairTimeEngine::NAME.' labor applied — %s · %s hr.',
                $primary->description,
                $primaryHours,
            );
        }

        return sprintf(
            RepairTimeEngine::NAME.' labor applied — %s · %s hr (+%d included · %s hr total).',
            $primary->description,
            $primaryHours,
            $this->includedLineCount(),
            number_format($this->totalEnteredHours(), 2),
        );
    }
}
