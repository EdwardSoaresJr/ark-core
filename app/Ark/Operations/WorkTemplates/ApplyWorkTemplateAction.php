<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\EstimatePricing\LaborRateOverrideReason;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Operations\RepairOrders\AssignRepairActionOwnerAction;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\NoteAudience;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineLaborInput;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Authors a Repair Action + ordinary lines from a Work Template, then detaches.
 * Template edit/retire after apply must never rewrite the RO.
 */
final class ApplyWorkTemplateAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly RepairOrderLinePricing $pricing,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderLifecycleTransition $lifecycle,
        private readonly RefreshCustomerInvoiceAction $refreshInvoice,
    ) {}

    /**
     * @param  array{hours: float, tier: string}|null  $laborOverride  Historical recall preparation (Exact/confirmed Likely)
     * @return array{work_group: RepairOrderWorkGroup, concern: RepairOrderConcern, line_ids: list<int>}
     */
    public function handle(
        RepairOrder $repairOrder,
        WorkTemplate $template,
        User $actor,
        ?RepairOrderConcern $attachToConcern = null,
        ?array $laborOverride = null,
        ?RecommendationIntent $newConcernIntent = null,
    ): array {
        $repairOrder->ensureOpenForEditing();

        if ($template->isRetired()) {
            throw ValidationException::withMessages([
                'work_template_id' => 'That saved work has been retired.',
            ]);
        }

        $template->loadMissing('lines');

        if ($template->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'work_template_id' => 'That saved work has no labor, parts, or fees to add.',
            ]);
        }

        foreach ($template->lines as $line) {
            if (! in_array($line->type, [
                RepairOrderLineType::Labor,
                RepairOrderLineType::Part,
                RepairOrderLineType::Fee,
                RepairOrderLineType::Note,
            ], true)) {
                throw new RuntimeException('Work templates may only author labor, part, fee, or note lines.');
            }
        }

        return DB::transaction(function () use ($repairOrder, $template, $actor, $attachToConcern, $laborOverride, $newConcernIntent): array {
            $repairOrder->loadMissing('customer');

            if ($attachToConcern !== null) {
                abort_unless((int) $attachToConcern->repair_order_id === (int) $repairOrder->id, 404);
                $concern = $attachToConcern;
            } else {
                $position = ((int) $repairOrder->concerns()->max('position')) + 1;
                $concern = RepairOrderConcern::query()->create([
                    'repair_order_id' => $repairOrder->id,
                    'summary' => $template->title,
                    'disposition' => RepairOrderConcernDisposition::Recommended,
                    'billing_posture' => ConcernBillingPosture::defaultForCustomerTag($repairOrder->customer?->customer_type),
                    'recommendation_intent' => ($newConcernIntent ?? $template->recommendationIntent())->value,
                    'position' => max(1, $position),
                ]);
            }

            $workGroup = $concern->workGroups()->create([
                'title' => $template->title,
                'position' => ((int) $concern->workGroups()->max('position')) + 1,
                'owner_type' => RepairActionOwnerType::Technician,
                'owner_user_id' => null,
                'created_from_template_id' => $template->id,
            ]);

            app(AssignRepairActionOwnerAction::class)->seedFromPrimaryTechnician(
                $workGroup,
                $repairOrder,
                $actor,
            );

            $lineIds = [];
            $settings = ShopSettings::current();
            $laborDefaults = $settings->laborDefaultsForConcern($concern->billing_posture, $repairOrder->customer);

            foreach ($template->lines as $blueprint) {
                $line = $this->createLineFromBlueprint(
                    $repairOrder,
                    $concern,
                    $workGroup,
                    $blueprint,
                    $laborDefaults,
                    $laborOverride,
                );
                $lineIds[] = $line->id;

                $this->events->record(
                    OperationalEventName::EstimateLineAdded,
                    $repairOrder,
                    actor: $actor,
                    payload: [
                        'line_id' => $line->id,
                        'concern_id' => $line->repair_order_concern_id,
                        'type' => $line->type->value,
                        'subtotal_cents' => $line->subtotal_cents,
                        'total_cents' => $line->total_cents,
                        'from_work_template_id' => $template->id,
                        ...($blueprint->type->isLabor() && $laborOverride !== null
                            ? [
                                'labor_source' => 'historical_recall',
                                'historical_match_tier' => $laborOverride['tier'],
                            ]
                            : []),
                    ],
                );
            }

            if (filled($template->internal_note)) {
                $noteData = [
                    'type' => RepairOrderLineType::Note->value,
                    'description' => $template->internal_note,
                    'quantity' => '1.00',
                    'repair_order_concern_id' => $concern->id,
                    'repair_order_work_group_id' => $workGroup->id,
                    'visible_to_advisor' => true,
                    'visible_to_technician' => true,
                    'visible_to_customer' => false,
                ];
                $pricingAttributes = $this->pricing->attributesFor($noteData, $repairOrder);
                $note = $repairOrder->lines()->create([
                    'repair_order_concern_id' => $concern->id,
                    'repair_order_work_group_id' => $workGroup->id,
                    'type' => RepairOrderLineType::Note,
                    'description' => $template->internal_note,
                    'quantity' => '1.00',
                    'unit_price_cents' => 0,
                    'part_cost_cents' => null,
                    'matrix_suggested_price_cents' => null,
                    'pricing_mode' => null,
                    'pricing_matrix_key' => null,
                    'pricing_matrix_name' => null,
                    'matrix_applied' => false,
                    'vendor_name' => null,
                    'part_number' => null,
                    'sourcing_notes' => null,
                    'has_core' => false,
                    'save_old_part' => false,
                    'is_overridden' => false,
                    'subtotal_cents' => 0,
                    ...NoteAudience::fromLegacyPrivate(true)->persistenceAttributes(),
                    ...RepairOrderLineLaborInput::persistenceAttributes($pricingAttributes),
                ]);
                $lineIds[] = $note->id;
            }

            $this->calculator->recalculateRepairOrder($repairOrder);

            if (RepairOrderWorkflowStatus::from($repairOrder->status)->is(RepairOrderStatus::Draft)) {
                $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $actor);
            }

            $this->documents->markDirtyForRepairOrder($repairOrder);
            $this->recordRepairOrderEstimateMutation($repairOrder, $actor);
            $this->refreshInvoice->executeIfNeeded(
                $repairOrder->fresh(['concerns', 'lines.concern', 'customer']),
                $actor,
            );

            return [
                'work_group' => $workGroup->fresh(),
                'concern' => $concern->fresh(),
                'line_ids' => $lineIds,
            ];
        });
    }

    /**
     * @param  array{category_key: string, rate: string}|array<string, mixed>  $laborDefaults
     * @param  array{hours: float, tier: string}|null  $laborOverride
     */
    private function createLineFromBlueprint(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RepairOrderWorkGroup $workGroup,
        WorkTemplateLine $blueprint,
        array $laborDefaults,
        ?array $laborOverride = null,
    ): RepairOrderLine {
        $type = $blueprint->type;
        $quantity = (string) $blueprint->quantity;

        $data = [
            'type' => $type->value,
            'description' => $blueprint->description,
            'quantity' => $quantity,
            'repair_order_concern_id' => $concern->id,
            'repair_order_work_group_id' => $workGroup->id,
        ];

        if ($type->isLabor()) {
            if ($laborOverride !== null) {
                $quantity = number_format($laborOverride['hours'], 2, '.', '');
            }
            $data['labor_entered_hours'] = $quantity;
            $data['labor_category_key'] = $laborDefaults['category_key'] ?? null;
            $data['labor_adjustment'] = 'normal';
            $data['quantity'] = $quantity;
            if ($blueprint->unit_price_cents !== null) {
                $data['unit_price'] = number_format($blueprint->unit_price_cents / 100, 2, '.', '');
            }
        } elseif ($type->isPart()) {
            $data['part_cost'] = $blueprint->part_cost_cents !== null
                ? number_format($blueprint->part_cost_cents / 100, 2, '.', '')
                : '0';
            if ($blueprint->unit_price_cents !== null) {
                $data['pricing_mode'] = 'manual';
                $data['unit_price'] = number_format($blueprint->unit_price_cents / 100, 2, '.', '');
                $data['unit_price_override'] = true;
            } else {
                $data['pricing_mode'] = $concern->billing_posture->prefersManualPartPricing() ? 'manual' : 'matrix';
                $data['unit_price'] = $concern->billing_posture->prefersManualPartPricing() ? '0' : null;
            }
        } elseif ($type === RepairOrderLineType::Fee) {
            $cents = $blueprint->unit_price_cents ?? 0;
            $data['unit_price'] = number_format($cents / 100, 2, '.', '');
            $data['quantity'] = $quantity !== '' ? $quantity : '1.00';
        } elseif ($type->isNote()) {
            $data['visible_to_advisor'] = true;
            $data['visible_to_technician'] = true;
            $data['visible_to_customer'] = true;
            $data['quantity'] = '1.00';
        }

        $data = $type->applyInputDefaults($data);
        $pricingAttributes = $this->pricing->attributesFor($data, $repairOrder);
        $resolvedQuantity = $pricingAttributes['quantity'] ?? $data['quantity'];

        if ($type->isLabor() && $blueprint->unit_price_cents !== null) {
            $pricingAttributes['unit_price_cents'] = $blueprint->unit_price_cents;
            $pricingAttributes['labor_rate_cents'] = $blueprint->unit_price_cents;
            $pricingAttributes['labor_rate_override_reason'] = LaborRateOverrideReason::MenuOrPackage->value;
        }

        if ($type->isPart()) {
            if ($blueprint->part_cost_cents !== null) {
                $pricingAttributes['part_cost_cents'] = $blueprint->part_cost_cents;
            }
            if ($blueprint->unit_price_cents !== null) {
                $pricingAttributes['unit_price_cents'] = $blueprint->unit_price_cents;
                $pricingAttributes['pricing_mode'] = 'manual';
            }
        }

        if ($type === RepairOrderLineType::Fee && $blueprint->unit_price_cents !== null) {
            $pricingAttributes['unit_price_cents'] = $blueprint->unit_price_cents;
        }

        return $repairOrder->lines()->create([
            'repair_order_concern_id' => $concern->id,
            'repair_order_work_group_id' => $workGroup->id,
            'type' => $type,
            'description' => $blueprint->description,
            'customer_description' => null,
            'quantity' => $resolvedQuantity,
            'unit_price_cents' => $pricingAttributes['unit_price_cents'],
            'part_cost_cents' => $pricingAttributes['part_cost_cents'],
            'matrix_suggested_price_cents' => $pricingAttributes['matrix_suggested_price_cents'],
            'pricing_mode' => $pricingAttributes['pricing_mode'],
            'pricing_matrix_key' => $pricingAttributes['pricing_matrix_key'],
            'pricing_matrix_name' => $pricingAttributes['pricing_matrix_name'],
            'matrix_applied' => $pricingAttributes['matrix_applied'],
            'vendor_name' => $pricingAttributes['vendor_name'],
            'part_number' => $pricingAttributes['part_number'],
            'sourcing_notes' => $pricingAttributes['sourcing_notes'],
            'has_core' => false,
            'save_old_part' => false,
            'is_overridden' => $pricingAttributes['is_overridden'],
            'subtotal_cents' => $this->calculator->lineTotalCents($resolvedQuantity, $pricingAttributes['unit_price_cents']),
            ...($type->isNote()
                ? NoteAudience::fromLegacyPrivate(false)->persistenceAttributes()
                : NoteAudience::none()->persistenceAttributes()),
            ...RepairOrderLineLaborInput::persistenceAttributes($pricingAttributes),
        ]);
    }
}
