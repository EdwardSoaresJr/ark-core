<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Carbon;

final class ReviewRequestProjection
{
    public function __construct(
        private readonly ReviewRequestAuthority $authority,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @return array{
     *     can_text: bool,
     *     can_email: bool,
     *     can_send: bool,
     *     already_sent: bool,
     *     history_entries: list<array{channel_label: string, when_label: string, by_label: ?string}>,
     *     sent_by_label: ?string,
     *     status_label: ?string,
     *     sent_at: ?Carbon,
     *     sent_channels: list<string>,
     *     no_contact_message: ?string,
     *     review_url: string,
     *     contact_url: string,
     *     preview_sms_body: string,
     *     preview_email_subject: string,
     *     preview_email_body: string,
     *     show_on_closeout: bool,
     *     show_follow_up: bool,
     * }
     */
    public function for(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'reviewRequestRecordedBy']);
        $customer = $repairOrder->customer;

        $canText = false;
        if ($customer !== null && filled($customer->phone)) {
            $canText = CustomerSmsSendEligibility::for($customer, $this->credentials)->canSend();
        }

        $canEmail = $customer !== null && filled($customer->email);

        $alreadySent = $this->authority->alreadySent($repairOrder);
        $historyEntries = $this->authority->historyEntries($repairOrder);
        $latest = $this->authority->latestMessage($repairOrder);
        $channels = $historyEntries === []
            ? []
            : collect($historyEntries)
                ->map(fn (array $entry): string => strtolower($entry['channel_label']))
                ->filter(fn (string $label): bool => in_array($label, ['text', 'email'], true))
                ->unique()
                ->values()
                ->all();

        if ($channels === [] && $latest !== null) {
            $channels = $this->authority->messagesFor($repairOrder)
                ->map(fn ($message): string => $message->channel->value)
                ->unique()
                ->values()
                ->all();
        }

        $sentByLabel = collect($historyEntries)
            ->pluck('by_label')
            ->filter()
            ->unique()
            ->values()
            ->first();

        $statusLabel = null;
        $sentAt = null;

        if ($historyEntries !== []) {
            $sentAt = $latest?->occurred_at ?? $repairOrder->review_request_recorded_at;
            $statusLabel = 'Review Requested';
        }

        $noContactMessage = null;
        if (! $canText && ! $canEmail) {
            $noContactMessage = 'No text or email contact is available for this customer.';
        }

        $reviewUrl = ReviewRequestCopy::reviewUrl();
        $contactUrl = ReviewRequestCopy::contactUrl();
        $shopName = ReviewRequestCopy::shopName();
        $vehicleLabel = $repairOrder->vehicle?->display_name;

        $isClosedPaid = $repairOrder->status->is(RepairOrderStatus::Closed)
            && $repairOrder->close_variant_key === 'paid';

        return [
            'can_text' => $canText,
            'can_email' => $canEmail,
            'can_send' => ($canText || $canEmail) && ! $alreadySent,
            'already_sent' => $alreadySent,
            'history_entries' => $historyEntries,
            'sent_by_label' => $sentByLabel,
            'status_label' => $statusLabel,
            'sent_at' => $sentAt,
            'sent_channels' => $channels,
            'no_contact_message' => $noContactMessage,
            'review_url' => $reviewUrl,
            'contact_url' => $contactUrl,
            'preview_sms_body' => ReviewRequestCopy::smsBody($reviewUrl, $shopName),
            'preview_email_subject' => ReviewRequestCopy::emailSubject($shopName),
            'preview_email_body' => ReviewRequestCopy::emailPreviewBody($reviewUrl, $shopName, $vehicleLabel, $contactUrl),
            'show_on_closeout' => ! $repairOrder->isTerminal(),
            'show_follow_up' => $isClosedPaid,
        ];
    }
}
