<?php

namespace App\Ark\Growth\Journey;

enum JourneyEvidenceSource: string
{
    case GrowthSession = 'growth_session';
    case GrowthTouchpoint = 'growth_touchpoint';
    case GrowthAttribution = 'growth_attribution';
    case Lead = 'lead';
    case CommunicationEvent = 'communication_event';
    case ApprovalEvent = 'approval_event';
    case Appointment = 'appointment';
    case CallSession = 'call_session';
    case OperationalEvent = 'operational_event';
    case RepairOrder = 'repair_order';

    public function label(): string
    {
        return match ($this) {
            self::GrowthSession => 'Growth session',
            self::GrowthTouchpoint => 'Website touchpoint',
            self::GrowthAttribution => 'Revenue attribution',
            self::Lead => 'Lead',
            self::CommunicationEvent => 'Communication event',
            self::ApprovalEvent => 'Approval',
            self::Appointment => 'Appointment',
            self::CallSession => 'Call',
            self::OperationalEvent => 'Operational event',
            self::RepairOrder => 'Repair order',
        };
    }
}
