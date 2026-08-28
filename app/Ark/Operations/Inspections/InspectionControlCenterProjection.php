<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

/**
 * RO Inspection tab projection — control/review, not the technician walk.
 */
final class InspectionControlCenterProjection
{
    public function __construct(
        private readonly EnsureInspectionAction $ensureInspection,
        private readonly ApplyInspectionTemplateAction $applyTemplate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(RepairOrder $repairOrder, ?User $actor = null): array
    {
        DefaultInspectionTemplateCatalog::seedIfMissing();

        $inspection = $this->ensureInspection->execute($repairOrder, $actor);

        $hasChecklistItems = $inspection->items()
            ->whereNull('superseded_at')
            ->whereNotNull('inspection_template_item_id')
            ->exists();

        if (! $hasChecklistItems) {
            $this->applyTemplate->execute($repairOrder, $inspection, actor: $actor);
            $inspection->refresh();
        }

        $coverage = InspectionCoverageProjection::for($repairOrder->fresh(), $actor);
        $ordered = InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items.measurements', 'items.photos']));

        $attentionItems = $ordered
            ->filter(function (InspectionItem $item): bool {
                return in_array($item->observed_state, [
                    InspectionObservedState::NeedsAttention,
                    InspectionObservedState::Fail,
                    InspectionObservedState::Monitor,
                ], true)
                    || $item->photos->isNotEmpty()
                    || $item->measurements->isNotEmpty()
                    || filled(InspectionFindingIntent::stripNotesPrefix($item->notes));
            })
            ->take(12)
            ->map(fn (InspectionItem $item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'category' => $item->checklist_category_name ?: $item->categoryLabel(),
                'condition' => (
                    $item->observed_state instanceof InspectionObservedState
                        ? InspectionChecklistStatus::fromObservedState($item->observed_state)
                        : null
                )?->label() ?? 'Not checked',
                'measurement' => $item->measurements->first()?->formattedValue(),
                'photo_count' => $item->photos->count(),
                'note' => InspectionFindingIntent::stripNotesPrefix($item->notes),
                'walk_url' => InspectionCaptureLinks::walkUrl($repairOrder, $item->id),
            ])
            ->values()
            ->all();

        $repairOrder->loadMissing(['assignedTechnician', 'vehicle']);
        $technician = $repairOrder->assignedTechnician;
        $walkUrl = $coverage['walk_url'];
        $captureUrl = $coverage['capture_url'] ?? $walkUrl;
        $roLabel = 'RO #'.$repairOrder->repair_order_id;
        $vehicle = trim((string) ($repairOrder->vehicle?->display_name ?? 'Vehicle'));
        $recipients = $this->handoffRecipients($repairOrder);

        $canReset = ResetInspectionWalkAction::canReset($actor)
            && ! $repairOrder->isTerminal()
            && ((int) ($coverage['checked'] ?? 0) > 0 || count($attentionItems) > 0);

        return [
            'coverage' => $coverage,
            'can_record' => $coverage['can_record'],
            'can_reset' => $canReset,
            'attention_items' => $attentionItems,
            'assigned_technician_name' => $technician?->name,
            'actions' => [
                'open_inspection_url' => $captureUrl,
                'tablet_view_url' => $coverage['tablet_url'] ?? InspectionCaptureLinks::tabletUrl($repairOrder),
                'copy_url' => $walkUrl,
                'companion_deep_link' => $coverage['companion_deep_link'],
                'reset_url' => route('operations.repair-orders.inspection.reset', $repairOrder),
                'send_url' => route('operations.repair-orders.inspection.walk-link.send', $repairOrder),
                'recipients' => $recipients,
                'default_recipient_id' => $this->defaultRecipientId($recipients, $repairOrder),
                'sms_body' => "Vehicle inspection for {$vehicle} ({$roLabel}): {$walkUrl}",
                'email_subject' => "Vehicle inspection — {$vehicle} {$roLabel}",
                'email_body' => "Open the inspection walk:\n\n{$walkUrl}\n",
            ],
        ];
    }

    /**
     * Active floor staff who can receive the authenticated walk link.
     *
     * @return list<array{id: int, name: string, role_label: string, phone: ?string, email: ?string, is_assigned: bool}>
     */
    private function handoffRecipients(RepairOrder $repairOrder): array
    {
        $assignedId = $repairOrder->assigned_technician_id !== null
            ? (int) $repairOrder->assigned_technician_id
            : null;

        return User::query()
            ->active()
            ->role([
                ArkRole::Technician->value,
                ArkRole::Advisor->value,
                ArkRole::Admin->value,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone'])
            ->map(function (User $user) use ($assignedId): array {
                $rawPhone = $user->getRawOriginal('phone');
                $phone = filled($rawPhone)
                    ? PhoneNumber::normalize((string) $rawPhone)
                    : null;

                return [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'role_label' => $this->roleLabel($user),
                    'phone' => ($phone !== null && $phone !== '') ? $phone : null,
                    'email' => filled($user->email) ? (string) $user->email : null,
                    'is_assigned' => $assignedId !== null && (int) $user->id === $assignedId,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int, name: string, role_label: string, phone: ?string, email: ?string, is_assigned: bool}>  $recipients
     */
    private function defaultRecipientId(array $recipients, RepairOrder $repairOrder): ?int
    {
        if ($recipients === []) {
            return null;
        }

        $assignedId = $repairOrder->assigned_technician_id !== null
            ? (int) $repairOrder->assigned_technician_id
            : null;

        foreach ($recipients as $recipient) {
            if ($assignedId !== null && $recipient['id'] === $assignedId) {
                return $recipient['id'];
            }
        }

        foreach ($recipients as $recipient) {
            if ($recipient['phone'] !== null) {
                return $recipient['id'];
            }
        }

        return $recipients[0]['id'];
    }

    private function roleLabel(User $user): string
    {
        if ($user->hasRole(ArkRole::Technician->value)) {
            return 'Technician';
        }

        if ($user->hasRole(ArkRole::Advisor->value)) {
            return 'Advisor';
        }

        if ($user->hasRole(ArkRole::Admin->value)) {
            return 'Admin';
        }

        return 'Staff';
    }
}
