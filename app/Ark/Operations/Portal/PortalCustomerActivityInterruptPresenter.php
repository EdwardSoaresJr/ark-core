<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Orientation\Orientation;
use App\Ark\Orientation\OrientationDensity;
use Brick\Money\Money;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class PortalCustomerActivityInterruptPresenter
{
    public function __construct(
        private readonly Orientation $orientation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forEstimateView(RepairOrder $repairOrder, ConversationMessage $message): array
    {
        $repairOrder = $repairOrder->fresh(['customer', 'vehicle']);

        return $this->basePayload(
            repairOrder: $repairOrder,
            portalActivity: 'estimate_viewed',
            channelLabel: 'Estimate viewed',
            snippet: trim((string) $message->body),
            portalInterruptKey: 'estimate-view:'.$message->id,
            conversationMessageId: $message->id,
            conversationId: (int) $message->conversation_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forPayment(RepairOrder $repairOrder, PaymentGatewayAttempt $attempt): array
    {
        $repairOrder = $repairOrder->fresh(['customer', 'vehicle']);

        [$portalActivity, $channelLabel, $snippet] = match ($attempt->capture_surface) {
            PaymentCaptureSurface::PortalEstimateDeposit,
            PaymentCaptureSurface::PortalDepositRequest => [
                'deposit_paid',
                'Deposit paid',
                sprintf(
                    'Customer paid %s deposit on the portal.',
                    $this->formatCents((int) $attempt->amount_cents),
                ),
            ],
            PaymentCaptureSurface::Portal => [
                'invoice_paid',
                'Invoice paid',
                sprintf(
                    'Customer paid %s on the portal.',
                    $this->formatCents((int) $attempt->amount_cents),
                ),
            ],
            default => [
                'payment_received',
                'Payment received',
                sprintf(
                    'Customer paid %s on the portal.',
                    $this->formatCents((int) $attempt->amount_cents),
                ),
            ],
        };

        return $this->basePayload(
            repairOrder: $repairOrder,
            portalActivity: $portalActivity,
            channelLabel: $channelLabel,
            snippet: $snippet,
            portalInterruptKey: 'payment:'.$attempt->id,
            amountCents: (int) $attempt->amount_cents,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(
        RepairOrder $repairOrder,
        string $portalActivity,
        string $channelLabel,
        string $snippet,
        string $portalInterruptKey,
        ?int $conversationMessageId = null,
        ?int $conversationId = null,
        ?int $amountCents = null,
    ): array {
        $customer = $repairOrder->customer;
        $headline = trim($customer->first_name.' '.$customer->last_name);
        $repairOrderUrl = Route::has('operations.repair-orders.show')
            ? route('operations.repair-orders.show', $repairOrder)
            : route('operations.repair-orders.show', $repairOrder);

        return [
            'kind' => 'portal',
            'queue_tab' => 'portal',
            'channel' => 'portal',
            'channel_label' => $channelLabel,
            'portal_activity' => $portalActivity,
            'portal_interrupt_key' => $portalInterruptKey,
            'direction' => 'inbound',
            'direction_label' => 'Inbound',
            'state' => 'unread',
            'state_label' => $channelLabel,
            'conversation_message_id' => $conversationMessageId,
            'conversation_id' => $conversationId,
            'headline' => $headline !== '' ? $headline : 'Customer',
            'display_phone' => $customer->phone ?: 'Customer portal',
            'snippet' => Str::limit($snippet, 140),
            'matched' => true,
            'customer_id' => $customer->id,
            'repair_order_id' => $repairOrder->repair_order_id,
            'repair_order_url' => $repairOrderUrl,
            'context_summary' => trim(implode(' · ', array_filter([
                'RO #'.$repairOrder->repair_order_id,
                $repairOrder->vehicle?->display_name,
                $amountCents !== null ? $this->formatCents($amountCents) : null,
            ]))),
            'orientation' => array_merge(
                $this->orientation->repairOrder($repairOrder, OrientationDensity::Compact),
                ['density' => OrientationDensity::Compact->value],
            ),
            'primary_ro_url' => $repairOrderUrl,
            'customer_url' => route('operations.customers.show', $customer),
            'reply_url' => $repairOrderUrl,
            'show_reply_action' => true,
            'show_mark_read_action' => $conversationMessageId !== null,
            'mark_read_url' => $conversationMessageId !== null && $conversationId !== null && Route::has('operations.conversations.read')
                ? route('operations.conversations.read', $conversationId)
                : null,
            'lookup_url' => null,
            'intake_url' => null,
            'create_contact_url' => null,
            'dropdown_label' => $channelLabel.' · '.$headline,
            'priority' => str_contains($portalActivity, 'paid') ? 'high' : 'normal',
        ];
    }

    private function formatCents(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')->formatTo('en_US');
    }
}
