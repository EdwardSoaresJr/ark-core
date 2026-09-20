<?php

namespace App\Ark\Operations\Conversations\Projections;

use App\Ark\Mobile\MobileAdvisorBriefProjection;
use App\Ark\Mobile\MobileOperationalContextProjection;
use App\Ark\Mobile\MobileCallRecordingPlayback;
use App\Ark\Mobile\MobileTelephonyDialProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Communications\ConversationWorkboardPresenter;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationReadTracker;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Surface-agnostic Conversation projection — operational relationship history.
 */
final class ConversationProjection
{
    public function __construct(
        private readonly UnifiedOperationalTimeline $timeline,
        private readonly ConversationWorkboardPresenter $conversationPresenter,
        private readonly ConversationActivityPresenter $activityPresenter,
        private readonly ConversationContextProjection $contextProjection,
        private readonly ConversationLiveStateProjection $liveStateProjection,
        private readonly ConversationReadTracker $readTracker,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly MobileCallRecordingPlayback $callRecordingPlayback,
        private readonly MobileStaffAccess $access,
        private readonly RepairOrderConversationSendProjection $sendProjection,
        private readonly MobileTelephonyDialProjection $mobileDialProjection,
        private readonly MobileAdvisorBriefProjection $advisorBriefProjection,
        private readonly MobileOperationalContextProjection $operationalContextProjection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listItem(User $user, Conversation $conversation, int $recentLimit = 6): array
    {
        $conversation = Conversation::query()
            ->with(['owner:id,name'])
            ->findOrFail($conversation->id);

        $presented = $this->conversationPresenter->present($conversation, 'needs_shop');
        $recent = $this->recentActivities($conversation, ConversationSurface::Mobile, $recentLimit);
        $latest = $recent !== [] ? $recent[0] : null;
        $lastActivityAt = $latest !== null
            ? Carbon::parse((string) ($latest['occurred_at'] ?? now()->toIso8601String()))
            : null;

        return [
            'headline' => (string) ($presented['headline'] ?? 'Unknown'),
            'unread_count' => $this->readTracker->unreadInboundCount($conversation, $user),
            'last_activity' => $this->contextProjection->forConversation($conversation, $lastActivityAt)['last_activity'],
            'recent_activities' => $recent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function thread(
        User $user,
        Conversation $conversation,
        ConversationSurface $surface,
        int $limit = 100,
    ): array {
        $conversation = Conversation::query()
            ->with(['owner:id,name'])
            ->findOrFail($conversation->id);

        $entries = $this->timeline->forConversationRelationship($conversation, $limit)->all();
        $presented = $this->conversationPresenter->present($conversation, 'needs_shop');
        $primaryRo = $this->primaryRepairOrder($conversation);
        $contactPhone = $this->contactPhone($conversation);

        $activities = array_values(array_map(
            fn (OperationalEventEntry $entry): array => $this->presentActivity(
                $entry,
                $surface,
                $primaryRo,
                $contactPhone,
                $user,
            ),
            $entries,
        ));

        $latest = $activities !== [] ? $activities[0] : null;
        $lastActivityAt = $latest !== null
            ? Carbon::parse((string) ($latest['occurred_at'] ?? now()->toIso8601String()))
            : null;

        // Timeline is newest-first; a thread reads oldest -> newest (newest at the
        // bottom), mirroring the desktop workspace. $latest is captured above.
        $activities = array_reverse($activities);

        $canReply = $this->access->canReplyToCustomer($user);
        $canInternalNote = $this->access->canRecordInternalNote($user);
        $context = $this->contextProjection->forConversation($conversation, $lastActivityAt);

        return [
            'id' => $conversation->id,
            'headline' => (string) ($presented['headline'] ?? 'Unknown'),
            'display_phone' => (string) ($presented['display_phone'] ?? ''),
            'activity_preview_label' => is_array($latest) ? (string) ($latest['activity_label'] ?? '') : '',
            'status_label' => $conversation->status->label(),
            'waiting_on_label' => (string) ($presented['waiting_on_label'] ?? ''),
            'unread_count' => $this->readTracker->unreadInboundCount($conversation, $user),
            'poll_after_seconds' => 20,
            'context' => $context,
            'advisor_brief' => $surface === ConversationSurface::Mobile
                ? $this->advisorBriefProjection->forThread($user, $conversation, $context, $activities, $primaryRo)
                : null,
            'operational_context' => $surface === ConversationSurface::Mobile
                ? $this->operationalContextProjection->forThread($primaryRo, $context)
                : null,
            'live_state' => $this->liveStateProjection->forConversation($conversation),
            'composer_actions' => $this->composerActions($canReply, $canInternalNote, $contactPhone, $primaryRo, $user, $surface),
            'activities' => $activities,
            'events' => $activities,
            'can_reply' => $canReply,
            'can_internal_note' => $canInternalNote,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivities(
        Conversation $conversation,
        ConversationSurface $surface,
        int $limit = 6,
    ): array {
        $entries = $this->timeline->forConversationRelationship($conversation, $limit)->all();

        $primaryRo = $this->primaryRepairOrder($conversation);
        $contactPhone = $this->contactPhone($conversation);

        return array_values(array_map(
            fn (OperationalEventEntry $entry): array => $this->presentActivity(
                $entry,
                $surface,
                $primaryRo,
                $contactPhone,
            ),
            $entries,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentActivity(
        OperationalEventEntry $entry,
        ConversationSurface $surface,
        ?RepairOrder $primaryRo,
        ?string $contactPhone,
        ?User $viewer = null,
    ): array {
        $recordingMetadata = null;

        if ($entry->source === OperationalEventSource::CallSession
            && $entry->subject instanceof CallSession
            && $surface === ConversationSurface::Mobile) {
            $recordingMetadata = $this->callRecordingPlayback->projectFor($entry->subject);
        }

        $presented = $this->activityPresenter->present(
            $entry,
            $surface,
            $primaryRo,
            $contactPhone,
            $recordingMetadata,
            $viewer,
        );

        if ($surface !== ConversationSurface::Mobile) {
            return $presented;
        }

        return $this->enrichMobileAttachments($presented);
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     */
    private function enrichMobileAttachments(array $activity): array
    {
        $metadata = is_array($activity['metadata'] ?? null) ? $activity['metadata'] : [];
        $attachments = is_array($metadata['attachments'] ?? null) ? $metadata['attachments'] : [];
        $conversationId = (int) ($metadata['conversation_id'] ?? 0);
        $messageId = (int) ($metadata['message_id'] ?? 0);

        if ($attachments === [] || $conversationId <= 0 || $messageId <= 0) {
            return $activity;
        }

        $metadata['attachments'] = array_map(function (array $attachment) use ($conversationId, $messageId): array {
            $attachmentId = (int) ($attachment['id'] ?? 0);

            if ($attachmentId <= 0) {
                return $attachment;
            }

            $attachment['url'] = route('api.mobile.conversations.attachments.show', [
                'conversation' => $conversationId,
                'message' => $messageId,
                'attachment' => $attachmentId,
            ]);

            return $attachment;
        }, $attachments);

        $activity['metadata'] = $metadata;

        return $activity;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function composerActions(
        bool $canReply,
        bool $canInternalNote,
        ?string $contactPhone,
        ?RepairOrder $primaryRo,
        User $user,
        ConversationSurface $surface,
    ): array {
        $repairOrderId = $primaryRo?->repair_order_id;
        $estimateEnabled = false;
        $paymentEnabled = false;
        $inspectionEnabled = false;
        $estimateReason = null;
        $paymentReason = null;
        $inspectionReason = null;

        if ($primaryRo !== null && $canReply) {
            $send = $this->sendProjection->forRepairOrder($primaryRo, $user);
            $estimate = $send['estimate'];
            $payment = $send['payment'];
            $inspection = $send['inspection'];

            $estimateEnabled = ($estimate['can_sms'] ?? false) === true;
            $paymentEnabled = ($payment['can_sms'] ?? false) === true;
            $inspectionEnabled = ($inspection['can_sms'] ?? false) === true;
            $estimateReason = $estimate['send_block_reason']
                ?? $estimate['sms_block_reason']
                ?? null;
            $paymentReason = $payment['send_block_reason']
                ?? $payment['sms_block_reason']
                ?? null;
            $inspectionReason = $inspection['send_block_reason']
                ?? $inspection['sms_block_reason']
                ?? null;
        }

        $callParams = null;
        $callLabel = 'Call';

        if (filled($contactPhone)) {
            $callParams = ['phone' => $contactPhone];

            if ($surface === ConversationSurface::Mobile) {
                $dialMethod = $this->mobileDialProjection->dialMethodFor($user);
                $callParams['dial_method'] = $dialMethod;
                $callLabel = match ($dialMethod) {
                    'shop_callback' => 'Callback',
                    'in_app' => 'Call',
                    default => 'Call',
                };

                if ($primaryRo?->customer_id !== null) {
                    $callParams['customer_id'] = $primaryRo->customer_id;
                }

                if ($primaryRo?->id !== null) {
                    $callParams['repair_order_id'] = $primaryRo->id;
                }
            }
        }

        return [
            [
                'key' => 'reply',
                'label' => 'Reply',
                'enabled' => $canReply,
            ],
            [
                'key' => 'call',
                'label' => $callLabel,
                'enabled' => filled($contactPhone),
                'params' => $callParams,
            ],
            [
                'key' => 'internal_note',
                'label' => 'Internal note',
                'enabled' => $canInternalNote,
            ],
            [
                'key' => 'estimate',
                'label' => 'Send estimate',
                'enabled' => $estimateEnabled,
                'params' => $repairOrderId !== null
                    ? array_filter([
                        'repair_order_id' => $repairOrderId,
                        'block_reason' => $estimateEnabled ? null : $estimateReason,
                    ])
                    : null,
            ],
            [
                'key' => 'inspection',
                'label' => 'Send inspection',
                'enabled' => $inspectionEnabled,
                'params' => $repairOrderId !== null
                    ? array_filter([
                        'repair_order_id' => $repairOrderId,
                        'block_reason' => $inspectionEnabled ? null : $inspectionReason,
                    ])
                    : null,
            ],
            [
                'key' => 'payment',
                'label' => 'Send payment link',
                'enabled' => $paymentEnabled,
                'params' => $repairOrderId !== null
                    ? array_filter([
                        'repair_order_id' => $repairOrderId,
                        'block_reason' => $paymentEnabled ? null : $paymentReason,
                    ])
                    : null,
            ],
        ];
    }

    private function contactPhone(Conversation $conversation): ?string
    {
        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            return null;
        }

        $phone = trim((string) $conversation->contact_address);

        return $phone !== '' ? $phone : null;
    }

    private function primaryRepairOrder(Conversation $conversation): ?RepairOrder
    {
        $phone = $this->contactPhone($conversation);

        if ($phone !== null) {
            $callContext = $this->callContextResolver->resolve($phone);
            $primaryRo = $callContext?->openRepairOrders->first()?->repairOrder;

            if ($primaryRo instanceof RepairOrder) {
                return $primaryRo;
            }
        }

        $conversation->loadMissing('links.linkable');

        foreach ($conversation->links as $link) {
            if ($link->linkable instanceof RepairOrder) {
                return $link->linkable;
            }
        }

        return null;
    }
}
