<?php

namespace App\Ark\Growth\Authority;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Operational authority — effort (inputs) vs earned (outputs).
 *
 * Review requests are effort, not earned authority. Revenue ≠ invoices sent.
 *
 * @see config/growth_authority.php
 */
final class AuthorityLedgerProjection
{
    /**
     * @return array{
     *     effort: array<string, mixed>,
     *     earned: array<string, mixed>
     * }
     */
    public function resolve(?Carbon $through = null): array
    {
        return [
            'effort' => $this->effort($through),
            'earned' => $this->earned($through),
        ];
    }

    /**
     * @return array{
     *     period_label: string,
     *     entries: list<array{label: string, count: int, source: string}>,
     *     interpretation: string
     * }
     */
    public function effort(?Carbon $through = null): array
    {
        $through ??= now();
        $from = $through->copy()->subDays(30)->startOfDay();

        $closedPaid = RepairOrder::query()
            ->where('status', RepairOrderStatus::Closed)
            ->where('close_variant_key', 'paid')
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $through)
            ->get(['review_request_sent']);

        $reviewRequestsSent = $closedPaid->where('review_request_sent', true)->count();

        return [
            'period_label' => 'Last 30 days',
            'entries' => [
                $this->countEntry('Review requested at close', $reviewRequestsSent, 'repair_orders.review_request_sent'),
            ],
            'interpretation' => 'Authority effort — inputs the shop controls before the market responds.',
        ];
    }

    /**
     * @return array{
     *     period_label: string,
     *     entries: list<array{label: string, count: int, source: string}>,
     *     interpretation: string
     * }
     */
    public function earned(?Carbon $through = null): array
    {
        $through ??= now();
        $from = $through->copy()->subDays(30)->startOfDay();

        $closedPaid = RepairOrder::query()
            ->where('status', RepairOrderStatus::Closed)
            ->where('close_variant_key', 'paid')
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $through)
            ->with('customer')
            ->get();

        $returningVisits = $closedPaid
            ->filter(function (RepairOrder $repairOrder): bool {
                if ($repairOrder->customer_id === null) {
                    return false;
                }

                $priorCount = RepairOrder::query()
                    ->where('customer_id', $repairOrder->customer_id)
                    ->where('status', RepairOrderStatus::Closed)
                    ->where('close_variant_key', 'paid')
                    ->where('closed_at', '<', $repairOrder->closed_at)
                    ->count();

                return $priorCount > 0;
            })
            ->count();

        $newCustomers = $closedPaid
            ->filter(function (RepairOrder $repairOrder) use ($from): bool {
                if ($repairOrder->customer === null) {
                    return false;
                }

                return $repairOrder->customer->created_at >= $from;
            })
            ->unique('customer_id')
            ->count();

        $fromGoogle = $closedPaid
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->growth_session_id !== null)
            ->count();

        return [
            'period_label' => 'Last 30 days',
            'entries' => [
                $this->countEntry('Returning customer visit', $returningVisits, 'repair_orders.closed_at'),
                $this->countEntry('New customer (first visit)', $newCustomers, 'customers.created_at'),
                $this->countEntry('Website-attributed RO', $fromGoogle, 'repair_orders.growth_session_id'),
            ],
            'interpretation' => 'Authority earned — outputs that suggest the market trusts you more.',
        ];
    }

    /**
     * @return array{label: string, count: int, source: string}
     */
    private function countEntry(string $label, int $count, string $source): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'source' => $source,
        ];
    }
}
