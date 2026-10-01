<?php

namespace App\Ark\Station;

use App\Ark\Dragon\DragonWorkProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Advisor glass shop state from ARK authority.
 */
final class StationDashboardProjection
{
    public const STALE_AFTER_DAYS = 3;

    public function __construct(
        private readonly DragonWorkProjection $work,
        private readonly StationAttentionProjection $attention,
        private readonly StationCallsProjection $calls,
        private readonly StationGlassConfig $glassConfig,
        private readonly StationGlassTasksProjection $tasks,
        private readonly StationGlassDeskProjection $desk,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?StationDeviceToken $token = null): array
    {
        $floor = $this->work->shopFloor(includeCustomerLabel: true);
        $items = $floor['items'] ?? [];
        $summary = $floor['summary'] ?? [];

        $waiting = [];
        $production = [];
        $unassigned = [];
        $stale = [];
        $waitingParts = [];

        $productionStatuses = $this->work->productionStatusSlugs();
        $now = Carbon::now();

        foreach ($items as $card) {
            if (! is_array($card)) {
                continue;
            }

            $status = (string) ($card['status'] ?? '');
            $tech = $card['assigned_technician'] ?? null;
            $opened = isset($card['opened_at']) ? Carbon::parse((string) $card['opened_at']) : null;
            $ageDays = $opened !== null ? $opened->diffInDays($now) : 0;

            if ($status === RepairOrderStatus::WaitingApproval->value) {
                $waiting[] = $card;
            }

            if ($status === RepairOrderStatus::WaitingParts->value) {
                $waitingParts[] = $card;
            }

            if (in_array($status, $productionStatuses, true)) {
                $production[] = $card;
            }

            if ($tech === null || trim((string) $tech) === '') {
                $unassigned[] = $card;
            }

            if ($ageDays >= self::STALE_AFTER_DAYS) {
                $stale[] = $card;
            }
        }

        $attention = $this->attention->payload();
        $shop = $attention['shop_summary'];
        $todaySummary = [
            ...$summary,
            'open_ro_count' => $shop['open'],
            'waiting_for_approval_count' => $shop['waiting_approval'],
            'in_production_count' => $shop['in_production'],
            'waiting_approval_amount_cents' => $shop['waiting_approval_amount_cents'],
            'waiting_approval_amount_label' => $shop['waiting_approval_amount_label'],
            'unassigned_count' => $shop['unassigned'],
            'coming_in_count' => $shop['coming_in'],
            'money_semantics' => $shop['money_semantics'],
        ];
        $dragon = app(StationDragonStatusProjection::class)->payload($attention, $todaySummary);
        $glass = $token !== null ? $this->glassConfig->forToken($token) : [
            'appearance' => 'light',
            'advisor_mode' => 'two',
            'primary_advisor_user_id' => null,
            'secondary_advisor_user_id' => null,
            'eligible_advisors' => $this->glassConfig->eligibleAdvisors(),
        ];

        $briefing = $this->fallbackBriefing($todaySummary, $attention['rows'] ?? []);
        $calls = $this->calls->payload();
        $todos = $this->tasks->payload($glass);

        return [
            'generated_at' => $now->toIso8601String(),
            'surface' => 'advisor_station',
            'privacy' => 'shop_floor',
            'health' => [
                'ark' => 'ok',
            ],
            'glass' => $glass,
            'todos' => $todos,
            'dragon' => $dragon,
            'attention' => $attention,
            'today' => [
                'summary' => $todaySummary,
                'briefing' => $briefing,
                'attention' => $attention['rows'],
                'waiting_parts_count' => $shop['waiting_parts'],
                'nudge_fingerprint' => $attention['snapshot_fingerprint'],
                'waiting_approval' => $waiting,
                'in_production' => $production,
                'waiting_parts' => $waitingParts,
                'stale' => $stale,
                'unassigned' => $unassigned,
                'stale_after_days' => self::STALE_AFTER_DAYS,
            ],
            'repair_orders' => [
                'items' => $items,
                'items_truncated' => (bool) ($floor['items_truncated'] ?? false),
                'open_ro_total' => (int) ($floor['open_ro_total'] ?? count($items)),
            ],
            'approvals' => [
                'waiting_approval' => $waiting,
                'count' => count($waiting),
            ],
            'calls' => $calls,
            'desk' => $this->desk->payload($glass, $todos, $calls, $attention),
            'coming_in' => $attention['coming_in'],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $rows
     */
    private function fallbackBriefing(array $summary, array $rows): string
    {
        $approvals = (int) ($summary['waiting_for_approval_count'] ?? 0);
        $money = (string) ($summary['waiting_approval_amount_label'] ?? '');
        $first = $rows[0]['repair_order_id'] ?? null;
        $firstAge = $rows[0]['age_days'] ?? null;

        $line = $approvals.' approvals';
        if ($money !== '') {
            $line .= ' · '.$money.' waiting';
        }
        if ($first !== null) {
            $line .= '. Start with RO '.$first;
            if (is_int($firstAge) || is_numeric($firstAge)) {
                $line .= ' ('.$firstAge.'d)';
            }
            $line .= '.';
        }

        return $line;
    }
}
