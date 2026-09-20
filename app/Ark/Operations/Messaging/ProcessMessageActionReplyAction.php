<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Conversations\SyncConversationTurnAction;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Interprets expected replies on open Message Action contracts.
 *
 * Consent keywords still win first on inbound SMS.
 * ConversationMessage rows stay append-only — reply truth is written at create time.
 */
final class ProcessMessageActionReplyAction
{
    public function __construct(
        private readonly FindOpenMessageActionContract $contracts,
        private readonly InboundSmsConversationIngress $ingress,
        private readonly OutboundSmsTransport $transport,
        private readonly ConversationRecorder $recorder,
        private readonly SyncConversationTurnAction $syncTurn,
    ) {}

    /**
     * @return array{handled: bool, confirmation: ?string}
     */
    public function execute(InboundConversationPayload $payload): array
    {
        $contract = $this->contracts->forPhone($payload->contactKey);

        if ($contract === null) {
            return ['handled' => false, 'confirmation' => null];
        }

        $reply = MessageActionContract::matchReply($contract, $payload->body);

        if ($reply === null) {
            return ['handled' => false, 'confirmation' => null];
        }

        $ingest = $this->ingress->ingest($payload, array_filter([
            MessageActionContract::META_REPLY => $reply->value,
            MessageActionContract::META_ACTION => $contract->metadata[MessageActionContract::META_ACTION] ?? null,
            MessageActionContract::META_APPOINTMENT_ID => $contract->metadata[MessageActionContract::META_APPOINTMENT_ID] ?? null,
        ]));

        $inbound = $ingest['message'];

        if ($inbound instanceof ConversationMessage) {
            $inbound->loadMissing('conversation');

            if ($inbound->conversation !== null) {
                $this->syncTurn->execute($inbound->conversation);
            }
        }

        return match ($reply) {
            MessageActionReply::Confirm => $this->handleConfirm($contract),
            MessageActionReply::Reschedule => [
                'handled' => true,
                'confirmation' => 'Got it — we will text you about rescheduling.',
            ],
            MessageActionReply::Directions => $this->handleDirections($payload->contactKey, $inbound),
            MessageActionReply::Callback => [
                'handled' => true,
                'confirmation' => 'Got it — we will call you soon.',
            ],
        };
    }

    /**
     * @return array{handled: bool, confirmation: ?string}
     */
    private function handleConfirm(ConversationMessage $contract): array
    {
        $appointmentId = $contract->metadata[MessageActionContract::META_APPOINTMENT_ID] ?? null;

        if (is_numeric($appointmentId)) {
            $appointment = Appointment::query()->find((int) $appointmentId);

            if ($appointment instanceof Appointment
                && in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)
            ) {
                $appointment->forceFill([
                    'status' => AppointmentStatus::Confirmed,
                ])->save();

                $when = ShopDisplayTimezone::format($appointment->starts_at, 'D M j \\a\\t g:i A') ?? 'your appointment';

                return [
                    'handled' => true,
                    'confirmation' => "You're confirmed for {$when}. See you then!",
                ];
            }
        }

        return [
            'handled' => true,
            'confirmation' => "You're confirmed. See you then!",
        ];
    }

    /**
     * @return array{handled: bool, confirmation: ?string}
     */
    private function handleDirections(string $fromPhone, ?ConversationMessage $inbound): array
    {
        try {
            $body = ShopAddressSmsCopy::body();
        } catch (Throwable $exception) {
            Log::warning('message_action_directions_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'handled' => true,
                'confirmation' => 'Sorry — we could not look up directions right now. Please call the shop.',
            ];
        }

        try {
            $result = $this->transport->send($fromPhone, $body);
            $inbound?->loadMissing('conversation');
            $conversation = $inbound?->conversation;

            if ($conversation !== null) {
                $this->recorder->recordSystemOutboundSms(
                    $conversation,
                    $body,
                    $result->messageId,
                    [
                        MessageActionContract::META_ACTION => MessageActionKey::Address->value,
                        'auto_reply_to' => MessageActionReply::Directions->value,
                    ],
                );
                $this->syncTurn->execute($conversation->fresh());
            }

            return [
                'handled' => true,
                'confirmation' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('message_action_directions_send_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'handled' => true,
                'confirmation' => $body,
            ];
        }
    }
}
