<?php

namespace App\Ark\Operations\LaborGuides;

use App\Ark\Operations\RepairOrders\RepairOrder;

final class LaborGuideConnections
{
    public function __construct(
        private readonly LaborGuideLauncher $launcher,
    ) {}

    /**
     * @param  array{available?: bool, blocked_reason?: string|null}  $rteLaborGuide
     * @return list<array{
     *     key: string,
     *     label: string,
     *     kind: string,
     *     can_open: bool,
     *     can_pull_quote: bool,
     *     title: string,
     *     blocked_reason: string|null,
     *     labor_guide: array{url: string, vin: ?string, notice: string, windowName: string}|null
     * }>
     */
    public function forRepairOrder(RepairOrder $repairOrder, ?int $concernId = null, array $rteLaborGuide = []): array
    {
        $guides = [$this->rteRow($rteLaborGuide)];

        foreach (LaborGuideProvider::enabled() as $provider) {
            $guides[] = $this->externalRow($repairOrder, $provider, $concernId);
        }

        return $guides;
    }

    /**
     * @param  array{available?: bool, blocked_reason?: string|null}  $rteLaborGuide
     * @return array{
     *     key: string,
     *     label: string,
     *     kind: string,
     *     can_open: bool,
     *     can_pull_quote: bool,
     *     title: string,
     *     blocked_reason: string|null,
     *     labor_guide: null
     * }
     */
    private function rteRow(array $rteLaborGuide): array
    {
        $ready = LaborGuideIntent::rteAvailable() && (bool) ($rteLaborGuide['available'] ?? false);
        $blocked = $ready
            ? null
            : (string) ($rteLaborGuide['blocked_reason'] ?? LaborGuideIntent::tooltip());

        return [
            'key' => LaborGuideIntent::KEY,
            'label' => LaborGuideIntent::label(),
            'kind' => LaborGuideIntent::KEY,
            'can_open' => true,
            'can_pull_quote' => false,
            'title' => $ready ? LaborGuideIntent::tooltip() : (string) $blocked,
            'blocked_reason' => $blocked,
            'labor_guide' => null,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     kind: string,
     *     can_open: bool,
     *     can_pull_quote: bool,
     *     title: string,
     *     blocked_reason: string|null,
     *     labor_guide: array{url: string, vin: ?string, notice: string, windowName: string}|null
     * }
     */
    private function externalRow(RepairOrder $repairOrder, LaborGuideProvider $provider, ?int $concernId): array
    {
        $launchUrl = $this->launcher->launchUrl($repairOrder, $provider, $concernId);
        $clipboardVin = $this->launcher->clipboardVin($repairOrder);

        return [
            'key' => $provider->value,
            'label' => $provider->label(),
            'kind' => 'external',
            'can_open' => $launchUrl !== null,
            'can_pull_quote' => false,
            'title' => $clipboardVin !== null
                ? 'Open '.$provider->label().' - VIN copied for vehicle search'
                : 'Open '.$provider->label().' - search by year, make, and model after sign-in',
            'blocked_reason' => $launchUrl === null ? $this->launcher->blockedReason($repairOrder, $provider) : null,
            'labor_guide' => $launchUrl !== null ? [
                'url' => $launchUrl,
                'vin' => $clipboardVin,
                'notice' => $this->launcher->handoffNotice($repairOrder, $provider, $concernId),
                'windowName' => 'ark-labor-'.$provider->value.'-ro-'.$repairOrder->repair_order_id,
            ] : null,
        ];
    }
}
