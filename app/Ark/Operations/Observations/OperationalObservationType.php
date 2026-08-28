<?php

namespace App\Ark\Operations\Observations;

/**
 * Curated operational observation vocabulary — interpretive truth, NOT authority.
 *
 * Pipeline: authority changes → authority events (factual) → observations (this enum)
 * → operational observation stream → orientation → surface.
 *
 * Orientation must consume observations, not derive business meaning from authorities.
 * Most authority changes emit no observation.
 *
 * Emit gate: "Did the operation change in a way the operator would care about?"
 */
enum OperationalObservationType: string
{
    // Communication
    case CustomerReplied = 'customer_replied';
    case IncomingCall = 'incoming_call';
    case CustomerWaitingResponse = 'customer_waiting_response';
    case ConversationUnassigned = 'conversation_unassigned';
    case ConversationReopened = 'conversation_reopened';
    case CustomerSentMultipleMessages = 'customer_sent_multiple_messages';

    // Estimate
    case EstimateSent = 'estimate_sent';
    case EstimateViewed = 'estimate_viewed';
    case EstimateViewedMultipleTimes = 'estimate_viewed_multiple_times';
    case EstimateApproved = 'estimate_approved';
    case EstimateDeclined = 'estimate_declined';

    // Appointment
    case AppointmentMissed = 'appointment_missed';
    case AppointmentUpcoming = 'appointment_upcoming';
    case AppointmentNeedsConfirmation = 'appointment_needs_confirmation';

    // Repair order
    case RepairOrderStalled = 'repair_order_stalled';
    case RepairOrderWaitingParts = 'repair_order_waiting_parts';
    case RepairOrderReadyForPickup = 'repair_order_ready_for_pickup';
    case RepairOrderOverdue = 'repair_order_overdue';

    // Intake / lead
    case LeadUnassigned = 'lead_unassigned';
    case LeadMissingVehicle = 'lead_missing_vehicle';
    case LeadMissingCustomer = 'lead_missing_customer';
    case LeadAging = 'lead_aging';

    // Shop / portable station (stream vocabulary — emitters ship incrementally)
    case CustomerArrived = 'customer_arrived';
    case PaymentReceived = 'payment_received';
    case RepairOrderWaiting = 'repair_order_waiting';
    case WarrantyApproved = 'warranty_approved';
    case PartsArrived = 'parts_arrived';
    case VehicleReady = 'vehicle_ready';
    case InternalRequest = 'internal_request';

    public function label(): string
    {
        return match ($this) {
            self::CustomerReplied => 'Customer replied',
            self::IncomingCall => 'Incoming call',
            self::CustomerArrived => 'Customer arrived',
            self::PaymentReceived => 'Payment received',
            self::RepairOrderWaiting => 'Repair order waiting',
            self::WarrantyApproved => 'Warranty approved',
            self::PartsArrived => 'Parts arrived',
            self::VehicleReady => 'Vehicle ready',
            self::InternalRequest => 'Internal request',
            self::CustomerWaitingResponse => 'Customer waiting response',
            self::ConversationUnassigned => 'Conversation unassigned',
            self::ConversationReopened => 'Conversation reopened',
            self::CustomerSentMultipleMessages => 'Customer sent multiple messages',
            self::EstimateSent => 'Estimate sent',
            self::EstimateViewed => 'Estimate viewed',
            self::EstimateViewedMultipleTimes => 'Estimate viewed multiple times',
            self::EstimateApproved => 'Estimate approved',
            self::EstimateDeclined => 'Estimate declined',
            self::AppointmentMissed => 'Appointment missed',
            self::AppointmentUpcoming => 'Appointment upcoming',
            self::AppointmentNeedsConfirmation => 'Appointment needs confirmation',
            self::RepairOrderStalled => 'Repair order stalled',
            self::RepairOrderWaitingParts => 'Repair order waiting parts',
            self::RepairOrderReadyForPickup => 'Repair order ready for pickup',
            self::RepairOrderOverdue => 'Repair order overdue',
            self::LeadUnassigned => 'Lead unassigned',
            self::LeadMissingVehicle => 'Lead missing vehicle',
            self::LeadMissingCustomer => 'Lead missing customer',
            self::LeadAging => 'Lead aging',
        };
    }

