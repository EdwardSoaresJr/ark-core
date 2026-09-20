<?php

namespace App\Ark\Dragon;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Privacy-minimized closed-RO snapshots for Dragon Historical RI ingest.
 * No customer identity, full VIN, or money fields.
 */
final class DragonHistoryProjection
{
    /**
     * @return array{
     *     closed_ro_count: int,
     *     diagnostic_closed_ro_count: int,
     *     sample_limit: int,
     *     semantics: string,
     *     paid_closed_ro_count: int,
     *     lost_closed_ro_count: int
     * }
     */
    public function summary(): array
    {
        $closed = $this->closedQuery()->count();
        $diagnostic = $this->applyDiagnosticConstraint($this->closedQuery())->count();

        return [
            'closed_ro_count' => $closed,
            'diagnostic_closed_ro_count' => $diagnostic,
            'sample_limit' => 600,
            'semantics' => 'All status=closed ROs, including paid and lost. Not live open work. Not sales totals.',
            'paid_closed_ro_count' => (clone $this->closedQuery())
                ->where(function (Builder $query): void {
                    $query->whereNull('close_variant_key')
                        ->orWhere('close_variant_key', 'paid');
                })
                ->count(),
            'lost_closed_ro_count' => (clone $this->closedQuery())
                ->where('close_variant_key', 'lost')
                ->count(),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, truncated: bool, diagnostic_only: bool}
     */
    public function listRepairOrders(Request $request): array
    {
        $limit = max(1, min(600, (int) $request->query('limit', 75)));
        $diagnosticOnly = $request->boolean('diagnostic', true);

        $query = $this->closedQuery()->with($this->eagerLoads());
        if ($diagnosticOnly) {
            $this->applyDiagnosticConstraint($query);
        }

        $total = (clone $query)->count();
        $orders = $query
            ->orderByRaw('COALESCE(closed_at, posted_at, updated_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'items' => $orders->map(fn (RepairOrder $repairOrder): array => $this->snapshot($repairOrder))->values()->all(),
            'truncated' => $total > $limit,
            'diagnostic_only' => $diagnosticOnly,
            'returned' => $orders->count(),
            'matched' => $total,
            'summary' => $this->summary(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function repairOrder(string $repairOrderId): ?array
    {
        /** @var RepairOrder|null $repairOrder */
        $repairOrder = $this->closedQuery()
            ->with($this->eagerLoads())
            ->where('repair_order_id', $repairOrderId)
            ->first();

        if ($repairOrder === null) {
            return null;
        }

        return $this->snapshot($repairOrder);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RepairOrder $repairOrder): array
    {
        $vehicle = $repairOrder->vehicle;
        $vin = filled($vehicle?->vin) ? (string) $vehicle->vin : (filled($vehicle?->normalized_vin) ? (string) $vehicle->normalized_vin : null);

        return [
            'repair_order_id' => $repairOrder->repair_order_id,
            'status' => $repairOrder->status->value,
            'opened_at' => $repairOrder->displayOpenedAt()->toIso8601String(),
            'closed_at' => $repairOrder->closed_at?->toIso8601String() ?? $repairOrder->posted_at?->toIso8601String(),
            'close_variant' => $repairOrder->close_variant_key ?: null,
            'lost_reason_key' => $repairOrder->close_variant_key === 'lost'
                ? $this->lostReasonKey($repairOrder)
                : null,
            'posted' => $repairOrder->posted_at !== null,
            'warranty' => (bool) $repairOrder->warranty,
            'technician' => $repairOrder->assignedTechnician?->name,
            'concern_summary' => $this->short($repairOrder->concern_summary, 240),
            'mileage_in' => $repairOrder->mileage_in,
            'vehicle' => [
                'year' => $vehicle?->year,
                'make' => $vehicle?->make,
                'model' => $vehicle?->model,
                'trim' => $vehicle?->trim,
                'engine' => $vehicle?->engine_display ?: $vehicle?->engine,
                'vin_last6' => $this->vinLast6($vin),
                'vin_present' => filled($vin),
            ],
            'concerns' => $repairOrder->concerns
                ->map(fn (RepairOrderConcern $concern): array => $this->presentConcern($concern))
                ->values()
                ->all(),
            'lines' => $repairOrder->lines
                ->map(fn (RepairOrderLine $line): array => $this->presentLine($line))
                ->values()
                ->all(),
            'inspection_items' => [],
        ];
    }

    /**
     * @return Builder<RepairOrder>
     */
    private function closedQuery(): Builder
    {
        return RepairOrder::query()
            ->where('status', RepairOrderStatus::Closed->value);
    }

    /**
     * @param  Builder<RepairOrder>  $query
     * @return Builder<RepairOrder>
     */
    private function applyDiagnosticConstraint(Builder $query): Builder
    {
        return $query->whereHas('concerns', function (Builder $concerns): void {
            $concerns->where(function (Builder $inner): void {
                $inner->where(function (Builder $dtc): void {
                    $dtc->whereNotNull('dtcs_summary')->where('dtcs_summary', '!=', '');
                })->orWhere(function (Builder $findings): void {
                    $findings->whereNotNull('verified_findings')->where('verified_findings', '!=', '');
                });
            });
        });
    }

    /**
     * @return list<string>
     */
    private function eagerLoads(): array
    {
        return [
            'vehicle:id,year,make,model,trim,engine,engine_display,vin,normalized_vin',
            'assignedTechnician:id,name',
            'concerns',
            'lines',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConcern(RepairOrderConcern $concern): array
    {
        $notes = trim(implode("\n", array_filter([
            $this->short($concern->customer_states, 400),
            $this->short($concern->verified_findings, 600),
            $this->short($concern->recommendation, 240),
        ], static fn (?string $part): bool => filled($part))));

        $disposition = $concern->disposition;
        $dispositionValue = is_object($disposition) && isset($disposition->value)
            ? $disposition->value
            : (string) $disposition;

        return [
            'summary' => $this->short($concern->summary, 240),
            'notes' => $notes !== '' ? $notes : null,
            'customer_states' => $this->short($concern->customer_states, 400),
            'verified_findings' => $this->short($concern->verified_findings, 600),
            'dtcs_summary' => $this->short($concern->dtcs_summary, 160),
            'recommendation' => $this->short($concern->recommendation, 240),
            'disposition' => $dispositionValue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLine(RepairOrderLine $line): array
    {
        $type = $line->type;
        $typeValue = is_object($type) && isset($type->value) ? $type->value : (string) $type;

        $row = [
            'type' => $typeValue,
            'description' => $this->short($line->description, 200),
        ];
        if ($line->labor_billed_hours !== null) {
            $row['labor_billed_hours'] = (float) $line->labor_billed_hours;
        }
        if (filled($line->part_number)) {
            $row['part_number'] = $this->short((string) $line->part_number, 40);
        }

        return $row;
    }

    private function vinLast6(?string $vin): ?string
    {
        if ($vin === null || $vin === '') {
            return null;
        }
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $vin) ?? '');
        if (strlen($clean) < 6) {
            return null;
        }

        return substr($clean, -6);
    }

    private function short(?string $value, int $limit): ?string
    {
        if (! filled($value)) {
            return null;
        }
        $text = trim((string) $value);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1).'…';
    }

    private function lostReasonKey(RepairOrder $repairOrder): string
    {
        $raw = $repairOrder->getRawOriginal('lost_reason_key');
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        return 'unspecified_import';
    }
}
