<?php

namespace App\Ark\Growth\Events;

enum GrowthEventType: string
{
    case PageViewed = 'page_viewed';
    case ScrollDepth = 'scroll_depth';
    case CtaClicked = 'cta_clicked';
    case EstimateStarted = 'estimate_started';
    case EstimateSubmitted = 'estimate_submitted';
    case CallClicked = 'call_clicked';
    case PhoneCall = 'phone_call';
    case DirectionClick = 'direction_click';
    case ReviewClick = 'review_click';
    case AppointmentScheduled = 'appointment_scheduled';
    case RepairOrderCreated = 'repair_order_created';
    case RepairOrderClosed = 'repair_order_closed';
    case RevenueBooked = 'revenue_booked';
    case LeadSubmitted = 'lead_submitted';
    case LeadCreated = 'lead_created';

    public function label(): string
    {
        return match ($this) {
            self::PageViewed => 'Page viewed',
            self::ScrollDepth => 'Scroll depth',
            self::CtaClicked => 'CTA clicked',
            self::EstimateStarted => 'Estimate started',
            self::EstimateSubmitted => 'Estimate submitted',
            self::CallClicked => 'Call clicked',
            self::PhoneCall => 'Phone call',
            self::DirectionClick => 'Direction click',
            self::ReviewClick => 'Review click',
            self::AppointmentScheduled => 'Appointment scheduled',
            self::RepairOrderCreated => 'Repair order created',
            self::RepairOrderClosed => 'Repair order closed',
            self::RevenueBooked => 'Revenue booked',
            self::LeadSubmitted => 'Lead submitted',
            self::LeadCreated => 'Lead created',
        };
    }
}
