<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\ScheduledOutboundEstimateProjection;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Payments\CardPresentCaptureProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Vehicles\VehicleIdentityPressure;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class RepairOrderConversationSendProjection
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
        private readonly CardPresentCaptureProjection $capture,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly DepositPortalLinkContext $depositLink,
        private readonly ScheduledOutboundEstimateProjection $scheduledOutbound,
    ) {}

    /**
     * @return array{
     *     estimate: array{
     *         can_sms: bool,
     *         can_email: bool,
     *         sms_block_reason: ?string,
     *         email_block_reason: ?string,
     *         send_block_reason: ?string,
     *         missing_vin: bool,
     *         vin_block_message: string,
     *     },
     *     payment: array{
     *         can_sms: bool,
     *         can_email: bool,
     *         sms_block_reason: ?string,
     *         email_block_reason: ?string,
     *         send_block_reason: ?string,
     *     },
     *     deposit: array{
     *         can_sms: bool,
     *         can_email: bool,
     *         sms_block_reason: ?string,
     *         email_block_reason: ?string,
     *         send_block_reason: ?string,
     *         suggested_amount_decimal: ?string,
     *         max_amount_cents: int,
     *     },
     *     inspection: array{
     *         can_sms: bool,
     *         sms_block_reason: ?string,
     *         send_block_reason: ?string,
     *     },
     *     schedule: array{
     *         shop_is_open: bool,
     *         pending: ?array{id: int, scheduled_for: string, scheduled_for_label: string, delivery_mode: string},
     *     },
     * }
     */
    public function forRepairOrder(RepairOrder $repairOrder, ?User $actor = null): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        return [
            'estimate' => $this->estimateChannels($repairOrder, $actor),
            'payment' => $this->paymentChannels($repairOrder, $actor),
            'deposit' => $this->depositChannels($repairOrder, $actor),
            'inspection' => $this->inspectionChannels($repairOrder, $actor),
            'schedule' => $this->scheduledOutbound->forRepairOrder($repairOrder->id),
        ];
    }

    /**
     * @return array{
     *     can_sms: bool,
     *     can_email: bool,
     *     sms_block_reason: ?string,
     *     email_block_reason: ?string,
     *     send_block_reason: ?string,
     *     missing_vin: bool,
     *     vin_block_message: string,
     * }
     */
    private function estimateChannels(RepairOrder $repairOrder, ?User $actor): array
    {
        $missingVin = $repairOrder->missingVehicleVin();
        $vinBlockMessage = VehicleIdentityPressure::NoVin->estimateSendBlockedMessage();

        if ($repairOrder->isTerminal()) {
            return $this->blockedEstimateChannels(
                'Closed repair orders cannot send estimate links.',
                $missingVin,
                $vinBlockMessage,
            );
        }

        if ($repairOrder->lines()->doesntExist()) {
            return $this->blockedEstimateChannels(
                'Add at least one estimate line before sending the estimate.',
                $missingVin,
                $vinBlockMessage,
            );
        }

        $smsEligibility = CustomerSmsSendEligibility::for(
            $repairOrder->customer,
            $this->credentials,
        );

        $smsBlockReason = $smsEligibility->blockReason();
        $emailBlockReason = $this->estimateEmailBlockReason($repairOrder, $actor);

        return [
            'can_sms' => $smsBlockReason === null,
            'can_email' => $emailBlockReason === null,
            'sms_block_reason' => $smsBlockReason,
            'email_block_reason' => $emailBlockReason,
            'send_block_reason' => null,
            'missing_vin' => $missingVin,
            'vin_block_message' => $vinBlockMessage,
        ];
    }

    /**
     * @return array{
     *     can_sms: bool,
     *     can_email: bool,
     *     sms_block_reason: ?string,
     *     email_block_reason: ?string,
     *     send_block_reason: ?string,
     *     missing_vin: bool,
     *     vin_block_message: string,
     * }
     */
    private function blockedEstimateChannels(
        string $sendBlockReason,
        bool $missingVin,
        string $vinBlockMessage,
    ): array {
        return [
            'can_sms' => false,
            'can_email' => false,
            'sms_block_reason' => $sendBlockReason,
            'email_block_reason' => $sendBlockReason,
            'send_block_reason' => $sendBlockReason,
            'missing_vin' => $missingVin,
            'vin_block_message' => $vinBlockMessage,
        ];
    }

    private function estimateEmailBlockReason(RepairOrder $repairOrder, ?User $actor): ?string
    {
        if ($actor !== null && ! $actor->can(ArkCapability::RepairOrdersManage->value)) {
            return 'You do not have permission to email estimates.';
        }

        if (! $this->credentials->emailConfigured()) {
            return 'Shop email is not configured.';
        }

        $email = strtolower(trim((string) ($repairOrder->customer?->email ?? '')));

        if ($email === '') {
            return 'Add a customer email on file or enter one to send the estimate.';
        }

        return null;
    }

    /**
     * @return array{
     *     can_sms: bool,
     *     can_email: bool,
     *     sms_block_reason: ?string,
     *     email_block_reason: ?string,
     *     send_block_reason: ?string,
     * }
     */
    private function paymentChannels(RepairOrder $repairOrder, ?User $actor): array
    {
        $paymentBlockReason = $this->paymentSendBlockReason($repairOrder);

        if ($paymentBlockReason !== null) {
            return [
                'can_sms' => false,
                'can_email' => false,
                'sms_block_reason' => $paymentBlockReason,
                'email_block_reason' => $paymentBlockReason,
                'send_block_reason' => $paymentBlockReason,
            ];
        }

        $smsEligibility = CustomerSmsSendEligibility::for(
            $repairOrder->customer,
            $this->credentials,
        );

        $smsBlockReason = $smsEligibility->blockReason();
        $emailBlockReason = $this->paymentEmailBlockReason($repairOrder, $actor);

        return [
            'can_sms' => $smsBlockReason === null,
            'can_email' => $emailBlockReason === null,
            'sms_block_reason' => $smsBlockReason,
            'email_block_reason' => $emailBlockReason,
            'send_block_reason' => null,
        ];
    }

    private function paymentSendBlockReason(RepairOrder $repairOrder): ?string
    {
        if (! $this->capture->portalPayEnabled()) {
            return 'Customer portal payments are not enabled.';
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if (! $balance->hasIssuedInvoice) {
            return 'Generate the final invoice before sending a payment link.';
        }

        if ($balance->balanceDueCents <= 0) {
            return 'This repair order has no balance due.';
        }

        return null;
    }

    private function paymentEmailBlockReason(RepairOrder $repairOrder, ?User $actor): ?string
    {
        if ($actor !== null && ! $actor->can(ArkCapability::RepairOrdersManage->value)) {
            return 'You do not have permission to email payment links.';
        }

        if (! $this->credentials->emailConfigured()) {
            return 'Shop email is not configured. Check Settings → Integrations.';
        }

        $email = strtolower(trim((string) ($repairOrder->customer?->email ?? '')));

        if ($email === '') {
            return 'Add a customer email on file or enter one to send the payment link.';
        }

        return null;
    }

    /**
     * @return array{
     *     can_sms: bool,
     *     can_email: bool,
     *     sms_block_reason: ?string,
     *     email_block_reason: ?string,
     *     send_block_reason: ?string,
     *     suggested_amount_decimal: ?string,
     *     max_amount_cents: int,
     * }
     */
    private function depositChannels(RepairOrder $repairOrder, ?User $actor): array
    {
        $maxAmountCents = $this->depositLink->maxAmountCents($repairOrder);
        $suggestedAmountDecimal = $this->depositLink->suggestedAmountDecimal($repairOrder);
        $depositBlockReason = $this->depositSendBlockReason($repairOrder);

        if ($depositBlockReason !== null) {
            return [
                'can_sms' => false,
                'can_email' => false,
                'sms_block_reason' => $depositBlockReason,
                'email_block_reason' => $depositBlockReason,
                'send_block_reason' => $depositBlockReason,
                'suggested_amount_decimal' => $suggestedAmountDecimal,
                'max_amount_cents' => $maxAmountCents,
            ];
        }

        $smsEligibility = CustomerSmsSendEligibility::for(
            $repairOrder->customer,
            $this->credentials,
        );

        $smsBlockReason = $smsEligibility->blockReason();
        $emailBlockReason = $this->depositEmailBlockReason($repairOrder, $actor);

        return [
            'can_sms' => $smsBlockReason === null,
            'can_email' => $emailBlockReason === null,
            'sms_block_reason' => $smsBlockReason,
            'email_block_reason' => $emailBlockReason,
            'send_block_reason' => null,
            'suggested_amount_decimal' => $suggestedAmountDecimal,
            'max_amount_cents' => $maxAmountCents,
        ];
    }

    private function depositSendBlockReason(RepairOrder $repairOrder): ?string
    {
        if (! $this->capture->portalPayEnabled()) {
            return 'Customer portal payments are not enabled.';
        }

        if ($repairOrder->isTerminal()) {
            return 'Closed repair orders cannot send deposit requests.';
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if ($balance->hasIssuedInvoice) {
            return 'Use Send Pay Link after the final invoice is issued.';
        }

        if ($this->depositLink->maxAmountCents($repairOrder) <= 0) {
            return 'Nothing left to collect as a deposit.';
        }

        return null;
    }

    private function depositEmailBlockReason(RepairOrder $repairOrder, ?User $actor): ?string
    {
        if ($actor !== null && ! $actor->can(ArkCapability::RepairOrdersManage->value)) {
            return 'You do not have permission to email deposit requests.';
        }

        if (! $this->credentials->emailConfigured()) {
            return 'Shop email is not configured. Check Settings → Integrations.';
        }

        $email = strtolower(trim((string) ($repairOrder->customer?->email ?? '')));

        if ($email === '') {
            return 'Add a customer email on file or enter one to send the deposit request.';
        }

        return null;
    }

    /**
     * @return array{
     *     can_sms: bool,
     *     sms_block_reason: ?string,
     *     send_block_reason: ?string,
     * }
     */
    private function inspectionChannels(RepairOrder $repairOrder, ?User $actor): array
    {
        $sendBlockReason = $this->inspectionSendBlockReason($repairOrder);

        if ($sendBlockReason !== null) {
            return [
                'can_sms' => false,
                'sms_block_reason' => $sendBlockReason,
                'send_block_reason' => $sendBlockReason,
            ];
        }

        $smsEligibility = CustomerSmsSendEligibility::for(
            $repairOrder->customer,
            $this->credentials,
        );

        $smsBlockReason = $smsEligibility->blockReason();

        return [
            'can_sms' => $smsBlockReason === null,
            'sms_block_reason' => $smsBlockReason,
            'send_block_reason' => null,
        ];
    }

    private function inspectionSendBlockReason(RepairOrder $repairOrder): ?string
    {
        if ($repairOrder->isTerminal()) {
            return 'Closed repair orders cannot send inspection links.';
        }

        if (InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) === 0) {
            return 'Record at least one inspection finding before sharing.';
        }

        return null;
    }
}
