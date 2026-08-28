<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

final readonly class WorkboardTriageCard
{
    public function __construct(
        public RepairOrder $repairOrder,
        public string $vehicleLabel,
        public string $concernSummary,
        public string $concernHeadline,
        public ?string $signalLabel,
        public string $signalTone,
        public string $ageLabel,
        public int $ageMinutes,
        public int $pressureScore,
        public bool $countsAsNeedsAttention,
        public bool $countsAsCustomerWaiting,
        public bool $countsAsUnassigned,
        public bool $countsAsOverduePickup,
        public string $href,
    ) {}

    public function nextMoveLabel(?string $chipLabel = null): ?string
    {
        $move = $this->resolveNextMove();

        if ($move === null) {
            return null;
        }

        $chip = trim((string) $chipLabel);

        if ($chip !== '' && strcasecmp($move, $chip) === 0) {
            return null;
        }

        return $move;
    }

    public function homeActionStatement(int $totalCents = 0): string
    {
        $statement = $this->baseActionStatement();

        if ($this->isAwaitingCustomerDecision() && $totalCents > 0) {
            $moneyLabel = '$'.number_format($totalCents / 100, 0);

            if ($this->signalLabel === null && str_starts_with($statement, 'No recent activity')) {
                return $moneyLabel.' pending approval · '.$this->ageLabel;
            }

            if ($this->signalLabel !== null && ! str_contains($statement, 'pending')) {
                $decisionSignals = [
                    'Estimate Viewed',
                    'Estimate Sent',
                    'Multiple Customer',
                    'Customer Waiting',
                ];

                foreach ($decisionSignals as $needle) {
                    if (str_contains($this->signalLabel, $needle)) {
                        return str_replace(
                            ' · '.$this->ageLabel,
                            ' · '.$moneyLabel.' pending · '.$this->ageLabel,
                            $statement,
                        );
                    }
                }
            }
        }

        return $statement;
    }

    public function homeUrgencyScore(int $totalCents = 0): int
    {
        return $this->pressureScore + $this->moneyBoost($totalCents);
    }

    public function homeUrgencyTier(int $totalCents = 0): string
    {
        $score = $this->homeUrgencyScore($totalCents);

        if ($score >= 40 || ($totalCents >= 500_000 && $this->pressureScore >= 12)) {
            return 'critical';
        }

        if ($score >= 24 || $totalCents >= 300_000) {
            return 'high';
        }

        if ($score >= 12 || $totalCents >= 150_000) {
            return 'medium';
        }

        return 'normal';
    }

    private function resolveNextMove(): ?string
    {
        if ($this->countsAsOverduePickup) {
            return 'Call for pickup';
        }

        if ($this->isAwaitingCustomerDecision()) {
            return 'Follow up';
        }

        if ($this->signalLabel !== null) {
            $fromSignal = match (true) {
                str_contains($this->signalLabel, 'Estimate Viewed'),
                str_contains($this->signalLabel, 'Estimate Sent') => 'Follow up',
                str_contains($this->signalLabel, 'Multiple Customer'),
                str_contains($this->signalLabel, 'Customer Waiting') => 'Respond',
                str_contains($this->signalLabel, 'Waiting on Parts'),
                str_contains($this->signalLabel, 'Waiting Parts') => 'Check parts',
                str_contains($this->signalLabel, 'Vehicle ID') => 'Confirm vehicle',
                str_contains($this->signalLabel, 'Unassigned Tech'),
                str_contains($this->signalLabel, 'Unassigned') => 'Assign tech',
                str_contains($this->signalLabel, 'Overdue Pickup') => 'Call for pickup',
                default => null,
            };

            if ($fromSignal !== null) {
                return $fromSignal;
            }
        }

        if ($this->countsAsUnassigned) {
            return 'Assign tech';
        }

        if ($this->countsAsCustomerWaiting) {
            return 'Respond';
        }

        if ($this->countsAsNeedsAttention) {
            return 'Review';
        }

        return null;
    }

    private function baseActionStatement(): string
    {
        if ($this->signalLabel !== null) {
            return $this->humanizeSignal($this->signalLabel).' · '.$this->ageLabel;
        }

        if ($this->countsAsOverduePickup) {
            return 'Overdue pickup · '.$this->ageLabel;
        }

        if ($this->countsAsUnassigned) {
            return 'Waiting on tech assignment · '.$this->ageLabel;
        }

        if ($this->countsAsCustomerWaiting) {
            return 'Customer waiting on shop · '.$this->ageLabel;
        }

        if ($this->countsAsNeedsAttention) {
            return 'Needs advisor attention · '.$this->ageLabel;
        }

        return 'No recent activity · '.$this->ageLabel;
    }

    private function moneyBoost(int $totalCents): int
    {
        return match (true) {
            $totalCents >= 500_000 => 18,
            $totalCents >= 300_000 => 12,
            $totalCents >= 150_000 => 6,
            default => 0,
        };
    }

    private function isAwaitingCustomerDecision(): bool
    {
        return $this->repairOrder->status->is(RepairOrderStatus::WaitingApproval);
    }

    private function humanizeSignal(string $label): string
    {
        return match (true) {
            str_contains($label, 'Multiple Customer') => 'Customer replied',
            str_contains($label, 'Customer Waiting') => 'Waiting on advisor',
            str_contains($label, 'Estimate Viewed Multiple') => 'Estimate viewed repeatedly',
            str_contains($label, 'Estimate Viewed') => 'Estimate viewed',
            str_contains($label, 'Estimate Sent') => 'Estimate sent',
            str_contains($label, 'Waiting on Parts'),
            str_contains($label, 'Waiting Parts') => 'Waiting on parts',
            str_contains($label, 'Vehicle ID') => 'Vehicle ID needed',
            str_contains($label, 'Unassigned Tech'),
            str_contains($label, 'Unassigned') => 'Waiting on tech',
            str_contains($label, 'Overdue Pickup') => 'Overdue pickup',
            default => $label,
        };
    }
}