    public function workboardSignalLabel(): string
    {
        return match ($this) {
            self::CustomerReplied => 'Customer Replied',
            self::IncomingCall => 'Incoming Call',
            self::CustomerArrived => 'Customer Arrived',
            self::PaymentReceived => 'Payment Received',
            self::RepairOrderWaiting => 'Open Repair Order',
            self::WarrantyApproved => 'Warranty Approved',
            self::PartsArrived => 'Parts Arrived',
            self::VehicleReady => 'Vehicle Ready',
            self::InternalRequest => 'Internal Request',
            self::CustomerWaitingResponse => 'Customer Waiting',
            self::ConversationUnassigned => 'Unassigned Conversation',
            self::ConversationReopened => 'Conversation Reopened',
            self::CustomerSentMultipleMessages => 'Multiple Customer Messages',
            self::EstimateSent => 'Estimate Sent',
            self::EstimateViewed => 'Estimate Viewed',
            self::EstimateViewedMultipleTimes => 'Estimate Viewed Multiple Times',
            self::EstimateApproved => 'Estimate Approved',
            self::EstimateDeclined => 'Estimate Declined',
            self::RepairOrderStalled => 'Work Stalled',
            self::RepairOrderWaitingParts => 'Waiting on Parts',
            self::RepairOrderReadyForPickup => 'Ready for Pickup',
            self::RepairOrderOverdue => 'Overdue',
            default => $this->label(),
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::CustomerReplied,
            self::IncomingCall,
            self::CustomerWaitingResponse,
            self::ConversationUnassigned,
            self::ConversationReopened,
            self::CustomerSentMultipleMessages => 'communication',

            self::EstimateSent,
            self::EstimateViewed,
            self::EstimateViewedMultipleTimes,
            self::EstimateApproved,
            self::EstimateDeclined => 'estimate',

            self::AppointmentMissed,
            self::AppointmentUpcoming,
            self::AppointmentNeedsConfirmation => 'appointment',

            self::RepairOrderStalled,
            self::RepairOrderWaitingParts,
            self::RepairOrderReadyForPickup,
            self::RepairOrderOverdue => 'repair_order',

            self::LeadUnassigned,
            self::LeadMissingVehicle,
            self::LeadMissingCustomer,
            self::LeadAging => 'intake',

            self::CustomerArrived,
            self::PaymentReceived,
            self::WarrantyApproved,
            self::PartsArrived,
            self::VehicleReady,
            self::InternalRequest => 'shop',

            self::RepairOrderWaiting => 'repair_order',
        };
    }

    /**
     * Explainable presentation tone for the moment feed — NOT a score.
     *
     * - urgent   (red):   a decision or failure that needs attention now
     * - waiting  (amber): someone/something is waiting on the shop
     * - positive (green): a good thing just happened
     * - info     (grey):  informational, no action implied
     *
     * One sentence answers "why this color?" — never a numeric weight.
     */
    public function tone(): string
    {
        return match ($this) {
            self::RepairOrderOverdue,
            self::AppointmentMissed,
            self::EstimateDeclined,
            self::ConversationUnassigned,
            self::LeadAging => 'urgent',

            self::CustomerReplied,
            self::CustomerWaitingResponse,
            self::CustomerSentMultipleMessages,
            self::IncomingCall,
            self::RepairOrderStalled,
            self::RepairOrderWaitingParts,
            self::AppointmentNeedsConfirmation,
            self::EstimateViewedMultipleTimes,
            self::WarrantyApproved,
            self::InternalRequest,
            self::LeadUnassigned,
            self::LeadMissingVehicle,
            self::LeadMissingCustomer => 'waiting',

            self::EstimateApproved,
            self::PaymentReceived,
            self::CustomerArrived,
            self::VehicleReady,
            self::RepairOrderReadyForPickup,
            self::PartsArrived,
            self::AppointmentUpcoming => 'positive',

            self::EstimateSent,
            self::EstimateViewed,
            self::ConversationReopened,
            self::RepairOrderWaiting => 'info',
        };
    }

    /**
     * Surface request aliases (mobile workspace, deep links) — not authority events.
     */
    public static function tryFromSurfaceRequest(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = match ($value) {
            'browse', 'default' => null,
            'message', 'customer_replied' => self::CustomerReplied->value,
            'call', 'incoming_call' => self::IncomingCall->value,
            'check_in', 'customer_arrived' => self::CustomerArrived->value,
            'appointment', 'appointment_due' => self::AppointmentUpcoming->value,
            'payment', 'payment_activity', 'payment_received' => self::PaymentReceived->value,
            'repair_order', 'repair_order_waiting' => self::RepairOrderWaiting->value,
            'estimate_viewed' => self::EstimateViewed->value,
            default => $value,
        };

        if ($normalized === null) {
            return null;
        }

        return self::tryFrom($normalized);
    }
}
