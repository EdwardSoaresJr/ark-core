<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\RepairOrderBalanceProjection;
use App\Ark\Operations\Financial\RepairOrderCloseoutAuthority;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusVariant;
use App\Ark\Operations\Staff\SoloShopOperations;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class RepairOrderLifecycleSelectProjection
{
    /**
     * @param  list<array{value: string, label: string, blockedReason: ?string, disabled: bool}>  $statusOptions
     * @param  list<array{value: string, label: string, blockedReason: ?string}>  $closeOptions
     */
    public function __construct(
        public array $statusOptions,
        public array $closeOptions,
        public bool $showLostCloseOption,
    ) {}

    /**
     * @param  iterable<int, string>  $lifecycleSlugs
     * @param  iterable<int, RepairOrderStatusVariant>  $closeVariantOptions
     */
    public static function for(
        RepairOrder $repairOrder,
        iterable $lifecycleSlugs,
        iterable $closeVariantOptions,
        ?User $actor,
        RepairOrderLifecycleTransition $transition,
        RepairOrderStatusCatalog $statusCatalog,
        ?RepairOrderLifecycleSelectCache $selectCache = null,
        ?RepairOrderBalanceProjection $balanceProjection = null,
    ): self {
        $selectCache ??= new RepairOrderLifecycleSelectCache(
            $repairOrder,
            $actor,
            app(SoloShopOperations::class),
            app(RepairOrderCloseoutAuthority::class),
            $balanceProjection,
        );

        $statusOptions = [];

        foreach ($lifecycleSlugs as $nextStatusSlug) {
            $blockedReason = $transition->blockingReason($repairOrder, $nextStatusSlug, $actor, null, $selectCache);
            $label = $statusCatalog->labelForSlug($nextStatusSlug);

            if ($statusCatalog->isRetreatTransition($repairOrder->status->value, $nextStatusSlug)) {
                $label = '← '.$label;
            }

            $statusOptions[] = [
                'value' => $nextStatusSlug,
                'label' => $label,
                'blockedReason' => $blockedReason,
                'disabled' => $blockedReason !== null
                    || ($selectCache->lacksEstimateLines()
                        && ! in_array($nextStatusSlug, [RepairOrderStatus::Draft->value, RepairOrderStatus::Estimate->value], true)),
            ];
        }

        $closeOptions = [];

        foreach ($closeVariantOptions as $variant) {
            if ($variant->variant_key === 'lost') {
                continue;
            }

            $closeOptions[] = [
                'value' => 'closed:'.$variant->variant_key,
                'label' => 'Closed — '.$variant->name,
                'blockedReason' => $transition->blockingReason(
                    $repairOrder,
                    RepairOrderStatus::Closed->value,
                    $actor,
                    $variant->variant_key,
                    $selectCache,
                ),
            ];
        }

        $lostBlockedReason = $transition->blockingReason(
            $repairOrder,
            RepairOrderStatus::Closed->value,
            $actor,
            'lost',
            $selectCache,
        );

        return new self(
            statusOptions: $statusOptions,
            closeOptions: $closeOptions,
            showLostCloseOption: $lostBlockedReason === null,
        );
    }

    /**
     * @param  Collection<int, string>  $lifecycleSlugs
     * @param  array<int, RepairOrderStatusVariant>  $closeVariantOptions
     */
    public static function forRepairOrder(
        RepairOrder $repairOrder,
        Collection $lifecycleSlugs,
        array $closeVariantOptions,
        ?User $actor,
        ?RepairOrderBalanceProjection $balanceProjection = null,
    ): self {
        return self::for(
            $repairOrder,
            $lifecycleSlugs,
            $closeVariantOptions,
            $actor,
            app(RepairOrderLifecycleTransition::class),
            app(RepairOrderStatusCatalog::class),
            balanceProjection: $balanceProjection,
        );
    }

    /**
     * Same inputs the RO lifecycle select uses (allowedTargetSlugs + allowedCloseVariants).
     */
    public static function forCatalogTargets(
        RepairOrder $repairOrder,
        ?User $actor,
        ?RepairOrderBalanceProjection $balanceProjection = null,
    ): self {
        $statusCatalog = app(RepairOrderStatusCatalog::class);

        return self::forRepairOrder(
            $repairOrder,
            collect($statusCatalog->allowedTargetSlugs($repairOrder->status->value, $actor)),
            $statusCatalog->allowedCloseVariants($repairOrder->status, $actor),
            $actor,
            $balanceProjection,
        );
    }

    /**
     * Chip tone for Job Board / Hub / Comms status controls — keyed to lifecycle, not pressure.
     */
    public static function statusTone(RepairOrder $repairOrder): string
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            return $catalog->boardToneForSlug($repairOrder->status->value);
        }

        return match ($repairOrder->status->enum()) {
            RepairOrderStatus::WaitingApproval => 'warn',
            RepairOrderStatus::WaitingParts => 'parts',
            RepairOrderStatus::InProgress,
            RepairOrderStatus::QualityCheck => 'progress',
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup => 'ready',
            default => 'neutral',
        };
    }

    /**
     * Same selectable choices as the RO lifecycle <select>, packaged for Job Board cards.
     *
     * @return list<array{
     *     value: string,
     *     label: string,
     *     disabled: bool,
     *     blockedReason: ?string,
     *     needsRoConfirmation: bool
     * }>
     */
    public function boardMoves(): array
    {
        $moves = [];

        foreach ($this->statusOptions as $option) {
            $moves[] = [
                'value' => $option['value'],
                'label' => $option['label'],
                'disabled' => $option['disabled'],
                'blockedReason' => $option['blockedReason'],
                'needsRoConfirmation' => false,
            ];
        }

        foreach ($this->closeOptions as $option) {
            $blocked = $option['blockedReason'] !== null;

            $moves[] = [
                'value' => $option['value'],
                'label' => $option['label'],
                'disabled' => $blocked,
                'blockedReason' => $option['blockedReason'],
                'needsRoConfirmation' => ! $blocked,
            ];
        }

        if ($this->showLostCloseOption) {
            $moves[] = [
                'value' => 'closed:lost',
                'label' => 'Closed — Lost',
                'disabled' => false,
                'blockedReason' => null,
                'needsRoConfirmation' => true,
            ];
        }

        return $moves;
    }
}
